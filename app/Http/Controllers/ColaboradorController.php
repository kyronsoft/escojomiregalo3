<?php

namespace App\Http\Controllers;

use App\Models\Colaborador;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ColaboradorController extends Controller
{
    public function index()
    {
        $colaboradores = Colaborador::latest('updated_at')->paginate(15);
        return view('colaboradores.index', compact('colaboradores'));
    }

    // app/Http/Controllers/ColaboradorController.php

    public function data()
    {
        // Devuelve TODO (array plano) para Tabulator sin paginación
        $rows = \App\Models\Colaborador::orderBy('updated_at', 'desc')
            ->get([
                'documento',
                'nombre',
                'email',
                'direccion',
                'telefono',
                'ciudad',
                'observaciones',
                'barrio',
                'nit',
                'enviado',
                'politicadatos',
                'updatedatos',
                'sucursal',
                'welcome',
                'updated_at',
            ]);

        return response()->json($rows, 200, ['Content-Type' => 'application/json; charset=UTF-8'], JSON_UNESCAPED_UNICODE);
    }


    public function create()
    {
        return view('colaboradores.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'documento'     => ['required', 'string', 'max:15', 'unique:colaboradores,documento'],
            'nombre'        => ['required', 'string', 'max:100'],
            'email'         => ['nullable', 'string', 'email', 'max:75'],
            'direccion'     => ['nullable', 'string', 'max:255'],
            'telefono'      => ['nullable', 'string', 'max:10'], // ajusta si requieres regex
            'ciudad'        => ['nullable', 'string', 'max:5'],  // |exists:ciudades,codigo
            'observaciones' => ['nullable', 'string', 'max:255'],
            'barrio'        => ['nullable', 'string', 'max:100'],
            'nit'           => ['required', 'string', 'max:10'], // |exists:empresas,nit
            'enviado'       => ['nullable', 'boolean'],
            'politicadatos' => ['nullable', 'in:S,N,Y'], // según tu flujo ('S'/'N'/'Y'/'N')
            'updatedatos'   => ['nullable', 'in:S,N,Y'],
            'sucursal'      => ['nullable', 'string', 'max:100'],
            'welcome'       => ['nullable', 'in:S,N,Y'],
        ]);

        // Normalizaciones
        $data['enviado']       = (bool)($request->input('enviado', 0));
        $data['politicadatos'] = $request->input('politicadatos', 'N');
        $data['updatedatos']   = $request->input('updatedatos', 'N');
        $data['welcome']       = $request->input('welcome', 'N');

        Colaborador::create($data);

        return redirect()->route('colaboradores.index')->with('success', 'Colaborador creado correctamente.');
    }

    public function show(Colaborador $colaborador)
    {
        return view('colaboradores.show', compact('colaborador'));
    }

    public function edit(Colaborador $colaborador)
    {
        return view('colaboradores.edit', compact('colaborador'));
    }

    public function update(Request $request, Colaborador $colaborador)
    {
        $data = $request->validate([
            // Si NO quieres permitir cambiar el documento:
            'documento'     => ['required', 'string', 'max:15', Rule::in([$colaborador->documento])],
            // Si SÍ quieres permitir cambiarlo, usa:
            // 'documento'  => ['required','string','max:15', Rule::unique('colaboradores','documento')->ignore($colaborador->getKey(),'documento')],
            'nombre'        => ['required', 'string', 'max:100'],
            'email'         => ['nullable', 'string', 'email', 'max:75'],
            'direccion'     => ['nullable', 'string', 'max:255'],
            'telefono'      => ['nullable', 'string', 'max:10'],
            'ciudad'        => ['nullable', 'string', 'max:5'],  // |exists:ciudades,codigo
            'observaciones' => ['nullable', 'string', 'max:255'],
            'barrio'        => ['nullable', 'string', 'max:100'],
            'nit'           => ['required', 'string', 'max:10'], // |exists:empresas,nit
            'enviado'       => ['nullable', 'boolean'],
            'politicadatos' => ['nullable', 'in:S,N,Y'],
            'updatedatos'   => ['nullable', 'in:S,N,Y'],
            'sucursal'      => ['nullable', 'string', 'max:100'],
            'welcome'       => ['nullable', 'in:S,N,Y'],
        ]);

        $data['enviado']       = (bool)($request->input('enviado', 0));
        $data['politicadatos'] = $request->input('politicadatos', 'N');
        $data['updatedatos']   = $request->input('updatedatos', 'N');
        $data['welcome']       = $request->input('welcome', 'N');

        // Si bloqueaste cambio de documento con Rule::in, no hace falta unset.
        // Si permites cambio y usas unique->ignore, puedes actualizarlo.
        $colaborador->update($data);

        return redirect()->route('colaboradores.index')->with('success', 'Colaborador actualizado correctamente.');
    }

    public function destroy(Colaborador $colaborador)
    {
        $colaborador->delete();
        return redirect()->route('colaboradores.index')->with('success', 'Colaborador eliminado correctamente.');
    }
}
