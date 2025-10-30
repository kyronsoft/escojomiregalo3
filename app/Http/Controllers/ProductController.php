<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Campaign;
use App\Models\Parametro;
use App\Models\Colaborador;
use Illuminate\Http\Request;
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

        // Campaña y empresa
        $campaign = Campaign::with('empresa')->find($campaignId);

        // Empresa (fallback por NIT si falta relación)
        $empresa = $campaign?->empresa;
        if (!$empresa) {
            $nitEmpresa = $campaign?->nit
                ?? $nitSesion
                ?? DB::table('campaing_colaboradores')
                    ->where('documento', $documento)
                    ->where('idcampaing', $campaignId) // en esta tabla se llama idcampaing
                    ->value('nit');

            if ($nitEmpresa) {
                $empresa = Empresa::whereRaw('TRIM(nit) = ?', [trim((string)$nitEmpresa)])->first();
            }
        }

        // Banner y logo
        $campaignBannerUrl = $this->publicUrlIfExists($campaign?->banner);
        $empresaLogoUrl = $this->publicUrlLoose($empresa?->logo)
            ?? $this->publicUrlLoose($empresa ? "images/{$empresa->nit}/logo.png" : null)
            ?? asset('assets/images/placeholder.png');

        // Colores & mensajes
        $primaryColor   = $empresa?->color_primario   ?: '#ffffff';
        $secondaryColor = $empresa?->color_secundario ?: '#f2f2f2';
        $welcomeMsg     = (string) ($empresa->welcome_msg ?? '');

        // Colaborador (para modales)
        $colaborador = Colaborador::where('documento', $documento)->first();

        // Política de datos
        $pdValue = strtoupper(trim((string) ($colaborador->politicadatos ?? '')));
        $showPolitica = ($pdValue !== 'Y');
        $politicaHtml = Parametro::where('nombre', 'POLITICA DATOS')->value('valor') ?? '...';

        // Bienvenida (solo si hay mensaje y no marcada)
        $welcomeValue = strtoupper(trim((string) ($colaborador->welcome ?? '')));
        $showWelcome = ($welcomeMsg !== '') && ($welcomeValue !== 'Y');

        // Datos juguetes (filtrados por agotamiento de referencia a nivel campaña)
        $resultado = $this->juguetesPorColaboradorJoin($documento, $campaignId);

        return view('ecommerce.product', [
            'resultado'         => $resultado,
            'showPolitica'      => $showPolitica,
            'politicaHtml'      => $politicaHtml,
            'campaignBannerUrl' => $campaignBannerUrl,
            'campaign'          => $campaign,
            'primaryColor'      => $primaryColor,
            'secondaryColor'    => $secondaryColor,
            'welcomeMsg'        => $welcomeMsg,
            'showWelcome'       => $showWelcome,
            'empresaLogoUrl'    => $empresaLogoUrl,
            'colaborador'       => $colaborador,
        ]);
    }

    private function publicUrlLoose(?string $path): ?string
    {
        if (!$path) return null;
        if (preg_match('#^https?://#i', $path)) return $path;

        $p = str_replace('\\', '/', $path);
        $p = ltrim($p, '/');

        $p = preg_replace('#^storage/app/public/#', '', $p);
        $p = preg_replace('#^app/public/#', '', $p);
        $p = preg_replace('#^public/#', '', $p);

        if (preg_match('#^images/(\d+)/(.+)$#', $p, $m)) {
            $p = "campaigns/{$m[1]}/{$m[2]}";
        }

        if (preg_match('#^storage/#i', $p)) return '/' . $p;

        return Storage::disk('public')->url($p);
    }

    private function publicUrlIfExists(?string $path): ?string
    {
        if (!$path) return null;
        if (preg_match('#^https?://#i', $path)) return $path;

        $p = str_replace('\\', '/', $path);
        $p = ltrim($p, '/');
        $p = preg_replace('#^(storage/app/public/|app/public/|public/)#', '', $p);

        if (preg_match('#^storage/#i', $p)) return '/' . ltrim($p, '/');

        return Storage::disk('public')->exists($p) ? Storage::disk('public')->url($p) : null;
    }

    /**
     * Si COUNT(*) en `seleccionados` (por idcampaing + referencia) >= unidades de `campaign_toys`,
     * esa referencia queda EXCLUIDA del dataset final.
     * (Implementado con subconsulta COUNT(*) sin GROUP BY → compatible con ONLY_FULL_GROUP_BY)
     */
private function juguetesPorColaboradorJoin(string $documento, ?int $campaignId = null)
{
    // Hijos del colaborador (tabla usa idcampaing)
    $childrenSub = \App\Models\ColaboradorHijo::query()
        ->select([
            'colaborador_hijos.id         as hijo_id',
            'colaborador_hijos.identificacion',
            'colaborador_hijos.nombre_hijo',
            'colaborador_hijos.genero     as genero_hijo',
            DB::raw('CAST(colaborador_hijos.rango_edad AS UNSIGNED) as edad_int'),
            'colaborador_hijos.idcampaing',
            DB::raw("
                CASE
                  WHEN UPPER(TRIM(colaborador_hijos.genero)) IN ('F','FEM','FEMENINO','NIÑA') THEN 'F'
                  WHEN UPPER(TRIM(colaborador_hijos.genero)) IN ('M','MAS','MASCULINO','NIÑO') THEN 'M'
                  WHEN colaborador_hijos.genero IS NULL OR TRIM(colaborador_hijos.genero) = '' THEN 'U'
                  WHEN UPPER(TRIM(colaborador_hijos.genero)) IN ('U','UNISEX','UNI','TODOS','ANY') THEN 'U'
                  ELSE 'U'
                END as genero_norm_hijo
            "),
        ])
        ->where('colaborador_hijos.identificacion', $documento)
        ->when($campaignId, fn($q) => $q->where('colaborador_hijos.idcampaing', $campaignId));

    // Agregado de seleccionados por campaña + referencia (normalizada)
    // (contamos TODAS las filas; si quisieras solo confirmadas, agrega AND UPPER(TRIM(selected))='Y' en el origen)
    $selAgg = DB::table('seleccionados')
        ->select([
            'idcampaing',
            DB::raw('UPPER(TRIM(referencia)) as ref_norm'),
            DB::raw('COUNT(*) as total_sel'),
        ])
        ->groupBy('idcampaing', DB::raw('UPPER(TRIM(referencia))'));

    // Juguetes disponibles de la campaña (tabla usa idcampaign)
    $toysSub = DB::table('campaign_toys')
        ->select([
            'campaign_toys.id            as toy_id',
            'campaign_toys.idcampaign    as idcampaign',
            'campaign_toys.referencia    as referencia',
            DB::raw('UPPER(TRIM(campaign_toys.referencia)) as referencia_norm'),
            'campaign_toys.nombre        as toy_nombre',
            'campaign_toys.imagenppal    as imagenppal',
            'campaign_toys.descripcion   as descripcion',
            DB::raw('CAST(campaign_toys.desde AS UNSIGNED) as desde_int'),
            DB::raw('CAST(campaign_toys.hasta AS UNSIGNED) as hasta_int'),
            'campaign_toys.genero        as genero_toy',
            DB::raw('CAST(COALESCE(campaign_toys.unidades,0) AS UNSIGNED) as unidades'),
            // para depurar / usar en Blade:
            DB::raw('COALESCE(sa.total_sel, 0) as total_sel'),
            DB::raw('(CAST(COALESCE(campaign_toys.unidades,0) AS UNSIGNED) - COALESCE(sa.total_sel, 0)) as stock_disponible'),
            DB::raw("
                CASE
                  WHEN campaign_toys.genero IS NULL OR TRIM(campaign_toys.genero) = '' THEN 'U'
                  WHEN UPPER(TRIM(campaign_toys.genero)) IN ('U','UNISEX','UNI','TODOS','ANY') THEN 'U'
                  WHEN UPPER(TRIM(campaign_toys.genero)) IN ('F','FEM','FEMENINO','NIÑA') THEN 'F'
                  WHEN UPPER(TRIM(campaign_toys.genero)) IN ('M','MAS','MASCULINO','NIÑO') THEN 'M'
                  ELSE 'U'
                END as genero_norm_toy
            "),
        ])
        ->leftJoinSub($selAgg, 'sa', function ($j) {
            $j->on('sa.idcampaing', '=', 'campaign_toys.idcampaign')
              ->whereRaw('sa.ref_norm = UPPER(TRIM(campaign_toys.referencia))');
        })
        ->when($campaignId, fn($q) => $q->where('campaign_toys.idcampaign', $campaignId))
        // FILTRO CLAVE: solo juguetes con stock global (unidades > seleccionados)
        ->whereRaw('CAST(COALESCE(campaign_toys.unidades,0) AS UNSIGNED) > COALESCE(sa.total_sel, 0)');

    // JOIN hijos + juguetes disponibles
    $rows = DB::query()
        ->fromSub($childrenSub, 'ch')
        ->joinSub($toysSub, 'ct', function ($join) {
            $join->on('ct.idcampaign', '=', 'ch.idcampaing')
                 ->whereRaw('ch.edad_int BETWEEN ct.desde_int AND ct.hasta_int')
                 ->where(function ($w) {
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
            'ch.edad_int            as rango_edad',
            'ch.idcampaing',
            'ct.idcampaign',
            'ct.toy_id        as toy_id',
            'ct.referencia',
            'ct.toy_nombre    as toy_nombre',
            'ct.imagenppal',
            'ct.genero_norm_toy as genero_toy',
            'ct.desde_int     as desde',
            'ct.hasta_int     as hasta',
            'ct.descripcion',
            // datos útiles (también para corte en Blade)
            'ct.stock_disponible',
            'ct.unidades',
            'ct.total_sel',
        ]);

    return $rows->groupBy('hijo_id')->map(function ($items) {
        $f = $items->first();
        return [
            'hijo' => [
                'id'             => $f->hijo_id,
                'identificacion' => $f->identificacion,
                'nombre'         => $f->nombre_hijo,
                'genero'         => $f->genero_hijo,
                'rango_edad'     => $f->rango_edad,
                'idcampaign'     => $f->idcampaign ?? $f->idcampaing,
            ],
            'juguetes' => $items->map(fn($r) => [
                'id'          => $r->toy_id,
                'referencia'  => $r->referencia,
                'nombre'      => $r->toy_nombre,
                'imagenppal'  => $r->imagenppal,
                'genero'      => $r->genero_toy,
                'desde'       => $r->desde,
                'hasta'       => $r->hasta,
                'descripcion' => $r->descripcion,
                'stock'       => (int) $r->stock_disponible, // <-- para Blade
                'unidades'    => (int) $r->unidades,         // debug opcional
                'seleccion'   => (int) $r->total_sel,        // debug opcional
            ])->values(),
        ];
    })->values();
}

    public function aceptarPolitica(Request $request)
    {
        $user = $request->user();

        $colaborador = Colaborador::where('documento', $user->documento)->first();
        if (!$colaborador && !empty($user->email)) {
            $colaborador = Colaborador::where('email', $user->email)->first();
        }

        if ($colaborador) {
            $colaborador->forceFill(['politicadatos' => 'Y'])->save();
        }

        return back()->with('swal', [
            'icon'  => 'success',
            'title' => 'Gracias',
            'text'  => 'Has aceptado la política de tratamiento de datos.',
        ]);
    }

    public function aceptarWelcome(Request $request)
    {
        $user = $request->user();

        $colaborador = Colaborador::where('documento', $user->documento)->first();
        if (!$colaborador && !empty($user->email)) {
            $colaborador = Colaborador::where('email', $user->email)->first();
        }

        if ($colaborador) {
            $colaborador->forceFill(['welcome' => 'Y'])->save();
        }

        return back()->with('swal', [
            'icon'  => 'success',
            'title' => '¡Bienvenido(a)!',
            'text'  => 'Gracias por leer el mensaje de bienvenida.',
        ]);
    }
}
