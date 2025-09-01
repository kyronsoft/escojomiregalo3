<?php

namespace App\Jobs;

use App\Models\CampaignToy;
use App\Services\MsGraphClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DownloadCampaignToyImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $campaignId;
    public ?string $jobId; // para actualizar progreso

    /** Resultado compacto (también reflejado en cache->counts.images) */
    public array $result = [
        'ok'        => 0,
        'fail'      => 0,
        'processed' => 0,
        'total'     => 0,
    ];

    public function __construct(int $campaignId, ?string $jobId = null)
    {
        $this->campaignId = $campaignId;
        $this->jobId      = $jobId;
    }

    public function handle(MsGraphClient $graph): void
    {
        $token    = $graph->getToken();
        $shareUrl = config('services.msgraph.share_url');

        // Índice minúsculas: nombre -> downloadUrl
        $files = $graph->listAllSharedChildren($shareUrl, $token);
        $index = [];
        foreach ($files as $f) {
            $index[mb_strtolower(trim($f['name']))] = $f['downloadUrl'];
        }

        $items = CampaignToy::where('idcampaign', $this->campaignId)->get(['id', 'combo', 'imagenppal']);

        // Calcula total (por si no lo pasó el orquestador)
        $total = 0;
        foreach ($items as $toy) {
            $img = trim((string)$toy->imagenppal);
            if ($img === '') continue;
            $total += ($toy->combo === 'COM') ? count(array_filter(array_map('trim', explode('+', $img)))) : 1;
        }
        $this->result['total'] = $total;

        if ($this->jobId) {
            $this->patchProgress([
                'message' => 'Descargando imágenes…',
                'counts'  => ['images' => ['total' => $total]],
            ]);
        }

        foreach ($items as $toy) {
            $img = trim((string)$toy->imagenppal);
            if ($img === '') {
                $toy->update(['imgexists' => 'N']);
                continue;
            }

            $names = ($toy->combo === 'COM')
                ? array_filter(array_map('trim', explode('+', $img)))
                : [$img];

            $allOk = true;
            $anyOk = false;

            foreach ($names as $name) {
                $key = mb_strtolower($name);

                if (!isset($index[$key])) {
                    Log::warning('Imagen no encontrada en OneDrive', ['toy_id' => $toy->id, 'file' => $name]);
                    $allOk = false;
                    $this->result['fail']++;
                    $this->tick(1);
                    continue;
                }

                try {
                    $bin  = $graph->downloadBySignedUrl($index[$key]);
                    $path = "campaign_toys/{$this->campaignId}/{$name}";
                    Storage::disk('public')->put($path, $bin);
                    $anyOk = true;
                    $this->result['ok']++;
                } catch (Throwable $e) {
                    Log::error('Error descargando imagen', [
                        'toy_id' => $toy->id,
                        'file'   => $name,
                        'error'  => $e->getMessage()
                    ]);
                    $allOk = false;
                    $this->result['fail']++;
                }

                $this->tick(1);
            }

            // Reglas de marcado
            $markS = $toy->combo === 'COM' ? (count($names) > 0 && $allOk) : $anyOk;
            $toy->update(['imgexists' => $markS ? 'S' : 'N']);
        }

        // Parche final (por si acaso)
        if ($this->jobId) {
            $this->patchProgress([
                'counts' => ['images' => $this->result],
            ]);
        }
    }

    private function tick(int $n): void
    {
        $this->result['processed'] += $n;

        if (!$this->jobId) return;

        // Además del conteo específico de imágenes, incrementamos el "processed_records" global
        $key   = self::progressKey($this->jobId);
        $state = Cache::get($key, []);
        $meta  = $state['meta'] ?? [];
        $done  = (int)($meta['processed_records'] ?? 0);
        $meta['processed_records'] = $done + $n;

        // Actualiza counts.images parciales
        $counts = $state['counts'] ?? [];
        $imgs   = $counts['images'] ?? [];
        $counts['images'] = array_replace($imgs, [
            'processed' => $this->result['processed'],
            'ok'        => $this->result['ok'],
            'fail'      => $this->result['fail'],
            'total'     => $this->result['total'],
        ]);

        $state['meta']   = $meta;
        $state['counts'] = $counts;
        // opcional: $state['percent'] = null; (el controlador recalcula si es null)
        Cache::put($key, $state, now()->addHours(2));
    }

    private function patchProgress(array $patch): void
    {
        $key   = self::progressKey($this->jobId);
        $state = Cache::get($key, []);
        foreach ($patch as $k => $v) {
            if (is_array($v) && isset($state[$k]) && is_array($state[$k])) {
                $state[$k] = array_replace_recursive($state[$k], $v);
            } else {
                $state[$k] = $v;
            }
        }
        Cache::put($key, $state, now()->addHours(2));
    }

    public static function progressKey(string $jobId): string
    {
        return "campaign_toys:import:progress:{$jobId}";
    }
}
