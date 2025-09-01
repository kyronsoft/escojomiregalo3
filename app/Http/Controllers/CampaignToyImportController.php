<?php

namespace App\Http\Controllers;

use App\Jobs\ImportAndDownloadCampaignToysJob;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// 👇 PhpSpreadsheet para prevalidación del archivo
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class CampaignToyImportController extends Controller
{
    public function showForm()
    {
        return view('campaign_toys.import');
    }

    /**
     * Lanza el Job en cola y devuelve jobId para hacer polling.
     * Prevalida:
     *  - Solo UNA hoja (XLS/XLSX).
     *  - NO filas vacías en el bloque de datos (después del encabezado).
     */
    public function importAsync(Request $request)
    {
        $data = $request->validate([
            'idcampaign' => ['required', 'integer', 'exists:campaigns,id'],
            'file'       => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        $campaign = Campaign::findOrFail($data['idcampaign']);

        $realPath = $request->file('file')->getRealPath();
        $ext      = strtolower($request->file('file')->getClientOriginalExtension());

        try {
            $this->prevalidateSpreadsheet($realPath, $ext);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // NUEVO: cuenta filas de datos para sembrar total_records
        $rowsTotal = $this->countDataRows($realPath, $ext);

        $jobId   = (string) Str::uuid();
        $tmpPath = "tmp/imports/{$jobId}.{$ext}";
        Storage::disk('local')->put($tmpPath, file_get_contents($realPath));

        $key = self::progressKey($jobId);
        Cache::put($key, [
            'status'  => 'queued',
            'percent' => 0,
            'message' => 'En cola…',
            'meta'    => [
                'total_records'     => $rowsTotal,   // 👈 sembrado
                'processed_records' => 0,
            ],
            'counts'  => [
                'import' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
                'images' => ['ok' => 0, 'fail' => 0, 'toys_marked_S' => 0, 'toys_marked_N' => 0, 'processed' => 0, 'total' => 0],
            ],
            // opcional: marca de tiempo si la quieres usar
            'started_at' => null,
        ], now()->addHours(2));

        ImportAndDownloadCampaignToysJob::dispatch(
            campaignId: $campaign->id,
            tmpPath: $tmpPath,
            jobId: $jobId
        )->onQueue('default');

        return response()->json([
            'job_id'  => $jobId,
            'message' => 'Importación encolada',
            // opcional, por si quieres que el front lo muestre de una:
            'meta' => ['total_records' => $rowsTotal],
        ]);
    }

    /**
     * Cuenta las filas con datos (excluye encabezado) respetando la misma
     * lógica de columnas relevantes usada en la prevalidación.
     */
    private function countDataRows(string $realPath, string $ext): int
    {
        if ($ext === 'csv') {
            $fh = fopen($realPath, 'rb');
            if ($fh === false) return 0;
            $count = 0;
            $header = fgetcsv($fh);
            if ($header === false) {
                fclose($fh);
                return 0;
            }
            while (($row = fgetcsv($fh)) !== false) {
                $allEmpty = true;
                foreach ($row as $cell) {
                    if (trim((string)$cell) !== '') {
                        $allEmpty = false;
                        break;
                    }
                }
                if (!$allEmpty) $count++;
            }
            fclose($fh);
            return $count;
        }

        // xls/xlsx
        $reader = IOFactory::createReaderForFile($realPath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($realPath);
        $ws = $spreadsheet->getSheet(0);

        $highestCol   = $ws->getHighestColumn();
        $highestIndex = Coordinate::columnIndexFromString($highestCol);
        $headers = [];
        for ($c = 1; $c <= $highestIndex; $c++) {
            $raw = (string) $ws->getCellByColumnAndRow($c, 1)->getValue();
            $headers[$c] = $this->normHeader($raw);
        }
        $relevantes = ['codigo', 'nombre', 'descripcion', 'genero', 'desde', 'hasta', 'unidades', 'porcentaje', 'combo'];
        $cols = [];
        foreach ($headers as $idx => $h) if (in_array($h, $relevantes, true)) $cols[] = $idx;

        if (empty($cols)) return 0;

        $highestDataRow = $ws->getHighestDataRow();
        $count = 0;
        for ($r = 2; $r <= $highestDataRow; $r++) {
            $allEmpty = true;
            foreach ($cols as $c) {
                $val = $ws->getCellByColumnAndRow($c, $r)->getCalculatedValue();
                if (trim((string)$val) !== '') {
                    $allEmpty = false;
                    break;
                }
            }
            if (!$allEmpty) $count++;
        }
        return $count;
    }

    // =========================
    // Helpers de prevalidación
    // =========================

    /**
     * Prevalida el archivo:
     * - XLS/XLSX: una sola hoja y SIN filas vacías dentro del bloque de datos (desde la fila 2).
     * - CSV: SIN filas vacías (desde línea 2).
     *
     * @throws \RuntimeException si no cumple las reglas
     */
    private function prevalidateSpreadsheet(string $realPath, string $ext): void
    {
        if ($ext === 'csv') {
            $this->validateCsvNoEmptyRows($realPath);
            return;
        }

        // Excel (xls/xlsx)
        $reader = IOFactory::createReaderForFile($realPath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($realPath);

        // 1) Solo una hoja
        $sheetCount = $spreadsheet->getSheetCount();
        if ($sheetCount !== 1) {
            throw new \RuntimeException('El archivo debe contener exactamente UNA hoja. Elimine hojas adicionales.');
        }

        // 2) Sin filas vacías en el bloque de datos
        $ws = $spreadsheet->getSheet(0);

        // Encabezados normalizados (fila 1)
        $highestCol   = $ws->getHighestColumn();
        $highestIndex = Coordinate::columnIndexFromString($highestCol);

        $headers = [];
        for ($c = 1; $c <= $highestIndex; $c++) {
            $raw = (string) $ws->getCellByColumnAndRow($c, 1)->getValue();
            $headers[$c] = $this->normHeader($raw);
        }

        // Columnas relevantes de tu import
        $relevantes = ['codigo', 'nombre', 'descripcion', 'genero', 'desde', 'hasta', 'unidades', 'porcentaje', 'combo'];
        $cols = [];
        foreach ($headers as $idx => $h) {
            if (in_array($h, $relevantes, true)) {
                $cols[] = $idx;
            }
        }

        if (empty($cols)) {
            throw new \RuntimeException('No se encontraron encabezados válidos. Asegúrese de incluir al menos "codigo" y "nombre".');
        }

        $firstEmptyRow = $this->findFirstEmptyDataRow($ws, $cols);
        if ($firstEmptyRow !== null) {
            throw new \RuntimeException("Se detectó al menos una fila vacía en el bloque de datos (fila {$firstEmptyRow}). Elimine TODAS las filas vacías antes de importar.");
        }
    }

    /**
     * Busca la primera fila vacía (todas las columnas relevantes vacías) a partir de la fila 2.
     * Devuelve el número de fila o null si no encuentra vacías.
     */
    private function findFirstEmptyDataRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, array $cols): ?int
    {
        $highestDataRow = $ws->getHighestDataRow(); // último índice con datos
        for ($r = 2; $r <= $highestDataRow; $r++) {
            $allEmpty = true;
            foreach ($cols as $c) {
                $val = $ws->getCellByColumnAndRow($c, $r)->getCalculatedValue();
                if (trim((string)$val) !== '') {
                    $allEmpty = false;
                    break;
                }
            }
            if ($allEmpty) {
                return $r;
            }
        }
        return null;
    }

    /**
     * CSV: valida que NO haya líneas vacías después del encabezado.
     */
    private function validateCsvNoEmptyRows(string $realPath): void
    {
        $fh = fopen($realPath, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('No se pudo leer el archivo CSV.');
        }

        // Leer encabezado
        $header = fgetcsv($fh);
        if ($header === false) {
            fclose($fh);
            throw new \RuntimeException('El CSV no contiene encabezados.');
        }

        $line = 2;
        while (($row = fgetcsv($fh)) !== false) {
            // Consideramos "vacía" si TODAS las celdas están vacías
            $allEmpty = true;
            foreach ($row as $cell) {
                if (trim((string)$cell) !== '') {
                    $allEmpty = false;
                    break;
                }
            }
            if ($allEmpty) {
                fclose($fh);
                throw new \RuntimeException("Se detectó al menos una fila vacía en el bloque de datos (línea {$line}). Elimine TODAS las filas vacías antes de importar.");
            }
            $line++;
        }

        fclose($fh);
    }

    /**
     * Normaliza encabezados: minúsculas, sin tildes, espacios→guiones bajos, solo [a-z0-9_]
     */
    private function normHeader(string $v): string
    {
        $v = trim(mb_strtolower($v));
        // reemplaza tildes
        $v = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
        // espacios/tabs→_
        $v = preg_replace('/\s+/', '_', $v);
        // deja solo a-z0-9_
        $v = preg_replace('/[^a-z0-9_]/', '', $v);
        return $v ?: '';
    }

    // ----------------------
    // Resto de tu controlador (progress, etc.)
    // ----------------------

    public function progress(string $jobId): JsonResponse
    {
        $state = Cache::get(self::progressKey($jobId));

        if (!$state) {
            return response()->json(['status' => 'unknown', 'message' => 'Job no encontrado'], 404);
        }

        $status  = $state['status']  ?? 'running';
        $message = $state['message'] ?? '';
        $percent = isset($state['percent']) ? (float) $state['percent'] : null;
        $meta    = is_array($state['meta'] ?? null) ? $state['meta'] : [];

        $total = $meta['total_records'] ?? $state['total_records'] ?? $meta['total'] ?? $state['total'] ?? null;
        $done  = $meta['processed_records'] ?? $state['processed_records'] ?? $meta['done'] ?? $state['done'] ?? null;

        $total = is_numeric($total) ? (int) $total : null;
        $done  = is_numeric($done)  ? (int) $done  : null;

        if ($percent === null && $total && $total > 0 && $done !== null && $done >= 0) {
            $proc    = max(0, min(1, $done / $total));
            $percent = 40 + ($proc * 60);
        }

        $etaSeconds = null;
        if ($status === 'success') {
            $percent    = 100;
            $etaSeconds = 0;
        } else {
            if ($total !== null && $done !== null && $total >= $done) {
                $remaining = max(0, $total - $done);
                $etaSeconds = $remaining * 3;
            }
        }

        $percent = (float) max(0, min(100, round($percent ?? 0)));

        $state['status']  = $status;
        $state['message'] = $message;
        $state['percent'] = $percent;
        $state['meta'] = [
            'total_records'     => $total,
            'processed_records' => $done,
        ] + $meta;

        $state['timing'] = [
            'eta_seconds' => $etaSeconds,
            'eta_human'   => $etaSeconds !== null ? $this->humanizeSeconds($etaSeconds) : null,
        ];

        return response()->json($state);
    }

    private function humanizeSeconds(int $seconds): string
    {
        if ($seconds <= 0) return '0s';
        $h = intdiv($seconds, 3600);
        $seconds -= $h * 3600;
        $m = intdiv($seconds, 60);
        $s = $seconds - $m * 60;

        $pad = static fn($n) => str_pad((string)$n, 2, '0', STR_PAD_LEFT);

        if ($h > 0) return sprintf('%dh %sm %ss', $h, $pad($m), $pad($s));
        if ($m > 0) return sprintf('%dm %ss', $m, $pad($s));
        return sprintf('%ds', $s);
    }

    private static function progressKey(string $jobId): string
    {
        return "campaign_toys:import:progress:{$jobId}";
    }
}
