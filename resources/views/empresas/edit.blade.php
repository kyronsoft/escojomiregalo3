@extends('layouts.admin.master')

@section('title')
    Editar Empresa
    {{ $title ?? '' }}
@endsection

@push('css')
    {{-- Select2 CSS + theme Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.6.2/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
@endpush

@section('content')
    @php
        use Illuminate\Support\Str;

        $nit = $empresa->nit;
        $bust = optional($empresa->updated_at)->timestamp ?? time();
        $empBase = url('storage/empresas'); // /storage/empresas
        $storageFn = fn($path) => asset('storage/' . ltrim($path, '/')); // asset('storage/...')

        // Normaliza una ruta de imagen: usa absoluta si ya viene; si es relativa "empresas/..." usa asset('storage/...');
        // si viene solo el nombre del archivo, lo arma como /storage/empresas/{nit}/{archivo}; si no viene, usa fallback.
        $normImg = function ($val, string $fallback) use ($nit, $bust, $empBase, $storageFn) {
            $val = trim((string) $val);
            if ($val !== '' && Str::startsWith($val, ['http://', 'https://', '//'])) {
                return $val . '?v=' . $bust;
            }
            if ($val !== '' && Str::startsWith($val, 'empresas/')) {
                return $storageFn($val) . '?v=' . $bust;
            }
            $file = $val !== '' ? ltrim($val, '/') : $fallback;
            return "{$empBase}/" . rawurlencode($nit) . "/{$file}?v={$bust}";
        };

        $placeholder = asset('assets/images/placeholder.png');

        $logoUrl = $normImg($empresa->logo, 'logo.png');
        $bannerUrl = $normImg($empresa->banner, 'banner.jpeg'); // ajusta si usas .jpg/.png
        $imagenLoginUrl = $normImg($empresa->imagen_login, 'imagen_login.jpg');
    @endphp

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

                @if (session('success'))
                    <div class="alert alert-success mb-3">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger mb-3">{{ session('error') }}</div>
                @endif

                <div class="row">
                    <div class="card">
                        <div class="card-header pb-0">
                            <h5>Editar Empresa</h5>
                            <span>Actualiza los datos de la empresa y guarda los cambios.</span>
                        </div>

                        <div class="col-12 mb-3 d-flex justify-content-end">
                            <a href="{{ route('empresas.index') }}" class="btn btn-outline-secondary me-2">Cancelar</a>
                            <button class="btn btn-primary" type="submit" form="form-empresas">Guardar cambios</button>
                        </div>

                        <div class="card-body">
                            <form id="form-empresas" class="theme-form mt-3" enctype="multipart/form-data" method="POST"
                                action="{{ route('empresas.update', $empresa->nit) }}">
                                @csrf
                                @method('PUT')

                                {{-- Fila: NIT y Nombre --}}
                                <div class="row">
                                    <div class="col-12 col-md-4 mb-3">
                                        <label class="col-form-label" for="nit">NIT</label>
                                        <input class="form-control @error('nit') is-invalid @enderror" id="nit"
                                            type="text" name="nit" maxlength="10"
                                            value="{{ old('nit', $empresa->nit) }}" readonly />
                                        @error('nit')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="text-muted">El NIT es la clave primaria y no puede cambiarse.</small>
                                    </div>

                                    <div class="col-12 col-md-8 mb-3">
                                        <label class="col-form-label" for="nombre">Nombre</label>
                                        <input class="form-control @error('nombre') is-invalid @enderror" id="nombre"
                                            type="text" name="nombre" maxlength="50"
                                            value="{{ old('nombre', $empresa->nombre) }}"
                                            placeholder="Nombre de la empresa" />
                                        @error('nombre')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Fila: Ciudad y Dirección --}}
                                <div class="row">
                                    <div class="col-12 col-md-3 mb-3">
                                        <label class="col-form-label" for="ciudad">Ciudad</label>
                                        @php
                                            $selectedId = old('ciudad', $empresa->ciudad);
                                            $selectedText = old('ciudad_text', $ciudadTexto ?? null);
                                        @endphp

                                        <select id="ciudad" name="ciudad"
                                            class="form-select @error('ciudad') is-invalid @enderror"
                                            data-placeholder="Buscar ciudad..." style="width:100%">
                                            @if ($selectedId && $selectedText)
                                                <option value="{{ $selectedId }}" selected>{{ $selectedText }}</option>
                                            @endif
                                        </select>
                                        <input type="hidden" name="ciudad_text" id="ciudad_text"
                                            value="{{ $selectedText ?? '' }}">
                                        @error('ciudad')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12 col-md-9 mb-3">
                                        <label class="col-form-label" for="direccion">Dirección</label>
                                        <input class="form-control @error('direccion') is-invalid @enderror" id="direccion"
                                            type="text" name="direccion" maxlength="100"
                                            value="{{ old('direccion', $empresa->direccion) }}" placeholder="Dirección" />
                                        @error('direccion')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Archivos (Logo, Banner, Imagen Login) --}}
                                <div class="row">
                                    {{-- Logo --}}
                                    <div class="col-12 mb-3">
                                        <div class="row">
                                            <div class="col-12 col-md-3">
                                                <label class="col-form-label" for="logo">Logo</label>
                                            </div>
                                            <div class="col-12 col-md-9">
                                                <input class="form-control @error('logo') is-invalid @enderror"
                                                    id="logo" type="file" name="logo"
                                                    accept=".bmp,.jpg,.jpeg,.png" />
                                                @error('logo')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                                <div class="mt-2">
                                                    <div class="mb-2">
                                                        <small class="text-muted d-block">Actual:</small>
                                                        <img src="{{ $empresa->logo }}" alt="Logo actual"
                                                            style="max-width:200px; display:block;"
                                                            onerror="this.onerror=null;this.src='{{ $placeholder }}'">
                                                    </div>
                                                    <img id="preview_logo" src="" alt="Vista previa del logo"
                                                        style="max-width:200px; display:none;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Banner --}}
                                    <div class="col-12 mb-3">
                                        <div class="row">
                                            <div class="col-12 col-md-3">
                                                <label class="col-form-label" for="banner">Banner</label>
                                            </div>
                                            <div class="col-12 col-md-9">
                                                <input class="form-control @error('banner') is-invalid @enderror"
                                                    id="banner" type="file" name="banner"
                                                    accept=".bmp,.jpg,.jpeg,.png" />
                                                @error('banner')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                                <div class="mt-2">
                                                    <div class="mb-2">
                                                        <small class="text-muted d-block">Actual:</small>
                                                        <img src="{{ $empresa->banner }}" alt="Banner actual"
                                                            style="max-width:200px; display:block;"
                                                            onerror="this.onerror=null;this.src='{{ $placeholder }}'">
                                                    </div>
                                                    <img id="preview_banner" src="" alt="Vista previa del banner"
                                                        style="max-width:200px; display:none;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Imagen Login --}}
                                    <div class="col-12 mb-3">
                                        <div class="row">
                                            <div class="col-12 col-md-3">
                                                <label class="col-form-label" for="imagen_login">Imagen Login</label>
                                            </div>
                                            <div class="col-12 col-md-9">
                                                <input class="form-control @error('imagen_login') is-invalid @enderror"
                                                    id="imagen_login" type="file" name="imagen_login"
                                                    accept=".bmp,.jpg,.jpeg,.png" />
                                                @error('imagen_login')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                                <div class="mt-2">
                                                    <div class="mb-2">
                                                        <small class="text-muted d-block">Actual:</small>
                                                        <img src="{{ $empresa->imagen_login }}" alt="Imagen login actual"
                                                            style="max-width:200px; display:block;"
                                                            onerror="this.onerror=null;this.src='{{ $placeholder }}'">
                                                    </div>
                                                    <img id="preview_login" src=""
                                                        alt="Vista previa de la imagen de login"
                                                        style="max-width:200px; display:none;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Colores y Código Vendedor --}}
                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <div class="row">
                                            <div class="col-12 col-md-3">
                                                <label class="col-form-label" for="color_primario">Color Primario</label>
                                            </div>
                                            <div class="col-12 col-md-9">
                                                <input class="form-control @error('color_primario') is-invalid @enderror"
                                                    id="color_primario" type="color" name="color_primario"
                                                    value="{{ old('color_primario', $empresa->color_primario) }}" />
                                                @error('color_primario')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6 mb-3">
                                        <div class="row">
                                            <div class="col-12 col-md-4">
                                                <label class="col-form-label" for="color_secundario">Color
                                                    Secundario</label>
                                            </div>
                                            <div class="col-12 col-md-8">
                                                <input class="form-control @error('color_secundario') is-invalid @enderror"
                                                    id="color_secundario" type="color" name="color_secundario"
                                                    value="{{ old('color_secundario', $empresa->color_secundario) }}" />
                                                @error('color_secundario')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <div class="row">
                                            <div class="col-12 col-md-3">
                                                <label class="col-form-label" for="color_terciario">Color
                                                    Terciario</label>
                                            </div>
                                            <div class="col-12 col-md-9">
                                                <input class="form-control @error('color_terciario') is-invalid @enderror"
                                                    id="color_terciario" type="color" name="color_terciario"
                                                    value="{{ old('color_terciario', $empresa->color_terciario) }}" />
                                                @error('color_terciario')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6 mb-3">
                                        <div class="row">
                                            <div class="col-12 col-md-4">
                                                <label class="col-form-label" for="codigoVendedor">Código Vendedor</label>
                                            </div>
                                            <div class="col-12 col-md-8">
                                                <input class="form-control @error('codigoVendedor') is-invalid @enderror"
                                                    id="codigoVendedor" type="text" name="codigoVendedor"
                                                    maxlength="10"
                                                    value="{{ old('codigoVendedor', $empresa->codigoVendedor) }}"
                                                    placeholder="Código vendedor" />
                                                @error('codigoVendedor')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Mensaje de bienvenida --}}
                                <div class="col-12 mb-3">
                                    <label class="col-form-label" for="welcome_msg">Mensaje de Bienvenida</label>
                                    <textarea class="form-control @error('welcome_msg') is-invalid @enderror" id="welcome_msg" name="welcome_msg"
                                        rows="3" placeholder="Mensaje de bienvenida">{{ old('welcome_msg', $empresa->welcome_msg) }}</textarea>
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
    <script src="{{ asset('assets/js/bootstrap/popper.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap/bootstrap.min.js') }}"></script>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('assets/js/blockui/jquery.blockUI.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Previsualización de imágenes
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

        // BlockUI al enviar
        $('#form-empresas').on('submit', function() {
            $.blockUI({
                message: '<div class="p-3"><div class="spinner-border" role="status"></div><div class="mt-2">Guardando, por favor espera...</div></div>',
                css: {
                    border: 'none',
                    padding: '15px',
                    backgroundColor: '#000',
                    opacity: 0.6,
                    color: '#fff',
                    borderRadius: '8px'
                },
                baseZ: 2000
            });
        });

        @if (session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: @json(session('success')),
                confirmButtonText: 'Aceptar'
            });
        @endif
        @if (session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: @json(session('error')),
                confirmButtonText: 'Aceptar'
            });
        @endif

        // ----- Select2 para Ciudad -----
        const $ciudad = $('#ciudad');
        const $ciudadText = $('#ciudad_text');

        $ciudad.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: $ciudad.data('placeholder') || 'Buscar ciudad...',
            allowClear: true,
            minimumInputLength: 1,
            ajax: {
                url: '{{ route('api.ciudades') }}',
                dataType: 'json',
                delay: 250,
                data: params => ({
                    q: params.term || '',
                    page: params.page || 1,
                    per_page: 20
                }),
                processResults: (data, params) => {
                    params.page = params.page || 1;
                    const results = (data && (data.results || data.items)) || [];
                    const more = (data && (data.more || (data.pagination && data.pagination.more))) || false;
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

        $ciudad.on('select2:select', function(e) {
            $ciudadText.val(e.params.data.text || '');
        }).on('select2:clear', function() {
            $ciudadText.val('');
        });

        (function preloadCiudad() {
            const selectedId = @json(old('ciudad', $empresa->ciudad));
            const selectedText = @json(old('ciudad_text', $ciudadTexto ?? null));
            if (selectedId && selectedText) {
                const opt = new Option(selectedText, selectedId, true, true);
                $ciudad.append(opt).trigger('change');
                $ciudadText.val(selectedText);
            } else if (selectedId) {
                $.get('{{ route('api.ciudades') }}', {
                        q: selectedId,
                        page: 1,
                        per_page: 1
                    })
                    .then(resp => {
                        const list = (resp && (resp.results || resp.items)) || [];
                        const match = list.find(r => String(r.id) === String(selectedId)) || null;
                        const text = match ? match.text : selectedId;
                        const opt = new Option(text, selectedId, true, true);
                        $ciudad.append(opt).trigger('change');
                        $ciudadText.val(text);
                    })
                    .catch(() => {
                        const opt = new Option(selectedId, selectedId, true, true);
                        $ciudad.append(opt).trigger('change');
                        $ciudadText.val(String(selectedId));
                    });
            }
        })();
    </script>
@endpush
