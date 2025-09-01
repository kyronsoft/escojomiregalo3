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
            'genero'         => ['nullable', 'string', 'max:10'],   // p. ej. 'M','F','Otro'
            'rango_edad'     => ['nullable', 'string', 'max:15'],   // p. ej. '0-3','4-6'
            'idcampaign'     => ['required', 'integer', 'exists:campaigns,id'],
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
            'genero'         => ['nullable', 'string', 'max:10'],
            'rango_edad'     => ['nullable', 'string', 'max:15'],
            'idcampaign'     => ['required', 'integer', 'exists:campaigns,id'],
        ]);

        $colaborador_hijo->update($data);

        return redirect()
            ->route('colaborador_hijos.index')
            ->with('success', 'Hijo(a) actualizado correctamente.');
    }

    public function destroy(ColaboradorHijo $colaborador_hijo)
    {
        $colaborador_hijo->delete();

        return redirect()
            ->route('colaborador_hijos.index')
            ->with('success', 'Hijo(a) eliminado correctamente.');
    }
}
