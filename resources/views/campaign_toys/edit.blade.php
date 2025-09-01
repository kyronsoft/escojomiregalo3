@extends('layouts.admin.master')

@section('title', 'Editar Juguete / Combo')

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

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <div class="card">
                    <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Editar Juguete / Combo</h5>
                            <small class="text-muted">ID: {{ $toy->id }}</small>
                        </div>
                        <a href="{{ route('campaign_toys.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
                    </div>
                    <div class="card-body">
                        <form id="form-toy" method="POST" action="{{ route('campaign_toys.update', $toy->id) }}"
                            enctype="multipart/form-data" class="row g-3">
                            @csrf
                            @method('PUT')

                            <div class="col-12 col-md-6">
                                <label class="form-label">Campaña</label>
                                <select id="idcampaign" name="idcampaign"
                                    class="form-control @error('idcampaign') is-invalid @enderror">
                                    <option value="{{ old('idcampaign', $toy->idcampaign) }}" selected>
                                        {{ old('idcampaign_text', $toy->campaign->nombre ?? 'ID ' . $toy->idcampaign) }}
                                    </option>
                                </select>
                                @error('idcampaign')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Combo</label>
                                <input type="text" name="combo" maxlength="3" value="{{ old('combo', $toy->combo) }}"
                                    class="form-control @error('combo') is-invalid @enderror">
                                @error('combo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Referencia</label>
                                <input type="text" name="referencia" maxlength="100"
                                    value="{{ old('referencia', $toy->referencia) }}"
                                    class="form-control @error('referencia') is-invalid @enderror">
                                @error('referencia')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">Nombre</label>
                                <input type="text" name="nombre" value="{{ old('nombre', $toy->nombre) }}"
                                    class="form-control @error('nombre') is-invalid @enderror">
                                @error('nombre')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Imagen principal</label>
                                <input type="file" id="imagenppal" name="imagenppal" accept=".bmp,.jpg,.jpeg,.png,.webp"
                                    class="form-control @error('imagenppal') is-invalid @enderror">
                                @error('imagenppal')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div class="mt-2">
                                    @if ($toy->imagenppal)
                                        <small class="text-muted d-block">Actual:</small>
                                        <img src="{{ asset('storage/' . $toy->imagenppal) }}" alt="Actual"
                                            style="max-width:220px;border-radius:8px;">
                                    @endif
                                    <img id="preview" src="" alt="Vista previa"
                                        style="max-width:220px; display:none; margin-top:8px;border-radius:8px;">
                                </div>
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Género</label>
                                <select name="genero" class="form-select @error('genero') is-invalid @enderror">
                                    @php $g = old('genero', $toy->genero); @endphp
                                    <option value="UNISEX" @selected($g === 'UNISEX')>UNISEX</option>
                                    <option value="M" @selected($g === 'M')>Masculino</option>
                                    <option value="F" @selected($g === 'F')>Femenino</option>
                                </select>
                                @error('genero')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Desde (edad)</label>
                                <input type="text" name="desde" maxlength="3" value="{{ old('desde', $toy->desde) }}"
                                    class="form-control @error('desde') is-invalid @enderror">
                                @error('desde')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Hasta (edad)</label>
                                <input type="text" name="hasta" maxlength="10" value="{{ old('hasta', $toy->hasta) }}"
                                    class="form-control @error('hasta') is-invalid @enderror">
                                @error('hasta')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Unidades</label>
                                <input type="number" name="unidades" min="0"
                                    value="{{ old('unidades', $toy->unidades) }}"
                                    class="form-control @error('unidades') is-invalid @enderror">
                                @error('unidades')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Precio unitario</label>
                                <input type="number" name="precio_unitario" min="0"
                                    value="{{ old('precio_unitario', $toy->precio_unitario) }}"
                                    class="form-control @error('precio_unitario') is-invalid @enderror">
                                @error('precio_unitario')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">% (texto)</label>
                                <input type="text" name="porcentaje" value="{{ old('porcentaje', $toy->porcentaje) }}"
                                    class="form-control @error('porcentaje') is-invalid @enderror">
                                @error('porcentaje')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Seleccionadas</label>
                                <input type="number" name="seleccionadas" min="0"
                                    value="{{ old('seleccionadas', $toy->seleccionadas) }}"
                                    class="form-control @error('seleccionadas') is-invalid @enderror">
                                @error('seleccionadas')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Escogidos</label>
                                <input type="number" name="escogidos" min="0"
                                    value="{{ old('escogidos', $toy->escogidos) }}"
                                    class="form-control @error('escogidos') is-invalid @enderror">
                                @error('escogidos')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">ID Original</label>
                                <input type="text" name="idoriginal" maxlength="15"
                                    value="{{ old('idoriginal', $toy->idoriginal) }}"
                                    class="form-control @error('idoriginal') is-invalid @enderror">
                                @error('idoriginal')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">Descripción</label>
                                <textarea name="descripcion" rows="3" class="form-control @error('descripcion') is-invalid @enderror">{{ old('descripcion', $toy->descripcion) }}</textarea>
                                @error('descripcion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 d-flex justify-content-end">
                                <a href="{{ route('campaign_toys.index') }}"
                                    class="btn btn-outline-secondary me-2">Cancelar</a>
                                <button class="btn btn-primary" type="submit">Guardar cambios</button>
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
        $(function() {
            $('#idcampaign').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Seleccione campaña',
                allowClear: true,
                ajax: {
                    url: '{{ route('api.campaigns') }}',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({
                        q: params.term || '',
                        page: params.page || 1,
                        per_page: 20,
                        only_active: 0
                    }),
                    processResults: data => ({
                        results: data.results,
                        pagination: {
                            more: data.pagination.more
                        }
                    })
                }
            });

            const input = document.getElementById('imagenppal');
            const preview = document.getElementById('preview');
            input?.addEventListener('change', e => {
                const file = e.target.files[0];
                if (file && file.type.match('image.*')) {
                    const reader = new FileReader();
                    reader.onload = ev => {
                        preview.src = ev.target.result;
                        preview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.src = '';
                    preview.style.display = 'none';
                }
            });

            $('#form-toy').on('submit', function() {
                $.blockUI({
                    message: '<div class="p-3"><div class="spinner-border"></div><div class="mt-2">Guardando...</div></div>',
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
        });
    </script>
@endpush
