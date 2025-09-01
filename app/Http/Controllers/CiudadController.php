<?php

namespace App\Http\Controllers;

use App\Models\Ciudad;
use Illuminate\Http\Request;

class CiudadController extends Controller
{
    /**
     * Endpoint GET /api/ciudades
     * Soporta:
     * - q:     término de búsqueda (por nombre o código)
     * - page:  página (1 por defecto)
     * - per_page: tamaño de página (20 por defecto)
     * - coddepto: (opcional) filtra por código de departamento
     *
     * Respuesta compatible con Select2:
     * {
     *   results: [{id, text, codigo, nombre, coddepto, departamento}],
     *   pagination: { more: bool }
     * }
     */
    public function index(Request $request)
    {
        $q        = trim((string) $request->input('q', ''));
        $page     = max(1, (int) $request->input('page', 1));
        $perPage  = (int) $request->input('per_page', 20);
        $coddepto = $request->input('coddepto'); // opcional

        $query = Ciudad::query()
            ->when($coddepto, fn($qb) => $qb->where('coddepto', $coddepto))
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('nombre', 'like', "%{$q}%")
                        ->orWhere('codigo', 'like', "%{$q}%");
                });
            });

        $total = (clone $query)->count();

        // Paginación manual estilo Select2
        $rows = $query->orderBy('nombre')
            ->forPage($page, $perPage)
            ->get(['codigo', 'nombre', 'coddepto', 'departamento']);

        // Map a formato Select2 (id/text) + campos extra por si los necesitas
        $results = $rows->map(function ($c) {
            return [
                'id'           => $c->codigo,
                'text'         => $c->nombre,
                'codigo'       => $c->codigo,
                'nombre'       => $c->nombre,
                'coddepto'     => $c->coddepto,
                'departamento' => $c->departamento,
            ];
        });

        $more = ($page * $perPage) < $total;

        return response()->json([
            'results'    => $results,
            'pagination' => ['more' => $more],
        ]);
    }
}
