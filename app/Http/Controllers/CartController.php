<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignToy;
use App\Models\Colaborador;
use Illuminate\Support\Str;
use App\Models\Seleccionado;
use Illuminate\Http\Request;
use App\Models\ColaboradorHijo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Jobs\SendSelectionCompletedMail;

class CartController extends Controller
{
    public function index()
    {
        // 1) Asegura que solo Colaborador vea el carrito (ajusta si quieres permitir otros)
        if (!auth()->check() || !auth()->user()->hasRole('Colaborador')) {
            // Redirige a login o muestra 403 (elige una)
            // return redirect()->route('login');
            abort(403, 'Solo los colaboradores pueden ver el carrito.');
        }

        $documento = auth()->user()->documento; // ya seguro

        // 2) Carga de ítems del carrito
        $items = \App\Models\Seleccionado::query()
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
                    ->on('campaign_toys.idcampaign', '=', 'colaborador_hijos.idcampaing');
            })
            ->where('seleccionados.documento', $documento)
            ->orderByDesc('seleccionados.created_at')
            ->get();

        // 3) Determinar la campaña activa con operador null-safe (?->)
        $nitSesion  = session('empresa_nit');
        $firstItem  = $items->first();
        $campaignId = $firstItem?->idcampaing
            ?? session('campaign_id')
            ?? DB::table('campaing_colaboradores')
            ->where('documento', $documento)
            ->when($nitSesion, fn($q) => $q->where('nit', $nitSesion))
            ->orderByDesc('created_at')
            ->value('idcampaign');

        // 4) Banner de campaña (si aplica)
        $campaignBannerUrl = null;
        if ($campaignId) {
            $campaign = \App\Models\Campaign::find($campaignId);
            if ($campaign && filled($campaign->banner) && $campaign->banner !== 'ND') {
                // Usa disk 'public'
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
            'idhijo'      => ['required', 'integer', 'min:1', 'exists:colaborador_hijos,id'],
            'referencia'  => ['required', 'string', 'max:100'],
            'idcampaign'  => ['nullable', 'integer', 'min:1'], // ← opcional, si lo mandas desde la vista
        ]);

        // Debe estar logueado y ser Colaborador
        if (!auth()->check() || !auth()->user()->hasRole('Colaborador')) {
            return redirect()->route('ecommerce.cart.index')->with('swal', [
                'icon'  => 'error',
                'title' => 'Acceso restringido',
                'text'  => 'Debes iniciar sesión como Colaborador.',
            ]);
        }

        // Hijo y pertenencia
        $hijo = ColaboradorHijo::find($data['idhijo']);
        $userDoc = (string) auth()->user()->documento;
        if ((string) $hijo->identificacion !== $userDoc) {
            return back()->with('swal', [
                'icon'  => 'error',
                'title' => 'Hijo no válido',
                'text'  => 'El hijo seleccionado no pertenece al colaborador autenticado.',
            ]);
        }

        // Normaliza referencia (quita NBSP/control chars y trim)
        $refRaw = (string) $data['referencia'];
        $ref    = preg_replace('/[\x{00A0}\p{C}]+/u', '', $refRaw);
        $ref    = trim($ref);

        // ===== Resolver campaignId con múltiples fallbacks =====
        $campaignId = (int) ($data['idcampaign'] ?? 0);
        if ($campaignId <= 0) {
            // Soporta ambos nombres de columna por si tu tabla conserva "idcampaing"
            $campaignId = (int) ($hijo->getAttribute('idcampaign') ?? 0);
        }
        if ($campaignId <= 0) {
            $campaignId = (int) ($hijo->getAttribute('idcampaing') ?? 0);
        }
        if ($campaignId <= 0) {
            // Campaña más reciente por pivot (campaing_colaboradores)
            $campaignId = (int) DB::table('campaing_colaboradores')
                ->where('documento', $userDoc)
                ->orderByDesc('created_at')
                ->value('idcampaign');
        }

        // Si aún no lo tenemos, intenta encontrar la referencia en campañas del colaborador
        $toy = null;
        if ($campaignId > 0) {
            $toy = CampaignToy::where('idcampaign', $campaignId)
                ->where(function ($q) use ($ref) {
                    $q->where('referencia', $ref)
                        ->orWhereRaw('TRIM(referencia) = ?', [$ref])
                        ->orWhereRaw('REPLACE(referencia," ","") = REPLACE(?, " ", "")', [$ref]);
                })
                ->first();
        }

        if (!$toy) {
            // Buscar esa referencia en campañas a las que esté asignado el colaborador
            $toy = DB::table('campaign_toys as t')
                ->join('campaing_colaboradores as pc', 'pc.idcampaign', '=', 't.idcampaign')
                ->where('pc.documento', $userDoc)
                ->where(function ($q) use ($ref) {
                    $q->where('t.referencia', $ref)
                        ->orWhereRaw('TRIM(t.referencia) = ?', [$ref])
                        ->orWhereRaw('REPLACE(t.referencia," ","") = REPLACE(?, " ", "")', [$ref]);
                })
                ->select('t.*')
                ->first();

            if ($toy) {
                $campaignId = (int) $toy->idcampaign;
            }
        }

        if (!$toy || $campaignId <= 0) {
            Log::warning('addcart: no se pudo resolver toy/campaign', [
                'ref_in'    => $refRaw,
                'ref_norm'  => $ref,
                'child_id'  => $hijo->id,
                'doc'       => $userDoc,
                'campaignId' => $campaignId,
            ]);

            return back()->with('active_child_id', $hijo->id)->with('swal', [
                'icon'  => 'error',
                'title' => 'No se pudo agregar',
                'text'  => 'No ubicamos la referencia en una campaña válida para tu usuario.',
            ]);
        }

        // ===== Chequeo de elegibilidad: edad + género =====
        $edad = (int) ($hijo->rango_edad ?? 0); // numérico desde tu ajuste en el form

        // Normaliza género del hijo a clave M/F/'' aceptando NIÑO/NIÑA/UNISEX
        $gH = strtoupper(trim((string) ($hijo->genero ?? '')));
        $gHKey = match ($gH) {
            'M', 'NIÑO', 'NINO', 'BOY', 'MALE'   => 'M',
            'F', 'NIÑA', 'NINA', 'GIRL', 'FEMALE' => 'F',
            default                              => '',
        };

        $desde  = (int) (($toy->desde ?? 0));
        $hasta  = (int) (($toy->hasta ?? 99));
        $gT     = strtoupper(trim((string) ($toy->genero ?? '')));
        $isUni  = in_array($gT, ['', 'UNISEX', 'U', 'UNI'], true);

        // Permite emparejar M/F con NIÑO/NIÑA también
        $genOk = $isUni || (
            $gHKey !== '' && (
                $gT === $gHKey ||           // M/F
                ($gHKey === 'M' && in_array($gT, ['NIÑO', 'NINO'], true)) ||
                ($gHKey === 'F' && in_array($gT, ['NIÑA', 'NINA'], true))
            )
        );
        $edadOk = ($edad >= $desde && $edad <= $hasta);

        if (!($edadOk && $genOk)) {
            return back()->with('active_child_id', $hijo->id)->with('swal', [
                'icon'  => 'error',
                'title' => 'Juguete no elegible',
                'text'  => 'No coincide edad o género para ' . ($hijo->nombre_hijo ?? 'el hijo(a)') . '.',
            ]);
        }

        // ===== Solo 1 selección por hijo en esa campaña =====
        $yaHay = Seleccionado::where('documento', $userDoc)
            ->where('idcampaing', $campaignId) // nombre en BD de tu tabla seleccionados
            ->where('idhijo', $hijo->id)
            ->exists();

        if ($yaHay) {
            return back()->with('active_child_id', $hijo->id)->with('swal', [
                'icon'  => 'warning',
                'title' => 'Solo 1 por hijo(a)',
                'text'  => "Ya elegiste un juguete para {$hijo->nombre_hijo}.",
            ]);
        }

        // ===== Insertar selección =====
        DB::transaction(function () use ($userDoc, $campaignId, $hijo, $toy) {
            Seleccionado::updateOrCreate(
                [
                    'documento'  => $userDoc,
                    'idcampaing' => $campaignId,        // ← respeta el nombre real de la columna
                    'idhijo'     => $hijo->id,
                    'referencia' => $toy->referencia,
                ],
                [
                    'selected'   => 'Y',
                ]
            );
        });

        return back()->with('active_child_id', $hijo->id)->with('swal', [
            'icon'  => 'success',
            'title' => 'Agregado',
            'text'  => "{$toy->referencia} - {$toy->nombre} agregado para {$hijo->nombre_hijo}.",
        ]);
    }

    public function remove(Request $request)
    {
        $data = $request->validate([
            'idhijo'      => ['required', 'integer', 'min:1'],
            'referencia'  => ['required', 'string', 'max:100'],
            'idcampaing'  => ['nullable', 'integer', 'min:1'], // viene del form
        ]);

        // Asegura sesión y rol (ajusta si permites otros)
        if (!auth()->check() || !auth()->user()->hasRole('Colaborador')) {
            abort(403, 'Solo los colaboradores pueden eliminar del carrito.');
        }

        $hijo = ColaboradorHijo::findOrFail($data['idhijo']);

        // Solo puede eliminar si el hijo pertenece al usuario autenticado
        if ((string) $hijo->identificacion !== (string) auth()->user()->documento) {
            abort(403, 'No puedes eliminar selecciones de otro usuario.');
        }

        // Normaliza referencia (quita &nbsp; y caracteres de control, y trim)
        $refRaw = (string) $data['referencia'];
        $ref    = preg_replace('/[\x{00A0}\p{C}]+/u', '', $refRaw);
        $ref    = trim($ref);

        // Resuelve campaña con prioridad al valor enviado; cae a columnas reales del hijo
        $campId = (int) ($data['idcampaing'] ?? 0);
        if ($campId <= 0) {
            $campId = (int) ($hijo->getAttribute('idcampaing') ?? 0);
        }
        if ($campId <= 0) {
            $campId = (int) ($hijo->getAttribute('idcampaign') ?? 0); // por si tu modelo trae este alias
        }

        // Elimina con comparaciones tolerantes para referencia
        $deleted = Seleccionado::where('documento', $hijo->identificacion)
            ->where('idhijo', $hijo->id)
            ->when($campId > 0, fn($q) => $q->where('idcampaing', $campId))
            ->where(function ($q) use ($ref) {
                $q->where('referencia', $ref)
                    ->orWhereRaw('TRIM(referencia) = ?', [$ref])
                    ->orWhereRaw('REPLACE(referencia," ","") = REPLACE(?, " ", "")', [$ref]);
            })
            ->delete();

        return back()->with('swal', [
            'icon'  => $deleted ? 'success' : 'info',
            'title' => $deleted ? 'Eliminado' : 'Nada para eliminar',
            'text'  => $deleted
                ? 'Producto eliminado del carrito.'
                : 'No se encontró la selección a eliminar.',
        ]);
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

        // === Hijos del colaborador en la campaña ===
        $children = ColaboradorHijo::where('identificacion', $documento)
            ->where('idcampaing', $campaignId)
            ->get(['id', 'nombre_hijo']);

        if ($children->isEmpty()) {
            return redirect()->route('product')->with('swal', [
                'icon'  => 'warning',
                'title' => 'Sin hijos registrados',
                'text'  => 'No tienes hijos registrados en esta campaña.',
            ]);
        }

        // === Selecciones realizadas ===
        $selectedIds = Seleccionado::where('documento', $documento)
            ->where('idcampaing', $campaignId)
            ->where('selected', 'Y')
            ->pluck('idhijo')
            ->unique();

        // ⚠️ Caso 1: NO hay ninguna selección aún
        if ($selectedIds->isEmpty()) {
            $primero = optional($children->first())->id;

            return redirect()
                ->route('product')
                ->with('active_child_id', $primero)
                ->with('swal', [
                    'icon'  => 'error',
                    'title' => 'Sin selección',
                    'text'  => 'No puedes finalizar sin haber seleccionado al menos un juguete.',
                ]);
        }

        // ⚠️ Caso 2: Hay selecciones, pero faltan hijos por escoger
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
                    ->on('campaign_toys.idcampaign', '=', 'colaborador_hijos.idcampaing');
            })
            ->where('seleccionados.documento', $documento)
            ->where('seleccionados.selected', 'Y')
            ->where('colaborador_hijos.idcampaing', $campaignId)
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

            DB::table('colaboradores')->where('documento', $documento)->update(['enviado' => 1]);
        }

        /**
         * 🔒 Deshabilitar usuario para futuros logins
         * - Contraseña aleatoria
         * - Limpiar remember_token
         */
        DB::table('users')->where('id', $user->id)->update([
            'password'       => Hash::make(Str::random(64)),
            'remember_token' => null,
            'updated_at'     => now(),
        ]);

        // Revisión por tipo de campaña
        $campaign = Campaign::find($campaignId);
        if ($campaign && (int)$campaign->idtipo === 1) {
            // En este flujo necesitas al usuario autenticado para el modal,
            // pero ya quedó deshabilitado para futuros logins.
            session(['_finish_campaign_id' => $campaign->id]);
            return redirect()->route('ecommerce.cart.finish.review');
        }

        // 🚫 NO cerramos sesión aquí: checkout() ya la cierra tras armar el resumen.
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

        // 🔒 Asegurar deshabilitación (idempotente)
        DB::table('users')->where('id', $user->id)->update([
            'password'       => Hash::make(Str::random(64)),
            'remember_token' => null,
            'updated_at'     => now(),
        ]);

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
                    ->on('campaign_toys.idcampaign', '=', 'colaborador_hijos.idcampaing');
            })
            ->where('seleccionados.documento', $documento)
            ->where('seleccionados.selected', 'Y')
            ->where('colaborador_hijos.idcampaing', $campaignId)
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
