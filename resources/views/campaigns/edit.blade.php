@extends('layouts.admin.master')

@push('css')
    <link rel="stylesheet" href="https://unpkg.com/trix@2.0.8/dist/trix.css">
    {{-- ✅ CSS base Select2 + tema Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.6.2/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
    {{-- ✅ CSS DateRangePicker --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
@endpush

@section('title', 'Editar campaña')

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
                    <div class="card-header pb-0 d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Editar campaña #{{ $campaign->id }}</h5>
                        <div>
                            <a href="{{ route('campaigns.index') }}" class="btn btn-outline-secondary me-2">Cancelar</a>
                            {{-- ⚠️ Este botón ahora está dentro del form más abajo; aquí lo dejamos opcional como link --}}
                        </div>
                    </div>

                    <div class="card-body">
                        <form id="form-campaign" class="row g-3" method="POST" enctype="multipart/form-data"
                            action="{{ route('campaigns.update', $campaign) }}">
                            @csrf @method('PUT')

                            {{-- NIT como Select2 --}}
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="nit">NIT (Empresa)</label>
                                <select id="nit" name="nit" class="form-select @error('nit') is-invalid @enderror"
                                    data-placeholder="Buscar por NIT o nombre" required>
                                    {{-- opción vacía para placeholder/allowClear --}}
                                    <option value=""></option>
                                    {{-- Pre-carga del valor actual para que Select2 lo muestre al entrar --}}
                                    @php
                                        $nitActual = old('nit', $campaign->nit);
                                        $nombreEmpresa =
                                            optional($campaign->empresa)->nombre ?? old('nombre', $campaign->nombre);
                                        // fallback: si no hay relación empresa, usamos el nombre actual de la campaña
                                    @endphp
                                    @if ($nitActual)
                                        <option value="{{ $nitActual }}" selected>{{ $nitActual }} -
                                            {{ $nombreEmpresa }}</option>
                                    @endif
                                </select>
                                @error('nit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Escribe para buscar por NIT o nombre de empresa.</small>
                            </div>

                            {{-- Nombre: solo lectura, se llena con el nombre de la empresa --}}
                            <div class="col-12 col-md-5">
                                <label class="form-label" for="nombre">Nombre</label>
                                <input type="text" class="form-control @error('nombre') is-invalid @enderror"
                                    id="nombre" name="nombre" maxlength="100"
                                    value="{{ old('nombre', $nombreEmpresa) }}" readonly>
                                @error('nombre')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Tipo: select fijo 1/2 --}}
                            <div class="col-12 col-md-2">
                                <label class="form-label" for="idtipo">Tipo</label>
                                @php $idtipoVal = old('idtipo', $campaign->idtipo); @endphp
                                <select id="idtipo" name="idtipo"
                                    class="form-select @error('idtipo') is-invalid @enderror" required>
                                    <option value="">-- Selecciona --</option>
                                    <option value="1" @selected($idtipoVal == 1)>Domicilio</option>
                                    <option value="2" @selected($idtipoVal == 2)>Empresarial</option>
                                </select>
                                @error('idtipo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- <div class="col-12 col-md-2">
                                <label class="form-label" for="doc_yeminus">Doc Yeminus</label>
                                <input type="number" class="form-control @error('doc_yeminus') is-invalid @enderror"
                                    id="doc_yeminus" name="doc_yeminus"
                                    value="{{ old('doc_yeminus', $campaign->doc_yeminus) }}" min="0">
                                @error('doc_yeminus')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div> --}}

                            {{-- Rango de fechas (visible) --}}
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="fecharango">Rango de fechas</label>
                                <input type="text" id="fecharango" class="form-control"
                                    placeholder="Selecciona inicio y fin">
                                <small class="text-muted">Selecciona un rango de fechas (sin hora).</small>
                            </div>

                            {{-- Fechas visibles (solo lectura) --}}
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="fechaini_display">Fecha inicio</label>
                                <input type="text" id="fechaini_display" class="form-control" value="" readonly
                                    style="background-color:#f8f9fa; cursor:not-allowed;">
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label" for="fechafin_display">Fecha fin</label>
                                <input type="text" id="fechafin_display" class="form-control" value="" readonly
                                    style="background-color:#f8f9fa; cursor:not-allowed;">
                            </div>

                            {{-- Fechas REALES ocultas (formato YYYY-MM-DD) --}}
                            <input type="hidden" id="fechaini" name="fechaini"
                                value="{{ old('fechaini', optional($campaign->fechaini)->format('Y-m-d')) }}">
                            <input type="hidden" id="fechafin" name="fechafin"
                                value="{{ old('fechafin', optional($campaign->fechafin)->format('Y-m-d')) }}">

                            <div class="col-12 col-md-3">
                                <label class="form-label" for="demo">Demo</label>
                                <select id="demo" name="demo"
                                    class="form-select @error('demo') is-invalid @enderror">
                                    @php $demo = old('demo', $campaign->demo); @endphp
                                    <option value="off" @selected($demo === 'off')>off</option>
                                    <option value="on" @selected($demo === 'on')>on</option>
                                </select>
                                @error('demo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label" for="dashboard">Dashboard</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="dashboard" name="dashboard"
                                        value="1" {{ old('dashboard', $campaign->dashboard) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="dashboard">Activar</label>
                                </div>
                                @error('dashboard')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="subject">Asunto</label>
                                <input type="text" class="form-control @error('subject') is-invalid @enderror"
                                    id="subject" name="subject" maxlength="150"
                                    value="{{ old('subject', $campaign->subject) }}" required>
                                @error('subject')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- MAILTEXT con Trix --}}
                            <div class="col-12">
                                <label class="form-label" for="mailtext">Contenido de correo</label>

                                <input id="mailtext" type="hidden" name="mailtext"
                                    value="{{ old('mailtext', $campaign->mailtext) }}">

                                <trix-editor input="mailtext" class="trix-content"
                                    style="min-height: 220px;"></trix-editor>

                                <small class="text-muted d-block mt-2">
                                    Debe incluir los metacampos:
                                    <code>[COLABORADOR]</code>, <code>[EMPRESA]</code>, <code>[NOMBRE CAMPAÑA]</code>,
                                    <code>[LINK]</code>, <code>[LINKHTML]</code>, <code>[FECHAFIN]</code>.
                                </small>

                                <div class="mt-2">
                                    @php $tokens = ['[COLABORADOR]','[EMPRESA]','[NOMBRE CAMPAÑA]','[LINK]','[LINKHTML]','[FECHAFIN]']; @endphp
                                    @foreach ($tokens as $tk)
                                        <button type="button"
                                            class="btn btn-sm btn-outline-secondary me-1 js-insert-token"
                                            data-token="{{ $tk }}">{{ $tk }}</button>
                                    @endforeach
                                    <button type="button" class="btn btn-sm btn-primary ms-1" id="btn-insert-template">
                                        Insertar plantilla base
                                    </button>
                                </div>

                                @error('mailtext')
                                    <div class="text-danger mt-2">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="customlogin">Custom login (HTML opcional)</label>
                                <textarea class="form-control @error('customlogin') is-invalid @enderror" id="customlogin" name="customlogin"
                                    rows="4">{{ old('customlogin', $campaign->customlogin) }}</textarea>
                                @error('customlogin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="banner">Banner</label>
                                <input class="form-control @error('banner') is-invalid @enderror" id="banner"
                                    type="file" name="banner" accept=".bmp,.jpg,.jpeg,.png">
                                @error('banner')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror

                                <div class="mt-2">
                                    @if ($campaign->banner && $campaign->banner !== 'ND')
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Actual:</small>
                                            <img src="{{ asset('storage/' . $campaign->banner) }}" alt="banner actual"
                                                style="max-width:260px; display:block;">
                                        </div>
                                    @endif
                                    <img id="preview_banner" src="" alt="Vista previa banner"
                                        style="max-width:260px; display:none;">
                                </div>
                            </div>

                            <div class="col-12 d-flex justify-content-end">
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
    <script src="https://unpkg.com/trix@2.0.8/dist/trix.umd.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('assets/js/blockui/jquery.blockUI.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- ✅ Select2 --}}
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    {{-- ✅ Moment + DateRangePicker --}}
    <script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/min/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/locale/es.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

    <script>
        $(function() {
            moment.locale('es');

            // Evitar adjuntos en Trix
            document.addEventListener('trix-file-accept', function(e) {
                e.preventDefault();
            });

            // Tokens Trix
            document.querySelectorAll('.js-insert-token').forEach(btn => {
                btn.addEventListener('click', () => {
                    const token = btn.getAttribute('data-token');
                    const ed = document.querySelector('trix-editor[input="mailtext"]');
                    ed.editor.insertString(token + ' ');
                });
            });

            // Plantilla base
            const baseTemplate =
                '<div>Hola [COLABORADOR],<br>&nbsp;[EMPRESA] te invitan a escoger personalmente los regalos de tus hijos para el evento: [NOMBRE CAMPAÑA].<br>&nbsp;Para escoger los juguetes haz clic [LINK]<br>&nbsp;[LINKHTML]<br>&nbsp;Recuerda que tienes hasta el [FECHAFIN] para seleccionar los juguetes de tus hijos.<br>&nbsp;Porque sabemos que tus hijos son lo más importante, [EMPRESA] quiere que escojas el regalo que recibirán este año.<br><br></div>';
            document.getElementById('btn-insert-template')?.addEventListener('click', () => {
                const ed = document.querySelector('trix-editor[input="mailtext"]');
                ed.editor.loadHTML(baseTemplate);
            });

            // Preview banner
            $('#banner').on('change', function(e) {
                const file = e.target.files[0];
                const img = document.getElementById('preview_banner');
                if (file && file.type.match('image.*')) {
                    const reader = new FileReader();
                    reader.onload = ev => {
                        img.src = ev.target.result;
                        img.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    img.src = '';
                    img.style.display = 'none';
                }
            });

            // BlockUI al enviar
            $('#form-campaign').on('submit', function() {
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

            // ---------- INIT SELECT2 PARA NIT ----------
            $('#nit').select2({
                theme: 'bootstrap-5',
                placeholder: $('#nit').data('placeholder') || 'Buscar empresa...',
                allowClear: true,
                width: '100%',
                minimumInputLength: 1,
                ajax: {
                    url: "{{ route('empresas.select2') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term || '',
                            page: params.page || 1
                        };
                    },
                    processResults: function(data, params) {
                        params.page = params.page || 1;
                        return {
                            results: (data.items || []),
                            pagination: {
                                more: !!data.more
                            }
                        };
                    }
                },
                templateResult: function(item) {
                    if (item.loading) return item.text;
                    return $('<div>').text(item.text || item.id || '');
                },
                templateSelection: function(item) {
                    return item.text || item.id || '';
                }
            });

            // Al seleccionar empresa, llenar nombre (readonly)
            $('#nit').on('select2:select', function(e) {
                const data = e.params.data;
                if (data && data.nombre) {
                    $('#nombre').val(data.nombre);
                } else if (data && data.text) {
                    // fallback: si no viene 'nombre' separado, parseamos "NIT - Nombre"
                    const parts = (data.text + '').split(' - ');
                    $('#nombre').val(parts[1] || '');
                }
            });

            // ---------- INIT DATE RANGE PICKER (solo fecha) ----------
            const $picker = $('#fecharango');
            const $iniHid = $('#fechaini');
            const $finHid = $('#fechafin');
            const $iniDisp = $('#fechaini_display');
            const $finDisp = $('#fechafin_display');

            function parseAny(d) {
                if (!d) return null;
                const m = moment(d, ['YYYY-MM-DD', 'DD-MMM-YYYY', moment.ISO_8601], true);
                return m.isValid() ? m : null;
            }

            let oldIni = parseAny($iniHid.val());
            let oldFin = parseAny($finHid.val());
            let start = oldIni || moment().startOf('day');
            let end = oldFin || moment().add(1, 'day').startOf('day');

            function syncFields(s, e) {
                $iniDisp.val(s.format('DD-MMM-YYYY'));
                $finDisp.val(e.format('DD-MMM-YYYY'));
                $iniHid.val(s.format('YYYY-MM-DD'));
                $finHid.val(e.format('YYYY-MM-DD'));
                $picker.val(s.format('DD-MMM-YYYY') + ' - ' + e.format('DD-MMM-YYYY'));
            }

            $picker.daterangepicker({
                startDate: start,
                endDate: end,
                autoUpdateInput: true,
                timePicker: false,
                locale: {
                    format: 'DD-MMM-YYYY',
                    separator: ' - ',
                    applyLabel: 'Aplicar',
                    cancelLabel: 'Cancelar',
                    fromLabel: 'Desde',
                    toLabel: 'Hasta',
                    customRangeLabel: 'Personalizado',
                    daysOfWeek: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'],
                    monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto',
                        'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
                    ],
                    firstDay: 1
                }
            }, function(startSel, endSel) {
                syncFields(startSel.startOf('day'), endSel.startOf('day'));
            });

            // Inicial
            syncFields(start, end);

            // Cancelar limpia campos
            $picker.on('cancel.daterangepicker', function() {
                $(this).val('');
                $iniDisp.val('');
                $finDisp.val('');
                $iniHid.val('');
                $finHid.val('');
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
