<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Campaign;
use App\Models\Parametro;
use App\Models\Colaborador;
use Illuminate\Http\Request;
use App\Models\ColaboradorHijo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index()
    {
        $documento = Auth::user()->documento;
        $nitSesion = session('empresa_nit');

        $campaignId = session('campaign_id') ??
            DB::table('campaing_colaboradores')
            ->where('documento', $documento)
            ->when($nitSesion, fn($q) => $q->where('nit', $nitSesion))
            ->orderByDesc('created_at')
            ->value('idcampaign');

        if (!$campaignId) abort(403, 'No tienes campañas asignadas para esta empresa.');

        $pertenece = DB::table('campaing_colaboradores')
            ->where('documento', $documento)
            ->where('idcampaign', $campaignId)
            ->when($nitSesion, fn($q) => $q->where('nit', $nitSesion))
            ->exists();

        if (!$pertenece) abort(403, 'No estás asignado a esta campaña.');

        // Campaña
        $campaign = Campaign::with('empresa')->find($campaignId);

        // Banner
        $campaignBannerUrl = null;
        if ($campaign && !empty($campaign->banner) && $campaign->banner !== 'ND') {
            $campaignBannerUrl = url(Storage::disk('public')->url($campaign->banner));
        }

        // Empresa (por relación o por NIT defensivo)
        $empresa = $campaign?->empresa;
        if (!$empresa && $campaign?->nit) {
            $empresa = Empresa::whereRaw('TRIM(nit) = ?', [trim((string)$campaign->nit)])->first();
        }

        // Colores (fallbacks seguros)
        $primaryColor   = $empresa?->color_primario   ?: '#ffffff';
        $secondaryColor = $empresa?->color_secundario ?: '#f2f2f2';
        $welcomeMsg  = (string) ($empresa->welcome_msg ?? '');

        // Lógica existente
        $colaborador  = Colaborador::where('documento', $documento)->first();
        $showPolitica = is_null(optional($colaborador)->politicadatos);
        $politicaHtml = \App\Models\Parametro::where('nombre', 'POLITICA DATOS')->value('valor') ?? '...';
        $resultado    = $this->juguetesPorColaboradorJoin($documento, $campaignId);

        return view('ecommerce.product', [
            'resultado'         => $resultado,
            'showPolitica'      => $showPolitica,
            'politicaHtml'      => $politicaHtml,
            'campaignBannerUrl' => $campaignBannerUrl,
            'campaign'          => $campaign,
            'primaryColor'      => $primaryColor,
            'secondaryColor'    => $secondaryColor,
            'welcomeMsg'        => $welcomeMsg,
        ]);
    }

    private function juguetesPorColaboradorJoin(string $documento, ?int $campaignId = null)
    {
        $toysTable = 'campaign_toys'; // cambia si tu tabla tiene otro nombre

        // Hijos normalizados (género vacío/NULL => 'U')
        $childrenSub = \App\Models\ColaboradorHijo::query()
            ->select([
                'colaborador_hijos.id         as hijo_id',
                'colaborador_hijos.identificacion',
                'colaborador_hijos.nombre_hijo',
                'colaborador_hijos.genero     as genero_hijo',
                DB::raw('CAST(colaborador_hijos.rango_edad AS UNSIGNED) as edad_int'),
                'colaborador_hijos.idcampaign',
                DB::raw("
                CASE
                  WHEN UPPER(TRIM(colaborador_hijos.genero)) IN ('F','FEM','FEMENINO','NIÑA') THEN 'F'
                  WHEN UPPER(TRIM(colaborador_hijos.genero)) IN ('M','MAS','MASCULINO','NIÑO') THEN 'M'
                  WHEN colaborador_hijos.genero IS NULL OR TRIM(colaborador_hijos.genero) = '' THEN 'U'
                  WHEN UPPER(TRIM(colaborador_hijos.genero)) IN ('U','UNISEX','UNI','TODOS','ANY') THEN 'U'
                  ELSE 'U' -- fallback inclusivo
                END as genero_norm_hijo
            "),
            ])
            ->where('colaborador_hijos.identificacion', $documento)
            ->when($campaignId, fn($q) => $q->where('colaborador_hijos.idcampaign', $campaignId));

        // Juguetes normalizados (género vacío/NULL => 'U')
        $toysSub = DB::table($toysTable)
            ->select([
                "$toysTable.id            as toy_id",
                "$toysTable.idcampaign    as idcampaign",
                "$toysTable.referencia    as referencia",
                "$toysTable.nombre        as toy_nombre",
                "$toysTable.imagenppal    as imagenppal",
                "$toysTable.descripcion   as descripcion",
                DB::raw("CAST($toysTable.desde AS UNSIGNED) as desde_int"),
                DB::raw("CAST($toysTable.hasta AS UNSIGNED) as hasta_int"),
                "$toysTable.genero        as genero_toy",
                DB::raw("
                CASE
                  WHEN $toysTable.genero IS NULL OR TRIM($toysTable.genero) = '' THEN 'U'
                  WHEN UPPER(TRIM($toysTable.genero)) IN ('U','UNISEX','UNI','TODOS','ANY') THEN 'U'
                  WHEN UPPER(TRIM($toysTable.genero)) IN ('F','FEM','FEMENINO','NIÑA') THEN 'F'
                  WHEN UPPER(TRIM($toysTable.genero)) IN ('M','MAS','MASCULINO','NIÑO') THEN 'M'
                  ELSE 'U' -- fallback inclusivo
                END as genero_norm_toy
            "),
            ]);

        $rows = DB::query()
            ->fromSub($childrenSub, 'ch')
            ->joinSub($toysSub, 'ct', function ($join) {
                $join->on('ct.idcampaign', '=', 'ch.idcampaign')
                    ->whereRaw('ch.edad_int BETWEEN ct.desde_int AND ct.hasta_int')
                    ->where(function ($w) {
                        // Regla de género:
                        // - Juguete 'U' → válido para todos
                        // - Hijo 'U' → acepta cualquier juguete
                        // - Si no, igualdad F/M
                        $w->whereRaw("ct.genero_norm_toy = 'U'")
                            ->orWhereRaw("ch.genero_norm_hijo = 'U'")
                            ->orWhereRaw('ct.genero_norm_toy = ch.genero_norm_hijo');
                    });
            })
            ->orderBy('ch.nombre_hijo')
            ->orderBy('ct.desde_int')
            ->get([
                'ch.hijo_id',
                'ch.identificacion',
                'ch.nombre_hijo',
                'ch.genero_hijo',
                'ch.edad_int      as rango_edad',
                'ch.idcampaign',
                'ct.toy_id        as toy_id',
                'ct.referencia',
                'ct.toy_nombre    as toy_nombre',
                'ct.imagenppal',
                'ct.genero_norm_toy as genero_toy', // ya normalizado
                'ct.desde_int     as desde',
                'ct.hasta_int     as hasta',
                'ct.descripcion',
            ]);

        // Estructura: hijos -> juguetes
        return $rows->groupBy('hijo_id')->map(function ($items) {
            $f = $items->first();
            return [
                'hijo' => [
                    'id'             => $f->hijo_id,
                    'identificacion' => $f->identificacion,
                    'nombre'         => $f->nombre_hijo,
                    'genero'         => $f->genero_hijo,
                    'rango_edad'     => $f->rango_edad,
                    'idcampaign'     => $f->idcampaign,
                ],
                'juguetes' => $items->map(fn($r) => [
                    'id'          => $r->toy_id,
                    'referencia'  => $r->referencia,
                    'nombre'      => $r->toy_nombre,
                    'imagenppal'  => $r->imagenppal,
                    'genero'      => $r->genero_toy,   // 'F' | 'M' | 'U'
                    'desde'       => $r->desde,
                    'hasta'       => $r->hasta,
                    'descripcion' => $r->descripcion,
                ])->values(),
            ];
        })->values();
    }


    public function aceptarPolitica(Request $request)
    {
        $user = $request->user();

        $colaborador = \App\Models\Colaborador::where('email', $user->email)->firstOrFail();

        // Guardar aceptación como 'Y'
        $colaborador->forceFill(['politicadatos' => 'Y'])->save();

        return back()->with('swal', [
            'icon'  => 'success',
            'title' => 'Gracias',
            'text'  => 'Has aceptado la política de tratamiento de datos.',
        ]);
    }
}
