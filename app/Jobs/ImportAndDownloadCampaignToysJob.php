<?php

namespace App\Jobs;

use App\Imports\CampaignToysImport;
use App\Models\CampaignToy;
use App\Services\MsGraphClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ImportAndDownloadCampaignToysJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $campaignId;
    public string $tmpPath; // storage/app/tmp/imports/<uuid>.xlsx
    public string $jobId;

    public function __construct(int $campaignId, string $tmpPath, string $jobId)
    {
        $this->campaignId = $campaignId;
        $this->tmpPath    = $tmpPath;
        $this->jobId      = $jobId;
    }

    public function handle(MsGraphClient $graph): void
    {
        $key = $this->progressKey();

        $state = Cache::get($key, []);
        if (empty($state['timing']['started_at'])) {
            $state['timing'] = [
                'started_at' => now()->timestamp, // epoch (segundos)
                'last_update' => now()->timestamp,
                'elapsed'    => 0,
                'eta'        => null,  // en segundos
                'eta_human'  => null,
            ];
            Cache::put($key, $state, now()->addHours(2));
        }

        // --- Etapa 1: IMPORT ---
        $this->updateProgress('running', 5, 'Importando Excel…');

        $import = new CampaignToysImport(\App\Models\Campaign::findOrFail($this->campaignId));
        try {
            Excel::import($import, Storage::disk('local')->path($this->tmpPath));
        } catch (Throwable $e) {
            $this->failWithMessage("Error importando: {$e->getMessage()}");
            return;
        } finally {
            // elimina tmp
            Storage::disk('local')->delete($this->tmpPath);
        }

        // Guardar conteos import
        $state = Cache::get($key, []);
        $state['counts']['import'] = $import->summary(); // ['creados','actualizados','omitidos']
        $state['percent'] = 40;
        $state['message'] = 'Importación completada. Preparando descarga de imágenes…';
        $state['status']  = 'running';
        Cache::put($key, $state, now()->addHours(2));

        // --- Etapa 2: Descarga de imágenes ---
        $this->updateProgress('running', 45, 'Indexando imágenes en OneDrive…');

        try {
            $token   = $graph->getToken();
            $share   = config('services.msgraph.share_url');
            $files   = $graph->listAllSharedChildren($share, $token);
        } catch (Throwable $e) {
            $this->failWithMessage("Error indexando OneDrive: {$e->getMessage()}");
            return;
        }

        $index = [];
        foreach ($files as $f) {
            if (!empty($f['name']) && !empty($f['downloadUrl'])) {
                $index[mb_strtolower(trim($f['name']))] = $f['downloadUrl'];
            }
        }

        // Calcula total de imágenes a procesar (partes incluidas si combo)
        $toys = CampaignToy::where('idcampaign', $this->campaignId)->get(['id', 'combo', 'imagenppal']);
        $totalImages = 0;
        foreach ($toys as $t) {
            $img = trim((string) $t->imagenppal);
            if ($img === '') continue;
            $totalImages += ($t->combo === 'COM')
                ? count(array_filter(array_map('trim', explode('+', $img))))
                : 1;
        }

        $ok = 0;
        $fail = 0;
        $markS = 0;
        $markN = 0;
        $processed = 0;

        // Guardar en estado
        $this->updateCounts([
            'images' => [
                'ok' => 0,
                'fail' => 0,
                'toys_marked_S' => 0,
                'toys_marked_N' => 0,
                'processed' => 0,
                'total' => $totalImages
            ]
        ]);

        // Descarga
        foreach ($toys as $toy) {
            $img = trim((string) $toy->imagenppal);
            if ($img === '') {
                $toy->update(['imgexists' => 'N']);
                $markN++;
                $this->bumpImages($processed, $totalImages, $ok, $fail, $markS, $markN);
                continue;
            }

            $names = ($toy->combo === 'COM')
                ? array_filter(array_map('trim', explode('+', $img)))
                : [$img];

            $allOk = true;
            $anyOk = false;

            foreach ($names as $name) {
                $processed++;
                $keyName = mb_strtolower($name);
                if (!isset($index[$keyName])) {
                    $allOk = false;
                    $fail++;
                    Log::warning('Imagen no encontrada OneDrive', ['file' => $name, 'toy_id' => $toy->id]);
                    $this->bumpImages($processed, $totalImages, $ok, $fail, $markS, $markN);
                    continue;
                }
                try {
                    $bin  = $graph->downloadBySignedUrl($index[$keyName]);
                    $path = "campaign_toys/{$this->campaignId}/{$name}";
                    Storage::disk('public')->put($path, $bin);
                    $ok++;
                    $anyOk = true;
                } catch (Throwable $e) {
                    $allOk = false;
                    $fail++;
                    Log::error('Error descargando imagen', ['file' => $name, 'toy_id' => $toy->id, 'err' => $e->getMessage()]);
                }
                $this->bumpImages($processed, $totalImages, $ok, $fail, $markS, $markN);
            }

            $setS = ($toy->combo === 'COM') ? (count($names) > 0 && $allOk) : $anyOk;
            $toy->update(['imgexists' => $setS ? 'S' : 'N']);
            if ($setS) $markS++;
            else $markN++;

            // actualizar contadores post toy
            $this->bumpImages($processed, $totalImages, $ok, $fail, $markS, $markN);
        }

        // Final
        $this->updateProgress('success', 100, 'Proceso finalizado.', [
            'images' => [
                'ok' => $ok,
                'fail' => $fail,
                'toys_marked_S' => $markS,
                'toys_marked_N' => $markN,
                'processed' => $processed,
                'total' => $totalImages
            ]
        ]);
    }

    public function failed(Throwable $e): void
    {
        $this->failWithMessage("Fallo general del Job: {$e->getMessage()}");
    }

    // ---- Helpers progreso ----
    private function progressKey(): string
    {
        return "campaign_toys:import:progress:{$this->jobId}";
    }

    private function updateProgress(string $status, int $percent, string $message, array $mergeCounts = []): void
    {
        $key = $this->progressKey();
        $state = Cache::get($key, []);
        $state['status']  = $status;
        $state['percent'] = $percent;
        $state['message'] = $message;
        if (!isset($state['counts'])) $state['counts'] = [];
        foreach ($mergeCounts as $k => $v) {
            $state['counts'][$k] = array_merge($state['counts'][$k] ?? [], $v);
        }
        Cache::put($key, $state, now()->addHours(2));
    }

    private function updateCounts(array $mergeCounts): void
    {
        $key = $this->progressKey();
        $state = Cache::get($key, []);
        if (!isset($state['counts'])) $state['counts'] = [];
        foreach ($mergeCounts as $k => $v) {
            $state['counts'][$k] = array_merge($state['counts'][$k] ?? [], $v);
        }
        Cache::put($key, $state, now()->addHours(2));
    }

    private function bumpImages(int $processed, int $total, int $ok, int $fail, int $markS, int $markN): void
    {
        $percent = 40 + (int) (($total > 0 ? ($processed / $total) : 1) * 60);

        // Actualiza contadores y mensaje
        $this->updateProgress('running', min(100, $percent), "Descargando imágenes… {$processed}/{$total}", [
            'images' => [
                'ok' => $ok,
                'fail' => $fail,
                'toys_marked_S' => $markS,
                'toys_marked_N' => $markN,
                'processed' => $processed,
                'total' => $total
            ]
        ]);

        // 🔸 calcular ETA y guardarlo
        $this->computeEtaAndUpdateState($processed, $total);
    }

    private function failWithMessage(string $msg): void
    {
        $key = $this->progressKey();
        $state = Cache::get($key, []);
        $state['status']  = 'error';
        $state['percent'] = $state['percent'] ?? 0;
        $state['message'] = $msg;
        Cache::put($key, $state, now()->addHours(2));
        Log::error($msg);
    }

    private function computeEtaAndUpdateState(int $processed, int $total): void
    {
        $key   = $this->progressKey();
        $state = Cache::get($key, []);

        $now        = now()->timestamp;
        $started_at = $state['timing']['started_at'] ?? $now;
        $elapsed    = max(1, $now - $started_at);         // s (evitar /0)
        $rate       = $processed > 0 ? ($processed / $elapsed) : 0; // imgs/s
        $remaining  = max(0, $total - $processed);
        $eta        = ($rate > 0) ? (int) ceil($remaining / $rate) : null;

        $state['timing'] = [
            'started_at' => $started_at,
            'last_update' => $now,
            'elapsed'    => $elapsed,
            'eta'        => $eta,
            'eta_human'  => $eta !== null ? $this->formatSeconds($eta) : null,
        ];

        Cache::put($key, $state, now()->addHours(2));
    }

    private function formatSeconds(int $s): string
    {
        $m = intdiv($s, 60);
        $r = $s % 60;
        return sprintf('%02d:%02d', $m, $r); // mm:ss
    }
}
