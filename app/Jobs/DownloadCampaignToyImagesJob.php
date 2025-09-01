<?php

namespace App\Jobs;

use App\Models\CampaignToy;
use App\Services\MsGraphClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DownloadCampaignToyImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $campaignId;

    /** Resultado compacto para la vista */
    public array $result = [
        'ok'   => 0,  // imágenes descargadas
        'fail' => 0,  // imágenes faltantes/errores
        // (opcional) también puedes incluir:
        // 'toys_marked_S' => 0,
        // 'toys_marked_N' => 0,
    ];

    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
    }

    public function handle(MsGraphClient $graph): void
    {
        $token    = $graph->getToken();
        $shareUrl = config('services.msgraph.share_url');

        // Índice nombre -> downloadUrl (en minúsculas)
        $files = $graph->listAllSharedChildren($shareUrl, $token);
        $index = [];
        foreach ($files as $f) {
            $index[mb_strtolower(trim($f['name']))] = $f['downloadUrl'];
        }

        // Recorremos los toys de la campaña
        $items = CampaignToy::where('idcampaign', $this->campaignId)->get(['id', 'combo', 'imagenppal']);

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
                    Log::warning('Imagen no encontrada', ['toy_id' => $toy->id, 'file' => $name]);
                    $allOk = false;
                    $this->result['fail']++;
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
                        'file' => $name,
                        'error' => $e->getMessage()
                    ]);
                    $allOk = false;
                    $this->result['fail']++;
                }
            }

            // Regla de marcado:
            // - NC: S si anyOk
            // - COM: S solo si allOk (todas las partes)
            $markS = $toy->combo === 'COM' ? (count($names) > 0 && $allOk) : $anyOk;
            $toy->update(['imgexists' => $markS ? 'S' : 'N']);

            // (Opcional) contadores por toy
            // $this->result[$markS ? 'toys_marked_S' : 'toys_marked_N'] =
            //    ($this->result[$markS ? 'toys_marked_S' : 'toys_marked_N']] ?? 0) + 1;
        }
    }
}
