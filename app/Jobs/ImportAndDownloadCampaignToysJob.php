<?php

namespace App\Jobs;

use App\Imports\CampaignToysImport;
use App\Jobs\DownloadCampaignToyImagesJob;
use App\Models\Campaign;
use App\Models\CampaignToy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ImportAndDownloadCampaignToysJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $campaignId;
    public string $tmpPath; // ej: "tmp/imports/{jobId}.xlsx"
    public string $jobId;

    public function __construct(int $campaignId, string $tmpPath, string $jobId)
    {
        $this->campaignId = $campaignId;
        $this->tmpPath    = $tmpPath;
        $this->jobId      = $jobId;
    }

    public function handle(): void
    {
        $campaign = Campaign::findOrFail($this->campaignId);
        $absPath  = storage_path('app/' . $this->tmpPath);

        // -------------------------
        // 1) PRE-CÁLCULO: total filas a importar
        // -------------------------
        [$rowsTotal, $headerCols] = $this->countDataRows($absPath);

        // Si ya venías sembrando algo en cache desde el controller, lo respetamos y parcheamos
        $this->patchProgress([
            'status'  => 'running',
            'message' => 'Importando referencias…',
            'meta'    => [
                'total_records'     => $rowsTotal,   // se sumarán las imágenes después
                'processed_records' => 0,
            ],
            'counts'  => [
                'import' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
                'images' => ['ok' => 0, 'fail' => 0, 'processed' => 0, 'total' => 0],
            ],
        ]);

        // -------------------------
        // 2) IMPORTACIÓN (fila a fila) con progress
        // -------------------------
        $import = new CampaignToysImport($campaign, $this->jobId);
        Excel::import($import, $absPath);

        $sum = $import->summary();
        $this->patchProgress([
            'message' => 'Importación finalizada. Preparando descarga de imágenes…',
            'counts'  => [
                'import' => [
                    'created'  => $sum['creados']      ?? 0,
                    'updated'  => $sum['actualizados'] ?? 0,
                    'skipped'  => $sum['omitidos']     ?? 0,
                ],
            ],
        ]);

        // -------------------------
        // 3) DESCARGA DE IMÁGENES (Microsoft Graph) + progress
        // -------------------------
        // Calculamos total de imágenes por campaña (sumando partes de combos)
        $imagesTotal = $this->countImagesToDownload();
        // Sumamos al total global (filas + imágenes)
        $state = $this->getState();
        $prevTotal = (int)($state['meta']['total_records'] ?? 0);
        $this->patchProgress([
            'message' => 'Descargando imágenes…',
            'meta'    => [
                'total_records' => $prevTotal + $imagesTotal,
            ],
            'counts'  => [
                'images' => [
                    'total'     => $imagesTotal,
                    'processed' => 0,
                    'ok'        => 0,
                    'fail'      => 0,
                ]
            ],
        ]);

        // Ejecuta el job de descarga (sincrónico dentro del mismo worker para continuar actualizando el progreso)
        DownloadCampaignToyImagesJob::dispatchSync($this->campaignId, $this->jobId);

        // -------------------------
        // 4) FINAL
        // -------------------------
        $this->patchProgress([
            'status'  => 'success',
            'message' => 'Proceso finalizado.',
            // percent lo puede calcular el controlador; si quieres, puedes forzarlo a 100 aquí también
            'percent' => 100,
        ]);

        // Limpia el archivo temporal
        Storage::disk('local')->delete($this->tmpPath);
    }

    private function countDataRows(string $absPath): array
    {
        $reader = IOFactory::createReaderForFile($absPath);
        $reader->setReadDataOnly(true);
        $ss = $reader->load($absPath);
        $ws = $ss->getSheet(0);

        $highestCol   = $ws->getHighestColumn();
        $highestIndex = Coordinate::columnIndexFromString($highestCol);

        // cuenta filas no vacías desde la 2
        $rows = 0;
        $last = $ws->getHighestDataRow();
        for ($r = 2; $r <= $last; $r++) {
            $nonEmpty = false;
            for ($c = 1; $c <= $highestIndex; $c++) {
                $val = trim((string)$ws->getCellByColumnAndRow($c, $r)->getCalculatedValue());
                if ($val !== '') {
                    $nonEmpty = true;
                    break;
                }
            }
            if ($nonEmpty) $rows++;
        }
        return [$rows, $highestIndex];
    }

    private function countImagesToDownload(): int
    {
        $items = CampaignToy::where('idcampaign', $this->campaignId)->get(['imagenppal', 'combo']);
        $total = 0;
        foreach ($items as $t) {
            $img = trim((string)$t->imagenppal);
            if ($img === '') continue;
            $total += ($t->combo === 'COM') ? count(array_filter(array_map('trim', explode('+', $img)))) : 1;
        }
        return $total;
    }

    private function getState(): array
    {
        return Cache::get(self::progressKey($this->jobId), []);
    }

    private function patchProgress(array $patch): void
    {
        $key   = self::progressKey($this->jobId);
        $state = Cache::get($key, []);
        // merge superficial + anidado simple
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
