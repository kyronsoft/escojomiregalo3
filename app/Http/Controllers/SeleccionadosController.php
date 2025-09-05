<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\SeleccionadosExport;

class SeleccionadosController extends Controller
{
    public function index(Request $request)
    {
        $perPage    = (int) $request->input('per_page', 25);
        $perPage    = max(5, min($perPage, 200));

        $q = $this->baseQuery($request);

        $rows = $q->orderByDesc('s.created_at')
            ->paginate($perPage)
            ->withQueryString();

        $campaigns = Campaign::orderBy('updated_at', 'desc')->get(['id', 'nombre']);

        // valores para mantener filtros en la vista
        $campaignId = $request->input('idcampaign', $request->input('idcampaing'));
        $referencia = $request->input('referencia');
        $documento  = $request->input('documento');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');

        return view('seleccionados.index', compact(
            'rows',
            'campaigns',
            'campaignId',
            'referencia',
            'documento',
            'dateFrom',
            'dateTo',
            'perPage'
        ));
    }

    public function export(Request $request)
    {
        $filename = 'seleccionados_' . now()->format('Ymd_His') . '.xlsx';
        return (new SeleccionadosExport($request->all()))->download($filename);
    }

    /**
     * Builder base con filtros compartidos (index/export)
     */
    private function baseQuery(Request $request)
    {
        $campaignId = $request->input('idcampaign', $request->input('idcampaing'));
        $referencia = $request->input('referencia');
        $documento  = $request->input('documento');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');

        $q = DB::table('seleccionados as s')
            ->leftJoin('campaigns as c', 'c.id', '=', 's.idcampaing')
            ->leftJoin('campaign_toys as t', function ($join) {
                $join->on('t.idcampaign', '=', 's.idcampaing')
                    ->on('t.referencia', '=', 's.referencia');
            })
            ->select([
                's.id',
                's.idcampaing',
                's.referencia',
                's.documento',
                's.created_at',
                DB::raw("COALESCE(NULLIF(t.nombre,''), CONCAT('Ref ', s.referencia)) as toy_name"),
                'c.nombre as campaign_name',
            ]);

        if (!empty($campaignId)) {
            $q->where('s.idcampaing', (int) $campaignId);
        }
        if (!empty($referencia)) {
            $q->where('s.referencia', 'like', "%{$referencia}%");
        }
        if (!empty($documento)) {
            $q->where('s.documento', 'like', "%{$documento}%");
        }
        if (!empty($dateFrom)) {
            $q->whereDate('s.created_at', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $q->whereDate('s.created_at', '<=', $dateTo);
        }

        return $q;
    }
}
