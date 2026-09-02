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

            // Si hay cookie XSRF-TOKEN => usar header X-XSRF-TOKEN.
            // Si no, fallback a meta csrf-token => usar X-CSRF-TOKEN.
            function csrfHeader() {
                const xsrfCookie = getCookie('XSRF-TOKEN'); // Laravel la firma y rota
                if (xsrfCookie) {
                    return { name: 'X-XSRF-TOKEN', value: decodeURIComponent(xsrfCookie) };
                }
                const meta = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
                return { name: 'X-CSRF-TOKEN', value: meta };
            }

            function commonHeaders(json = true) {
                const h = csrfHeader();
                const base = {
                    'X-Requested-With': 'XMLHttpRequest',
                    [h.name]: h.value
                };
                return json
                    ? { ...base, 'Content-Type': 'application/json', 'Accept': 'application/json' }
                    : base;
            }

            // Reintento suave si el token rotó entre renders (estatus 419)
            async function retryAfterCsrf(url, init, isJson) {
                try {
                    // Fuerza refrescar cookies/headers desde el mismo origen
                    await fetch(window.location.href, { credentials: 'same-origin', cache: 'no-store' });
                } catch {}
                const newInit = { ...init, headers: commonHeaders(!!isJson) };
                return fetch(url, newInit);
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
            const dataURL         = `{{ route('campaigns.collaborators.data', $campaign) }}`;
            const sendAllURL      = `{{ route('campaigns.collaborators.emailAll', $campaign) }}`;
            const sendOneURL      = `{{ route('campaigns.collaborators.emailOne', $campaign) }}`;
            const updateOneURL    = `{{ route('campaigns.collaborators.updateOne', $campaign) }}`;
            const toggleNotifyURL = `{{ route('campaigns.collaborators.toggleNotify', $campaign) }}`;
            const destroyTpl      = `{{ route('campaigns.collaborators.destroy', ['campaign' => $campaign, 'documento' => '__DOC__']) }}`;

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

            function notifyToggleFormatter(cell) {
                if (IS_EXEC) {
                    const v = cell.getValue();
                    return v
                        ? '<span class="badge bg-success">Habilitada</span>'
                        : '<span class="badge bg-secondary">Deshabilitada</span>';
                }
                const r = cell.getRow().getData();
                const doc = String(r.documento || '');
                const docEnc = encodeURIComponent(doc);
                const enabled = cell.getValue();
                const label = enabled ? 'Habilitada' : 'Deshabilitada';
                const cls   = enabled ? 'btn-success' : 'btn-secondary';
                return `<button class="btn btn-sm ${cls}" onclick="toggleNotify('${docEnc}', ${enabled ? 0 : 1})">${label}</button>`;
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
                {
                    title: 'Notificación',
                    field: 'notify_enabled',
                    width: 150,
                    hozAlign: 'center',
                    formatter: notifyToggleFormatter,
                    headerSort: false,
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

            // -------- Activar/desactivar notificación --------
            window.toggleNotify = function (docEnc, newValue) {
                if (IS_EXEC) return;
                const documento = decodeURIComponent(docEnc);
                const row = (table.getRows() || []).find(r => (r.getData()?.documento || '') == documento);
                const d = row ? row.getData() : {};
                const accion = newValue ? 'habilitar' : 'deshabilitar';

                Swal.fire({
                    title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} notificación?`,
                    html: `Se va a <b>${accion}</b> la notificación para:<br><b>${d?.nombre || documento}</b>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Confirmar',
                    cancelButtonText: 'Cancelar'
                }).then(res => {
                    if (!res.isConfirmed) return;

                    const init = {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: commonHeaders(true),
                        body: JSON.stringify({ documento, notify_enabled: newValue })
                    };

                    fetch(toggleNotifyURL, init)
                    .then(async r => {
                        if (r.status === 419) {
                            const r2 = await retryAfterCsrf(toggleNotifyURL, init, true);
                            if (!r2.ok) { showExpiredAndSuggestReload(); return; }
                            if (row) row.update({ notify_enabled: !!newValue });
                            await reloadTable();
                            return;
                        }
                        const { data } = await parseResponseSafe(r);
                        if (r.ok) {
                            if (row) row.update({ notify_enabled: !!newValue });
                            await reloadTable();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: (data && data.message) || 'No se pudo actualizar.' });
                        }
                    })
                    .catch(() => {
                        Swal.fire({ icon: 'error', title: 'Error de red', text: 'No fue posible contactar el servidor.' });
                    });
                });
            };

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

                    const init = {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: commonHeaders(true),
                        body: JSON.stringify({ documento, plantilla })
                    };

                    fetch(sendOneURL, init)
                    .then(async r => {
                        if (r.status === 419) {
                            // reintento por posible rotación de token
                            const r2 = await retryAfterCsrf(sendOneURL, init, true);
                            if (!r2.ok) {
                                $.unblockUI();
                                showExpiredAndSuggestReload();
                                return;
                            }
                            const { data: d2, txt: t2 } = await parseResponseSafe(r2);
                            $.unblockUI();
                            if (row) row.update({ email_notified: 1, updated_at: new Date().toISOString() });
                            await reloadTable();
                            Swal.fire({ icon: 'success', title: 'Envío de Correo', text: (d2 && (d2.message||d2.msg)) || 'El sistema procesó el envío.' });
                            return;
                        }

                        const { data, txt } = await parseResponseSafe(r);
                        $.unblockUI();

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

                    const init = {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: commonHeaders(true),
                        body: JSON.stringify(payload)
                    };

                    fetch(updateOneURL, init)
                    .then(async r => {
                        if (r.status === 419) {
                            const r2 = await retryAfterCsrf(updateOneURL, init, true);
                            if (!r2.ok) { $.unblockUI(); showExpiredAndSuggestReload(); return; }
                            const { data: d2 } = await parseResponseSafe(r2);
                            $.unblockUI();
                            if (row && d2 && d2.updated_row) row.update(d2.updated_row);
                            else if (row) row.update({ ...payload, updated_at: new Date().toISOString() });
                            await reloadTable();
                            Swal.fire({ icon: 'success', title: 'Actualizado', text: (d2 && (d2.message||d2.msg)) || 'Datos guardados correctamente.' });
                            return;
                        }

                        const { data, txt } = await parseResponseSafe(r);
                        $.unblockUI();

                        if (r.ok) {
                            if (row && data && data.updated_row) {
                                row.update(data.updated_row)
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

                    const init = {
                        method: 'DELETE',
                        credentials: 'same-origin',
                        headers: commonHeaders(false)
                    };

                    fetch(delUrl, init)
                    .then(async r => {
                        if (r.status === 419) {
                            const r2 = await retryAfterCsrf(delUrl, init, false);
                            if (!r2.ok) { $.unblockUI(); showExpiredAndSuggestReload(); return; }
                            const { data: d2, txt: t2 } = await parseResponseSafe(r2);
                            $.unblockUI();
                            if (r2.ok && d2 && d2.ok) {
                                if (row) row.delete();
                                else await reloadTable();
                                Swal.fire({ icon: 'success', title: 'Eliminado', text: d2.message || 'Colaborador quitado de la campaña.' });
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: (d2 && (d2.message||d2.error||d2.msg)) || (t2 && t2.trim()) || 'No se pudo eliminar.' });
                            }
                            return;
                        }

                        const { data, txt } = await parseResponseSafe(r);
                        $.unblockUI();

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
                const allData = table.getData() || [];
                const habilitados = allData.filter(r => r.notify_enabled).length;
                if (!habilitados) {
                    Swal.fire({ icon: 'info', title: 'Sin colaboradores', text: 'No hay colaboradores con notificación habilitada para enviar.' });
                    return;
                }

                Swal.fire({
                    title: '¿Enviar correos?',
                    html: `Se enviará la plantilla <b>${$('#plantilla option:selected').text()}</b> a <b>${habilitados}</b> colaborador(es) con notificación habilitada.`,
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

                    const init = {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: commonHeaders(true),
                        body: JSON.stringify({ plantilla })
                    };

                    fetch(sendAllURL, init)
                    .then(async r => {
                        if (r.status === 419) {
                            const r2 = await retryAfterCsrf(sendAllURL, init, true);
                            if (!r2.ok) { $.unblockUI(); showExpiredAndSuggestReload(); return; }
                            const { data: d2, txt: t2 } = await parseResponseSafe(r2);
                            $.unblockUI();
                            Swal.fire({
                                icon: 'success',
                                title: 'Correos encolados',
                                text: (d2 && (d2.message || d2.msg)) || 'Se inició el envío de correos.'
                            });
                            reloadTable();
                            return;
                        }

                        const { data, txt } = await parseResponseSafe(r);
                        $.unblockUI();

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
