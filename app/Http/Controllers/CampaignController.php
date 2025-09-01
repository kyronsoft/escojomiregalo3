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
            'nit'         => ['required', 'string', 'max:20'],     // antes tenías 10; subo a 20 por seguridad
            'nombre'      => ['required', 'string', 'max:100'],    // readonly en la vista pero validamos
            'idtipo'      => ['required', 'in:1,2'],               // ahora es select fijo: 1 o 2

            // ahora vienen como YYYY-MM-DD (sin hora)
            'fechaini'    => ['required', 'date_format:Y-m-d'],
            'fechafin'    => ['required', 'date_format:Y-m-d', 'after_or_equal:fechaini'],

            'banner'      => ['nullable', 'image', 'mimes:jpg,jpeg,png,bmp', 'max:4096'],
            'demo'        => ['nullable', 'in:on,off'],
            'doc_yeminus' => ['nullable', 'integer', 'min:0'],
            'customlogin' => ['nullable', 'string'],
            'subject'     => ['required', 'string', 'max:150'],
            'dashboard'   => ['nullable', 'boolean'],
        ]);

        // Normalizaciones
        $data['demo']        = $request->input('demo', 'off'); // 'on' | 'off'
        $data['dashboard']   = (bool) $request->boolean('dashboard', false);
        $data['doc_yeminus'] = (int) $request->input('doc_yeminus', 0);

        // Convertir fechas: guardamos inicio de día y fin de día (si columnas son DATETIME)
        // Si tus columnas son DATE, igual puedes enviar 'Y-m-d' directamente y omitir estos setStart/EndOfDay.
        $ini = Carbon::createFromFormat('Y-m-d', $data['fechaini'])->startOfDay();
        $fin = Carbon::createFromFormat('Y-m-d', $data['fechafin'])->endOfDay();
        $data['fechaini'] = $ini;
        $data['fechafin'] = $fin;

        // Banner (guarda en disco 'public')
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
            'nit'         => ['required', 'string', 'max:20'],   // antes 10; ampliar si tu NIT puede ser mayor
            'nombre'      => ['required', 'string', 'max:100'],  // readonly en la vista pero se valida igual
            'idtipo'      => ['required', 'in:1,2'],             // ahora es select fijo 1/2

            // Ahora llegan como YYYY-MM-DD (solo fecha)
            'fechaini'    => ['required', 'date_format:Y-m-d'],
            'fechafin'    => ['required', 'date_format:Y-m-d', 'after_or_equal:fechaini'],

            'banner'      => ['nullable', 'image', 'mimes:jpg,jpeg,png,bmp', 'max:4096'],
            'demo'        => ['nullable', 'in:on,off'],
            'doc_yeminus' => ['nullable', 'integer', 'min:0'],
            'customlogin' => ['nullable', 'string'],
            'subject'     => ['required', 'string', 'max:150'],
            'dashboard'   => ['nullable', 'boolean'],
        ]);

        // Normalizaciones
        $data['demo']        = $request->input('demo', 'off');         // 'on' | 'off'
        $data['dashboard']   = (bool) $request->boolean('dashboard');  // checkbox
        $data['doc_yeminus'] = (int) $request->input('doc_yeminus', 0);

        // Convertir fechas
        // Si columnas son DATETIME/TIMESTAMP:
        $ini = Carbon::createFromFormat('Y-m-d', $data['fechaini'])->startOfDay();
        $fin = Carbon::createFromFormat('Y-m-d', $data['fechafin'])->endOfDay();
        $data['fechaini'] = $ini;
        $data['fechafin'] = $fin;

        // Si en tu BD las columnas son DATE, en lugar de lo anterior podrías hacer:
        // $data['fechaini'] = $data['fechaini']; // 'Y-m-d'
        // $data['fechafin'] = $data['fechafin']; // 'Y-m-d'

        // Banner: reemplazar y borrar el anterior (si no es 'ND')
        if ($request->hasFile('banner')) {
            if ($campaign->banner && $campaign->banner !== 'ND' && Storage::disk('public')->exists($campaign->banner)) {
                Storage::disk('public')->delete($campaign->banner);
            }
            $data['banner'] = $request->file('banner')->store('campaigns', 'public');
        }
        // Si no suben banner, se conserva el existente

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
