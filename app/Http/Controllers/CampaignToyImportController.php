<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Endpoint de progreso para polling.
     */
    public function progress(string $jobId)
    {
        $state = Cache::get(self::progressKey($jobId));
        if (!$state) {
            return response()->json(['status' => 'unknown', 'message' => 'Job no encontrado'], 404);
        }
        return response()->json($state);
    }

    private static function progressKey(string $jobId): string
    {
        return "campaign_toys:import:progress:{$jobId}";
    }
}
