@extends('layouts.admin.master')

@section('title', 'Errores de importación')

@push('css')
    <link rel="stylesheet" href="https://unpkg.com/tabulator-tables@5.5.2/dist/css/tabulator.min.css">
    <style>
        #errors-table .tabulator-row {
            min-height: 48px;
        }

        .actions-bar {
            gap: .5rem;
        }

        details summary {
            cursor: pointer;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <div>
                <h3 class="mb-0">Errores de importación</h3>
                <div class="text-muted">Tabla: <code>importerrors</code></div>
            </div>
            <div class="d-flex align-items-center actions-bar">
                <button id="btn-reload" class="btn btn-outline-primary btn-sm">Recargar</button>
                <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">Volver</a>
            </div>
        </div>

        <div id="errors-table"></div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@5.5.2/dist/js/tabulator.min.js"></script>
    <script>
        (function() {
            const dataURL = `{{ route('importerrors.index', ['json' => 1]) }}`;

            const escapeHtml = (str) => String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            function valuesFormatter(cell) {
                const raw = cell.getValue() ?? '';
                const txt = String(raw);
                const MAX = 140;

                if (txt.length <= MAX) {
                    return `<span title="${escapeHtml(txt)}">${escapeHtml(txt)}</span>`;
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

            function dateFormatter(cell) {
                const v = cell.getValue();
                if (!v) return '';
                const d = new Date(v);
                return isNaN(d) ? escapeHtml(v) : d.toLocaleString();
            }

            const columns = [{
                    title: "ID",
                    field: "id",
                    width: 80,
                    hozAlign: "center",
                    headerFilter: "input"
                },
                {
                    title: "Fila (Excel)",
                    field: "row",
                    width: 120,
                    hozAlign: "center",
                    headerFilter: "input"
                },
                {
                    title: "Atributo",
                    field: "attribute",
                    minWidth: 140,
                    headerFilter: "input"
                },
                {
                    title: "Error",
                    field: "errors",
                    minWidth: 240,
                    headerFilter: "input",
                    formatter: (cell) =>
                        `<span title="${escapeHtml(cell.getValue())}">${escapeHtml(cell.getValue())}</span>`
                },
                {
                    title: "Valores",
                    field: "values",
                    minWidth: 280,
                    headerFilter: "input",
                    formatter: valuesFormatter
                },
                {
                    title: "Creado",
                    field: "created_at",
                    width: 170,
                    formatter: dateFormatter
                },
            ];

            const table = new Tabulator("#errors-table", {
                layout: "fitColumns",
                height: "600px",
                responsiveLayout: "collapse",
                placeholder: "No hay registros",
                columns,
                // --- Paginación en el cliente ---
                pagination: true,
                paginationMode: "local",
                paginationSize: 25,
                paginationSizeSelector: [10, 25, 50, 100],
                initialSort: [{
                    column: "created_at",
                    dir: "desc"
                }],

                // --- Carga por AJAX (una sola vez), pero Tabulator pagina localmente ---
                ajaxURL: dataURL,
                ajaxConfig: {
                    method: "GET"
                },
                ajaxContentType: "json",
                ajaxResponse: function(url, params, resp) {
                    // Soportar varios formatos: array plano, {data:[]}, {items:[]}
                    if (Array.isArray(resp)) return resp;
                    if (resp && Array.isArray(resp.data)) return resp.data;
                    if (resp && Array.isArray(resp.items)) return resp.items;
                    return [];
                },
            });

            // Recargar: vuelve a pedir TODO y sigue paginando local
            $('#btn-reload').on('click', () => table.replaceData());

            // Redibujo en resize (opcional)
            window.addEventListener('resize', () => table.redraw(true));
        })();
    </script>
@endpush
