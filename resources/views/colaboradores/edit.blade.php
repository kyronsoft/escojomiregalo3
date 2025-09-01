@extends('layouts.admin.master')

@section('title', 'Editar colaborador')

@push('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <strong>Hay errores en el formulario:</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header pb-0">
                        <h5>Editar colaborador</h5>
                    </div>
                    <div class="card-body">
                        <div class="col-12 d-flex justify-content-end">
                            <a href="{{ route('colaboradores.index') }}" class="btn btn-outline-secondary me-2">Cancelar</a>
                            <button class="btn btn-primary" type="submit">Guardar cambios</button>
                        </div>
                        <form id="form-colab" class="row g-3" method="POST"
                            action="{{ route('colaboradores.update', $colaborador) }}">
                            @csrf @method('PUT')

                            <div class="col-12 col-md-4">
                                <label class="form-label" for="documento">Documento</label>
                                <input type="text" class="form-control @error('documento') is-invalid @enderror"
                                    id="documento" name="documento" maxlength="15"
                                    value="{{ old('documento', $colaborador->documento) }}" required>
                                @error('documento')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-8">
                                <label class="form-label" for="nombre">Nombre</label>
                                <input type="text" class="form-control @error('nombre') is-invalid @enderror"
                                    id="nombre" name="nombre" maxlength="100"
                                    value="{{ old('nombre', $colaborador->nombre) }}" required>
                                @error('nombre')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror"
                                    id="email" name="email" maxlength="75"
                                    value="{{ old('email', $colaborador->email) }}">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="telefono">Teléfono</label>
                                <input type="text" class="form-control @error('telefono') is-invalid @enderror"
                                    id="telefono" name="telefono" maxlength="10"
                                    value="{{ old('telefono', $colaborador->telefono) }}">
                                @error('telefono')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="direccion">Dirección</label>
                                <input type="text" class="form-control @error('direccion') is-invalid @enderror"
                                    id="direccion" name="direccion" maxlength="255"
                                    value="{{ old('direccion', $colaborador->direccion) }}">
                                @error('direccion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="barrio">Barrio</label>
                                <input type="text" class="form-control @error('barrio') is-invalid @enderror"
                                    id="barrio" name="barrio" maxlength="100"
                                    value="{{ old('barrio', $colaborador->barrio) }}">
                                @error('barrio')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="ciudad">Ciudad</label>
                                <select id="ciudad" name="ciudad"
                                    class="form-control @error('ciudad') is-invalid @enderror">
                                    @php
                                        $selectedId = old('ciudad', $colaborador->ciudad);
                                        $selectedText = old('ciudad_text', $ciudadTexto ?? null);
                                    @endphp
                                    @if ($selectedId && $selectedText)
                                        <option value="{{ $selectedId }}" selected>{{ $selectedText }}</option>
                                    @endif
                                </select>
                                @error('ciudad')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="nit">NIT</label>
                                <input type="text" class="form-control @error('nit') is-invalid @enderror" id="nit"
                                    name="nit" maxlength="10" value="{{ old('nit', $colaborador->nit) }}" required>
                                @error('nit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="observaciones">Observaciones</label>
                                <input type="text" class="form-control @error('observaciones') is-invalid @enderror"
                                    id="observaciones" name="observaciones" maxlength="255"
                                    value="{{ old('observaciones', $colaborador->observaciones) }}">
                                @error('observaciones')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="sucursal">Sucursal</label>
                                <input type="text" class="form-control @error('sucursal') is-invalid @enderror"
                                    id="sucursal" name="sucursal" maxlength="100"
                                    value="{{ old('sucursal', $colaborador->sucursal) }}">
                                @error('sucursal')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label d-block">Flags</label>
                                <div class="d-flex gap-3 flex-wrap">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="enviado" name="enviado"
                                            value="1" {{ old('enviado', $colaborador->enviado) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="enviado">Correo Enviado</label>
                                    </div>
                                    <div>
                                        <label class="form-label me-2">Política datos</label>
                                        @php $pd = old('politicadatos', $colaborador->politicadatos); @endphp
                                        <select name="politicadatos"
                                            class="form-select form-select-sm d-inline-block w-auto">
                                            <option value="N" @selected($pd === 'N')>N</option>
                                            <option value="S" @selected($pd === 'S')>S</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label me-2">Update datos</label>
                                        @php $ud = old('updatedatos', $colaborador->updatedatos); @endphp
                                        <select name="updatedatos"
                                            class="form-select form-select-sm d-inline-block w-auto">
                                            <option value="N" @selected($ud === 'N')>N</option>
                                            <option value="S" @selected($ud === 'S')>S</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label me-2">Welcome</label>
                                        @php $w = old('welcome', $colaborador->welcome); @endphp
                                        <select name="welcome" class="form-select form-select-sm d-inline-block w-auto">
                                            <option value="N" @selected($w === 'N')>N</option>
                                            <option value="S" @selected($w === 'S')>S</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('assets/js/blockui/jquery.blockUI.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        const $ciudad = $('#ciudad');

        $ciudad.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Seleccione ciudad',
            allowClear: true,
            ajax: {
                url: '{{ route('api.ciudades') }}',
                dataType: 'json',
                delay: 250,
                data: params => ({
                    q: params.term || '',
                    page: params.page || 1,
                    per_page: 20
                }),
                processResults: (data, params) => ({
                    results: data.results,
                    pagination: {
                        more: data.pagination.more
                    }
                })
            }
        });

        // Preselección segura:
        (function() {
            const selectedId = @json(old('ciudad', $colaborador->ciudad));
            const selectedText = @json(old('ciudad_text', $ciudadTexto ?? null));

            if (selectedId && selectedText) {
                const opt = new Option(selectedText, selectedId, true, true);
                $ciudad.append(opt).trigger('change');
            } else if (selectedId) {
                // Fallback AJAX para resolver el nombre a partir del id
                $.get('{{ route('api.ciudades') }}', {
                    q: selectedId,
                    page: 1,
                    per_page: 1
                }).then(resp => {
                    const match = (resp.results || []).find(r => String(r.id) === String(selectedId));
                    const text = match ? match.text : selectedId;
                    const opt = new Option(text, selectedId, true, true);
                    $ciudad.append(opt).trigger('change');
                });
            }
        })();

        // BlockUI al enviar
        $('#form-colab').on('submit', function() {
            $.blockUI({
                message: '<div class="p-3"><div class="spinner-border" role="status"></div><div class="mt-2">Guardando...</div></div>',
                css: {
                    border: 'none',
                    padding: '15px',
                    background: '#000',
                    opacity: 0.6,
                    color: '#fff',
                    borderRadius: '8px'
                },
                baseZ: 2000
            });
        });

        // SweetAlerts por flash
        @if (session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: @json(session('success'))
            });
        @endif
        @if (session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: @json(session('error'))
            });
        @endif
    </script>
@endpush
