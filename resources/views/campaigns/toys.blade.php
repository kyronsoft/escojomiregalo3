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
            const IS_EXEC = @json(auth()->user()->hasRole('Ejecutiva-Empresas')); // 👈 sin botón Modificar para este rol

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
                    width: 90,
                    hozAlign: "right"
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

            // 👉 Agregar columna "Acciones > Modificar" SOLO si NO es Ejecutiva-Empresas
            if (!IS_EXEC) {
                columns.push({
                    title: "Acciones",
                    field: "id",
                    hozAlign: "center",
                    headerSort: false,
                    width: 120,
                    formatter: function(cell) {
                        const id = cell.getValue();
                        const editUrl =
                            `{{ route('campaigns.toys.edit', ['campaign' => $campaign->id, 'toy' => ':id']) }}`
                            .replace(':id', id);
                        return `
                            <div class="d-flex gap-1 justify-content-center">
                                <a href="${editUrl}" class="btn btn-sm btn-primary" title="Editar juguete">
                                    <i class="fa fa-edit"></i>
                                </a>
                            </div>`;
                    }
                });
            }

            const table = new Tabulator("#toys-table", {
                // Hace que TODAS las columnas se ajusten al ancho disponible
                layout: "fitColumns",
                columnDefaults: {
                    minWidth: 100, // evita que las columnas pidan demasiado ancho
                    headerSort: true,
                },

                // Responsive: si aún no caben, colapsa las menos importantes
                responsiveLayout: "collapse",
                responsiveLayoutCollapseStartOpen: false,

                // UI
                height: "600px",
                rowHeight: 68,
                placeholder: "No hay registros",

                // Datos (igual que antes)
                ajaxURL: dataUrl,
                ajaxConfig: "GET",
                ajaxResponse: (url, params, resp) => Array.isArray(resp) ? resp : [],

                // Paginación local
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
            });

            // Redibuja al cambiar tamaño de ventana
            window.addEventListener('resize', () => table.redraw(true));

        })();
    </script>
@endpush
