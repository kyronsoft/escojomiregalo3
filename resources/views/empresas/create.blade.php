@extends('layouts.admin.master')

@section('title')
    {{ $title ?? 'Empresas' }}
@endsection

@push('css')
    {{-- Incluye esto solo si tu layout NO trae select2.css --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                {{-- Alertas generales de validación (lista) --}}
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

                @if (session('success'))
                    <div class="alert alert-success mb-3">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger mb-3">{{ session('error') }}</div>
                @endif

                <div class="row">
                    <div class="card">
                        <div class="card-header pb-0">
                            <h5>Empresas</h5>
                            <span>Cree empresas que ejecutarán campañas</span>
                        </div>

                        <div class="card-body">
                            <form id="form-empresas" class="theme-form mt-3" enctype="multipart/form-data" method="POST"
                                action="{{ route('empresas.store') }}">
                                @csrf

                                <div class="d-flex justify-content-end mb-3">
                                    <button class="btn btn-primary" type="submit">Guardar</button>
                                </div>

                                <div class="row">
                                    <div class="col-4 mb-3">
                                        <label class="col-form-label pe-2" for="nit">NIT</label>
                                        <input class="form-control @error('nit') is-invalid @enderror" id="nit"
                                            type="text" name="nit" placeholder="NIT" maxlength="10"
                                            value="{{ old('nit') }}" />
                                        @error('nit')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-8 mb-3">
                                        <label class="col-form-label pe-2" for="nombre">Nombre</label>
                                        <input class="form-control @error('nombre') is-invalid @enderror" id="nombre"
                                            type="text" name="nombre" placeholder="Nombre de la empresa" maxlength="50"
                                            value="{{ old('nombre') }}" />
                                        @error('nombre')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-3 mb-3">
                                        <label class="col-form-label" for="ciudad">Ciudad</label>
                                        <select id="ciudad" name="ciudad"
                                            class="form-control @error('ciudad') is-invalid @enderror"></select>
                                        @error('ciudad')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-9 mb-3">
                                        <label class="col-form-label pe-2" for="direccion">Dirección</label>
                                        <input class="form-control @error('direccion') is-invalid @enderror" id="direccion"
                                            type="text" name="direccion" placeholder="Dirección" maxlength="100"
                                            value="{{ old('direccion') }}" />
                                        @error('direccion')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Logo --}}
                                <div class="col-12 mb-3">
                                    <div class="col-3">
                                        <label class="col-form-label pe-2" for="logo">Logo</label>
                                    </div>
                                    <div class="col-9">
                                        <input class="form-control @error('logo') is-invalid @enderror" id="logo"
                                            type="file" name="logo" accept=".bmp, .jpg, .jpeg, .png" />
                                        @error('logo')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <div class="mt-2">
                                            <img id="preview_logo" src="" alt="Vista previa del logo"
                                                style="max-width:200px; display:none;" />
                                        </div>
                                    </div>
                                </div>

                                {{-- Banner --}}
                                <div class="col-12 mb-3">
                                    <div class="col-3">
                                        <label class="col-form-label pe-2" for="banner">Banner</label>
                                    </div>
                                    <div class="col-9">
                                        <input class="form-control @error('banner') is-invalid @enderror" id="banner"
                                            type="file" name="banner" accept=".bmp, .jpg, .jpeg, .png" />
                                        @error('banner')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <div class="mt-2">
                                            <img id="preview_banner" src="" alt="Vista previa del banner"
                                                style="max-width:200px; display:none;" />
                                        </div>
                                    </div>
                                </div>

                                {{-- Imagen login --}}
                                <div class="col-12 mb-3">
                                    <div class="col-3">
                                        <label class="col-form-label pe-2" for="imagen_login">Imagen Login</label>
                                    </div>
                                    <div class="col-9">
                                        <input class="form-control @error('imagen_login') is-invalid @enderror"
                                            id="imagen_login" type="file" name="imagen_login"
                                            accept=".bmp, .jpg, .jpeg, .png" />
                                        @error('imagen_login')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <div class="mt-2">
                                            <img id="preview_login" src=""
                                                alt="Vista previa de la imagen de login"
                                                style="max-width:200px; display:none;" />
                                        </div>
                                    </div>
                                </div>

                                {{-- Colores --}}
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <div class="col-3">
                                            <label class="col-form-label pe-2" for="color_primario">Color Primario</label>
                                        </div>
                                        <div class="col-9">
                                            <input class="form-control @error('color_primario') is-invalid @enderror"
                                                id="color_primario" type="color" name="color_primario"
                                                value="{{ old('color_primario') }}" />
                                            @error('color_primario')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-6 mb-3">
                                        <div class="col-4">
                                            <label class="col-form-label pe-2" for="color_secundario">Color
                                                Secundario</label>
                                        </div>
                                        <div class="col-8">
                                            <input class="form-control @error('color_secundario') is-invalid @enderror"
                                                id="color_secundario" type="color" name="color_secundario"
                                                value="{{ old('color_secundario') }}" />
                                            @error('color_secundario')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <div class="col-3">
                                            <label class="col-form-label pe-2" for="color_terciario">Color
                                                Terciario</label>
                                        </div>
                                        <div class="col-9">
                                            <input class="form-control @error('color_terciario') is-invalid @enderror"
                                                id="color_terciario" type="color" name="color_terciario"
                                                value="{{ old('color_terciario') }}" />
                                            @error('color_terciario')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                {{-- Mensaje bienvenida --}}
                                <div class="col-12 mb-3">
                                    <label class="col-form-label" for="welcome_msg">Mensaje de Bienvenida</label>
                                    <textarea class="form-control @error('welcome_msg') is-invalid @enderror" id="welcome_msg" name="welcome_msg"
                                        rows="3" placeholder="Mensaje de bienvenida">{{ old('welcome_msg') }}</textarea>
                                    @error('welcome_msg')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                            </form>
                        </div> {{-- card-body --}}
                    </div> {{-- card --}}
                </div> {{-- row --}}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- jQuery --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    {{-- BlockUI --}}
    <script src="{{ asset('assets/js/blockui/jquery.blockUI.js') }}"></script>

    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- Select2 (inclúyelo aquí solo si tu layout no lo trae ya) --}}
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // Preview imágenes
        ['logo', 'banner', 'imagen_login'].forEach(function(id) {
            const input = document.getElementById(id);
            const preview = document.getElementById('preview_' + (id === 'imagen_login' ? 'login' : id));
            if (!input) return;

            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file && file.type.match('image.*')) {
                    const reader = new FileReader();
                    reader.onload = function(evt) {
                        preview.src = evt.target.result;
                        preview.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                } else {
                    preview.src = '';
                    preview.style.display = 'none';
                }
            });
        });

        // BlockUI al enviar el formulario
        $('#form-empresas').on('submit', function() {
            $.blockUI({
                message: '<div class="p-3"><div class="spinner-border" role="status"></div><div class="mt-2">Guardando, por favor espera...</div></div>',
                css: {
                    border: 'none',
                    padding: '15px',
                    backgroundColor: '#000',
                    opacity: 0.6,
                    color: '#fff',
                    'border-radius': '8px'
                },
                baseZ: 2000
            });
        });

        // Select2 CIUDAD — evita doble inicialización
        (function initCiudadSelect2() {
            const $el = $('#ciudad');

            // Si ya está inicializado por otro script, destrúyelo primero
            if ($el.hasClass('select2-hidden-accessible')) {
                $el.select2('destroy');
            }

            $el.select2({
                placeholder: 'Seleccione ciudad',
                allowClear: true,
                width: '100%',
                ajax: {
                    url: '{{ route('api.ciudades') }}',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({
                        q: params.term || '',
                        page: params.page || 1,
                        per_page: 20,
                    }),
                    processResults: (data, params) => {
                        params.page = params.page || 1;
                        return {
                            results: data.results || [],
                            pagination: {
                                more: !!(data.pagination && data.pagination.more)
                            }
                        };
                    },
                },
            });

            // Preselección si vuelves con old()
            @if (old('ciudad'))
                const preId = @json(old('ciudad'));
                const preText = @json(old('ciudad_text', null));
                const opt = new Option(preText || ('Ciudad ' + preId), preId, true, true);
                $el.append(opt).trigger('change');
            @endif
        })();

        // SweetAlert2 para flash
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
        @if ($errors->any())
            Swal.fire({
                icon: 'error',
                title: 'Errores de validación',
                html: `{!! implode('<br>', $errors->all()) !!}`
            });
        @endif
    </script>
@endpush
