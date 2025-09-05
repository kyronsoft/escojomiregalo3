<?php

namespace App\Jobs;

use App\Models\CampaignToy;
use App\Services\MsGraphClient; // ajusta el namespace a donde tengas tu cliente
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

    /** Campaña a procesar */
    public int $campaignId;

    /**
     * Lista blanca de referencias a las que SÍ se les deben descargar imágenes.
     * Si está vacío/null, por compatibilidad, NO se filtra (descargaría todas).
     */
    public ?array $onlyRefs;

    /** jobId para actualizar el progreso en cache */
    public ?string $jobId;

    /** Resultado compacto para el resumen */
    public array $result = [
        'ok'   => 0,
        'fail' => 0,
        // 'toys_marked_S' => 0,
        // 'toys_marked_N' => 0,
    ];

    /**
     * Nueva firma: recibe referencias y jobId (ambos opcionales).
     */
    public function __construct(int $campaignId, array $onlyRefs = [], ?string $jobId = null)
    {
        $this->campaignId = $campaignId;
        $this->onlyRefs   = $onlyRefs ?: null; // guarda null si llega vacío
        $this->jobId      = $jobId;
    }

    public function handle(MsGraphClient $graph): void
    {
        try {
            $token    = $graph->getToken();
            $shareUrl = config('services.msgraph.share_url');

            // Indexa (nombre -> downloadUrl) en minúsculas
            $files = $graph->listAllSharedChildren($shareUrl, $token);
            $index = [];
            foreach ($files as $f) {
                $index[mb_strtolower(trim($f['name']))] = $f['downloadUrl'];
            }

            // Trae SOLO las referencias importadas si $onlyRefs viene con datos
            $q = CampaignToy::where('idcampaign', $this->campaignId);
            if (!empty($this->onlyRefs)) {
                $q->whereIn('referencia', $this->onlyRefs);
            }
            $items = $q->get(['id', 'combo', 'imagenppal']);

            // Total para progreso de imágenes (si el orquestador no lo calculó)
            $imagesTotal = 0;
            foreach ($items as $toy) {
                $img = trim((string) $toy->imagenppal);
                if ($img === '') continue;
                $imagesTotal += ($toy->combo === 'COM')
                    ? count(array_filter(array_map('trim', explode('+', $img))))
                    : 1;
            }
            if ($this->jobId && $imagesTotal > 0) {
                $this->patchProgress([
                    'counts' => [
                        'images' => [
                            'total' => $imagesTotal,
                        ]
                    ]
                ]);
            }

            // Descarga por cada toy
            foreach ($items as $toy) {
                $img = trim((string) $toy->imagenppal);
                if ($img === '') {
                    $toy->update(['imgexists' => 'N']);
                    // $this->result['toys_marked_N'] = ($this->result['toys_marked_N'] ?? 0) + 1;
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
                        Log::warning('Imagen no encontrada en SharePoint', [
                            'toy_id' => $toy->id,
                            'file' => $name
                        ]);
                        $allOk = false;
                        $this->result['fail']++;
                        $this->bumpProgress(images: 1, ok: 0, fail: 1);
                        continue;
                    }

                    try {
                        $bin  = $graph->downloadBySignedUrl($index[$key]);
                        $path = "campaign_toys/{$this->campaignId}/{$name}";
                        Storage::disk('public')->put($path, $bin);

                        $anyOk = true;
                        $this->result['ok']++;
                        $this->bumpProgress(images: 1, ok: 1, fail: 0);
                    } catch (Throwable $e) {
                        Log::error('Error descargando imagen desde SharePoint', [
                            'toy_id' => $toy->id,
                            'file'   => $name,
                            'error'  => $e->getMessage()
                        ]);
                        $allOk = false;
                        $this->result['fail']++;
                        $this->bumpProgress(images: 1, ok: 0, fail: 1);
                    }
                }

                // Regla de marcado:
                // - NC: S si anyOk
                // - COM: S solo si allOk (todas las partes)
                $markS = $toy->combo === 'COM' ? (count($names) > 0 && $allOk) : $anyOk;
                $toy->update(['imgexists' => $markS ? 'S' : 'N']);

                // (Opcional) contadores por toy
                // $this->result[$markS ? 'toys_marked_S' : 'toys_marked_N'] =
                //     ($this->result[$markS ? 'toys_marked_S' : 'toys_marked_N'] ?? 0) + 1;
            }
        } catch (Throwable $e) {
            Log::error('Fallo en DownloadCampaignToyImagesJob', [
                'campaignId' => $this->campaignId,
                'error'      => $e->getMessage()
            ]);

            // Si algo falla a nivel global, deja el estado en error para que el frontend no se quede "pegado"
            if ($this->jobId) {
                $this->patchProgress([
                    'status'  => 'error',
                    'message' => 'Error descargando imágenes: ' . $e->getMessage(),
                ]);
            }

            // Re-lanza para que quede registrado en failed_jobs (útil para retry)
            throw $e;
        }
    }

    /** Incrementa contadores e incrementa processed_records global */
    private function bumpProgress(int $images = 0, int $ok = 0, int $fail = 0): void
    {
        if (!$this->jobId) return;

        $key   = $this->progressKey($this->jobId);
        $state = Cache::get($key, []);

        // counts.images
        $countsImages = $state['counts']['images'] ?? [];
        $countsImages['processed'] = (int)($countsImages['processed'] ?? 0) + $images;
        $countsImages['ok']        = (int)($countsImages['ok'] ?? 0) + $ok;
        $countsImages['fail']      = (int)($countsImages['fail'] ?? 0) + $fail;
        $state['counts']['images'] = $countsImages;

        // meta.processed_records (para el % global 40→100 en tu frontend)
        $meta = $state['meta'] ?? [];
        $meta['processed_records'] = (int)($meta['processed_records'] ?? 0) + $images;
        $state['meta'] = $meta;

        Cache::put($key, $state, now()->addHours(2));
    }

    /** Aplica un parche (merge) al estado en cache */
    private function patchProgress(array $patch): void
    {
        if (!$this->jobId) return;

        $key   = $this->progressKey($this->jobId);
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

    private function progressKey(string $jobId): string
    {
        return "campaign_toys:import:progress:{$jobId}";
    }
}
