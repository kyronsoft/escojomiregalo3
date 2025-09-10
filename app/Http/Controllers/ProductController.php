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
                ->where('idcampaing', $campaignId)
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

        // Colaborador y modales
        $colaborador = Colaborador::where('documento', $documento)->first();

        // Política de datos
        $pdValue = strtoupper(trim((string) ($colaborador->politicadatos ?? '')));
        $showPolitica = ($pdValue !== 'Y');
        $politicaHtml = Parametro::where('nombre', 'POLITICA DATOS')->value('valor') ?? '...';

        // Bienvenida (solo si hay mensaje y no marcada)
        $welcomeValue = strtoupper(trim((string) ($colaborador->welcome ?? '')));
        $showWelcome = ($welcomeMsg !== '') && ($welcomeValue !== 'Y');

        // Datos juguetes
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

    private function juguetesPorColaboradorJoin(string $documento, ?int $campaignId = null)
    {
        $toysTable = 'campaign_toys';

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

        $toysSub = DB::table($toysTable)
            ->select([
                "$toysTable.id            as toy_id",
                "$toysTable.idcampaign    as idcampaing",
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
                      ELSE 'U'
                    END as genero_norm_toy
                "),
            ]);

        $rows = DB::query()
            ->fromSub($childrenSub, 'ch')
            ->joinSub($toysSub, 'ct', function ($join) {
                $join->on('ct.idcampaing', '=', 'ch.idcampaing')
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
                'ch.edad_int      as rango_edad',
                'ch.idcampaing',
                'ct.toy_id        as toy_id',
                'ct.referencia',
                'ct.toy_nombre    as toy_nombre',
                'ct.imagenppal',
                'ct.genero_norm_toy as genero_toy',
                'ct.desde_int     as desde',
                'ct.hasta_int     as hasta',
                'ct.descripcion',
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
                    'idcampaign'     => $f->idcampaing,
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

    /** ✅ Nuevo: aceptar mensaje de bienvenida */
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
