<?php

namespace App\Http\Controllers;

use App\Models\Ciudad;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EmpresaController extends Controller
{
    public function index()
    {
        $empresas = Empresa::select([
            'nit',
            'nombre',
            'ciudad',
            'direccion',
            'logo',
            'banner',
            'imagen_login',
            'color_primario',
            'color_secundario',
            'color_terciario',
            'welcome_msg',
            'username',
            'codigoVendedor',
            'updated_at'
        ])->get();
        return view('empresas.index', compact('empresas'));
    }

    public function create()
    {
        return view('empresas.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nit'             => ['required', 'string', 'max:10', 'unique:empresas,nit'],
            'nombre'          => ['nullable', 'string', 'max:50'],
            'ciudad'          => ['nullable', 'string', 'max:5'],
            'direccion'       => ['nullable', 'string', 'max:100'],
            'logo'            => ['nullable', 'image', 'mimes:bmp,jpg,jpeg,png', 'max:2048'],
            'banner'          => ['nullable', 'image', 'mimes:bmp,jpg,jpeg,png', 'max:4096'],
            'imagen_login'    => ['nullable', 'image', 'mimes:bmp,jpg,jpeg,png', 'max:2048'],
            'color_primario'  => ['nullable', 'string', 'size:7'],
            'color_secundario' => ['nullable', 'string', 'size:7'],
            'color_terciario' => ['nullable', 'string', 'size:7'],
            'welcome_msg'     => ['nullable', 'string'],
        ]);

        // Subidas (guardamos la ruta tipo "images/xxxx.jpg")
        foreach (['logo', 'banner', 'imagen_login'] as $field) {
            if ($request->hasFile($field)) {
                $data[$field] = $request->file($field)->store('images', 'public');
            }
        }

        Empresa::create($data);
        sleep(1);
        return redirect()
            ->route('empresas.index')
            ->with('success', 'Empresa creada correctamente');
    }

    public function show(Empresa $empresa)
    {
        return view('empresas.show', compact('empresa'));
    }

    public function edit(Empresa $empresa)
    {
        $ciudadTexto = null;

        if (!empty($empresa->ciudad)) {
            $ciudadTexto = Ciudad::where('codigo', $empresa->ciudad)->value('nombre');
        }

        return view('empresas.edit', compact('empresa', 'ciudadTexto'));
    }

    public function update(Request $request, Empresa $empresa)
    {
        $data = $request->validate([
            'nombre'          => ['nullable', 'string', 'max:50'],
            'ciudad'          => ['nullable', 'string', 'max:5'],
            'direccion'       => ['nullable', 'string', 'max:100'],
            'logo'            => ['nullable', 'image', 'mimes:bmp,jpg,jpeg,png', 'max:2048'],
            'banner'          => ['nullable', 'image', 'mimes:bmp,jpg,jpeg,png', 'max:4096'],
            'imagen_login'    => ['nullable', 'image', 'mimes:bmp,jpg,jpeg,png', 'max:2048'],
            'color_primario'  => ['nullable', 'string', 'size:7'],
            'color_secundario' => ['nullable', 'string', 'size:7'],
            'color_terciario' => ['nullable', 'string', 'size:7'],
            'welcome_msg'     => ['nullable', 'string'],
            'codigoVendedor'  => ['nullable', 'string', 'max:10'],
        ]);

        // Si viene nueva imagen, borra la anterior y guarda la nueva
        foreach (['logo', 'banner', 'imagen_login'] as $field) {
            if ($request->hasFile($field)) {
                if ($empresa->$field && Storage::disk('public')->exists($empresa->$field)) {
                    Storage::disk('public')->delete($empresa->$field);
                }
                $data[$field] = $request->file($field)->store('images', 'public');
            }
        }

        $empresa->update($data);
        sleep(1);
        return redirect()
            ->route('empresas.index')
            ->with('success', 'Empresa actualizada correctamente');
    }

    public function destroy(Empresa $empresa)
    {
        // Elimina archivos asociados
        foreach (['logo', 'banner', 'imagen_login'] as $field) {
            if ($empresa->$field && Storage::disk('public')->exists($empresa->$field)) {
                Storage::disk('public')->delete($empresa->$field);
            }
        }

        $empresa->delete();

        return redirect()
            ->route('empresas.index')
            ->with('success', 'Empresa eliminada correctamente');
    }

    public function select2(Request $request)
    {
        // Lookup puntual por id (para precargar old('nit'))
        if ($request->filled('id')) {
            $e = Empresa::where('nit', $request->id)->first();
            if (!$e) {
                return response()->json(['item' => null]);
            }
            return response()->json([
                'item' => [
                    'id'   => (string)$e->nit,
                    'text' => (string)$e->nit . ' - ' . $e->nombre,
                ],
            ]);
        }

        // Búsqueda con paginación
        $q       = trim((string)$request->get('q', ''));
        $page    = max(1, (int)$request->get('page', 1));
        $perPage = 20;

        $query = Empresa::query();
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('nit', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%");
            });
        }

        $total   = $query->count();
        $results = $query->orderBy('nombre')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn($e) => [
                'id'   => (string)$e->nit,
                'text' => (string)$e->nit . ' - ' . $e->nombre,
                'nombre' => (string)$e->nombre,
            ])->toArray();

        return response()->json([
            'items' => $results,
            'more'  => ($page * $perPage) < $total,
        ]);
    }
}
