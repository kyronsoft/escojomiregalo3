<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    // Esta propiedad ya no es relevante cuando usamos 'authenticated()'
    protected $redirectTo = '/dashboard';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    protected function authenticated(\Illuminate\Http\Request $request, $user)
    {
        return $user->hasRole('colaborador')
            ? redirect()->route('product')
            : redirect()->route('dashboard');
    }
}
