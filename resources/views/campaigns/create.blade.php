@extends('layouts.admin.master')

@push('css')
    <link rel="stylesheet" href="https://unpkg.com/trix@2.0.8/dist/trix.css">
    {{-- ✅ CSS base de Select2 (requerido) --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    {{-- Tema Bootstrap 5 (opcional) --}}
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.6.2/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
    {{-- ✅ CSS del DateRangePicker --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
@endpush

@section('title', 'Crear campaña')

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
                        <h5>Nueva Campaña</h5>
                    </div>
                    <div class="card-body">
                        <form id="form-campaign" class="row g-3" method="POST" enctype="multipart/form-data"
                            action="{{ route('campaigns.store') }}">
                            @csrf

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="nit">NIT (Empresa)</label>
                                <select id="nit" name="nit" class="form-select @error('nit') is-invalid @enderror"
                                    data-placeholder="Buscar por NIT o nombre">
                                    {{-- ✅ opción vacía para placeholder/allowClear --}}
                                    <option value=""></option>
                                    {{-- Si hay old('nit'), lo precargamos vía AJAX más abajo --}}
                                </select>
                                @error('nit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Escribe para buscar por NIT o nombre de empresa.</small>
                            </div>

                            <div class="col-12 col-md-5">
                                <label class="form-label" for="nombre">Nombre</label>
                                <input type="text" class="form-control @error('nombre') is-invalid @enderror"
                                    id="nombre" name="nombre" maxlength="100" value="{{ old('nombre') }}" readonly>
                                {{-- ✅ ahora es solo lectura --}}
                                @error('nombre')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-2">
                                <label class="form-label" for="idtipo">Tipo</label>
                                <select id="idtipo" name="idtipo"
                                    class="form-select @error('idtipo') is-invalid @enderror" required>
                                    <option value="">-- Selecciona --</option>
                                    <option value="1" @selected(old('idtipo') == 1)>Domicilio</option>
                                    <option value="2" @selected(old('idtipo') == 2)>Empresarial</option>
                                </select>
                                @error('idtipo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>



                            {{-- <div class="col-12 col-md-2">
                                <label class="form-label" for="doc_yeminus">Doc Yeminus</label>
                                <input type="number" class="form-control @error('doc_yeminus') is-invalid @enderror"
                                    id="doc_yeminus" name="doc_yeminus" value="{{ old('doc_yeminus', 0) }}" min="0">
                                @error('doc_yeminus')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div> --}}

                            {{-- Picker visible para seleccionar el rango --}}
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="fecharango">Rango de fechas</label>
                                <input type="text" id="fecharango" class="form-control"
                                    placeholder="Selecciona inicio y fin">
                                <small class="text-muted">Elige el inicio y fin; se llenarán los campos reales de la
                                    campaña.</small>
                            </div>

                            {{-- Campos visibles (solo lectura) en dd-mmm-yyyy --}}
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

                            {{-- Campos REALES ocultos que se envían al backend (YYYY-MM-DD) --}}
                            <input type="hidden" id="fechaini" name="fechaini" value="{{ old('fechaini') }}">
                            <input type="hidden" id="fechafin" name="fechafin" value="{{ old('fechafin') }}">


                            <div class="col-12 col-md-3">
                                <label class="form-label" for="demo">Demo</label>
                                <select id="demo" name="demo"
                                    class="form-select @error('demo') is-invalid @enderror">
                                    <option value="off" @selected(old('demo', 'off') === 'off')>off</option>
                                    <option value="on" @selected(old('demo') === 'on')>on</option>
                                </select>
                                @error('demo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label" for="dashboard">Dashboard</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="dashboard" name="dashboard"
                                        value="1" {{ old('dashboard') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="dashboard">Activar</label>
                                </div>
                                @error('dashboard')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="subject">Asunto Correo Invitación</label>
                                <input type="text" class="form-control @error('subject') is-invalid @enderror"
                                    id="subject" name="subject" maxlength="150" value="{{ old('subject') }}"
                                    required>
                                @error('subject')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- MAILTEXT con Trix --}}
                            <div class="col-12">
                                <label class="form-label" for="mailtext">Contenido de correo</label>

                                {{-- Campo real --}}
                                <input id="mailtext" type="hidden" name="mailtext"
                                    value="{{ old('mailtext', $campaign->mailtext ?? '') }}">

                                {{-- Editor --}}
                                <trix-editor input="mailtext" class="trix-content"
                                    style="min-height: 220px;"></trix-editor>

                                <small class="text-muted d-block mt-2">
                                    Debe incluir los metacampos:
                                    <code>[COLABORADOR]</code>, <code>[EMPRESA]</code>, <code>[NOMBRE CAMPAÑA]</code>,
                                    <code>[LINK]</code>, <code>[LINKHTML]</code>, <code>[FECHAFIN]</code>.
                                </small>

                                {{-- Botones de inserción de metacampos y plantilla base --}}
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
                                <div class="input-group mb-2">
                                    <textarea class="form-control @error('customlogin') is-invalid @enderror" id="customlogin" name="customlogin"
                                        rows="4">{{ old('customlogin') }}</textarea>
                                    <button type="button" class="btn btn-outline-primary" id="btn-generate-url">
                                        Generar URL
                                    </button>
                                </div>
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
                                    <img id="preview_banner" src="" alt="Vista previa banner"
                                        style="max-width:260px; display:none;">
                                </div>
                            </div>

                            <div class="col-12 d-flex justify-content-end">
                                <a href="{{ route('campaigns.index') }}"
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
    <script src="https://unpkg.com/trix@2.0.8/dist/trix.umd.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('assets/js/blockui/jquery.blockUI.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    {{-- ✅ Moment + DateRangePicker --}}
    <script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/min/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/locale/es.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

    <script>
        // Evitar adjuntar archivos en Trix (mejora para correos)
        document.addEventListener('trix-file-accept', function(e) {
            e.preventDefault();
        });

        // Metacampos: insertar en cursor
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
    <script>
        $(function() {
            // Seguridad: verificar que select2 esté cargado
            if (!$.fn.select2) {
                console.error('Select2 no está disponible. Revisa el <script> de CDN.');
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Select2',
                    text: 'No se pudo cargar Select2. Verifica el CDN en la consola del navegador.'
                });
                return;
            }

            // ---------- INIT SELECT2 PARA NIT ----------
            $('#nit').select2({
                theme: 'bootstrap-5',
                placeholder: $('#nit').data('placeholder') || 'Buscar empresa...',
                allowClear: true,
                width: '100%', // o 'resolve'
                minimumInputLength: 1, // evita query vacía
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
                language: {
                    inputTooShort: function() {
                        return 'Escribe al menos 1 carácter';
                    },
                    searching: function() {
                        return 'Buscando…';
                    },
                    noResults: function() {
                        return 'Sin resultados';
                    },
                    loadingMore: function() {
                        return 'Cargando más…';
                    },
                },
                templateResult: function(item) {
                    if (item.loading) return item.text;
                    return $('<div>').text(item.text || item.id || '');
                },
                templateSelection: function(item) {
                    return item.text || item.id || '';
                }
            });

            // Precarga del valor viejo si existiera (old('nit'))
            const oldNit = @json(old('nit'));
            if (oldNit) {
                $.ajax({
                    url: "{{ route('empresas.select2') }}",
                    data: {
                        id: oldNit
                    },
                    dataType: 'json'
                }).then(function(data) {
                    if (data && data.item) {
                        const opt = new Option(data.item.text, data.item.id, true, true);
                        $('#nit').append(opt).trigger('change');
                    }
                });
            }

            // ---------- GENERAR URL Y PEGARLA EN EL TEXTAREA ----------
            $('#btn-generate-url').on('click', function() {
                const nit = $('#nit').val();
                if (!nit) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Atención',
                        text: 'Selecciona la empresa (NIT) antes de generar la URL.'
                    });
                    return;
                }

                $.ajax({
                    url: "{{ route('campaigns.generateCustomUrl') }}",
                    method: "POST",
                    data: {
                        nit: nit,
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        if (response.url) {
                            $('#customlogin').val(response.url);
                            Swal.fire({
                                icon: 'success',
                                title: 'URL generada',
                                text: 'La URL se insertó en el campo Custom Login.'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo generar la URL.'
                        });
                    }
                });
            });

            $('#nit').on('select2:select', function(e) {
                const data = e.params.data;
                if (data && data.nombre) {
                    $('#nombre').val(data.nombre); // ✅ rellenar el campo nombre con el nombre de la empresa
                }
            });
        });
    </script>
    <script>
        $(function() {
            moment.locale('es'); // para nombres de meses en español (ene, feb, mar...)

            const $picker = $('#fecharango');
            const $iniHidden = $('#fechaini');
            const $finHidden = $('#fechafin');
            const $iniDisp = $('#fechaini_display');
            const $finDisp = $('#fechafin_display');

            // Lee old() si viene del servidor
            // Admitimos ambos formatos: ISO (YYYY-MM-DD) o "DD-MMM-YYYY"
            function parseAny(d) {
                if (!d) return null;
                const m = moment(d, ['YYYY-MM-DD', 'DD-MMM-YYYY', moment.ISO_8601], true);
                return m.isValid() ? m : null;
            }

            let oldIni = parseAny(@json(old('fechaini')));
            let oldFin = parseAny(@json(old('fechafin')));

            // Defaults si no hay old()
            let start = oldIni || moment().startOf('day');
            let end = oldFin || moment().add(1, 'day').startOf('day');

            // Función para sincronizar visibles (dd-mmm-yyyy) y ocultos (YYYY-MM-DD)
            function syncFields(s, e) {
                // Visibles
                $iniDisp.val(s.format('DD-MMM-YYYY'));
                $finDisp.val(e.format('DD-MMM-YYYY'));
                // Ocultos (lo que se envía al backend)
                $iniHidden.val(s.format('YYYY-MM-DD'));
                $finHidden.val(e.format('YYYY-MM-DD'));
                // Visual en el input del picker
                $picker.val(s.format('DD-MMM-YYYY') + ' - ' + e.format('DD-MMM-YYYY'));
            }

            // Init daterangepicker (solo fecha)
            $picker.daterangepicker({
                startDate: start,
                endDate: end,
                autoUpdateInput: true,
                timePicker: false, // ✅ sin hora
                locale: {
                    format: 'DD-MMM-YYYY', // ✅ dd-mmm-yyyy (ej: 05-ago-2025)
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
                // Cuando el usuario confirma, sincronizamos todo
                syncFields(startSel.startOf('day'), endSel.startOf('day'));
            });

            // Inicializa valores en pantalla y ocultos
            syncFields(start, end);

            // Si el usuario cancela el rango
            $picker.on('cancel.daterangepicker', function() {
                $(this).val('');
                $iniDisp.val('');
                $finDisp.val('');
                $iniHidden.val('');
                $finHidden.val('');
            });
        });
    </script>
@endpush
