@extends('layouts.admin.master')

@section('title', 'Juguetes / Combos de campaña')

@push('css')
    <link rel="stylesheet" href="https://unpkg.com/tabulator-tables@5.5.2/dist/css/tabulator.min.css">
    <style>
        #toys-table .tabulator-row {
            min-height: 68px;
        }

        .toy-thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: .5rem;
            background: #f5f5f5;
        }

        .toy-thumb-sm {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: .5rem;
            background: #f5f5f5;
        }

        .row-sin-unidades {
            background-color: #fff1f0 !important;
        }

        .badge-sin-unidades {
            font-size: .7rem;
            vertical-align: middle;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <h3 class="mb-0">Juguetes / Combos — {{ $campaign->nombre }} (ID {{ $campaign->id }})</h3>
            <a href="{{ route('campaigns.index') }}" class="btn btn-outline-secondary btn-sm">Volver a campañas</a>
        </div>

        <div id="toys-table"></div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('assets/js/blockui/jquery.blockUI.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/tabulator-tables@5.5.2/dist/js/tabulator.min.js"></script>
    <script>
        (function() {
            const dataUrl = @json(route('campaigns.toys.data', $campaign));
            const placeholder = @json(asset('assets/images/placeholder.png'));
            const IS_EXEC = @json(auth()->user()->hasRole('Ejecutiva-Empresas')); // 👈 Oculta acciones a este rol
            const CSRF = @json(csrf_token());

            // Plantilla de URLs para editar/eliminar
            const editUrlTemplate =
                `{{ route('campaigns.toys.edit', ['campaign' => $campaign->id, 'toy' => '__ID__']) }}`;
            const destroyUrlTemplate =
                `{{ route('campaigns.toys.destroy', ['campaign' => $campaign->id, 'toy' => '__ID__']) }}`;

            function actionFormatter(cell) {
                const row = cell.getRow().getData();
                const id = row.id;

                if (IS_EXEC) return ''; // Ejecutiva-Empresas sin acciones

                const editUrl = editUrlTemplate.replace('__ID__', id);

                return `
                    <div class="d-flex gap-1 justify-content-center">
                        <a href="${editUrl}" class="btn btn-sm btn-primary" title="Editar">
                            Editar
                        </a>
                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"
                            onclick="deleteToy(${id}, '${(row.nombre || '').replace(/'/g, "\\'")}')">
                            Eliminar
                        </button>
                    </div>`;
            }

            const columns = [{
                    title: "ID",
                    field: "id",
                    width: 80
                },
                {
                    title: "Ref.",
                    field: "referencia",
                    width: 140,
                    headerFilter: "input"
                },
                {
                    title: "Nombre",
                    field: "nombre",
                    minWidth: 240,
                    headerFilter: "input",
                    formatter: cell => {
                        const v = cell.getValue() || '';
                        return `<div class="text-truncate" title="${v}">${v}</div>`;
                    }
                },
                {
                    title: "Imagen",
                    field: "image_urls",
                    width: 160,
                    hozAlign: "center",
                    headerSort: false,
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const urls = Array.isArray(row.image_urls) ? row.image_urls : [];
                        const partsCount = Number(row.image_parts_count || 0);

                        if (partsCount === 2) {
                            const src1 = urls[0] || placeholder;
                            const src2 = urls[1] || placeholder;
                            return `
                                <div class="d-inline-flex align-items-center gap-1">
                                    <img src="${src1}" class="toy-thumb-sm" alt="img1"
                                        onerror="this.onerror=null; this.src='${placeholder}';">
                                    <img src="${src2}" class="toy-thumb-sm" alt="img2"
                                        onerror="this.onerror=null; this.src='${placeholder}';">
                                </div>`;
                        }

                        if (partsCount >= 3) {
                            const first = urls[0] || placeholder;
                            const badge =
                                `<span class="badge bg-secondary ms-1 align-middle">+${partsCount - 1}</span>`;
                            return `
                                <div class="d-inline-flex align-items-center">
                                    <img src="${first}" class="toy-thumb" alt="thumb"
                                        onerror="this.onerror=null; this.src='${placeholder}';">
                                    ${badge}
                                </div>`;
                        }

                        const one = urls[0] || placeholder;
                        return `
                            <div class="d-inline-flex align-items-center">
                                <img src="${one}" class="toy-thumb" alt="thumb"
                                    onerror="this.onerror=null; this.src='${placeholder}';">
                            </div>`;
                    }
                },
                {
                    title: "Género",
                    field: "genero",
                    width: 110,
                    headerFilter: "input"
                },
                {
                    title: "Unid.",
                    field: "unidades",
                    width: 120,
                    hozAlign: "right",
                    formatter: function(cell) {
                        const val = cell.getValue() ?? 0;
                        if (val <= 0) {
                            return `<span class="text-danger fw-semibold">${val}</span>
                                <span class="badge bg-danger badge-sin-unidades ms-1">Sin unidades</span>`;
                        }
                        return String(val);
                    }
                },
                {
                    title: "Precio",
                    field: "precio_unitario",
                    width: 110,
                    hozAlign: "right",
                    formatter: cell => new Intl.NumberFormat().format(cell.getValue() ?? 0)
                },
                {
                    title: "% / Sel.",
                    field: "porcentaje",
                    width: 110,
                    headerFilter: "input",
                    formatter: c => c.getValue() || '0'
                },
            ];

            // 👉 Columna Acciones si NO es Ejecutiva-Empresas
            if (!IS_EXEC) {
                columns.push({
                    title: "Acciones",
                    field: "_actions",
                    hozAlign: "center",
                    headerSort: false,
                    width: 180,
                    formatter: actionFormatter,
                    responsive: 0 // última en colapsarse
                });
            }

            const table = new Tabulator("#toys-table", {
                layout: "fitColumns",
                columnDefaults: {
                    minWidth: 100,
                    headerSort: true
                },

                responsiveLayout: "collapse",
                responsiveLayoutCollapseStartOpen: false,
                responsiveLayoutCollapseUseFormatters: true, // 👈 mantiene botones en vista colapsada

                height: "600px",
                rowHeight: 68,
                placeholder: "No hay registros",

                ajaxURL: dataUrl,
                ajaxConfig: "GET",
                ajaxResponse: (url, params, resp) => Array.isArray(resp) ? resp : [],

                pagination: true,
                paginationMode: "local",
                paginationSize: 10,
                paginationSizeSelector: [10, 25, 50, 100],
                paginationCounter: "rows",

                columns,
                initialSort: [{
                    column: "updated_at",
                    dir: "desc"
                }],
                rowFormatter: function(row) {
                    const data = row.getData();
                    if ((data.unidades ?? 1) <= 0) {
                        row.getElement().classList.add('row-sin-unidades');
                    } else {
                        row.getElement().classList.remove('row-sin-unidades');
                    }
                },
            });

            window.addEventListener('resize', () => table.redraw(true));

            // ======= Acción ELIMINAR =======
            window.deleteToy = function(id, nombre) {
                const destroyUrl = destroyUrlTemplate.replace('__ID__', id);

                Swal.fire({
                    title: 'Eliminar juguete',
                    html: `¿Seguro que deseas eliminar <b>${nombre || ('ID '+id)}</b>? Esta acción no se puede deshacer.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then(res => {
                    if (!res.isConfirmed) return;

                    $.blockUI({
                        message: '<div class="p-3"><div class="spinner-border" role="status"></div><div class="mt-2">Eliminando...</div></div>',
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

                    fetch(destroyUrl, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': CSRF,
                                'Accept': 'application/json'
                            }
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
                            if (r.ok && payload.ok) {
                                // Elimina la fila del Tabulator si existe
                                const row = table.getRow(id);
                                if (row) row.delete();
                                else table.replaceData();

                                Swal.fire({
                                    icon: 'success',
                                    title: 'Eliminado',
                                    text: payload.message ||
                                        'El juguete fue eliminado correctamente.'
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'No se pudo eliminar',
                                    text: payload.message || 'Inténtalo más tarde.'
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
        })();
    </script>
@endpush
