<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Empresa;
use App\Models\Campaign;
use App\Models\Colaborador;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\Route as RouteFacade;

class CustomLoginController extends Controller
{
    public function show(string $token)
    {
        // 1) Desencriptar token -> NIT
        try {
            $nit = Crypt::decryptString($token);
        } catch (\Throwable $e) {
            abort(404);
        }

        // 2) Buscar empresa por NIT
        $empresa = Empresa::find($nit);
        if (!$empresa) {
            return view('admin.errors.error-page1', [
                'message' => 'La empresa no existe o la URL no está asociada a una campaña válida.',
            ]);
        }

        // 3) Buscar campaña por NIT
        //    Primero intentamos una campaña activa (hoy dentro del rango),
        //    si no existe, tomamos la más reciente por fechaini.
        $today = Carbon::today();

        $campaign = Campaign::where('nit', $nit)
            ->whereDate('fechaini', '<=', $today)
            ->whereDate('fechafin', '>=', $today)
            ->orderByDesc('fechaini')
            ->first();

        if (!$campaign) {
            return view('admin.errors.error-page1', [
                'message' => 'La campaña no está activa este momento.',
            ]);
        }

        // 4) Preparar URL de ingreso (ajusta según tu app)
        //    Si existe ruta('login'), usamos esa. Si no, '/login' por defecto.
        $ingresoUrl = RouteFacade::has('login') ? route('login') : url('/login');

        // 5) Banner absoluto (fallback si ND o vacío)
        $bannerUrl = null;
        if (!empty($campaign->banner) && $campaign->banner !== 'ND') {
            // asumiendo que guardaste en disco 'public'
            $bannerUrl = asset('storage/' . ltrim($campaign->banner, '/'));
        }

        return view('custom-login', [
            'empresa'    => $empresa,
            'campaign'   => $campaign,
            'ingresoUrl' => $ingresoUrl,
            'bannerUrl'  => $bannerUrl,
            'token'      => $token, // opcional si lo necesitas
        ]);
    }

    public function auth(Request $request)
    {
        $data = $request->validate([
            'token'          => ['required', 'string'],
            'identificacion' => ['required', 'string', 'max:50'],
            // 'email'       => ['nullable','email'],
        ]);

        // 1) Desencriptar token -> NIT
        try {
            $nit = Crypt::decryptString($data['token']);
        } catch (\Throwable $e) {
            return back()->withErrors(['identificacion' => 'Token inválido'])->withInput();
        }

        // 2) Empresa existente
        $empresa = Empresa::find($nit);
        if (!$empresa) return back()->withErrors(['identificacion' => 'Empresa no encontrada'])->withInput();

        // 3) Campaña activa (o la más reciente)
        $today = Carbon::today();
        $campaign = Campaign::where('nit', $nit)
            ->whereDate('fechaini', '<=', $today)
            ->whereDate('fechafin', '>=', $today)
            ->orderByDesc('fechaini')
            ->first()
            ?? Campaign::where('nit', $nit)->orderByDesc('fechaini')->first();

        if (!$campaign) return back()->withErrors(['identificacion' => 'No hay campañas disponibles'])->withInput();

        // 4) Verificar colaborador asignado a esta campaña
        // Ajusta esta consulta a tu esquema real:
        $asignado = DB::table('campaing_colaboradores')
            ->where('documento', $data['identificacion'])
            ->where('idcampaign', $campaign->id)
            ->where('nit', $nit) // amarra a la misma empresa del token
            ->exists();

        if (!$asignado) {
            return back()
                ->withErrors(['identificacion' => 'No estás asignado a esta campaña.'])
                ->withInput();
        }

        // 🔐 Autenticar al usuario web por documento
        $user = User::where('documento', $data['identificacion'])->first();

        if (!$user) {
            return back()
                ->withErrors(['identificacion' => 'No existe un usuario con ese documento.'])
                ->withInput();
        }

        // (Opcional) Si por diseño todo colaborador debe tener el rol 'colaborador':
        if (!$user->hasRole('colaborador')) {
            // Asegúrate que el rol exista con guard 'web' (ver nota abajo)
            $user->assignRole('colaborador');
        }

        // ⚠️ Muy importante: autenticarlo en el guard 'web'
        Auth::login($user);

        // (Opcional) resetear cache de permisos si has cambiado roles/permisos recientemente
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 5) Marcar sesión de colaborador y campaña
        session([
            'collab_identificacion' => $data['identificacion'],
            'campaign_id'           => $campaign->id,
            'empresa_nit'           => $nit,
        ]);

        // 6) Redirigir a product
        return redirect()->route('product');
    }
}
