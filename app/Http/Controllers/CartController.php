<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignToy;
use App\Models\Colaborador;
use App\Models\Seleccionado;
use Illuminate\Http\Request;
use App\Models\ColaboradorHijo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Mail\SelectionCompletedMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Jobs\SendSelectionCompletedMail;

class CartController extends Controller
{
    public function index()
    {
        // Documento del colaborador autenticado
        $documento = auth()->user()->documento;

        // Ítems actuales del carrito
        $items = Seleccionado::query()
            ->select([
                'seleccionados.id',
                'seleccionados.documento',
                'seleccionados.idcampaing',
                'seleccionados.idhijo',
                'seleccionados.referencia',
                'campaign_toys.nombre as toy_nombre',
                'campaign_toys.imagenppal',
                'colaborador_hijos.nombre_hijo as child_nombre',
            ])
            ->join('colaborador_hijos', 'colaborador_hijos.id', '=', 'seleccionados.idhijo')
            ->join('campaign_toys', function ($j) {
                $j->on('campaign_toys.referencia', '=', 'seleccionados.referencia')
                    ->on('campaign_toys.idcampaign', '=', 'colaborador_hijos.idcampaign');
            })
            ->where('seleccionados.documento', $documento)
            ->orderByDesc('seleccionados.created_at')
            ->get();

        // Determinar campaignId: items -> sesión -> puente (más reciente, opcional por NIT de sesión)
        $nitSesion  = session('empresa_nit');
        $campaignId = $items->first()->idcampaing
            ?? session('campaign_id')
            ?? DB::table('campaing_colaboradores')
            ->where('documento', $documento)
            ->when($nitSesion, fn($q) => $q->where('nit', $nitSesion))
            ->orderByDesc('created_at')
            ->value('idcampaign');

        // Resolver URL absoluta del banner (si existe)
        $campaignBannerUrl = null;
        if ($campaignId) {
            $campaign = Campaign::find($campaignId);
            if ($campaign && !empty($campaign->banner) && $campaign->banner !== 'ND') {
                // asume que el banner está en el disk 'public'
                $campaignBannerUrl = url(Storage::disk('public')->url($campaign->banner));
            }
        }

        return view('ecommerce.cart', [
            'items'             => $items,
            'campaignBannerUrl' => $campaignBannerUrl,
        ]);
    }


    public function addcart(Request $request)
    {
        $data = $request->validate([
            'idhijo'     => ['required', 'integer', 'min:1'],
            'referencia' => ['required', 'string', 'max:100'],
        ]);

        $hijo       = ColaboradorHijo::findOrFail($data['idhijo']);
        $documento  = (string) $hijo->identificacion;
        $campaignId = (int) $hijo->idcampaign;
        $edad       = (int) $hijo->rango_edad;

        // Género del hijo: F, M o NULL => '' (neutro)
        $generoHijo = strtoupper(trim((string) ($hijo->genero ?? '')));

        $toy = CampaignToy::where('idcampaign', $campaignId)
            ->where('referencia', $data['referencia'])
            ->firstOrFail();

        // --- Elegibilidad por edad + género ---
        $desde  = (int) $toy->desde;
        $hasta  = (int) $toy->hasta;

        // Género del juguete: F, M o NULL/'' => Unisex
        // (también soporta 'UNISEX' por compatibilidad con datos antiguos)
        $genToyRaw = $toy->genero; // puede venir NULL
        $genToy    = strtoupper(trim((string) ($genToyRaw ?? '')));
        $isUnisex  = is_null($genToyRaw) || $genToy === '' || $genToy === 'UNISEX';

        // Si es unisex, OK; si no, debe coincidir con F/M del hijo
        $genOk = $isUnisex || ($generoHijo !== '' && $genToy === $generoHijo);

        if (!($edad >= $desde && $edad <= $hasta && $genOk)) {
            return redirect()
                ->route('product')
                ->with('active_child_id', $hijo->id)
                ->with('swal', [
                    'icon'  => 'error',
                    'title' => 'Juguete no elegible',
                    'text'  => 'No coincide edad o género para ' . ($hijo->nombre_hijo ?? 'el hijo(a)') . '.',
                ]);
        }

        // ✅ Solo 1 por hijo en esta campaña
        $yaHay = Seleccionado::where('documento', $documento)
            ->where('idcampaing', $campaignId)   // nombre tal cual en BD
            ->where('idhijo', $hijo->id)
            ->exists();

        if ($yaHay) {
            return redirect()
                ->route('product')
                ->with('active_child_id', $hijo->id)
                ->with('swal', [
                    'icon'  => 'warning',
                    'title' => 'Solo 1 por hijo(a)',
                    'text'  => "Ya elegiste un juguete para {$hijo->nombre_hijo}.",
                ]);
        }

        // Insertar selección
        DB::transaction(function () use ($documento, $campaignId, $hijo, $toy) {
            Seleccionado::updateOrCreate(
                [
                    'documento'  => $documento,
                    'idcampaing' => $campaignId,
                    'idhijo'     => $hijo->id,
                    'referencia' => $toy->referencia,
                ],
                [
                    'selected'   => 'Y',
                ]
            );
        });

        return redirect()
            ->route('product')
            ->with('active_child_id', $hijo->id)
            ->with('swal', [
                'icon'  => 'success',
                'title' => 'Agregado',
                'text'  => "{$toy->referencia} - {$toy->nombre} agregado para {$hijo->nombre_hijo}.",
            ]);
    }

    public function remove(Request $request)
    {
        $data = $request->validate([
            'idhijo'     => ['required', 'integer', 'min:1'],
            'referencia' => ['required', 'string', 'max:100'],
        ]);

        $hijo = ColaboradorHijo::findOrFail($data['idhijo']);

        Seleccionado::where('documento', $hijo->identificacion)
            ->where('idcampaing', $hijo->idcampaign)
            ->where('idhijo', $hijo->id)
            ->where('referencia', $data['referencia'])
            ->delete();

        return back()->with('status', 'Producto eliminado del carrito.');
    }

    public function finish(Request $request)
    {
        $user      = Auth::user();
        $userEmail = $user?->email;
        $userName  = $user?->name ?? 'Usuario';

        // Documento del colaborador
        $documento = Colaborador::where('email', $userEmail)->value('documento');
        if (!$documento) {
            return redirect()->route('product')->with('swal', [
                'icon'  => 'error',
                'title' => 'No identificado',
                'text'  => 'No fue posible identificar tu documento.',
            ]);
        }

        // Campaña activa
        $nitSesion  = session('empresa_nit');
        $campaignId = session('campaign_id') ??
            DB::table('campaing_colaboradores')
            ->where('documento', $documento)
            ->when($nitSesion, fn($q) => $q->where('nit', $nitSesion))
            ->orderByDesc('created_at')
            ->value('idcampaign');

        if (!$campaignId) {
            return redirect()->route('product')->with('swal', [
                'icon'  => 'error',
                'title' => 'Sin campaña',
                'text'  => 'No tienes una campaña activa asignada.',
            ]);
        }

        // === Validación: todos los hijos deben tener 1 juguete ===
        $children = ColaboradorHijo::where('identificacion', $documento)
            ->where('idcampaign', $campaignId)
            ->get(['id', 'nombre_hijo']);

        if ($children->isEmpty()) {
            return redirect()->route('product')->with('swal', [
                'icon'  => 'warning',
                'title' => 'Sin hijos registrados',
                'text'  => 'No tienes hijos registrados en esta campaña.',
            ]);
        }

        $selectedIds = Seleccionado::where('documento', $documento)
            ->where('idcampaing', $campaignId)
            ->where('selected', 'Y')
            ->pluck('idhijo')
            ->unique();

        if ($selectedIds->count() < $children->count()) {
            $faltantes = $children->whereNotIn('id', $selectedIds)->pluck('nombre_hijo')->values();
            $primero   = optional($children->whereNotIn('id', $selectedIds)->first())->id;

            return redirect()
                ->route('product')
                ->with('active_child_id', $primero)
                ->with('swal', [
                    'icon'  => 'error',
                    'title' => 'Faltan selecciones',
                    'html'  => 'Debes escoger un juguete para cada hijo(a). Te faltan: <br><b>' .
                        e($faltantes->implode(', ')) . '</b>.',
                ]);
        }

        // Traer selección (para el correo)
        $items = Seleccionado::query()
            ->select([
                'seleccionados.referencia',
                'seleccionados.idcampaing',
                'seleccionados.idhijo',
                'campaign_toys.nombre as toy_nombre',
                'campaign_toys.imagenppal',
                'colaborador_hijos.nombre_hijo as child_nombre',
            ])
            ->join('colaborador_hijos', 'colaborador_hijos.id', '=', 'seleccionados.idhijo')
            ->join('campaign_toys', function ($j) {
                $j->on('campaign_toys.referencia', '=', 'seleccionados.referencia')
                    ->on('campaign_toys.idcampaign', '=', 'colaborador_hijos.idcampaign');
            })
            ->where('seleccionados.documento', $documento)
            ->where('seleccionados.selected', 'Y')
            ->where('colaborador_hijos.idcampaign', $campaignId)
            ->orderBy('colaborador_hijos.nombre_hijo')
            ->get()
            ->map(fn($r) => [
                'referencia'   => (string) $r->referencia,
                'idcampaing'   => (int) $r->idcampaing,
                'idhijo'       => (int) $r->idhijo,
                'toy_nombre'   => (string) $r->toy_nombre,
                'imagenppal'   => $r->imagenppal ? (string)$r->imagenppal : '',
                'child_nombre' => (string) $r->child_nombre,
            ])
            ->toArray();

        // Enviar email de confirmación
        if ($userEmail) {
            dispatch(new SendSelectionCompletedMail(
                to: $userEmail,
                userName: $userName,
                items: $items
            ))->onQueue('emails');

            DB::table('colaboradores')->where('documento', $documento)->update(['enviado' => 'Y']);
        }

        // Revisión por tipo de campaña
        $campaign = Campaign::find($campaignId);
        if ($campaign && (int)$campaign->idtipo === 1) {
            session(['_finish_campaign_id' => $campaign->id]);
            return redirect()->route('ecommerce.cart.finish.review');
        }

        // 🚫 NO cerrar sesión aquí. Vamos a checkout autenticados.
        return redirect()
            ->route('ecommerce.checkout')
            ->with('status', 'Tu selección ha sido registrada. Te mostraremos el resumen.');
    }



    public function finishReview(Request $request)
    {
        $user        = $request->user();
        $colaborador = Colaborador::where('email', $user->email)->firstOrFail();

        // si no vino por finish (sin campaña), igual mostramos el modal por defecto
        $campaignId  = session('_finish_campaign_id');
        $campaign    = $campaignId ? Campaign::find($campaignId) : null;

        // Solo mostramos el modal si la campaña exige datos (idtipo = 1)
        if (!$campaign || (int)$campaign->idtipo !== 1) {
            return redirect()->route('ecommerce.checkout');
        }

        // Renderiza una vista intermedia que abre el modal automáticamente
        return view('ecommerce.finish-review', [
            'colaborador' => $colaborador,
            'campaign'    => $campaign,
        ]);
    }

    public function finishUpdate(Request $request)
    {
        $data = $request->validate([
            'direccion'     => ['nullable', 'string', 'max:255'],
            'telefono'      => ['nullable', 'string', 'max:50'],
            'ciudad'        => ['nullable', 'string', 'max:100'],
            'barrio'        => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'updatedatos' => ['Y'],
        ]);

        $user        = $request->user();
        $colaborador = Colaborador::where('email', $user->email)->firstOrFail();

        // Actualiza solo los campos provistos
        $colaborador->fill($data);
        $colaborador->save();

        // Limpia flag de campaña usada para finish
        $request->session()->forget('_finish_campaign_id');

        // Ahora sí, cerrar sesión y a checkout
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('ecommerce.checkout')
            ->with('status', 'Tu selección ha sido registrada y tus datos han sido actualizados.');
    }

    public function checkout(Request $request)
    {
        // 1) Usuario autenticado (llegamos aún con sesión abierta)
        $user = Auth::user();
        if (!$user) {
            // Si alguien llegó aquí sin sesión, mándalo al login
            return redirect()->route('login');
        }

        // 2) Documento y campaña (misma lógica que usas en otras vistas)
        $documento = Colaborador::where('email', $user->email)->value('documento');

        $nitSesion  = session('empresa_nit');
        $campaignId = session('campaign_id') ??
            DB::table('campaing_colaboradores')
            ->where('documento', $documento)
            ->when($nitSesion, fn($q) => $q->where('nit', $nitSesion))
            ->orderByDesc('created_at')
            ->value('idcampaign');

        // 3) Items seleccionados para el resumen
        $items = Seleccionado::query()
            ->select([
                'seleccionados.referencia',
                'seleccionados.idcampaing',
                'seleccionados.idhijo',
                'campaign_toys.nombre as toy_nombre',
                'campaign_toys.imagenppal',
                'colaborador_hijos.nombre_hijo as child_nombre',
            ])
            ->join('colaborador_hijos', 'colaborador_hijos.id', '=', 'seleccionados.idhijo')
            ->join('campaign_toys', function ($j) {
                $j->on('campaign_toys.referencia', '=', 'seleccionados.referencia')
                    ->on('campaign_toys.idcampaign', '=', 'colaborador_hijos.idcampaign');
            })
            ->where('seleccionados.documento', $documento)
            ->where('seleccionados.selected', 'Y')
            ->where('colaborador_hijos.idcampaign', $campaignId)
            ->orderBy('colaborador_hijos.nombre_hijo')
            ->get();

        // 4) Banner de la campaña
        $campaignBannerUrl = null;
        if ($campaignId) {
            $campaign = Campaign::find($campaignId);
            if ($campaign && !empty($campaign->banner) && $campaign->banner !== 'ND') {
                $campaignBannerUrl = url(Storage::disk('public')->url($campaign->banner));
            }
        }

        // 5) **Cerrar sesión después de tener todo listo**
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 6) Renderizar el checkout (accesible sin session, porque ya pasamos los datos)
        return view('ecommerce.checkout', compact('items', 'campaignBannerUrl'))
            ->with('status', session('status')); // conserva el flash si lo deseas
    }
}
