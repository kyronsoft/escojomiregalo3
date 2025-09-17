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
use Illuminate\Support\Facades\Storage;
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

        // 2) Empresa por NIT
        $empresa = Empresa::find($nit);
        if (!$empresa) {
            return view('admin.errors.error-page1', [
                'message' => 'La empresa no existe o la URL no está asociada a una campaña válida.',
            ]);
        }

        // 3) Campaña activa para el NIT (requerida para el flujo)
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

        // 4) URL de ingreso
        $ingresoUrl = RouteFacade::has('login') ? route('login') : url('/login');

        // 5) Imagen de fondo desde EMPRESA.imagen_login (NO desde campaign.banner)
        $bgUrl = null;
        if (!empty($empresa->imagen_login) && $empresa->imagen_login !== 'ND') {
            $path = ltrim((string)$empresa->imagen_login, '/');
            if (Str::startsWith($path, ['http://', 'https://'])) {
                $bgUrl = $path; // URL absoluta
            } elseif (Storage::disk('public')->exists($path)) {
                $bgUrl = Storage::disk('public')->url($path); // storage público
            } else {
                // Por compatibilidad si no existe físicamente pero lo quieres servir igual
                $bgUrl = asset('storage/' . $path);
            }
        }

        // Fallback SVG si no hay imagen_login válida
        if (!$bgUrl) {
            $bgUrl = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#111"/><stop offset="100%" stop-color="#333"/></linearGradient></defs><rect width="100%" height="100%" fill="url(#g)"/></svg>'
            );
        }

        return view('custom-login', [
            'empresa'    => $empresa,
            'campaign'   => $campaign,
            'ingresoUrl' => $ingresoUrl,
            'bgUrl'      => $bgUrl,
            'token'      => $token,
        ]);
    }

    public function auth(Request $request)
    {
        $data = $request->validate([
            'token'          => ['required', 'string'],
            'documento'      => ['required', 'string', 'max:50'],
        ]);

        // 1) Desencriptar token -> NIT
        try {
            $nit = Crypt::decryptString($data['token']);
        } catch (\Throwable $e) {
            return back()->withErrors(['documento' => 'Token inválido'])->withInput();
        }

        // 2) Empresa existente
        $empresa = Empresa::find($nit);
        if (!$empresa) return back()->withErrors(['documento' => 'Empresa no encontrada'])->withInput();

        // 3) Campaña activa (o la más reciente)
        $today = Carbon::today();
        $campaign = Campaign::where('nit', $nit)
            ->whereDate('fechaini', '<=', $today)
            ->whereDate('fechafin', '>=', $today)
            ->orderByDesc('fechaini')
            ->first()
            ?? Campaign::where('nit', $nit)->orderByDesc('fechaini')->first();

        if (!$campaign) return back()->withErrors(['documento' => 'No hay campañas disponibles'])->withInput();

        // 4) Verificar colaborador asignado a esta campaña
        $asignado = DB::table('campaing_colaboradores')
            ->where('documento', $data['documento'])
            ->where('idcampaign', $campaign->id)
            ->where('nit', $nit)
            ->exists();

        if (!$asignado) {
            return back()
                ->withErrors(['documento' => 'No estás asignado a esta campaña.'])
                ->withInput();
        }

        // 5) Autenticar usuario por documento
        $user = User::where('documento', $data['documento'])->first();
        if (!$user) {
            return back()->withErrors(['documento' => 'No existe un usuario con ese documento.'])->withInput();
        }

        if (!$user->hasRole('colaborador')) {
            $user->assignRole('colaborador');
        }

        Auth::login($user);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 6) Marcar sesión y redirigir
        session([
            'collab_identificacion' => $data['documento'],
            'campaign_id'           => $campaign->id,
            'empresa_nit'           => $nit,
        ]);

        return redirect()->route('product');
    }
}
