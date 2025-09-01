@extends('layouts.admin.master')

@section('title', 'Nuevo colaborador')

@push('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    {{-- (opcional) tema Bootstrap 5 --}}
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
                        <h5>Nuevo colaborador</h5>
                    </div>
                    <div class="card-body">
                        <form id="form-colab" class="row g-3" method="POST" action="{{ route('colaboradores.store') }}">
                            @csrf

                            <div class="col-12 col-md-4">
                                <label class="form-label" for="documento">Documento</label>
                                <input type="text" class="form-control @error('documento') is-invalid @enderror"
                                    id="documento" name="documento" maxlength="15" value="{{ old('documento') }}" required>
                                @error('documento')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-8">
                                <label class="form-label" for="nombre">Nombre</label>
                                <input type="text" class="form-control @error('nombre') is-invalid @enderror"
                                    id="nombre" name="nombre" maxlength="100" value="{{ old('nombre') }}" required>
                                @error('nombre')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror"
                                    id="email" name="email" maxlength="75" value="{{ old('email') }}">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="telefono">Teléfono</label>
                                <input type="text" class="form-control @error('telefono') is-invalid @enderror"
                                    id="telefono" name="telefono" maxlength="10" value="{{ old('telefono') }}">
                                @error('telefono')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="direccion">Dirección</label>
                                <input type="text" class="form-control @error('direccion') is-invalid @enderror"
                                    id="direccion" name="direccion" maxlength="255" value="{{ old('direccion') }}">
                                @error('direccion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="barrio">Barrio</label>
                                <input type="text" class="form-control @error('barrio') is-invalid @enderror"
                                    id="barrio" name="barrio" maxlength="100" value="{{ old('barrio') }}">
                                @error('barrio')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="ciudad">Ciudad</label>
                                <select id="ciudad" name="ciudad"
                                    class="form-control @error('ciudad') is-invalid @enderror"></select>
                                @error('ciudad')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="nit">NIT</label>
                                <input type="text" class="form-control @error('nit') is-invalid @enderror" id="nit"
                                    name="nit" maxlength="10" value="{{ old('nit') }}" required>
                                @error('nit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="observaciones">Observaciones</label>
                                <input type="text" class="form-control @error('observaciones') is-invalid @enderror"
                                    id="observaciones" name="observaciones" maxlength="255"
                                    value="{{ old('observaciones') }}">
                                @error('observaciones')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="sucursal">Sucursal</label>
                                <input type="text" class="form-control @error('sucursal') is-invalid @enderror"
                                    id="sucursal" name="sucursal" maxlength="100" value="{{ old('sucursal') }}">
                                @error('sucursal')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label d-block">Flags</label>
                                <div class="d-flex gap-3 flex-wrap">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="enviado" name="enviado"
                                            value="1" {{ old('enviado') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="enviado">Correo Enviado</label>
                                    </div>
                                    <div>
                                        <label class="form-label me-2">Política datos</label>
                                        <select name="politicadatos"
                                            class="form-select form-select-sm d-inline-block w-auto">
                                            <option value="N" @selected(old('politicadatos', 'N') === 'N')>N</option>
                                            <option value="S" @selected(old('politicadatos') === 'S')>S</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label me-2">Update datos</label>
                                        <select name="updatedatos"
                                            class="form-select form-select-sm d-inline-block w-auto">
                                            <option value="N" @selected(old('updatedatos', 'N') === 'N')>N</option>
                                            <option value="S" @selected(old('updatedatos') === 'S')>S</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label me-2">Welcome</label>
                                        <select name="welcome" class="form-select form-select-sm d-inline-block w-auto">
                                            <option value="N" @selected(old('welcome', 'N') === 'N')>N</option>
                                            <option value="S" @selected(old('welcome') === 'S')>S</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 d-flex justify-content-end">
                                <a href="{{ route('colaboradores.index') }}"
                                    class="btn btn-outline-secondary me-2">Cancelar</a>
                                <button class="btn btn-primary" type="submit">Guardar</button>
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
        // Init Select2 ciudad (AJAX)
        $('#ciudad').select2({
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
                }),
            }
        });

        // old('ciudad') (si hubo validación fallida)
        @if (old('ciudad'))
            (function() {
                const id = @json(old('ciudad'));
                const option = new Option(id, id, true, true); // si no tienes texto, muestra el id
                $('#ciudad').append(option).trigger('change');
                // Si quieres resolver el texto real:
                $.get('{{ route('api.ciudades') }}', {
                    q: id,
                    page: 1,
                    per_page: 1
                }).then(resp => {
                    const match = (resp.results || []).find(r => String(r.id) === String(id));
                    if (match) {
                        const opt = new Option(match.text, match.id, true, true);
                        $('#ciudad').empty().append(opt).trigger('change');
                    }
                });
            })();
        @endif

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
