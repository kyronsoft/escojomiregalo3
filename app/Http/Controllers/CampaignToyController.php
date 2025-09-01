<?php

namespace App\Http\Controllers;

use App\Models\CampaignToy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CampaignToyController extends Controller
{
    public function index(Request $request)
    {
        // Filtros opcionales
        $q = CampaignToy::query()->with('campaign:id,nombre');

        if ($request->filled('idcampaign')) {
            $q->where('idcampaign', (int) $request->input('idcampaign'));
        }
        if ($request->filled('referencia')) {
            $q->where('referencia', 'like', '%' . $request->input('referencia') . '%');
        }
        if ($request->filled('nombre')) {
            $q->where('nombre', 'like', '%' . $request->input('nombre') . '%');
        }

        $toys = $q->latest('updated_at')->paginate(15)->withQueryString();

        return view('campaign_toys.index', compact('toys'));
    }

    public function data(Request $request)
    {
        $q = CampaignToy::query()->with('campaign:id,nombre');

        if ($request->filled('idcampaign')) {
            $q->where('idcampaign', (int) $request->input('idcampaign'));
        }

        return response()->json(
            $q->latest('updated_at')->get([
                'id',
                'idcampaign',
                'referencia',
                'nombre',
                'imagenppal',
                'genero',
                'desde',
                'hasta',
                'unidades',
                'precio_unitario',
                'porcentaje',
                'seleccionadas',
                'imgexists',
                'escogidos',
                'idoriginal',
                'updated_at'
            ])
        );
    }


    public function create()
    {
        return view('campaign_toys.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        // Manejo de imagen
        if ($request->hasFile('imagenppal')) {
            $data['imagenppal'] = $request->file('imagenppal')->store('campaign_toys', 'public');
            $data['imgexists']  = 'S';
        }

        $toy = CampaignToy::create($data);

        return redirect()
            ->route('campaign_toys.show', $toy->id)
            ->with('success', 'Juguete/Combo creado correctamente.');
    }

    public function show(CampaignToy $campaign_toy)
    {
        return view('campaign_toys.show', ['toy' => $campaign_toy->load('campaign:id,nombre')]);
    }

    public function edit(CampaignToy $campaign_toy)
    {
        return view('campaign_toys.edit', ['toy' => $campaign_toy->load('campaign:id,nombre')]);
    }

    public function update(Request $request, CampaignToy $campaign_toy)
    {
        $data = $this->validateData($request, $campaign_toy->id);

        // Si se sube nueva imagen, borrar la anterior
        if ($request->hasFile('imagenppal')) {
            if ($campaign_toy->imagenppal && Storage::disk('public')->exists($campaign_toy->imagenppal)) {
                Storage::disk('public')->delete($campaign_toy->imagenppal);
            }
            $data['imagenppal'] = $request->file('imagenppal')->store('campaign_toys', 'public');
            $data['imgexists']  = 'S';
        }

        $campaign_toy->update($data);

        return redirect()
            ->route('campaign_toys.show', $campaign_toy->id)
            ->with('success', 'Juguete/Combo actualizado correctamente.');
    }

    public function destroy(CampaignToy $campaign_toy)
    {
        // Borrar imagen si existe
        if ($campaign_toy->imagenppal && Storage::disk('public')->exists($campaign_toy->imagenppal)) {
            Storage::disk('public')->delete($campaign_toy->imagenppal);
        }

        $campaign_toy->delete();

        return redirect()
            ->route('campaign_toys.index')
            ->with('success', 'Juguete/Combo eliminado correctamente.');
    }

    /**
     * Reglas de validación (crear/editar)
     */
    protected function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'combo'            => ['nullable', 'string', 'max:3'],
            'idcampaign'       => ['required', 'integer', 'exists:campaigns,id'],
            'referencia'       => [
                'required',
                'string',
                'max:100',
                // Si quieres evitar duplicados por campaña:
                // Rule::unique('campaign_toys', 'referencia')->where(fn($q)=>$q->where('idcampaign', $request->input('idcampaign')))->ignore($id),
            ],
            'nombre'           => ['required', 'string'],
            'imagenppal'       => ['nullable', 'file', 'mimes:bmp,jpg,jpeg,png,webp', 'max:5120'], // 5MB
            'genero'           => ['nullable', 'string', 'max:10'], // p.ej. UNISEX, M, F
            'desde'            => ['nullable', 'string', 'max:3'],
            'hasta'            => ['nullable', 'string', 'max:10'],
            'unidades'         => ['nullable', 'integer', 'min:0'],
            'precio_unitario'  => ['required', 'integer', 'min:0'],
            'porcentaje'       => ['nullable', 'string', 'max:100'],
            'seleccionadas'    => ['nullable', 'integer', 'min:0'],
            'imgexists'        => ['nullable', 'string', 'in:S,N', 'max:1'],
            'descripcion'      => ['nullable', 'string'],
            'escogidos'        => ['nullable', 'integer', 'min:0'],
            'idoriginal'       => ['required', 'string', 'max:15'],
        ], [
            'idcampaign.required' => 'Debes seleccionar la campaña.',
            'referencia.required' => 'La referencia es obligatoria.',
            'nombre.required'     => 'El nombre es obligatorio.',
            'precio_unitario.required' => 'El precio unitario es obligatorio.',
            'imagenppal.mimes'    => 'La imagen debe ser bmp, jpg, jpeg, png o webp.',
        ]);
    }
}
