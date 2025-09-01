@extends('layouts.admin.master')

@section('title', 'Editar Hijo de Colaborador')

@push('css')
    {{-- Select2 CSS (y tema Bootstrap 5 opcional) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                {{-- Errores de validación (lista) --}}
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

                {{-- Flash messages (también se muestran con SweetAlert2) --}}
                @if (session('success'))
                    <div class="alert alert-success mb-3">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger mb-3">{{ session('error') }}</div>
                @endif

                <div class="card">
                    <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">Editar Hijo(a)</h5>
                            <small class="text-muted">ID registro: {{ $colaborador_hijo->id }}</small>
                        </div>
                        <a href="{{ route('colaborador_hijos.index', ['identificacion' => request('identificacion')]) }}"
                            class="btn btn-outline-secondary btn-sm">
                            Volver
                        </a>
                    </div>

                    <div class="card-body">
                        <form id="form-hijo" class="row g-3" method="POST"
                            action="{{ route('colaborador_hijos.update', $colaborador_hijo->id) }}">
                            @csrf
                            @method('PUT')

                            {{-- Colaborador (identificación) --}}
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="identificacion">Colaborador (Documento)</label>
                                <select id="identificacion" name="identificacion"
                                    class="form-control @error('identificacion') is-invalid @enderror">
                                    @php
                                        // Si el controlador envía (opcionalmente) $colaboradorNombre
                                        $selIdColab = old('identificacion', $colaborador_hijo->identificacion);
                                        $selTextColab = old('identificacion_text', $colaboradorNombre ?? null);
                                    @endphp
                                    @if ($selIdColab && $selTextColab)
                                        <option value="{{ $selIdColab }}" selected>{{ $selTextColab }}</option>
                                    @elseif($selIdColab)
                                        {{-- Fallback: muestra el id si no tienes el texto aún --}}
                                        <option value="{{ $selIdColab }}" selected>{{ $selIdColab }}</option>
                                    @endif
                                </select>
                                @error('identificacion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Campaña --}}
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="idcampaign">Campaña</label>
                                <select id="idcampaign" name="idcampaign"
                                    class="form-control @error('idcampaign') is-invalid @enderror">
                                    @php
                                        $selIdCamp = old('idcampaign', $colaborador_hijo->idcampaign);
                                        $selTextCamp = old('idcampaign_text', $campaignNombre ?? null);
                                    @endphp
                                    @if ($selIdCamp && $selTextCamp)
                                        <option value="{{ $selIdCamp }}" selected>{{ $selTextCamp }}</option>
                                    @elseif($selIdCamp)
                                        <option value="{{ $selIdCamp }}" selected>{{ $selIdCamp }}</option>
                                    @endif
                                </select>
                                @error('idcampaign')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Nombre hijo --}}
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="nombre_hijo">Nombre del hijo(a)</label>
                                <input type="text" id="nombre_hijo" name="nombre_hijo"
                                    class="form-control @error('nombre_hijo') is-invalid @enderror" maxlength="100"
                                    value="{{ old('nombre_hijo', $colaborador_hijo->nombre_hijo) }}">
                                @error('nombre_hijo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Género --}}
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="genero">Género</label>
                                <select id="genero" name="genero"
                                    class="form-select @error('genero') is-invalid @enderror">
                                    @php $g = old('genero', $colaborador_hijo->genero); @endphp
                                    <option value="" @selected($g === '')>Seleccione</option>
                                    <option value="M" @selected($g === 'M')>Masculino</option>
                                    <option value="F" @selected($g === 'F')>Femenino</option>
                                    <option value="Otro" @selected($g === 'Otro')>Otro</option>
                                </select>
                                @error('genero')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Rango edad --}}
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="rango_edad">Rango de edad</label>
                                <select id="rango_edad" name="rango_edad"
                                    class="form-select @error('rango_edad') is-invalid @enderror">
                                    @php $re = old('rango_edad', $colaborador_hijo->rango_edad); @endphp
                                    <option value="">Seleccione</option>
                                    <option value="0-3" @selected($re === '0-3')>0-3</option>
                                    <option value="4-6" @selected($re === '4-6')>4-6</option>
                                    <option value="7-9" @selected($re === '7-9')>7-9</option>
                                    <option value="10-12" @selected($re === '10-12')>10-12</option>
                                    <option value="13-15" @selected($re === '13-15')>13-15</option>
                                    <option value="16-18" @selected($re === '16-18')>16-18</option>
                                </select>
                                @error('rango_edad')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 d-flex justify-content-end">
                                <a href="{{ route('colaborador_hijos.index', ['identificacion' => request('identificacion')]) }}"
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
    {{-- BlockUI --}}
    <script src="{{ asset('assets/js/blockui/jquery.blockUI.js') }}"></script>
    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    {{-- Select2 --}}
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(function() {

            // --- Select2: Colaborador (buscar por documento/nombre) ---
            const $ident = $('#identificacion');
            $ident.select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Buscar colaborador',
                allowClear: true,
                ajax: {
                    url: '{{ route('api.colaboradores') }}', // <-- Ajusta si usas otra ruta
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
                            more: data.pagination?.more
                        }
                    })
                }
            });

            // Si tenemos solo el ID (y no el texto), resolverlo por AJAX
            (function preselectColaborador() {
                const id = @json(old('identificacion', $colaborador_hijo->identificacion));
                const text = @json(old('identificacion_text', $colaboradorNombre ?? null));
                if (id && !text) {
                    $.get('{{ route('api.colaboradores') }}', {
                        q: id,
                        page: 1,
                        per_page: 1
                    }).then(resp => {
                        const match = (resp.results || []).find(r => String(r.id) === String(id));
                        const label = match ? match.text : id;
                        const opt = new Option(label, id, true, true);
                        $ident.empty().append(opt).trigger('change');
                    });
                }
            })();

            // --- Select2: Campaña ---
            const $camp = $('#idcampaign');
            $camp.select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Buscar campaña',
                allowClear: true,
                ajax: {
                    url: '{{ route('api.campaigns') }}', // <-- Ajusta si usas otra ruta
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
                            more: data.pagination?.more
                        }
                    })
                }
            });

            (function preselectCampaign() {
                const id = @json(old('idcampaign', $colaborador_hijo->idcampaign));
                const text = @json(old('idcampaign_text', $campaignNombre ?? null));
                if (id && !text) {
                    $.get('{{ route('api.campaigns') }}', {
                        q: id,
                        page: 1,
                        per_page: 1
                    }).then(resp => {
                        const match = (resp.results || []).find(r => String(r.id) === String(id));
                        const label = match ? match.text : id;
                        const opt = new Option(label, id, true, true);
                        $camp.empty().append(opt).trigger('change');
                    });
                }
            })();

            // BlockUI al enviar
            $('#form-hijo').on('submit', function() {
                $.blockUI({
                    message: '<div class="p-3"><div class="spinner-border" role="status"></div><div class="mt-2">Guardando...</div></div>',
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

            // Errores de validación en modal (opcional)
            @if ($errors->any())
                Swal.fire({
                    icon: 'error',
                    title: 'Errores de validación',
                    html: `{!! implode('<br>', $errors->all()) !!}`,
                    confirmButtonText: 'Revisar'
                });
            @endif
        });
    </script>
@endpush
