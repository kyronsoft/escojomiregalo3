@extends('layouts.admin.master')

@section('title', 'Editar Hijo de Colaborador')

@push('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
@endpush

@section('content')
    @php
        // Nombres para preselección desde relaciones (el controlador ya hizo ->load(['colaborador','campaign']))
        $colaboradorNombre = optional($colaborador_hijo->colaborador)->nombre;
        $campaignNombre = optional($colaborador_hijo->campaign)->nombre;

        // Normalización de género a NIÑO/NIÑA/UNISEX
        $gRaw = old('genero', $colaborador_hijo->genero);
        $gMap = strtoupper(trim((string) $gRaw));
        $mapNina = ['F', 'FEMENINO', 'FEMENINA', 'NIÑA', 'NINA', 'GIRL', 'MUJER', 'FEMALE'];
        $mapNino = ['M', 'MASCULINO', 'MASCULINA', 'NIÑO', 'NINO', 'BOY', 'HOMBRE', 'MALE'];
        $g = in_array($gMap, $mapNino, true) ? 'NIÑO' : (in_array($gMap, $mapNina, true) ? 'NIÑA' : 'UNISEX');

        // Valor numérico para edad (si viene "7-9", toma 7)
        $reRaw = old('rango_edad', $colaborador_hijo->rango_edad);
        if (!is_null($reRaw) && !is_numeric($reRaw)) {
            if (preg_match('/\d+/', (string) $reRaw, $m)) {
                $reRaw = (int) $m[0];
            } else {
                $reRaw = '';
            }
        }
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
                                        $selIdColab = old('identificacion', $colaborador_hijo->identificacion);
                                        $selTextColab = old('identificacion_text', $colaboradorNombre);
                                    @endphp
                                    @if ($selIdColab)
                                        <option value="{{ $selIdColab }}" selected>
                                            {{ $selTextColab ?: $selIdColab }}
                                        </option>
                                    @endif
                                </select>
                                @error('identificacion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Campaña (usar idcampaing desde BD para preselección) --}}
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="idcampaign">Campaña</label>
                                <select id="idcampaign" name="idcampaign"
                                    class="form-control @error('idcampaign') is-invalid @enderror">
                                    @php
                                        $selIdCamp = old('idcampaign', $colaborador_hijo->idcampaing);
                                        $selTextCamp = old('idcampaign_text', $campaignNombre);
                                    @endphp
                                    @if ($selIdCamp)
                                        <option value="{{ $selIdCamp }}" selected>
                                            {{ $selTextCamp ?: $selIdCamp }}
                                        </option>
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

                            {{-- Género (NIÑO/NIÑA/UNISEX) --}}
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="genero">Género</label>
                                <select id="genero" name="genero"
                                    class="form-select @error('genero') is-invalid @enderror">
                                    <option value="NIÑO" @selected($g === 'NIÑO')>NIÑO</option>
                                    <option value="NIÑA" @selected($g === 'NIÑA')>NIÑA</option>
                                    <option value="UNISEX" @selected($g === 'UNISEX' || $g === null || $g === '')>UNISEX</option>
                                </select>
                                @error('genero')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Edad (0–14) --}}
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="rango_edad">Edad (años)</label>
                                <input type="number" id="rango_edad" name="rango_edad"
                                    class="form-control @error('rango_edad') is-invalid @enderror" min="0"
                                    max="14" step="1" value="{{ is_numeric($reRaw) ? (int) $reRaw : '' }}">
                                <div class="form-text">De 0 a 14 años</div>
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
    <script src="{{ asset('assets/js/blockui/jquery.blockUI.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(function() {
            // --- Select2: Colaborador ---
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
                        per_page: 20
                    }),
                    processResults: (data) => ({
                        results: data.results,
                        pagination: {
                            more: data.pagination?.more
                        }
                    })
                }
            });

            // Si vino id + texto desde servidor, no hace falta prefetch.
            (function preselectColaborador() {
                const hasOption = $ident.find('option[selected]').length > 0;
                if (!hasOption) {
                    const id = @json(old('identificacion', $colaborador_hijo->identificacion));
                    if (id) {
                        $.get('{{ route('api.colaboradores') }}', {
                                q: id,
                                page: 1,
                                per_page: 1
                            })
                            .then(resp => {
                                const match = (resp.results || []).find(r => String(r.id) === String(id));
                                const label = match ? match.text : id;
                                const opt = new Option(label, id, true, true);
                                $ident.empty().append(opt).trigger('change');
                            });
                    }
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
                    url: '{{ route('api.campaigns') }}',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({
                        q: params.term || '',
                        page: params.page || 1,
                        per_page: 20
                    }),
                    processResults: (data) => ({
                        results: data.results,
                        pagination: {
                            more: data.pagination?.more
                        }
                    })
                }
            });

            (function preselectCampaign() {
                const hasOption = $camp.find('option[selected]').length > 0;
                if (!hasOption) {
                    const id = @json(old('idcampaign', $colaborador_hijo->idcampaing));
                    if (id) {
                        $.get('{{ route('api.campaigns') }}', {
                                q: id,
                                page: 1,
                                per_page: 1
                            })
                            .then(resp => {
                                const match = (resp.results || []).find(r => String(r.id) === String(id));
                                const label = match ? match.text : id;
                                const opt = new Option(label, id, true, true);
                                $camp.empty().append(opt).trigger('change');
                            });
                    }
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
                    html: `{!! implode('<br>', $errors->all()) !!}`,
                    confirmButtonText: 'Revisar'
                });
            @endif
        });
    </script>
@endpush
