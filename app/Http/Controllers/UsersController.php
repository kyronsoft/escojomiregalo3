<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:Admin']);
    }

    /** Mapa de roles slug => etiqueta legible (opcional) */
    private function rolesMap(): array
    {
        return [
            'admin'              => 'Admin',
            'ejecutiva_empresas' => 'Ejecutiva Empresas',
            'business'           => 'RRHH-Cliente',
            'colaborador'        => 'Colaborador',
        ];
    }

    private function guard(): string
    {
        return config('auth.defaults.guard', 'web');
    }

    private function ensureRoleExists(string $name): void
    {
        Role::findOrCreate($name, $this->guard());
    }

    /** Vista principal (Tabulator) */
    public function index()
    {
        // La vista ya no necesita $users; Tabulator cargará por AJAX.
        return view('users.index');
    }

    /** Endpoint JSON para Tabulator */
    public function data(Request $request)
    {
        // Si tu dataset es grande, aquí podrías implementar filtros/orden y paginación remota.
        $users = User::with('roles')->latest()->get();

        $rows = $users->map(function (User $u) {
            return [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'documento'  => $u->documento,
                'nit'        => $u->nit,
                // Primer rol legible para la columna "Rol"
                'role_name'  => $u->getRoleNames()->first(),
                // ISO string para que el front pueda formatear
                'updated_at' => optional($u->updated_at)->toISOString(),
            ];
        });

        return response()->json($rows);
    }

    public function create()
    {
        $roles = [
            'Admin'               => 'Admin',
            'Ejecutiva-Empresas'  => 'Ejecutiva Empresas',
            'RRHH-Cliente'        => 'RRHH-Cliente',
            'Colaborador'         => 'Colaborador',
        ];
        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $roles = array_values((array) $request->input('roles', []));
        $requireNit = !empty(array_intersect($roles, ['RRHH-Cliente', 'Ejecutiva-Empresas']));

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'documento' => ['required', 'string', 'max:25', 'unique:users,documento'],
            'email'     => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'password'  => ['required', 'string', 'min:8'],
            'roles'     => ['required', 'array', 'min:1'],
            'roles.*'   => ['string', Rule::in(['Admin', 'Ejecutiva-Empresas', 'RRHH-Cliente', 'Colaborador'])],
            'nit'       => [
                Rule::requiredIf($requireNit),
                'nullable',
                'string',
                'max:20',
                'exists:empresas,nit',
            ],
        ]);

        $email = isset($data['email']) && trim((string)$data['email']) !== '' ? $data['email'] : null;
        $nit   = $requireNit ? ($data['nit'] ?? null) : null;

        $user = User::create([
            'name'      => $data['name'],
            'documento' => $data['documento'],
            'email'     => $email,
            'password'  => bcrypt($data['password']),
            'nit'       => $nit,
        ]);

        $user->syncRoles($roles);

        return redirect()->route('users.index')->with('success', 'Usuario creado');
    }

    public function edit(User $user)
    {
        $roles = [
            'Admin'               => 'Admin',
            'Ejecutiva-Empresas'  => 'Ejecutiva Empresas',
            'RRHH-Cliente'        => 'RRHH-Cliente',
            'Colaborador'         => 'Colaborador',
        ];

        $selectedRole = old('roles.0', $user->getRoleNames()->first());

        $empresaTexto = null;
        if ($user->nit) {
            $e = Empresa::select('nit', 'nombre')->where('nit', $user->nit)->first();
            if ($e) $empresaTexto = "{$e->nit} — {$e->nombre}";
        }

        return view('users.edit', compact('user', 'roles', 'selectedRole', 'empresaTexto'));
    }

    public function update(Request $request, User $user)
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
                'max:20',
                'exists:empresas,nit',
                Rule::requiredIf($requireNit),
            ],
        ]);

        $user->fill([
            'name'      => $data['name'],
            'documento' => $data['documento'],
            'email'     => $data['email'] ?? null,
            'nit'       => $requireNit ? ($data['nit'] ?? null) : null,
        ]);

        if (!empty($data['password'])) {
            $user->password = bcrypt($data['password']);
        }

        $user->save();
        $user->syncRoles($data['roles']);

        return redirect()->route('users.index')->with('success', 'Usuario actualizado');
    }

    public function destroy(Request $request, User $user)
    {
        if (auth()->id() === $user->id) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No puedes eliminar tu propio usuario.'], 422);
            }
            return back()->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $user->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Usuario eliminado.']);
        }

        return back()->with('success', 'Usuario eliminado.');
    }
}
