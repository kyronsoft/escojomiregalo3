<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{
    public function __construct()
    {
        // Ajusta los roles que pueden administrar usuarios según tu necesidad
        $this->middleware(['auth', 'role:Admin']);
    }

    /** Mapa de roles slug => etiqueta legible */
    private function rolesMap(): array
    {
        return [
            'admin'               => 'Admin',
            'ejecutiva_empresas'  => 'Ejecutiva Empresas',
            'business'            => 'RRHH-Cliente',
            'colaborador'         => 'Colaborador',
        ];
    }

    /** Guard que usa Spatie (normalmente 'web') */
    private function guard(): string
    {
        return config('auth.defaults.guard', 'web');
    }

    /** Asegura que el rol exista en BD (no crea duplicados) */
    private function ensureRoleExists(string $name): void
    {
        Role::findOrCreate($name, $this->guard());
    }

    public function index()
    {
        $users = User::with('roles')->latest()->paginate(15);
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = $this->rolesMap();
        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        // Roles marcados en el form (array de strings con los nombres EXACTOS de Spatie)
        $roles = array_values((array) $request->input('roles', []));
        $requireNit = in_array('RRHH-Cliente', $roles, true);

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'documento' => ['required', 'string', 'max:25', 'unique:users,documento'],
            'email'     => ['required', 'email', 'max:150', 'unique:users,email'],
            'password'  => ['required', 'string', 'min:8'],
            'roles'     => ['required', 'array', 'min:1'],
            'roles.*'   => ['string', Rule::in(['Admin', 'Ejecutiva-Empresas', 'RRHH-Cliente', 'Colaborador'])],

            // NIT solo es requerido si el rol incluye RRHH-Cliente
            'nit'       => [
                Rule::requiredIf($requireNit),
                'nullable',     // permitido null cuando NO es RRHH-Cliente
                'string',
                'max:20',       // por si tus NIT pueden ser más largos
                'exists:empresas,nit',
            ],
        ]);

        // Normaliza email vacío a null
        $email = $data['email'] ?? null;
        if (is_string($email) && trim($email) === '') {
            $email = null;
        }

        // Si NO es RRHH-Cliente, forzamos nit a null (aunque venga en el request)
        $nit = $requireNit ? ($data['nit'] ?? null) : null;

        $user = \App\Models\User::create([
            'name'      => $data['name'],
            'documento' => $data['documento'],
            'email'     => $email,
            'password'  => bcrypt($data['password']),
            'nit'       => $nit,
        ]);

        // Asignar roles tal cual (Spatie)
        $user->syncRoles($roles);

        return redirect()->route('users.index')->with('success', 'Usuario creado');
    }


    public function update(Request $request, \App\Models\User $user)
    {
        $roles = (array) $request->input('roles', $user->getRoleNames()->toArray());
        $requireNit = collect($roles)->contains(fn($r) => in_array($r, ['RRHH-Cliente', 'Ejecutiva-Empresas']));

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'documento' => ['required', 'string', 'max:25', Rule::unique('users', 'documento')->ignore($user->id)],
            'email'     => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'password'  => ['nullable', 'string', 'min:8'],
            'roles'     => ['required', 'array', 'min:1'],
            'roles.*'   => ['string', Rule::in(['Admin', 'Ejecutiva-Empresas', 'RRHH-Cliente', 'Colaborador'])],
            'nit'       => [
                'nullable',
                'string',
                'max:10',
                'exists:empresas,nit',
                Rule::requiredIf($requireNit),
            ],
        ]);

        $user->fill([
            'name'      => $data['name'],
            'documento' => $data['documento'],
            'email'     => $data['email'] ?? null,
            'nit'       => $data['nit'] ?? null,
        ]);

        if (!empty($data['password'])) {
            $user->password = bcrypt($data['password']);
        }

        $user->save();
        $user->syncRoles($data['roles']);

        return redirect()->route('users.index')->with('success', 'Usuario actualizado');
    }

    public function edit(User $user)
    {
        // Values EXACTOS según Spatie
        $roles = [
            'Admin'               => 'Admin',
            'Ejecutiva-Empresas'  => 'Ejecutiva Empresas',
            'RRHH-Cliente'        => 'RRHH-Cliente',
            'Colaborador'         => 'Colaborador',
        ];

        // Primer rol del usuario (o el que venga de old() tras un validation error)
        $selectedRole = old('roles.0', $user->getRoleNames()->first());

        // (Opcional) texto para precargar el Select2 de empresa
        $empresaTexto = null;
        if ($user->nit) {
            $e = Empresa::select('nit', 'nombre')->where('nit', $user->nit)->first();
            if ($e) {
                $empresaTexto = "{$e->nit} — {$e->nombre}";
            }
        }

        return view('users.edit', compact('user', 'roles', 'selectedRole', 'empresaTexto'));
    }

    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $user->delete();
        return back()->with('success', 'Usuario eliminado.');
    }
}
