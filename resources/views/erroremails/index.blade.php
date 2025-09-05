@extends('layouts.admin.master')

@section('title', 'Errores de Email')

@push('css')
    {{-- Tabulator CSS --}}
    <link href="https://unpkg.com/tabulator-tables@5.5.2/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .status-cell {
            max-width: 420px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        details summary {
            cursor: pointer;
        }

        /* Ajuste visual del contenedor de la tabla */
        #errors-table {
            min-height: 420px;
        }
    </style>
@endpush

@section('content')
    @php
        // Convertimos el paginator a arreglo simple para Tabulator
        $rows =
            $items instanceof \Illuminate\Contracts\Pagination\Paginator
                ? $items->items()
                : (is_iterable($items)
                    ? $items
                    : []);
    @endphp

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <h3 class="mb-0">Errores de Email</h3>
        </div>

        {{-- Filtros (GET: el servidor devuelve el dataset filtrado; Tabulator pagina en cliente) --}}
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-12 col-md-2">
                        <label class="form-label">Campaña (ID)</label>
                        <input type="number" name="idcampaing" value="{{ $filters['idcampaing'] }}" class="form-control"
                            placeholder="Ej: 123">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Documento</label>
                        <input type="text" name="documento" value="{{ $filters['documento'] }}" class="form-control"
                            placeholder="Documento">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Email</label>
                        <input type="text" name="email" value="{{ $filters['email'] }}" class="form-control"
                            placeholder="correo@dominio.com">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Desde</label>
                        <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control">
                    </div>
                    <div class="col-12 col-md-1 d-flex align-items-end">
                        <button class="btn btn-primary w-100" type="submit">Filtrar</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Contenedor Tabulator --}}
        <div class="card">
            <div class="card-body">
                <div id="errors-table"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- Tabulator JS --}}
    <script src="https://unpkg.com/tabulator-tables@5.5.2/dist/js/tabulator.min.js"></script>
    <script>
        (function() {
            // Dataset entregado por el servidor después del filtro
            const DATA = @json($rows);

            // Pequeño helper para escapar HTML (evita inyectar HTML en celdas)
            function escapeHtml(str) {
                return String(str ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            // Formatter de status: si es largo, usa <details> para expandir
            function statusFormatter(cell) {
                const txt = String(cell.getValue() ?? '');
                const MAX = 120;
                if (txt.length <= MAX) {
                    return `<span class="status-cell" title="${escapeHtml(txt)}">${escapeHtml(txt)}</span>`;
                }
                const short = escapeHtml(txt.slice(0, MAX)) + '…';
                const full = escapeHtml(txt);
                return `
                    <details>
                        <summary>${short}</summary>
                        <pre class="mb-0 mt-1" style="white-space:pre-wrap;">${full}</pre>
                    </details>
                `;
            }

            // Formatter de fecha legible
            function dateFormatter(cell) {
                const v = cell.getValue();
                if (!v) return '';
                const d = new Date(v);
                if (isNaN(d)) return escapeHtml(v);
                return d.toLocaleString();
            }

            // Definición de columnas
            const columns = [{
                    title: "ID",
                    field: "id",
                    width: 90,
                    hozAlign: "left",
                    headerFilter: "input"
                },
                {
                    title: "Campaña",
                    field: "idcampaing",
                    width: 110,
                    hozAlign: "left",
                    headerFilter: "input"
                },
                {
                    title: "Documento",
                    field: "documento",
                    width: 150,
                    hozAlign: "left",
                    headerFilter: "input"
                },
                {
                    title: "Email",
                    field: "email",
                    minWidth: 220,
                    headerFilter: "input",
                    formatter: (cell) =>
                        `<span title="${escapeHtml(cell.getValue())}">${escapeHtml(cell.getValue())}</span>`
                },
                {
                    title: "Status",
                    field: "status",
                    minWidth: 280,
                    headerSort: false,
                    formatter: statusFormatter
                },
                {
                    title: "Creado",
                    field: "created_at",
                    width: 170,
                    formatter: dateFormatter
                },
                {
                    title: "Actualizado",
                    field: "updated_at",
                    width: 170,
                    formatter: dateFormatter
                },
            ];

            // Inicializa Tabulator
            const table = new Tabulator("#errors-table", {
                data: DATA,
                columns,
                layout: "fitColumns",
                height: "600px",
                responsiveLayout: "collapse",
                placeholder: "No hay registros que coincidan con el filtro.",
                pagination: true, // paginación en cliente
                paginationMode: "local",
                paginationSize: 25,
                paginationSizeSelector: [10, 25, 50, 100],
                initialSort: [{
                    column: "created_at",
                    dir: "desc"
                }, ],
            });

            // Redibuja al cambiar tamaño ventana (opcional)
            window.addEventListener('resize', () => table.redraw(true));
        })();
    </script>
@endpush
