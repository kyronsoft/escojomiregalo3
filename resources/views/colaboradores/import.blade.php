@extends('layouts.admin.master')

@section('title', 'Importar Colaboradores')

@push('css')
    {{-- Select2 --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                {{-- Alertas rápidas (por si algo llega sin SweetAlert) --}}
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                {{-- Errores de validación --}}
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
                    <div class="card-header pb-0">
                        <h5>Importar Colaboradores</h5>
                        <span>Selecciona la campaña y carga el archivo Excel (.xlsx, .xls o .csv).</span>
                    </div>

                    <div class="card-body">
                        <form id="form-import" class="row g-3" method="POST"
                            action="{{ route('colaboradores.import.run') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="col-12 col-md-6">
                                <label for="campaign_select" class="form-label">Campaña (solo activas)</label>

                                {{-- Este es el que viaja al backend --}}
                                <input type="hidden" id="idcampaign_hidden" name="idcampaign"
                                    value="{{ old('idcampaign') }}" required>

                                {{-- Solo UI (sin name) --}}
                                <select id="campaign_select" class="form-control @error('idcampaign') is-invalid @enderror"
                                    style="width:100%"></select>

                                @error('idcampaign')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="file" class="form-label">Archivo</label>
                                <input id="file" name="file" type="file"
                                    class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv"
                                    required>
                                @error('file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Campos esperados: documento, nombre, email, nombre_hijo,
                                    rango_edad, genero</small>
                            </div>

                            <div class="col-12 d-flex justify-content-end">
                                <a href="{{ route('colaboradores.index') }}"
                                    class="btn btn-outline-secondary me-2">Cancelar</a>
                                <button type="submit" class="btn btn-primary">Importar</button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Resumen del proceso (se muestra cuando regresa del backend) --}}
                @if (session('import_summary'))
                    @php $s = session('import_summary'); @endphp
                    <div class="card mt-4">
                        <div class="card-header pb-0">
                            <h5>Resumen de Importación</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 col-lg-6">
                                    <h6>Colaboradores</h6>
                                    <ul class="mb-3">
                                        <li>Creados: <strong>{{ data_get($s, 'colaboradores.creados', 0) }}</strong></li>
                                        <li>Actualizados:
                                            <strong>{{ data_get($s, 'colaboradores.actualizados', 0) }}</strong>
                                        </li>
                                        <li>Omitidos: <strong>{{ data_get($s, 'colaboradores.omitidos', 0) }}</strong></li>
                                    </ul>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <h6>Usuarios</h6>
                                    <ul class="mb-0">
                                        <li>Creados: <strong>{{ data_get($s, 'users.creados', 0) }}</strong></li>
                                        <li>Actualizados: <strong>{{ data_get($s, 'users.actualizados', 0) }}</strong></li>
                                        <li>Omitidos (sin email):
                                            <strong>{{ data_get($s, 'users.omitidos_sin_email', 0) }}</strong>
                                        </li>
                                        <li>Omitidos (email inválido):
                                            <strong>{{ data_get($s, 'users.omitidos_email_invalido', 0) }}</strong>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- jQuery + BlockUI + SweetAlert2 + Select2 --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('assets/js/blockui/jquery.blockUI.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(function() {
            const $form = $('#form-import');
            const $hidden = $('#idcampaign_hidden'); // el que se envía
            const $select = $('#campaign_select'); // solo UI
            const apiCamp = '{{ route('api.campaigns') }}';

            $select.select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Seleccione campaña (activas)',
                allowClear: true,
                ajax: {
                    url: apiCamp,
                    dataType: 'json',
                    delay: 250,
                    data: params => ({
                        q: params.term || '',
                        page: params.page || 1,
                        per_page: 20,
                        only_active: 1
                    }),
                    processResults: function(data) {
                        const raw = Array.isArray(data?.results) ? data.results : [];
                        const results = raw.map(it => ({
                            id: it.id ?? it.value ?? it.ID ?? it.Id ?? it.pk ?? '',
                            text: it.text ?? it.label ?? it.nombre ?? it.name ?? `#${it.id}`
                        })).filter(it => it.id !== '');
                        return {
                            results,
                            pagination: {
                                more: !!data?.pagination?.more
                            }
                        };
                    }
                }
            });

            // Cuando el usuario selecciona algo -> copia el id al hidden
            $select.on('select2:select', (e) => {
                const d = e.params?.data || {};
                $hidden.val(d.id || '');
                // Asegura que exista un <option> real seleccionado (evita valores fantasma)
                if (!$select.find("option[value='" + d.id + "']").length) {
                    const opt = new Option(d.text || ('#' + d.id), d.id, true, true);
                    $select.append(opt).trigger('change');
                }
            });

            // Si limpia, vaciamos el hidden
            $select.on('select2:clear', () => {
                $hidden.val('');
            });

            // Precarga si vienes con old('idcampaign')
            const oldId = $hidden.val();
            if (oldId) {
                const opt = new Option('{{ old('idcampaign_text', 'Campaña seleccionada') }}', oldId, true, true);
                $select.append(opt).trigger('change');
            }

            // Antes de enviar: 1) quita duplicados 2) asegura valor en el hidden
            $form.on('submit', function(e) {
                // Elimina cualquier campo duplicado llamado idcampaign que no sea nuestro hidden
                $('input[name="idcampaign"]').not($hidden).remove();

                // Si por algo el hidden está vacío, intenta tomar el valor actual del select
                if (!$hidden.val()) {
                    $hidden.val($select.val() || '');
                }

                if (!$hidden.val()) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Falta campaña',
                        text: 'Selecciona una campaña activa antes de importar.'
                    });
                    return false;
                }

                $.blockUI({
                    message: `
        <div class="p-3 text-center">
          <div class="spinner-border" role="status"></div>
          <div class="mt-2">Importando colaboradores...<br>Por favor espera.</div>
        </div>`,
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
        });
    </script>
@endpush
