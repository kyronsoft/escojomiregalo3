<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::latest('updated_at')->paginate(15);
        return view('campaigns.index', compact('campaigns'));
    }

    // app/Http/Controllers/CampaignController.php

    public function data(Request $request)
    {
        // Sin paginación: devolvemos TODO en un array plano
        $rows = Campaign::orderBy('updated_at', 'desc')
            ->get([
                'id',
                'nit',
                'nombre',
                'idtipo',
                'fechaini',
                'fechafin',
                'banner',
                'demo',
                'doc_yeminus',
                'subject',
                'dashboard',
                'updated_at',
            ]);

        return response()->json($rows, 200, ['Content-Type' => 'application/json; charset=UTF-8'], JSON_UNESCAPED_UNICODE);
    }

    public function create()
    {
        return view('campaigns.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'mailtext'    => ['required', 'string', function ($attr, $value, $fail) {
                $required = ['[COLABORADOR]', '[EMPRESA]', '[NOMBRE CAMPAÑA]', '[LINK]', '[LINKHTML]', '[FECHAFIN]'];
                foreach ($required as $tk) {
                    if (stripos($value, $tk) === false) {
                        return $fail("El texto debe contener el marcador {$tk}.");
                    }
                }
            }],
            'nit'         => ['required', 'string', 'max:20'],
            'nombre'      => ['required', 'string', 'max:100'],
            'idtipo'      => ['required', 'in:1,2'],
            'fechaini'    => ['required', 'date_format:Y-m-d'],
            'fechafin'    => ['required', 'date_format:Y-m-d', 'after_or_equal:fechaini'],
            'banner'      => ['nullable', 'image', 'mimes:jpg,jpeg,png,bmp', 'max:4096'],
            'demo'        => ['nullable', 'in:on,off'],
            'doc_yeminus' => ['nullable', 'integer', 'min:0'],
            'customlogin' => ['nullable', 'string'],
            'subject'     => ['required', 'string', 'max:150'],
            'dashboard'   => ['required', 'boolean'], // 👈 ahora requerido porque siempre llega (0/1)
        ]);

        // Normalizaciones
        $data['demo']        = $request->input('demo', 'off');   // 'on' | 'off'
        $data['dashboard']   = $request->boolean('dashboard');   // true/false a partir de '0'/'1'
        $data['doc_yeminus'] = (int) $request->input('doc_yeminus', 0);

        // Fechas
        $ini = Carbon::createFromFormat('Y-m-d', $data['fechaini'])->startOfDay();
        $fin = Carbon::createFromFormat('Y-m-d', $data['fechafin'])->endOfDay();
        $data['fechaini'] = $ini;
        $data['fechafin'] = $fin;

        // Banner
        if ($request->hasFile('banner')) {
            $data['banner'] = $request->file('banner')->store('campaigns', 'public');
        } else {
            $data['banner'] = 'ND';
        }

        Campaign::create($data);

        return redirect()
            ->route('campaigns.index')
            ->with('success', 'Campaña creada correctamente');
    }

    public function show(Campaign $campaign)
    {
        return view('campaigns.show', compact('campaign'));
    }

    public function edit(Campaign $campaign)
    {
        return view('campaigns.edit', compact('campaign'));
    }

    public function update(Request $request, Campaign $campaign)
    {
        $data = $request->validate([
            'mailtext'    => ['required', 'string', function ($attr, $value, $fail) {
                $required = ['[COLABORADOR]', '[EMPRESA]', '[NOMBRE CAMPAÑA]', '[LINK]', '[LINKHTML]', '[FECHAFIN]'];
                foreach ($required as $tk) {
                    if (stripos($value, $tk) === false) {
                        return $fail("El texto debe contener el marcador {$tk}.");
                    }
                }
            }],
            'nit'           => ['required', 'string', 'max:20'],
            'nombre'        => ['required', 'string', 'max:100'],
            'idtipo'        => ['required', 'in:1,2'],
            'fechaini'      => ['required', 'date_format:Y-m-d'],
            'fechafin'      => ['required', 'date_format:Y-m-d', 'after_or_equal:fechaini'],
            'banner'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,bmp', 'max:4096'],
            'demo'          => ['nullable', 'in:on,off'],
            'doc_yeminus'   => ['nullable', 'integer', 'min:0'],
            'customlogin'   => ['nullable', 'string'],
            'subject'       => ['required', 'string', 'max:150'],
            'dashboard'     => ['required', 'boolean'],
            'mostrargenero' => ['required', 'boolean'], // 👈 nuevo campo
        ]);

        // Normalizaciones
        $data['demo']          = $request->input('demo', 'off');
        $data['dashboard']     = $request->boolean('dashboard');
        $data['mostrargenero'] = $request->boolean('mostrargenero'); // 👈 guardar flag
        $data['doc_yeminus']   = (int) $request->input('doc_yeminus', 0);

        // Fechas
        $ini = Carbon::createFromFormat('Y-m-d', $data['fechaini'])->startOfDay();
        $fin = Carbon::createFromFormat('Y-m-d', $data['fechafin'])->endOfDay();
        $data['fechaini'] = $ini;
        $data['fechafin'] = $fin;

        // Banner (reemplazo)
        if ($request->hasFile('banner')) {
            if ($campaign->banner && $campaign->banner !== 'ND' && Storage::disk('public')->exists($campaign->banner)) {
                Storage::disk('public')->delete($campaign->banner);
            }
            $data['banner'] = $request->file('banner')->store('campaigns', 'public');
        }

        $campaign->update($data);

        return redirect()
            ->route('campaigns.index')
            ->with('success', 'Campaña actualizada correctamente');
    }


    public function destroy(Campaign $campaign)
    {
        if ($campaign->banner && $campaign->banner !== 'ND' && Storage::disk('public')->exists($campaign->banner)) {
            Storage::disk('public')->delete($campaign->banner);
        }

        $campaign->delete();

        return redirect()
            ->route('campaigns.index')
            ->with('success', 'Campaña eliminada correctamente');
    }
}
