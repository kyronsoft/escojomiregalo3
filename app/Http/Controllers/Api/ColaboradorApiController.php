<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use Illuminate\Http\Request;

class ColaboradorApiController extends Controller
{
    public function index(Request $request)
    {
        // Parámetros Select2
        $q        = trim((string) $request->input('q', ''));
        $page     = max(1, (int) $request->input('page', 1));
        $perPage  = max(1, min(50, (int) $request->input('per_page', 20)));

        // Filtros opcionales: por empresa (nit) o por ciudad
        $nit      = $request->input('nit');      // opcional
        $ciudad   = $request->input('ciudad');   // opcional

        $query = Colaborador::query();

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('documento', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($nit) {
            $query->where('nit', $nit);
        }

        if ($ciudad) {
            $query->where('ciudad', $ciudad);
        }

        $query->orderBy('nombre');

        $offset = ($page - 1) * $perPage;

        $items = $query->skip($offset)->take($perPage)->get(['documento', 'nombre', 'email']);

        $countForMore = (clone $query)->skip($offset + $perPage)->take(1)->exists();
        // También podrías usar ->count() para exactitud a cambio de costo

        // Mapear al shape Select2
        $results = $items->map(function ($c) {
            $label = trim($c->nombre ?: $c->documento);
            if ($c->email) {
                $label .= " — {$c->email}";
            }
            return [
                'id'   => $c->documento,
                'text' => $label,
            ];
        });

        return response()->json([
            'results'    => $results,
            'pagination' => ['more' => (bool) $countForMore],
        ]);
    }
}
