<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $t0   = microtime(true);
        $user = $request->user();

        // ===== Roles
        $roleAdmin     = $user->hasRole('Admin');
        $roleEjecutiva = $user->hasRole('Ejecutiva-Empresas');
        $roleBusiness  = $user->hasRole('RRHH-Cliente');
        $roleColab     = $user->hasRole('Colaborador');

        // ===== Fecha (timezone app)
        $today = Carbon::today(config('app.timezone', 'UTC'))->toDateString();

        // ===== NIT(s) del usuario
        $userNits = collect(
            is_string($user->nit)
                ? preg_split('/\s*,\s*/', $user->nit, -1, PREG_SPLIT_NO_EMPTY)
                : []
        )->filter()->values();

        // ===== Helpers de filtro por rol (siempre con campañas alias 'c')
        $applyRoleFilterCampaignScoped = function ($q) use ($user, $roleAdmin, $roleEjecutiva, $roleBusiness, $roleColab, $today, $userNits) {
            if ($roleAdmin) {
                return $q;
            }

            if ($roleEjecutiva || $roleBusiness) {
                if ($userNits->isNotEmpty()) {
                    $q->whereIn('c.nit', $userNits);
                } elseif (!empty($user->empresa_id)) {
                    // Mapear empresa_id -> nit sin necesidad de hacer join, usando subconsulta
                    $q->whereIn('c.nit', function ($sq) use ($user) {
                        $sq->from('empresas')->where('id', $user->empresa_id)->select('nit');
                    });
                } else {
                    // Sin vínculo de empresa/NIT => no debe ver nada
                    $q->whereRaw('1=0');
                }

                if ($roleBusiness) {
                    $q->whereDate('c.fechaini', '<=', $today)
                      ->whereDate('c.fechafin', '>=', $today);
                }

                return $q;
            }

            if ($roleColab) {
                if (!empty($user->documento)) {
                    // El colaborador ve campañas donde:
                    //   - aparece como convocado en campaing_colaboradores, o
                    //   - ya tiene una selección en seleccionados
                    $q->where(function ($w) use ($user) {
                        $w->whereExists(function ($ex) use ($user) {
                            $ex->from('campaing_colaboradores as pc')
                               ->whereColumn('pc.idcampaign', 'c.id')
                               ->where('pc.documento', $user->documento);
                        })->orWhereExists(function ($ex) use ($user) {
                            $ex->from('seleccionados as s')
                               ->whereColumn('s.idcampaing', 'c.id')
                               ->where('s.documento', $user->documento);
                        });
                    });
                } else {
                    $q->whereRaw('1=0');
                }
            }

            return $q;
        };

        // ===== Cache key (sensible a usuario/rol/NITs y fecha)
        $cacheKey = sprintf(
            'dash:v2:u:%s:r:%s:nits:%s:day:%s',
            (string) $user->id,
            implode(',', array_filter([
                $roleAdmin ? 'A' : null,
                $roleEjecutiva ? 'E' : null,
                $roleBusiness ? 'B' : null,
                $roleColab ? 'C' : null,
            ])),
            $userNits->implode('|') ?: ($user->empresa_id ?? 'none'),
            $today
        );

        // ===== Carga calculada + cache (2 minutos por defecto)
        $data = Cache::remember($cacheKey, now()->addMinutes(2), function () use (
            $applyRoleFilterCampaignScoped, $today
        ) {
            // ---------- TOP 10 JUGUETES ----------
            // Agregado directo, evitando joins que multipliquen filas.
            // Solo un JOIN a campaign_toys para nombre "amigable".
            $topQ = DB::table('seleccionados as s')
                ->join('campaigns as c', 'c.id', '=', 's.idcampaing')
                ->leftJoin('campaign_toys as t', function ($j) {
                    $j->on('t.idcampaign', '=', 's.idcampaing')
                      ->on('t.referencia',  '=', 's.referencia');
                })
                ->where('c.dashboard', 1);

            $applyRoleFilterCampaignScoped($topQ);

            $top10 = $topQ
                ->selectRaw("
                    s.referencia,
                    COALESCE(NULLIF(t.nombre, ''), CONCAT('Ref ', s.referencia)) AS toy_name,
                    COUNT(*) AS total
                ")
                ->groupBy('s.referencia', 't.nombre')
                ->orderByDesc('total')
                ->limit(10)
                ->get();

            $topLabels = $top10->pluck('toy_name')->values();
            $topRefs   = $top10->pluck('referencia')->values();
            $topCounts = $top10->pluck('total')->values();

            // ---------- PROGRESO POR CAMPAÑA ----------
            // Subconsultas agregadas para colaboradores y seleccionados
            // Evita el multiple-join y COUNT DISTINCT costoso en grandes volúmenes.
            $baseCamps = DB::table('campaigns as c')
                ->where('c.dashboard', 1)
                ->whereDate('c.fechaini', '<=', $today)
                ->whereDate('c.fechafin', '>=', $today);

            $applyRoleFilterCampaignScoped($baseCamps);

            // Subquery: colaboradores convocados por campaña
            $subColabs = DB::table('campaing_colaboradores')
                ->selectRaw('idcampaign, COUNT(DISTINCT documento) AS colaboradores')
                ->groupBy('idcampaign');

            // Subquery: seleccionados por campaña
            $subSels = DB::table('seleccionados')
                ->selectRaw('idcampaing, COUNT(DISTINCT documento) AS seleccionados')
                ->groupBy('idcampaing');

            $progress = DB::query()
                ->fromSub($baseCamps->select('c.id', 'c.nombre', 'c.updated_at'), 'c')
                ->leftJoinSub($subColabs, 'pc', function ($j) {
                    $j->on('pc.idcampaign', '=', 'c.id');
                })
                ->leftJoinSub($subSels, 's', function ($j) {
                    $j->on('s.idcampaing', '=', 'c.id');
                })
                ->selectRaw("
                    c.id,
                    c.nombre AS campaign_name,
                    COALESCE(pc.colaboradores, 0) AS colaboradores,
                    COALESCE(s.seleccionados, 0) AS seleccionados
                ")
                ->orderByDesc('c.updated_at')
                ->get();

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

            return compact('topLabels', 'topRefs', 'topCounts', 'campLabels', 'campSelected', 'campPending', 'campPercent');
        });

        $ms = intval((microtime(true) - $t0) * 1000);
        Log::info('DASHBOARD render', ['ms' => $ms, 'user_id' => $user->id ?? null]);

        return view('dashboard.index', $data);
    }
}
