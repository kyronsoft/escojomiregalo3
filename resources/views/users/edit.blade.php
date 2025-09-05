@extends('layouts.admin.master')

@section('title', 'Editar Usuario')

@push('css')
    {{-- Select2 + tema Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.6.2/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
    <style>
        /* Asegura que el select nativo permanezca oculto cuando Select2 lo manipula */
        #nit.select2-hidden-accessible {
            display: none !important;
        }
    </style>
@endpush

@section('content')
    @php
        // Nombres EXACTOS de roles (Spatie)
        $roles = $roles ?? [
            'Admin' => 'Admin',
            'Ejecutiva-Empresas' => 'Ejecutiva Empresas',
            'RRHH-Cliente' => 'RRHH-Cliente',
            'Colaborador' => 'Colaborador',
        ];

        $currentRole = $user->getRoleNames()->first(); // primer rol del usuario
        $selectedRole = old('roles.0', $currentRole); // rol seleccionado (old() o actual)
        $currentNit = old('nit', $user->nit); // nit actual/old
        $empresaTexto = $empresaTexto ?? null; // "860017055 — Empresa", si el controlador lo envía
        $showNit = $selectedRole === 'RRHH-Cliente'; // mostrar empresa solo para RRHH-Cliente
    @endphp

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <h3 class="mb-0">Editar usuario</h3>
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
                <form method="POST" action="{{ route('users.update', $user) }}" autocomplete="off">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="name" value="{{ old('name', $user->name) }}"
                                class="form-control @error('name') is-invalid @enderror" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Correo (opcional)</label>
                            <input type="email" name="email" value="{{ old('email', $user->email) }}"
                                class="form-control @error('email') is-invalid @enderror">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Documento</label>
                            <input type="text" name="documento" value="{{ old('documento', $user->documento) }}"
                                class="form-control @error('documento') is-invalid @enderror" required>
                            @error('documento')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- name="roles[]" (array) para compatibilidad con el controlador --}}
                        <div class="col-md-6">
                            <label class="form-label">Rol</label>
                            <select id="role" name="roles[]" class="form-select @error('roles') is-invalid @enderror"
                                required>
                                <option value="" disabled>Seleccione un rol…</option>
                                @foreach ($roles as $value => $label)
                                    <option value="{{ $value }}" {{ $selectedRole === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('roles')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Empresa (NIT) — visible solo si el rol es RRHH-Cliente --}}
                        <div class="col-md-6" id="nit-wrapper" style="display: {{ $showNit ? 'block' : 'none' }};">
                            <label class="form-label">Empresa (NIT)</label>
                            <select id="nit" name="nit" class="form-select @error('nit') is-invalid @enderror"
                                style="width:100%">
                                {{-- opción vacía para allowClear; SIN texto (placeholder solo en JS) --}}
                                <option value=""></option>

                                @if ($currentNit && $empresaTexto)
                                    <option value="{{ $currentNit }}" selected>{{ $empresaTexto }}</option>
                                @elseif ($currentNit)
                                    {{-- si no tenemos texto, se precarga por AJAX luego --}}
                                    <option value="{{ $currentNit }}" selected>{{ $currentNit }}</option>
                                @endif
                            </select>
                            @error('nit')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Obligatorio para RRHH-Cliente.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Nueva contraseña (opcional)</label>
                            <input type="password" name="password"
                                class="form-control @error('password') is-invalid @enderror">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Déjalo vacío para mantener la actual.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Confirmar nueva contraseña</label>
                            <input type="password" name="password_confirmation" class="form-control">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary me-2">Cancelar</a>
                        <button class="btn btn-primary" type="submit">Actualizar</button>
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

            function destroyNitSelect2IfAny() {
                // Si ya está inicializado, destrúyelo para evitar contenedores duplicados
                if ($nit.data('select2')) {
                    $nit.select2('destroy');
                    // por si quedó algún container “colgado”
                    const $next = $nit.next('.select2-container');
                    if ($next.length) $next.remove();
                }
            }

            function initNitSelect2() {
                destroyNitSelect2IfAny();
                $nit.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: 'Buscar empresa por NIT o nombre…',
                    allowClear: true,
                    dropdownParent: $nitBox, // ayuda en modales/contenedores
                    ajax: {
                        url: '{{ route('empresas.select2') }}', // Debe devolver {results:[{id,text}], pagination:{more}}
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
                        },
                    },
                    templateResult: item => item.loading ? item.text : $('<div>').text(item.text),
                    templateSelection: item => item.text || item.id,
                });
            }

            function toggleNit() {
                const val = $role.val();
                if (val === 'RRHH-Cliente') {
                    $nitBox.show();
                    if (!$nit.data('select2')) {
                        initNitSelect2();
                    }
                } else {
                    // Limpia y destruye para que si cambian varias veces de rol no se duplique
                    $nit.val(null).trigger('change');
                    destroyNitSelect2IfAny();
                    $nitBox.hide();
                }
            }

            // Inicial: prepara Select2 sólo si corresponde
            toggleNit();

            // Cambio de rol
            $role.on('change', toggleNit);

            // Precarga del texto de NIT si solo tenemos el ID (y no tenemos texto amigable)
            (function preloadNitTextIfNeeded() {
                const selectedId = $nit.val();
                const selectedText = $nit.find('option:selected').text();
                if (!selectedId) return;

                // Si la opción seleccionada ya tiene texto diferente al ID, no hace falta buscar
                if (selectedText && selectedText !== selectedId) return;

                // Asegura que Select2 esté listo si el rol es RRHH-Cliente
                if ($role.val() === 'RRHH-Cliente' && !$nit.data('select2')) {
                    initNitSelect2();
                }

                $.get('{{ route('empresas.select2') }}', {
                        q: selectedId,
                        page: 1,
                        per_page: 1
                    })
                    .then(resp => {
                        const list = (resp && (resp.results || resp.items)) || [];
                        const match = list.find(x => String(x.id) === String(selectedId));
                        if (match) {
                            // Reemplaza la opción seleccionada con el texto correcto sin duplicar
                            $nit.find('option[value="' + selectedId + '"]').remove();
                            const opt = new Option(match.text, match.id, true, true);
                            $nit.append(opt).trigger('change');
                        }
                    });
            })();
        })();
    </script>
@endpush
