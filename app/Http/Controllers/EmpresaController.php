<?php

namespace App\Http\Controllers;

use App\Models\Ciudad;
use App\Models\Empresa;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EmpresaController extends Controller
{
    public function index()
    {
        $raw = \App\Models\Empresa::query()
            ->select([
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
                'username',
                'codigoVendedor',
                'updated_at'
            ])
            ->orderByDesc('updated_at')
            ->get();

        $empresas = $raw->map(function ($e) {
            return [
                'nit'              => $e->nit,
                'nombre'           => $e->nombre,
                'ciudad'           => $e->ciudad,
                'direccion'        => $e->direccion,
                'logo'             => $this->publicUrlOrPlaceholder($e->logo),
                'banner'           => $this->publicUrlOrPlaceholder($e->banner),
                'imagen_login'     => $this->publicUrlOrPlaceholder($e->imagen_login),
                'color_primario'   => $e->color_primario,
                'color_secundario' => $e->color_secundario,
                'color_terciario'  => $e->color_terciario,
                'username'         => $e->username,
                'codigoVendedor'   => $e->codigoVendedor,
                'updated_at'       => $e->updated_at,
            ];
        })->values();

        return view('empresas.index', compact('empresas'));
    }

    private function publicUrlOrPlaceholder(?string $path): string
    {
        if (!$path) {
            return asset('assets/images/placeholder.png');
        }

        // Si ya es URL absoluta, déjala igual
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        // Normaliza separadores y remueve prefijos comunes
        $p = str_replace('\\', '/', $path);
        $p = preg_replace('#^/?storage/app/public/#', '', $p);
        $p = preg_replace('#^/?app/public/#', '', $p);
        $p = preg_replace('#^/?public/#', '', $p);
        $p = ltrim($p, '/');

        // Si ya viene /storage/... tal cual, respétalo
        if (preg_match('#^/?storage/#', $path)) {
            return '/' . ltrim($path, '/');
        }

        // Genera URL pública /storage/... desde el disco 'public'
        return Storage::disk('public')->url($p);
    }

    public function create()
    {
        return view('empresas.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nit'               => ['required', 'string', 'max:10', 'unique:empresas,nit'],
            'nombre'            => ['nullable', 'string', 'max:50'],
            'ciudad'            => ['nullable', 'string', 'max:5'],
            'direccion'         => ['nullable', 'string', 'max:100'],

            'logo'              => ['nullable', 'image', 'mimes:bmp,jpg,jpeg,png', 'max:2048'],
            'banner'            => ['nullable', 'image', 'mimes:bmp,jpg,jpeg,png', 'max:4096'],
            'imagen_login'      => ['nullable', 'image', 'mimes:bmp,jpg,jpeg,png', 'max:2048'],

            'color_primario'    => ['nullable', 'string', 'size:7'],
            'color_secundario'  => ['nullable', 'string', 'size:7'],
            'color_terciario'   => ['nullable', 'string', 'size:7'],
            'welcome_msg'       => ['nullable', 'string'],
        ]);

        $empresa = new Empresa([
            'nit'               => $data['nit'],
            'nombre'            => $data['nombre']           ?? null,
            'ciudad'            => $data['ciudad']           ?? null,
            'direccion'         => $data['direccion']        ?? null,
            'color_primario'    => $data['color_primario']   ?? null,
            'color_secundario'  => $data['color_secundario'] ?? null,
            'color_terciario'   => $data['color_terciario']  ?? null,
            'welcome_msg'       => $data['welcome_msg']      ?? null,
            'username'          => Auth::user()->documento,
        ]);

        // Carpeta por empresa en el disco public (campaigns/{nit})
        $folder = 'empresas/' . $empresa->nit;

        // Subidas: guarda y asigna ruta en el modelo (logo.jpg, banner.jpg, imagen_login.jpg)
        foreach (['logo', 'banner', 'imagen_login'] as $field) {
            if ($request->hasFile($field)) {
                $file     = $request->file($field);
                $ext      = Str::lower($file->getClientOriginalExtension() ?: 'jpg');
                $filename = "{$field}.{$ext}";
                $path     = $file->storeAs($folder, $filename, 'public'); // p.ej. public/campaigns/123/logo.jpg
                $empresa->{$field} = $path; // ruta relativa en el disco 'public'
            }
        }
        sleep(1);
        $empresa->save();

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
        // Texto de la ciudad para precargar Select2
        $ciudadTexto = null;
        if (!empty($empresa->ciudad)) {
            $ciudadTexto = Ciudad::where('codigo', $empresa->ciudad)->value('nombre');
        }

        // Normaliza a URL absoluta solo si vienen rutas relativas
        $empresa->logo         = $this->normalizeStorageUrl($empresa->logo, $empresa->nit);
        $empresa->banner       = $this->normalizeStorageUrl($empresa->banner, $empresa->nit);
        $empresa->imagen_login = $this->normalizeStorageUrl($empresa->imagen_login, $empresa->nit);

        return view('empresas.edit', compact('empresa', 'ciudadTexto'));
    }

    /**
     * Si $value ya es absoluta (http/https), se respeta.
     * Si es relativa, arma /storage/campaigns/{nit}/{archivo} o /storage/{value} si ya trae 'campaigns/...'
     */
    private function normalizeStorageUrl(?string $value, string $nit): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Si ya es URL absoluta, no tocar
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        // Quitar slashes iniciales
        $val = ltrim($value, '/');

        // Si ya viene como "campaigns/{nit}/archivo.ext", usarla tal cual bajo /storage
        if (str_starts_with($val, 'empresas/')) {
            return asset('storage/' . $val);
        }

        // Si solo viene el nombre del archivo, completar con el NIT
        return asset('storage/empresas/' . $nit . '/' . $val);
    }

    public function update(Request $request, Empresa $empresa)
    {
        $data = $request->validate([
            'nombre'           => ['nullable', 'string', 'max:50'],
            'ciudad'           => ['nullable', 'string', 'max:5'],
            'direccion'        => ['nullable', 'string', 'max:100'],
            'logo'             => ['nullable', 'image', 'mimes:bmp,jpg,jpeg,png', 'max:2048'],
            'banner'           => ['nullable', 'image', 'mimes:bmp,jpg,jpeg,png', 'max:4096'],
            'imagen_login'     => ['nullable', 'image', 'mimes:bmp,jpg,jpeg,png', 'max:2048'],
            'color_primario'   => ['nullable', 'string', 'size:7'],
            'color_secundario' => ['nullable', 'string', 'size:7'],
            'color_terciario'  => ['nullable', 'string', 'size:7'],
            'welcome_msg'      => ['nullable', 'string'],
            'codigoVendedor'   => ['nullable', 'string', 'max:10'],
        ]);

        // Carpeta por empresa en el disco public (campaigns/{nit})
        $folder = 'campaigns/' . $empresa->nit;

        // Si viene nueva imagen, borra la anterior y guarda la nueva con nombre fijo
        foreach (['logo', 'banner', 'imagen_login'] as $field) {
            if ($request->hasFile($field)) {
                // Elimina archivo previo si existe
                if ($empresa->$field && Storage::disk('public')->exists($empresa->$field)) {
                    Storage::disk('public')->delete($empresa->$field);
                }
                $file     = $request->file($field);
                $ext      = Str::lower($file->getClientOriginalExtension() ?: 'jpg');
                $filename = "{$field}.{$ext}";
                $path     = $file->storeAs($folder, $filename, 'public'); // p.ej. public/campaigns/{nit}/logo.jpg
                $data[$field] = $path; // guarda ruta relativa
            }
        }

        $empresa->update($data);

        // pequeña pausa para evitar race conditions con el filesystem en algunos entornos
        usleep(250000);

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
                'id'     => (string)$e->nit,
                'text'   => (string)$e->nit . ' - ' . $e->nombre,
                'nombre' => (string)$e->nombre,
            ])->toArray();

        return response()->json([
            'items' => $results,
            'more'  => ($page * $perPage) < $total,
        ]);
    }
}
