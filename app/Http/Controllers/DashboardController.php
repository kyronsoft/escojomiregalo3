<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // ===== Roles (usar nombres consistentes) =====
        $roleAdmin     = $user->hasRole('Admin');
        $roleEjecutiva = $user->hasRole('Ejecutiva-Empresas');
        $roleBusiness  = $user->hasRole('RRHH-Cliente');
        $roleColab     = $user->hasRole('Colaborador');

        $today = Carbon::now(config('app.timezone', 'UTC'))->toDateString();

        /**
         * Aplica filtro por rol a un query builder.
         * $hasPcJoin = true cuando la consulta tenga join a "campaing_colaboradores as pc"
         * para poder filtrar por pc.documento (en progreso de campaña).
         */
        $applyRoleFilter = function ($q, bool $hasPcJoin = false) use ($user, $roleAdmin, $roleEjecutiva, $roleBusiness, $roleColab, $today) {
            if ($roleAdmin) return;

            if ($roleEjecutiva) {
                // Si manejas asignación de empresas por ejecutiva, aplícala aquí (whereIn por NIT)
                return;
            }

            if ($roleBusiness) {
                // Restringe por empresa del usuario
                if (!empty($user->nit)) {
                    $q->where('c.nit', $user->nit);
                } elseif (!empty($user->empresa_id)) {
                    $q->where('e.id', $user->empresa_id);
                } else {
                    // si no hay empresa asociada, no mostrar nada
                    $q->whereRaw('1=0');
                }

                // Solo campañas vigentes para RRHH-Cliente
                $q->whereDate('c.fechaini', '<=', $today)
                    ->whereDate('c.fechafin', '>=', $today);

                return;
            }

            if ($roleColab) {
                // Solo datos del colaborador
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

        // ========= Top 10 juguetes seleccionados =========
        $top10Q = DB::table('seleccionados as s')
            ->join('campaigns as c', 'c.id', '=', 's.idcampaing')
            ->leftJoin('empresas as e', 'e.nit', '=', 'c.nit')
            ->leftJoin('campaign_toys as t', function ($join) {
                $join->on('t.idcampaign', '=', 's.idcampaing')
                    ->on('t.referencia', '=', 's.referencia');
            })
            ->selectRaw("
                COALESCE(NULLIF(t.nombre, ''), CONCAT('Ref ', s.referencia)) as toy_name,
                s.referencia,
                COUNT(*) as total
            ")
            ->groupBy('s.idcampaing', 's.referencia', 't.nombre')
            ->orderByDesc('total')
            ->limit(10);

        // Para Top10 la consulta NO tiene join a pc, por eso pasamos false
        $applyRoleFilter($top10Q, false);

        // (Opcional) si quisieras que TODOS vean sólo campañas activas en Top10, descomenta:
        // $top10Q->whereDate('c.fechaini', '<=', $today)->whereDate('c.fechafin', '>=', $today);

        $top10 = $top10Q->get();
        $topLabels = $top10->pluck('toy_name')->values();
        $topCounts = $top10->pluck('total')->values();

        // ========= Avance de campaña (siempre ACTIVAS) =========
        $progressQ = DB::table('campaigns as c')
            ->leftJoin('empresas as e', 'e.nit', '=', 'c.nit')
            ->leftJoin('campaing_colaboradores as pc', 'pc.idcampaign', '=', 'c.id')
            ->leftJoin('seleccionados as s', 's.idcampaing', '=', 'c.id')
            ->whereDate('c.fechaini', '<=', $today)
            ->whereDate('c.fechafin', '>=', $today)
            ->selectRaw("
                c.id,
                c.nombre as campaign_name,
                COUNT(DISTINCT pc.documento) as colaboradores,
                COUNT(DISTINCT s.documento) as seleccionados
            ")
            ->groupBy('c.id', 'c.nombre')
            ->orderBy('c.updated_at', 'desc');

        // Para progreso SÍ hay join a pc, pasamos true para habilitar el filtro pc.documento
        $applyRoleFilter($progressQ, true);

        $progress = $progressQ->get();

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
            'topCounts'    => $topCounts,
            'campLabels'   => $campLabels,
            'campSelected' => $campSelected,
            'campPending'  => $campPending,
            'campPercent'  => $campPercent,
        ]);
    }
}
