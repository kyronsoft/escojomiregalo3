<?php

namespace App\Http\Controllers;

use App\Models\ColaboradorHijo;
use Illuminate\Http\Request;

class ColaboradorHijoController extends Controller
{
    public function index(Request $request)
    {
        $identificacion = $request->query('identificacion'); // viene del botón "Hijos"
        return view('colaborador_hijos.index', compact('identificacion'));
    }

    public function data(Request $request)
    {
        $q = \App\Models\ColaboradorHijo::query()->orderBy('updated_at', 'desc');

        if ($request->filled('identificacion')) {
            $q->where('identificacion', $request->string('identificacion'));
        }

        return response()->json($q->get(), 200, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public function create()
    {
        // Si quieres precargar selects, pásalos aquí (opcional)
        return view('colaborador_hijos.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'identificacion' => ['required', 'string', 'max:15', 'exists:colaboradores,documento'],
            'nombre_hijo'    => ['nullable', 'string', 'max:100'],
            'genero'      => ['required', 'in:NIÑO,NIÑA,UNISEX'],
            'rango_edad'  => ['required', 'integer', 'min:0', 'max:14'],
            'idcampaing'     => ['required', 'integer', 'exists:campaigns,id'],
        ]);

        ColaboradorHijo::create($data);

        return redirect()
            ->route('colaborador_hijos.index')
            ->with('success', 'Hijo(a) registrado correctamente.');
    }

    public function show(ColaboradorHijo $colaborador_hijo)
    {
        $colaborador_hijo->load(['colaborador:documento,nombre', 'campaign:id,nombre']);
        return view('colaborador_hijos.show', compact('colaborador_hijo'));
    }

    public function edit(ColaboradorHijo $colaborador_hijo)
    {
        $colaborador_hijo->load(['colaborador:documento,nombre', 'campaign:id,nombre']);
        return view('colaborador_hijos.edit', compact('colaborador_hijo'));
    }

    public function update(Request $request, ColaboradorHijo $colaborador_hijo)
    {
        $data = $request->validate([
            'identificacion' => ['required', 'string', 'max:15', 'exists:colaboradores,documento'],
            'nombre_hijo'    => ['nullable', 'string', 'max:100'],
            'genero'         => ['nullable', 'string', 'in:NIÑO,NIÑA,UNISEX'],
            'rango_edad'     => ['nullable', 'integer', 'min:0', 'max:14'],
            'idcampaign'     => ['required', 'integer', 'exists:campaigns,id'],
        ]);

        // Género por defecto: UNISEX (si viene vacío o valor no permitido)
        $genero = strtoupper($data['genero'] ?? 'UNISEX');
        if (!in_array($genero, ['NIÑO', 'NIÑA', 'UNISEX'], true)) {
            $genero = 'UNISEX';
        }
        $data['genero'] = $genero;

        // Mapear al nombre real de la columna en BD
        $data['idcampaing'] = $data['idcampaign'];
        unset($data['idcampaign']);

        // Normalizar rango_edad: entero o null
        $data['rango_edad'] = array_key_exists('rango_edad', $data) && $data['rango_edad'] !== null && $data['rango_edad'] !== ''
            ? (int) $data['rango_edad']
            : null;
        sleep(1);
        $colaborador_hijo->update($data);

        return redirect()
            ->route('colaborador_hijos.index', ['identificacion' => $data['identificacion'] ?? null])
            ->with('success', 'Hijo(a) actualizado correctamente.');
    }


    public function destroy(\App\Models\ColaboradorHijo $colaborador_hijo, \Illuminate\Http\Request $request)
    {
        $colaborador_hijo->delete();

        // Si el cliente espera JSON (fetch/AJAX), no redirigir
        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            // Puedes devolver 204 sin cuerpo…
            // return response()->noContent();

            // …o 200 con un JSON sencillo:
            return response()->json([
                'ok'      => true,
                'message' => 'Hijo(a) eliminado correctamente.',
            ], 200);
        }

        // Flujo normal de navegador (full page)
        return redirect()
            ->route('colaborador_hijos.index')
            ->with('success', 'Hijo(a) eliminado correctamente.');
    }
}
