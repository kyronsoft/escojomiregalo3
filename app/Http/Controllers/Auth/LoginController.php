<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/dashboard';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    // Usaremos el campo 'documento' para ingresar
    public function username()
    {
        return 'documento';
    }

    // Credenciales exactas
    protected function credentials(Request $request)
    {
        return [
            'documento' => $request->input('documento'),
            'password'  => $request->input('password'),
        ];
    }

    // app/Http/Controllers/Auth/LoginController.php

    protected function authenticated(Request $request, $user)
    {
        // Usa SIEMPRE el mismo texto/case de los roles
        if ($user->hasRole('Colaborador')) {
            return redirect()->route('product');
        }

        if ($user->hasAnyRole(['Admin', 'Ejecutiva-Empresas', 'RRHH-Cliente'])) {
            return redirect()->route('dashboard.index');
        }

        auth()->logout();
        return redirect()->route('login')->withErrors([
            'documento' => 'Tu cuenta no tiene un rol válido para ingresar.',
        ]);
    }
}
