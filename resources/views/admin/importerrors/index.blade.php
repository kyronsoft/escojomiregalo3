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

            const table = new Tabulator("#errors-table", {
                layout: "fitColumns",
                height: "600px",
                responsiveLayout: "collapse",
                placeholder: "No hay registros",
                ajaxURL: dataURL,
                ajaxConfig: "GET",
                pagination: false,
                columns: [{
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
                        headerFilter: "input"
                    },
                    {
                        title: "Valores",
                        field: "values",
                        minWidth: 280,
                        headerFilter: "input",
                        formatter: function(cell) {
                            const v = cell.getValue() || '';
                            // Trunca visualmente para no romper la tabla
                            return `<span title="${v.replace(/"/g,'&quot;')}">${v}</span>`;
                        }
                    },
                    {
                        title: "Creado",
                        field: "created_at",
                        width: 170,
                        formatter: function(cell) {
                            const v = cell.getValue();
                            const d = v ? new Date(v) : null;
                            return d && !isNaN(d) ? d.toLocaleString() : (v || '');
                        }
                    },
                ],
                initialSort: [{
                    column: "created_at",
                    dir: "desc"
                }],
            });

            $('#btn-reload').on('click', () => table.replaceData());
            window.addEventListener('resize', () => table.redraw(true));
        })();
    </script>
@endpush
