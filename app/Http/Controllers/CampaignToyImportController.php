<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ImportAndDownloadCampaignToysJob;

class CampaignToyImportController extends Controller
{
    public function showForm()
    {
        return view('campaign_toys.import'); // tu vista con Select2 + formulario
    }

    /**
     * Lanza el Job en cola y devuelve jobId para hacer polling.
     */
    public function importAsync(Request $request)
    {
        $data = $request->validate([
            'idcampaign' => ['required', 'integer', 'exists:campaigns,id'],
            'file'       => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        $campaign = Campaign::findOrFail($data['idcampaign']);

        // guardar archivo temporal
        $jobId   = (string) Str::uuid();
        $tmpPath = "tmp/imports/{$jobId}.xlsx";
        Storage::disk('local')->put($tmpPath, file_get_contents($request->file('file')->getRealPath()));

        // Semilla de progreso en cache (TTL 2 horas)
        $key = self::progressKey($jobId);
        Cache::put($key, [
            'status'   => 'queued',
            'percent'  => 0,
            'message'  => 'En cola…',
            'counts'   => [
                'import' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
                'images' => ['ok' => 0, 'fail' => 0, 'toys_marked_S' => 0, 'toys_marked_N' => 0, 'processed' => 0, 'total' => 0],
            ],
        ], now()->addHours(2));

        // Despachar job asíncrono
        ImportAndDownloadCampaignToysJob::dispatch(
            campaignId: $campaign->id,
            tmpPath: $tmpPath,
            jobId: $jobId
        )->onQueue('default');

        return response()->json([
            'job_id'  => $jobId,
            'message' => 'Importación encolada',
        ]);
    }

    public function progress(string $jobId): JsonResponse
    {
        $state = Cache::get(self::progressKey($jobId));

        if (!$state) {
            return response()->json(['status' => 'unknown', 'message' => 'Job no encontrado'], 404);
        }

        // Normaliza estructura
        $status  = $state['status']  ?? 'running';
        $message = $state['message'] ?? '';
        $percent = isset($state['percent']) ? (float) $state['percent'] : null;
        $meta    = is_array($state['meta'] ?? null) ? $state['meta'] : [];

        // Intento de lectura flexible de total y procesados (por si guardaste con otros nombres)
        $total = $meta['total_records']
            ?? $state['total_records']
            ?? $meta['total']
            ?? $state['total']
            ?? null;

        $done = $meta['processed_records']
            ?? $state['processed_records']
            ?? $meta['done']
            ?? $state['done']
            ?? null;

        $total = is_numeric($total) ? (int) $total : null;
        $done  = is_numeric($done)  ? (int) $done  : null;

        // Si no hay percent y sí hay conteos, calcula percent ESCALADO 40→100 (la subida usa 0→40)
        if ($percent === null && $total && $total > 0 && $done !== null && $done >= 0) {
            $proc = max(0, min(1, $done / $total));    // 0..1
            $percent = 40 + ($proc * 60);              // 40..100
        }

        // Ajustes finales por estado
        $etaSeconds = null;
        if ($status === 'success') {
            $percent    = 100;
            $etaSeconds = 0;
        } else {
            // ETA = (registros restantes) * 3s
            if ($total !== null && $done !== null && $total >= $done) {
                $remaining = max(0, $total - $done);
                $etaSeconds = $remaining * 3;
            }
        }

        // Clamp y redondeo
        $percent = (float) max(0, min(100, round($percent ?? 0)));

        // Ensambla respuesta consistente
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

    /**
     * Convierte segundos a "1h 05m 03s", "5m 07s" o "12s".
     */
    private function humanizeSeconds(int $seconds): string
    {
        if ($seconds <= 0) return '0s';
        $h = intdiv($seconds, 3600);
        $seconds -= $h * 3600;
        $m = intdiv($seconds, 60);
        $s = $seconds - $m * 60;

        $pad = static fn($n) => str_pad((string)$n, 2, '0', STR_PAD_LEFT);

        if ($h > 0) {
            return sprintf('%dh %sm %ss', $h, $pad($m), $pad($s));
        }
        if ($m > 0) {
            return sprintf('%dm %ss', $m, $pad($s));
        }
        return sprintf('%ds', $s);
    }

    private static function progressKey(string $jobId): string
    {
        return "campaign_toys:import:progress:{$jobId}";
    }
}
