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
    @php
        $isExec = auth()->user()->hasRole('Ejecutiva-Empresas');
    @endphp

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <div>
                <h3 class="mb-0">Colaboradores asignados</h3>
                <div class="text-muted mt-3">
                    Campaña: <strong>#{{ $campaign->id }}</strong> ·
                    Nombre: <strong>{{ $campaign->nombre }}</strong> ·
                    NIT: <strong>{{ $campaign->nit }}</strong>
                </div>
            </div>

            <div class="d-flex align-items-center actions-bar mt-5">
                <div class="d-flex align-items-center">
                    <label for="plantilla" class="me-2 mb-0">Plantilla</label>
                    <select id="plantilla" class="form-select form-select-sm">
                        <option value="standard">Estándar</option>
                        <option value="juguetes">Juguetes</option>
                        <option value="navidad">Navidad</option>
                    </select>
                </div>

                @unless ($isExec)
                    <button type="button" id="btn-send-all" class="btn btn-sm btn-success">
                        Enviar correo a todos
                    </button>
                @endunless

                <a href="{{ route('campaigns.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
            </div>
        </div>

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
        (function () {
            // ========= Helpers CSRF / Cookies =========
            function getCookie(name) {
                return document.cookie
                    .split('; ')
                    .find(row => row.startsWith(name + '='))
                    ?.split('=')[1];
            }

            function getCsrfToken() {
                // Prioriza cookie XSRF-TOKEN (Laravel la publica y rota según sesión)
                const fromCookie = getCookie('XSRF-TOKEN');
                if (fromCookie) return decodeURIComponent(fromCookie);
                // Fallback a meta y luego a blade token render-time
                const fromMeta = document.querySelector('meta[name="csrf-token"]')?.content;
                return fromMeta || '{{ csrf_token() }}';
            }

            // ========= Utilitarios UI =========
            function parseResponseSafe(r) {
                return (async () => {
                    let d = null, t = null;
                    try { d = await r.clone().json() } catch {}
                    if (!d) {
                        try { t = await r.text() } catch {}
                    }
                    return { data: d, txt: t }
                })()
            }

            function showExpiredAndSuggestReload() {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sesión expirada',
                    text: 'Por seguridad, tu sesión ha expirado. Actualiza la página e inténtalo nuevamente.'
                });
            }

            // ========= Rutas =========
            const dataURL     = `{{ route('campaigns.collaborators.data', $campaign) }}`;
            const sendAllURL  = `{{ route('campaigns.collaborators.emailAll', $campaign) }}`;
            const sendOneURL  = `{{ route('campaigns.collaborators.emailOne', $campaign) }}`;
            const updateOneURL= `{{ route('campaigns.collaborators.updateOne', $campaign) }}`;
            const destroyTpl  =
                `{{ route('campaigns.collaborators.destroy', ['campaign' => $campaign, 'documento' => '__DOC__']) }}`;

            const IS_EXEC = @json($isExec);

            function reloadTable() {
                return table.setData(dataURL)
                    .then(() => table.redraw(true))
                    .catch(() => {
                        try {
                            table.clearData();
                            table.setData(dataURL)
                        } catch {}
                    })
            }

            function actionBtnFormatter(cell) {
                const r = cell.getRow().getData();
                const doc = String(r.documento || '');
                const docEnc = encodeURIComponent(doc);

                const resendBtn = IS_EXEC ? '' :
                    `<button class="btn btn-sm btn-outline-primary" onclick="sendOne('${docEnc}')">Reenviar</button>`;

                const editBtn =
                    `<button class="btn btn-sm btn-outline-secondary" onclick="editOne('${docEnc}')">Editar</button>`;

                const delBtn = IS_EXEC ? '' :
                    `<button class="btn btn-sm btn-outline-danger" onclick="deleteOne('${docEnc}')">Eliminar</button>`;

                return `<div class="d-flex gap-1 justify-content-center flex-wrap">${resendBtn}${editBtn}${delBtn}</div>`;
            }

            const columns = [
                { title: 'Documento', field: 'documento', width: 140, headerFilter: 'input' },
                { title: 'Nombre', field: 'nombre', minWidth: 220, headerFilter: 'input' },
                {
                    title: 'Email',
                    field: 'email',
                    minWidth: 220,
                    headerFilter: 'input',
                    formatter: c => {
                        const v = c.getValue() || '';
                        return v ? `<a href="mailto:${v}">${v}</a>` : '';
                    }
                },
                { title: 'Sucursal', field: 'sucursal', width: 160, headerFilter: 'input' },
                {
                    title: 'Notificado',
                    field: 'email_notified',
                    width: 120,
                    hozAlign: 'center',
                    formatter: c => c.getValue()
                        ? '<span class="badge bg-success">Sí</span>'
                        : '<span class="badge bg-secondary">No</span>'
                },
                { title: 'NIT', field: 'nit', width: 110, headerFilter: 'input' },
                {
                    title: 'Acciones',
                    field: '_actions',
                    headerSort: false,
                    hozAlign: 'center',
                    formatter: actionBtnFormatter,
                    minWidth: 260,
                    widthGrow: 0,
                    responsive: 0
                },
            ];

            const table = new Tabulator('#collaborators-table', {
                layout: 'fitColumns',
                layoutColumnsOnNewData: true,
                responsiveLayout: 'collapse',
                responsiveLayoutCollapseStartOpen: false,
                responsiveLayoutCollapseUseFormatters: true,
                height: '600px',
                placeholder: 'No hay colaboradores asignados a esta campaña',
                ajaxURL: dataURL,
                ajaxConfig: 'GET',
                ajaxResponse: (url, params, resp) => Array.isArray(resp) ? resp : [],
                sortMode: 'local',
                filterMode: 'local',
                pagination: true,
                paginationMode: 'local',
                paginationSize: 15,
                paginationSizeSelector: [10, 15, 25, 50, 100],
                paginationCounter: 'rows',
                columns,
                initialSort: [{ column: 'nombre', dir: 'asc' }],
            });

            window.addEventListener('resize', () => table.redraw(true));

            // -------- Reenviar correo --------
            window.sendOne = function (docEnc) {
                if (IS_EXEC) return;
                const documento = decodeURIComponent(docEnc);
                const plantilla = ($('#plantilla').val() || 'standard');
                const row = (table.getRows() || []).find(r => (r.getData()?.documento || '') == documento);
                const d = row ? row.getData() : {};

                Swal.fire({
                    title: '¿Reenviar correo?',
                    html: `Se enviará la plantilla <b>${$('#plantilla option:selected').text()}</b> a:<br><b>${d?.nombre||documento}</b><br><small>${d?.email||'sin email'}</small>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, reenviar',
                    cancelButtonText: 'Cancelar'
                }).then(res => {
                    if (!res.isConfirmed) return;

                    $.blockUI({
                        message: '<div class="p-3"><div class="spinner-border" role="status"></div><div class="mt-2">Encolando correo...</div></div>',
                        css: { border: 'none', padding: '15px', background: '#000', opacity: .6, color: '#fff', borderRadius: '8px' },
                        baseZ: 2000
                    });

                    fetch(sendOneURL, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ documento, plantilla })
                    })
                    .then(async r => {
                        $.unblockUI();
                        const { data, txt } = await parseResponseSafe(r);

                        if (r.status === 419) {
                            showExpiredAndSuggestReload();
                            return;
                        }

                        if (r.ok) {
                            if (row) row.update({ email_notified: 1, updated_at: new Date().toISOString() });
                            await reloadTable();
                            Swal.fire({
                                icon: 'success',
                                title: 'Envío de Correo',
                                text: (data && (data.message || data.msg)) || 'El sistema procesó el envío.'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error al enviar',
                                text: (data && (data.message || data.error || data.msg)) || (txt && txt.trim()) || 'No se pudo encolar el correo.'
                            });
                        }
                    })
                    .catch(() => {
                        $.unblockUI();
                        Swal.fire({ icon: 'error', title: 'Error de red', text: 'No fue posible contactar el servidor.' });
                    });
                });
            };

            // -------- Editar --------
            window.editOne = function (docEnc) {
                const documento = decodeURIComponent(docEnc);
                const row = (table.getRows() || []).find(r => (r.getData()?.documento || '') == documento);
                const d = row ? row.getData() : {};
                const html = `
                    <div class="text-start">
                        <div class="mb-2"><label class="form-label">Nombre</label>
                            <input id="swal-nombre" class="form-control" type="text" value="${(d.nombre||'').replace(/"/g,'&quot;')}">
                        </div>
                        <div class="mb-2"><label class="form-label">Email</label>
                            <input id="swal-email" class="form-control" type="email" value="${(d.email||'').replace(/"/g,'&quot;')}" placeholder="correo@dominio.com">
                        </div>
                        <div class="mb-2"><label class="form-label">Sucursal</label>
                            <input id="swal-sucursal" class="form-control" type="text" value="${(d.sucursal||'').replace(/"/g,'&quot;')}">
                        </div>
                    </div>`;

                Swal.fire({
                    title: 'Editar colaborador',
                    html,
                    focusConfirm: false,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar cambios',
                    cancelButtonText: 'Cancelar',
                    preConfirm: () => {
                        const nombre   = document.getElementById('swal-nombre').value.trim();
                        const email    = document.getElementById('swal-email').value.trim();
                        const sucursal = document.getElementById('swal-sucursal').value.trim();
                        if (!nombre) return Swal.showValidationMessage('El nombre es obligatorio');
                        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return Swal.showValidationMessage('El email no es válido');
                        return { nombre, email, sucursal };
                    }
                }).then(res => {
                    if (!res.isConfirmed) return;
                    const payload = { documento, ...res.value };

                    $.blockUI({
                        message: '<div class="p-3"><div class="spinner-border" role="status"></div><div class="mt-2">Guardando cambios...</div></div>',
                        css: { border: 'none', padding: '15px', background: '#000', opacity: .6, color: '#fff', borderRadius: '8px' },
                        baseZ: 2000
                    });

                    fetch(updateOneURL, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(async r => {
                        $.unblockUI();
                        const { data, txt } = await parseResponseSafe(r);

                        if (r.status === 419) {
                            showExpiredAndSuggestReload();
                            return;
                        }

                        if (r.ok) {
                            if (row && data && data.row) {
                                row.update(data.row)
                            } else if (row) {
                                row.update({ ...payload, updated_at: new Date().toISOString() })
                            }
                            await reloadTable();
                            Swal.fire({
                                icon: 'success',
                                title: 'Actualizado',
                                text: (data && (data.message || data.msg)) || 'Datos guardados correctamente.'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'No se pudo actualizar',
                                text: (data && (data.message || data.error || data.msg)) || (txt && txt.trim()) || 'Inténtalo más tarde.'
                            });
                        }
                    })
                    .catch(() => {
                        $.unblockUI();
                        Swal.fire({ icon: 'error', title: 'Error de red', text: 'No fue posible contactar el servidor.' });
                    });
                });
            };

            // -------- Eliminar --------
            window.deleteOne = function (docEnc) {
                if (IS_EXEC) return;
                const documento = decodeURIComponent(docEnc);
                const row = (table.getRows() || []).find(r => (r.getData()?.documento || '') == documento);
                const d = row ? row.getData() : {};
                const delUrl = destroyTpl.replace('__DOC__', encodeURIComponent(documento));

                Swal.fire({
                    title: 'Eliminar colaborador',
                    html: `¿Quitar a <b>${(d?.nombre||documento)}</b> de esta campaña?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then(res => {
                    if (!res.isConfirmed) return;

                    $.blockUI({
                        message: '<div class="p-3"><div class="spinner-border" role="status"></div><div class="mt-2">Eliminando...</div></div>',
                        css: { border: 'none', padding: '15px', background: '#000', opacity: .6, color: '#fff', borderRadius: '8px' },
                        baseZ: 2000
                    });

                    fetch(delUrl, {
                        method: 'DELETE',
                        credentials: 'same-origin',
                        headers: {
                            'X-CSRF-TOKEN': getCsrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                    .then(async r => {
                        $.unblockUI();
                        const { data, txt } = await parseResponseSafe(r);

                        if (r.status === 419) {
                            showExpiredAndSuggestReload();
                            return;
                        }

                        if (r.ok && data && data.ok) {
                            if (row) row.delete();
                            else await reloadTable();
                            Swal.fire({
                                icon: 'success',
                                title: 'Eliminado',
                                text: data.message || 'Colaborador quitado de la campaña.'
                            });
                        } else {
                            const msg = (data && (data.message || data.error || data.msg)) || (txt && txt.trim()) || 'No se pudo eliminar.';
                            Swal.fire({ icon: 'error', title: 'Error', text: msg });
                        }
                    })
                    .catch(() => {
                        $.unblockUI();
                        Swal.fire({ icon: 'error', title: 'Error de red', text: 'No fue posible contactar el servidor.' });
                    });
                });
            };

            // -------- Enviar a todos --------
            $('#btn-send-all').on('click', function () {
                if (IS_EXEC) return;
                const plantilla = ($('#plantilla').val() || 'standard');
                const total = (table.getData() || []).length;
                if (!total) {
                    Swal.fire({ icon: 'info', title: 'Sin colaboradores', text: 'No hay colaboradores para notificar.' });
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
                        css: { border: 'none', padding: '15px', background: '#000', opacity: .6, color: '#fff', borderRadius: '8px' },
                        baseZ: 2000
                    });

                    fetch(sendAllURL, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ plantilla })
                    })
                    .then(async r => {
                        $.unblockUI();
                        const { data, txt } = await parseResponseSafe(r);

                        if (r.status === 419) {
                            showExpiredAndSuggestReload();
                            return;
                        }

                        if (r.ok) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Correos encolados',
                                text: (data && (data.message || data.msg)) || 'Se inició el envío de correos.'
                            });
                            reloadTable();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error al enviar',
                                text: (data && (data.message || data.error || data.msg)) || (txt && txt.trim()) || 'Ocurrió un problema.'
                            });
                        }
                    })
                    .catch(() => {
                        $.unblockUI();
                        Swal.fire({ icon: 'error', title: 'Error de red', text: 'No fue posible contactar el servidor.' });
                    });
                });
            });

            // Mensajería por sesión (opcional)
            @if (session('success'))
                Swal.fire({ icon: 'success', title: 'Éxito', text: @json(session('success')) });
            @endif
            @if (session('error'))
                Swal.fire({ icon: 'error', title: 'Error', text: @json(session('error')) });
            @endif
        })();
    </script>
@endpush
