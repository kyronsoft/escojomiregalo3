@extends('layouts.admin.master')

@section('title', 'Colaboradores de la campaña')

@push('css')
    <style>
        #collaborators-table .tabulator-row {
            min-height: 56px;
        }

        .badge {
            font-size: .75rem;
        }

        .actions-bar {
            gap: .5rem;
        }

        @media (max-width: 576px) {
            .actions-bar {
                flex-direction: column;
                align-items: stretch !important;
            }
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <div>
                <h3 class="mb-0">Colaboradores asignados</h3>
                <div class="text-muted">
                    Campaña: <strong>#{{ $campaign->id }}</strong> ·
                    Nombre: <strong>{{ $campaign->nombre }}</strong> ·
                    NIT: <strong>{{ $campaign->nit }}</strong>
                </div>
            </div>

            <div class="d-flex align-items-center actions-bar">
                {{-- Select Plantilla --}}
                <div class="d-flex align-items-center">
                    <label for="plantilla" class="me-2 mb-0">Plantilla</label>
                    <select id="plantilla" class="form-select form-select-sm">
                        <option value="standard">Estándar</option>
                        <option value="juguetes">Juguetes</option>
                        <option value="navidad">Navidad</option>
                    </select>
                </div>

                {{-- Enviar correo a todos --}}
                <button type="button" id="btn-send-all" class="btn btn-sm btn-success">
                    Enviar correo a todos
                </button>

                <a href="{{ route('campaigns.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
            </div>
        </div>

        {{-- Flash --}}
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div id="collaborators-table"></div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('assets/js/blockui/jquery.blockUI.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/tabulator-tables@5.5.2/dist/js/tabulator.min.js"></script>
    <script>
        (function() {
            const dataURL = `{{ route('campaigns.collaborators.data', $campaign) }}`;
            const sendAllURL = `{{ route('campaigns.collaborators.emailAll', $campaign) }}`;
            const sendOneURL = `{{ route('campaigns.collaborators.emailOne', $campaign) }}`;
            const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                '{{ csrf_token() }}';

            function formatDate(v) {
                const d = new Date(v);
                return isNaN(d) ? (v || '') : d.toLocaleString();
            }

            function actionBtnFormatter(cell) {
                const r = cell.getRow().getData();
                const docEnc = encodeURIComponent(String(r.documento || ''));
                return `
                    <div class="d-flex gap-1 justify-content-center">
                        <button class="btn btn-sm btn-outline-primary" onclick="sendOne('${docEnc}')">
                            Reenviar correo
                        </button>
                    </div>
                `;
            }

            const columns = [{
                    title: "Documento",
                    field: "documento",
                    width: 140,
                    headerFilter: "input"
                },
                {
                    title: "Nombre",
                    field: "nombre",
                    minWidth: 220,
                    headerFilter: "input"
                },
                {
                    title: "Email",
                    field: "email",
                    minWidth: 220,
                    headerFilter: "input",
                    formatter: cell => {
                        const v = cell.getValue() || '';
                        return v ? `<a href="mailto:${v}">${v}</a>` : '';
                    }
                },
                {
                    title: "Sucursal",
                    field: "sucursal",
                    width: 160,
                    headerFilter: "input"
                },
                {
                    title: "Notificado",
                    field: "email_notified",
                    width: 120,
                    hozAlign: "center",
                    formatter: c => c.getValue() ?
                        '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>',
                },
                {
                    title: "NIT",
                    field: "nit",
                    width: 110,
                    headerFilter: "input"
                },
                {
                    title: "Asignado",
                    field: "created_at",
                    width: 170,
                    formatter: c => formatDate(c.getValue())
                },
                {
                    title: "Actualizado",
                    field: "updated_at",
                    width: 170,
                    formatter: c => formatDate(c.getValue())
                },
                {
                    title: "Acciones",
                    field: "_actions",
                    width: 160,
                    headerSort: false,
                    formatter: actionBtnFormatter,
                    hozAlign: "center"
                },
            ];

            const table = new Tabulator("#collaborators-table", {
                layout: "fitColumns",
                height: "600px",
                responsiveLayout: "collapse",
                placeholder: "No hay colaboradores asignados a esta campaña",
                ajaxURL: dataURL,
                ajaxConfig: "GET",
                pagination: false,
                sortMode: "local",
                filterMode: "local",
                ajaxResponse: (url, params, resp) => Array.isArray(resp) ? resp : [],
                initialSort: [{
                    column: "nombre",
                    dir: "asc"
                }],
                columns,
            });

            // Exponer función global para el botón por fila
            window.sendOne = function(docEnc) {
                const documento = decodeURIComponent(docEnc);
                const plantilla = ($('#plantilla').val() || 'standard');

                // Buscar la fila para mostrar datos al usuario
                const row = (table.getRows() || []).find(r => (r.getData()?.documento || '') == documento);
                const rowData = row ? row.getData() : {};
                const nombre = rowData?.nombre || documento;
                const email = rowData?.email || '';

                Swal.fire({
                    title: '¿Reenviar correo?',
                    html: `Se enviará la plantilla <b>${$('#plantilla option:selected').text()}</b> a:<br>
                           <b>${nombre}</b><br><small>${email || 'sin email'}</small>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, reenviar',
                    cancelButtonText: 'Cancelar'
                }).then(res => {
                    if (!res.isConfirmed) return;

                    $.blockUI({
                        message: '<div class="p-3"><div class="spinner-border" role="status"></div><div class="mt-2">Encolando correo...</div></div>',
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

                    fetch(sendOneURL, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': CSRF,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                documento,
                                plantilla
                            })
                        })
                        .then(async r => {
                            $.unblockUI();
                            const payload = await (async () => {
                                try {
                                    return await r.json();
                                } catch {
                                    return {};
                                }
                            })();

                            if (r.ok) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Envío de Correo',
                                    text: payload.message ||
                                        'El sistema solo procesa los envíos individuales inmediatamente!.'
                                });

                                // Si el backend devuelve flag 'notified' o similar, actualizamos la fila; si no, recargamos
                                if (row && (payload.notified === true || payload.updated_row)) {
                                    // Actualizamos email_notified y updated_at localmente
                                    row.update({
                                        email_notified: 1,
                                        updated_at: (new Date()).toISOString()
                                    });
                                } else {
                                    table.replaceData();
                                }
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error al enviar',
                                    text: payload.message || 'No se pudo encolar el correo.'
                                });
                            }
                        })
                        .catch(() => {
                            $.unblockUI();
                            Swal.fire({
                                icon: 'error',
                                title: 'Error de red',
                                text: 'No fue posible contactar el servidor.'
                            });
                        });
                });
            };

            // Enviar correo a todos
            $('#btn-send-all').on('click', function() {
                const plantilla = ($('#plantilla').val() || 'standard');
                const total = (table.getData() || []).length;

                if (!total) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Sin colaboradores',
                        text: 'No hay colaboradores para notificar.'
                    });
                    return;
                }

                Swal.fire({
                    title: '¿Enviar correos?',
                    html: `Se enviará la plantilla <b>${$('#plantilla option:selected').text()}</b> a <b>${total}</b> colaborador(es).`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, enviar',
                    cancelButtonText: 'Cancelar'
                }).then(res => {
                    if (!res.isConfirmed) return;

                    $.blockUI({
                        message: '<div class="p-3"><div class="spinner-border" role="status"></div><div class="mt-2">Enviando correos...</div></div>',
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

                    fetch(sendAllURL, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': CSRF,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                plantilla
                            })
                        })
                        .then(async r => {
                            $.unblockUI();
                            const payload = await (async () => {
                                try {
                                    return await r.json();
                                } catch {
                                    return {};
                                }
                            })();

                            if (r.ok) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Correos encolados',
                                    text: payload.message ||
                                        'Se inició el envío de correos.'
                                });
                                table.replaceData();
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error al enviar',
                                    text: payload.message ||
                                        'Ocurrió un problema al iniciar el envío.'
                                });
                            }
                        })
                        .catch(() => {
                            $.unblockUI();
                            Swal.fire({
                                icon: 'error',
                                title: 'Error de red',
                                text: 'No fue posible contactar el servidor.'
                            });
                        });
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

            window.addEventListener('resize', () => table.redraw(true));
        })();
    </script>
@endpush
