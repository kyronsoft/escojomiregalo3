<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $roleAdmin     = $user->hasRole('Admin');
        $roleEjecutiva = $user->hasRole('Ejecutiva-Empresas');
        $roleBusiness  = $user->hasRole('RRHH-Cliente');
        $roleColab     = $user->hasRole('Colaborador');

        $today = \Carbon\Carbon::now(config('app.timezone', 'UTC'))->toDateString();

        // NIT(s) asignados al usuario (puede venir separado por comas)
        $userNits = collect(
            is_string($user->nit)
                ? preg_split('/\s*,\s*/', $user->nit, -1, PREG_SPLIT_NO_EMPTY)
                : []
        )->filter()->values();

        $applyRoleFilter = function ($q, bool $hasPcJoin = false)
        use ($user, $roleAdmin, $roleEjecutiva, $roleBusiness, $roleColab, $today, $userNits) {

            if ($roleAdmin) return;

            if ($roleEjecutiva) {
                if ($userNits->isNotEmpty()) {
                    $q->whereIn('c.nit', $userNits);
                } elseif (!empty($user->empresa_id)) {
                    $q->where('e.id', $user->empresa_id);
                } else {
                    $q->whereRaw('1=0');
                }
                return;
            }

            if ($roleBusiness) {
                if ($userNits->isNotEmpty()) {
                    $q->whereIn('c.nit', $userNits);
                } elseif (!empty($user->empresa_id)) {
                    $q->where('e.id', $user->empresa_id);
                } else {
                    $q->whereRaw('1=0');
                }
                $q->whereDate('c.fechaini', '<=', $today)
                  ->whereDate('c.fechafin', '>=', $today);
                return;
            }

            if ($roleColab) {
                if (!empty($user->documento)) {
                    $q->where(function ($w) use ($user, $hasPcJoin) {
                        $w->where('s.documento', $user->documento);
                        if ($hasPcJoin) {
                            $w->orWhere('pc.documento', $user->documento);
                        }
                    });
                } else {
                    $q->whereRaw('1=0');
                }
            }
        };

        // ===== Top 10 juguetes seleccionados (solo campañas con dashboard activo) =====
        $top10Q = \DB::table('seleccionados as s')
            ->join('campaigns as c', 'c.id', '=', 's.idcampaing')
            ->leftJoin('empresas as e', 'e.nit', '=', 'c.nit')
            ->leftJoin('campaign_toys as t', function ($join) {
                $join->on('t.idcampaign', '=', 's.idcampaing')
                     ->on('t.referencia',  '=', 's.referencia');
            })
            ->where('c.dashboard', 1) // <<— filtro clave
            ->selectRaw("
                COALESCE(NULLIF(t.nombre, ''), CONCAT('Ref ', s.referencia)) AS toy_name,
                s.referencia,
                COUNT(*) AS total
            ")
            ->groupBy('s.idcampaing', 's.referencia', 't.nombre')
            ->orderByDesc('total')
            ->limit(10);

        $applyRoleFilter($top10Q, false);

        $top10     = $top10Q->get();
        $topLabels = $top10->pluck('toy_name')->values();
        $topRefs   = $top10->pluck('referencia')->values();
        $topCounts = $top10->pluck('total')->values();

        // ===== Avance por campaña (solo campañas activas y con dashboard activo) =====
        $progressQ = \DB::table('campaigns as c')
            ->leftJoin('empresas as e', 'e.nit', '=', 'c.nit')
            ->leftJoin('campaing_colaboradores as pc', 'pc.idcampaign', '=', 'c.id')
            ->leftJoin('seleccionados as s', 's.idcampaing', '=', 'c.id')
            ->where('c.dashboard', 1) // <<— filtro clave
            ->whereDate('c.fechaini', '<=', $today)
            ->whereDate('c.fechafin', '>=', $today)
            ->selectRaw("
                c.id,
                c.nombre AS campaign_name,
                COUNT(DISTINCT pc.documento) AS colaboradores,
                COUNT(DISTINCT s.documento)  AS seleccionados
            ")
            ->groupBy('c.id', 'c.nombre')
            ->orderBy('c.updated_at', 'desc');

        $applyRoleFilter($progressQ, true);

        $progress     = $progressQ->get();
        $campLabels   = [];
        $campSelected = [];
        $campPending  = [];
        $campPercent  = [];

        foreach ($progress as $row) {
            $totalColab = (int) $row->colaboradores;
            $sel        = (int) $row->seleccionados;
            $pending    = max($totalColab - $sel, 0);
            $pct        = $totalColab > 0 ? round(($sel / $totalColab) * 100, 1) : 0.0;

            $campLabels[]   = $row->campaign_name;
            $campSelected[] = $sel;
            $campPending[]  = $pending;
            $campPercent[]  = $pct;
        }

        return view('dashboard.index', [
            'topLabels'    => $topLabels,
            'topRefs'      => $topRefs,
            'topCounts'    => $topCounts,
            'campLabels'   => $campLabels,
            'campSelected' => $campSelected,
            'campPending'  => $campPending,
            'campPercent'  => $campPercent,
        ]);
    }
}
