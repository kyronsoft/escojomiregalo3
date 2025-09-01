@extends('layouts.admin.master')

@section('title', 'Crear Hijo de Colaborador')

@push('css')
    {{-- Select2 (y tema opcional Bootstrap 5) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                {{-- Errores de validación --}}
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

                {{-- Flash messages (también con SweetAlert2) --}}
                @if (session('success'))
                    <div class="alert alert-success mb-3">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger mb-3">{{ session('error') }}</div>
                @endif

                <div class="card">
                    <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Nuevo Hijo(a)</h5>
                        <a href="{{ route('colaborador_hijos.index', ['identificacion' => request('identificacion')]) }}"
                            class="btn btn-outline-secondary btn-sm">
                            Volver
                        </a>
                    </div>

                    <div class="card-body">
                        <form id="form-hijo" class="row g-3" method="POST" action="{{ route('colaborador_hijos.store') }}">
                            @csrf

                            {{-- Colaborador (identificación) --}}
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="identificacion">Colaborador (Documento)</label>
                                <select id="identificacion" name="identificacion"
                                    class="form-control @error('identificacion') is-invalid @enderror"></select>
                                @error('identificacion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @if (request('identificacion'))
                                    <small class="text-muted">Precargado desde el listado de colaboradores</small>
                                @endif
                            </div>

                            {{-- Campaña --}}
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="idcampaign">Campaña</label>
                                <select id="idcampaign" name="idcampaign"
                                    class="form-control @error('idcampaign') is-invalid @enderror"></select>
                                @error('idcampaign')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Nombre del hijo --}}
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="nombre_hijo">Nombre del hijo(a)</label>
                                <input type="text" id="nombre_hijo" name="nombre_hijo"
                                    class="form-control @error('nombre_hijo') is-invalid @enderror" maxlength="100"
                                    value="{{ old('nombre_hijo') }}">
                                @error('nombre_hijo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Género --}}
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="genero">Género</label>
                                @php $g = old('genero'); @endphp
                                <select id="genero" name="genero"
                                    class="form-select @error('genero') is-invalid @enderror">
                                    <option value="" @selected($g === '')>Seleccione</option>
                                    <option value="M" @selected($g === 'M')>Masculino</option>
                                    <option value="F" @selected($g === 'F')>Femenino</option>
                                    <option value="Otro" @selected($g === 'Otro')>Otro</option>
                                </select>
                                @error('genero')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Rango de edad --}}
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="rango_edad">Rango de edad</label>
                                @php $re = old('rango_edad'); @endphp
                                <select id="rango_edad" name="rango_edad"
                                    class="form-select @error('rango_edad') is-invalid @enderror">
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
                                    class="btn btn-outline-secondary me-2">
                                    Cancelar
                                </a>
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
    {{-- BlockUI --}}
    <script src="{{ asset('assets/js/blockui/jquery.blockUI.js') }}"></script>
    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    {{-- Select2 --}}
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(function() {

            // --- Select2 Colaborador ---
            const $ident = $('#identificacion');
            $ident.select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Buscar colaborador',
                allowClear: true,
                ajax: {
                    url: '{{ route('api.colaboradores') }}',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({
                        q: params.term || '',
                        page: params.page || 1,
                        per_page: 20,
                        // Puedes filtrar por NIT si tu caso lo requiere:
                        // nit: '{{ request('nit') }}'
                    }),
                    processResults: (data, params) => ({
                        results: data.results,
                        pagination: {
                            more: data.pagination?.more
                        }
                    })
                }
            });

            // Precargar identificación si viene desde el listado (?identificacion=xxx)
            (function preselectIdent() {
                const idQS = @json(request('identificacion'));
                const oldId = @json(old('identificacion'));
                const id = oldId || idQS;
                if (!id) return;

                // si además tienes el texto en old('identificacion_text'), úsalo:
                const text = @json(old('identificacion_text'));
                if (text) {
                    const opt = new Option(text, id, true, true);
                    $ident.append(opt).trigger('change');
                    return;
                }

                // Resuelve el texto con una consulta rápida
                $.get('{{ route('api.colaboradores') }}', {
                    q: id,
                    page: 1,
                    per_page: 1
                }).then(resp => {
                    const match = (resp.results || []).find(r => String(r.id) === String(id));
                    const label = match ? match.text : id;
                    const opt = new Option(label, id, true, true);
                    $ident.append(opt).trigger('change');
                });
            })();

            // --- Select2 Campaign ---
            const $camp = $('#idcampaign');
            $camp.select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Buscar campaña',
                allowClear: true,
                ajax: {
                    url: '{{ route('api.campaigns') }}',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({
                        q: params.term || '',
                        page: params.page || 1,
                        per_page: 20,
                        // nit: '{{ request('nit') }}'
                    }),
                    processResults: (data, params) => ({
                        results: data.results,
                        pagination: {
                            more: data.pagination?.more
                        }
                    })
                }
            });

            // Si hubo validación fallida y tenías old('idcampaign'), lo resolvemos:
            (function preselectCampaign() {
                const id = @json(old('idcampaign'));
                if (!id) return;

                const text = @json(old('idcampaign_text'));
                if (text) {
                    const opt = new Option(text, id, true, true);
                    $camp.append(opt).trigger('change');
                    return;
                }

                $.get('{{ route('api.campaigns') }}', {
                    q: id,
                    page: 1,
                    per_page: 1
                }).then(resp => {
                    const match = (resp.results || []).find(r => String(r.id) === String(id));
                    const label = match ? match.text : id;
                    const opt = new Option(label, id, true, true);
                    $camp.append(opt).trigger('change');
                });
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

            // Opcional: errores de validación en modal
            @if ($errors->any())
                Swal.fire({
                    icon: 'error',
                    title: 'Errores de validación',
                    html: `{!! implode('<br>', $errors->all()) !!}`
                });
            @endif
        });
    </script>
@endpush
