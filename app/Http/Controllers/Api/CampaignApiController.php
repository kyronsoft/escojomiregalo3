<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;

class CampaignApiController extends Controller
{
    public function index(Request $request)
    {
        $q        = trim((string) $request->input('q', ''));
        $page     = max(1, (int) $request->input('page', 1));
        $perPage  = max(1, min(50, (int) $request->input('per_page', 20)));
        $nit      = $request->input('nit');          // opcional
        $onlyActive = (bool) $request->boolean('only_active'); // <<--- NUEVO

        $query = \App\Models\Campaign::query();

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('nombre', 'like', "%{$q}%")
                    ->orWhere('subject', 'like', "%{$q}%")
                    ->orWhere('id', $q);
            });
        }

        if ($nit) {
            $query->where('nit', $nit);
        }

        if ($onlyActive) {
            $now = now(); // usa timezone de la app
            $query->where('fechaini', '<=', $now)
                ->where('fechafin', '>=', $now);
        }

        $query->orderBy('nombre');

        $offset = ($page - 1) * $perPage;
        $items = $query->skip($offset)->take($perPage)->get(['id', 'nombre', 'nit', 'fechaini', 'fechafin']);

        $countForMore = (clone $query)->skip($offset + $perPage)->take(1)->exists();

        $results = $items->map(function ($c) {
            $iniTxt = $c->fechaini ? (is_string($c->fechaini) ? substr($c->fechaini, 0, 10) : $c->fechaini->format('Y-m-d')) : '';
            $finTxt = $c->fechafin ? (is_string($c->fechafin) ? substr($c->fechafin, 0, 10) : $c->fechafin->format('Y-m-d')) : '';
            $label  = "{$c->nombre} (ID {$c->id})";
            if ($c->nit) $label .= " — {$c->nit}";
            if ($iniTxt || $finTxt) $label .= " — {$iniTxt}" . ($finTxt ? " → {$finTxt}" : "");
            return ['id' => $c->id, 'text' => $label];
        });

        return response()->json([
            'results'    => $results,
            'pagination' => ['more' => (bool) $countForMore],
        ]);
    }
}
