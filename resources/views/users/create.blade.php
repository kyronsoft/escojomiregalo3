@extends('layouts.admin.master')

@section('title', 'Nuevo Usuario')

@push('css')
    {{-- Select2 + tema Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.6.2/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />

    <style>
        /* Evita que se vea el <select> nativo cuando Select2 lo vuelve accesible */
        #nit.select2-hidden-accessible {
            display: none !important;
        }
    </style>
@endpush

@section('content')
    @php
        // Nombres EXACTOS tal como están en Spatie
        $roles = [
            'Admin' => 'Admin',
            'Ejecutiva-Empresas' => 'Ejecutiva Empresas',
            'RRHH-Cliente' => 'RRHH-Cliente',
            'Colaborador' => 'Colaborador',
        ];

        $oldRole = old('roles.0', 'Colaborador');
        $showNit = $oldRole === 'RRHH-Cliente';
    @endphp

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <h3 class="mb-0">Nuevo usuario</h3>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Hay errores en el formulario:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('users.store') }}" autocomplete="off">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="name" value="{{ old('name') }}"
                                class="form-control @error('name') is-invalid @enderror" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Correo (opcional)</label>
                            <input type="email" name="email" value="{{ old('email') }}"
                                class="form-control @error('email') is-invalid @enderror">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Documento</label>
                            <input type="text" name="documento" value="{{ old('documento') }}"
                                class="form-control @error('documento') is-invalid @enderror" required>
                            @error('documento')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- name="roles[]" para que llegue como array y pase la validación --}}
                        <div class="col-md-6">
                            <label class="form-label">Rol</label>
                            <select id="role" name="roles[]" class="form-select @error('roles') is-invalid @enderror"
                                required>
                                <option value="" disabled {{ old('roles') ? '' : 'selected' }}>Seleccione un rol…
                                </option>
                                @foreach ($roles as $value => $label)
                                    <option value="{{ $value }}" {{ $oldRole === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('roles')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Empresa (NIT) — solo si el rol es RRHH-Cliente --}}
                        <div class="col-md-6" id="nit-wrapper" style="display: {{ $showNit ? 'block' : 'none' }};">
                            <label class="form-label">Empresa (NIT)</label>
                            <select id="nit" name="nit" class="form-select @error('nit') is-invalid @enderror"
                                style="width:100%">
                                {{-- Opción vacía para allowClear; SIN texto (evita “doble placeholder”) --}}
                                <option value=""></option>
                                @if (old('nit'))
                                    <option value="{{ old('nit') }}" selected>{{ old('nit') }}</option>
                                @endif
                            </select>
                            @error('nit')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Obligatorio para RRHH-Cliente.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Contraseña</label>
                            <input type="password" name="password"
                                class="form-control @error('password') is-invalid @enderror" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Confirmar contraseña</label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary me-2">Cancelar</a>
                        <button class="btn btn-primary" type="submit">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        (function() {
            const $role = $('#role');
            const $nit = $('#nit');
            const $nitBox = $('#nit-wrapper');

            // Inicializa Select2 para empresas
            $nit.select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Buscar empresa por NIT o nombre…', // <-- sólo aquí
                allowClear: true,
                ajax: {
                    url: '{{ route('empresas.select2') }}',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({
                        q: params.term || '',
                        page: params.page || 1,
                        per_page: 20,
                    }),
                    processResults: (data, params) => {
                        params.page = params.page || 1;
                        const results = (data && (data.results || data.items)) || [];
                        const more = !!(data && (data.pagination?.more || data.more));
                        return {
                            results,
                            pagination: {
                                more
                            }
                        };
                    }
                },
                templateResult: item => item.loading ? item.text : $('<div>').text(item.text),
                templateSelection: item => item.text || item.id
            });

            function toggleNit() {
                const selected = $role.val();
                if (selected === 'RRHH-Cliente') {
                    $nitBox.show();
                } else {
                    $nit.val(null).trigger('change');
                    $nitBox.hide();
                }
            }

            $role.on('change', toggleNit);
            toggleNit();
        })();
    </script>
@endpush
