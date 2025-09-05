@extends('layouts.admin.master')

@section('title', 'Usuarios')

@push('css')
    <style>
        .badge-role {
            font-size: .85rem;
        }

        .table-users td,
        .table-users th {
            vertical-align: middle;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <h3 class="mb-0">Usuarios</h3>
            <a href="{{ route('users.create') }}" class="btn btn-primary">Nuevo usuario</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-users mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:80px;">ID</th>
                                <th>Nombre</th>
                                <th>Correo</th>
                                <th style="width:200px;">Rol</th>
                                <th style="width:180px;">Actualizado</th>
                                <th style="width:180px;" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $u)
                                @php
                                    $roleName = $u->getRoleNames()->first(); // uno solo
                                    $label = match ($roleName) {
                                        'admin' => 'Admin',
                                        'ejecutiva_empresas' => 'Ejecutiva Empresas',
                                        'business' => 'RRHH-Cliente',
                                        'colaborador' => 'Colaborador',
                                        default => $roleName,
                                    };
                                @endphp
                                <tr>
                                    <td>#{{ $u->id }}</td>
                                    <td>{{ $u->name }}</td>
                                    <td>{{ $u->email }}</td>
                                    <td>
                                        @if ($roleName)
                                            <span class="badge bg-primary badge-role">{{ $label }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ optional($u->updated_at)->format('Y-m-d H:i') }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('users.edit', $u) }}"
                                            class="btn btn-sm btn-outline-primary">Editar</a>
                                        <form action="{{ route('users.destroy', $u) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm('¿Eliminar este usuario?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No hay usuarios</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if (method_exists($users, 'links'))
                <div class="card-footer">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
