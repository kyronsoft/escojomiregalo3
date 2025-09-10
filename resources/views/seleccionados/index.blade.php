@extends('layouts.admin.master')

@section('title', 'Seleccionados')

@push('css')
    <style>
        .filter-group .form-control,
        .filter-group .form-select {
            height: 38px;
        }

        #seleccionadosTable {
            min-height: 520px;
        }

        .tabulator {
            border-radius: .5rem;
        }

        .tabulator .tabulator-header .tabulator-col {
            white-space: nowrap;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <h3 class="mb-0">Seleccionados</h3>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        {{-- Filtros (el JS intercepta el submit y recarga el grid vía AJAX) --}}
        <form id="filtersForm" method="GET" class="card mb-3" autocomplete="off">
            <div class="card-body">
                <div class="row g-2 align-items-end filter-group">
                    <div class="col-12 col-md-3">
                        <label class="form-label mb-1">Campaña</label>
                        <select name="idcampaign" class="form-select">
                            <option value="">-- Todas --</option>
                            @foreach ($campaigns as $c)
                                <option value="{{ $c->id }}"
                                    {{ (string) $c->id === (string) $campaignId ? 'selected' : '' }}>
                                    {{ $c->nombre ?? 'Campaña #' . $c->id }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1">Referencia</label>
                        <input type="text" name="referencia" class="form-control" value="{{ $referencia }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1">Documento</label>
                        <input type="text" name="documento" class="form-control" value="{{ $documento }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1">Desde</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1">Hasta</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label mb-1">/ pág.</label>
                        <select name="per_page" id="perPage" class="form-select">
                            @foreach ([10, 25, 50, 100, 200] as $pp)
                                <option value="{{ $pp }}" {{ (int) $perPage === $pp ? 'selected' : '' }}>
                                    {{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-12 d-flex gap-2 justify-content-end">
                        <a href="{{ route('seleccionados.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                        <a id="btnExportExcel" href="{{ route('seleccionados.export', request()->query()) }}"
                            class="btn btn-success">
                            Exportar Excel
                        </a>
                        <button class="btn btn-primary" type="submit">Filtrar</button>
                    </div>
                </div>
            </div>
        </form>

        {{-- Grid Tabulator --}}
        <div class="card">
            <div class="card-body">
                <div id="seleccionadosTable"></div>
                <div id="tablaResumen" class="text-muted small mt-2"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/tabulator-tables@5.5.2/dist/js/tabulator.min.js"></script>
    <script>
        (function() {
            if (typeof Tabulator === 'undefined') {
                console.error('Tabulator no se cargó. Revisa que existan los archivos en public/vendor/tabulator/.');
                const el = document.getElementById('seleccionadosTable');
                if (el) el.innerHTML =
                    '<div class="text-danger">No se pudo cargar el grid. Revisa que los assets locales existan.</div>';
                return;
            }

            const dataUrlBase = @json(route('seleccionados.data')); // Endpoint JSON (Laravel paginator)
            const exportUrlBase = @json(route('seleccionados.export')); // Endpoint Excel
            const formEl = document.getElementById('filtersForm');
            const perPageEl = document.getElementById('perPage');
            const exportBtn = document.getElementById('btnExportExcel');
            const resumenEl = document.getElementById('tablaResumen');

            // --- Helpers ---
            function getFilters() {
                const fd = new FormData(formEl);
                const o = {};
                for (const [k, v] of fd.entries()) o[k] = v || '';
                o.size = parseInt(o.per_page || 25, 10); // Tabulator envía page/size
                return o;
            }

            function buildQuery(params) {
                const usp = new URLSearchParams();
                Object.entries(params).forEach(([k, v]) => {
                    if (v !== undefined && v !== null && String(v) !== '') usp.set(k, v);
                });
                return usp.toString();
            }

            function updateExportHref() {
                const q = getFilters();
                delete q.size;
                delete q.page; // no enviar params internos del grid
                exportBtn.href = exportUrlBase + '?' + buildQuery(q);
            }

            function fmtDateTime(val) {
                if (!val) return '';
                const d = new Date(String(val).replace(' ', 'T'));
                if (isNaN(d)) return val;
                const pad = n => String(n).padStart(2, '0');
                return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
            }

            function fmtGenero(val) {
                const g = String(val || '').trim().toUpperCase();
                if (['M', 'NIÑO', 'NINO', 'BOY', 'MALE'].includes(g)) return 'Niño';
                if (['F', 'NIÑA', 'NINA', 'GIRL', 'FEMALE'].includes(g)) return 'Niña';
                return 'Unisex';
            }

            // --- Grid ---
            const table = new Tabulator("#seleccionadosTable", {
                layout: "fitDataStretch", // muchas columnas -> estira
                responsiveLayout: "collapse", // colapsa columnas en pantallas pequeñas
                height: "600px",

                ajaxURL: dataUrlBase,
                ajaxConfig: "GET",
                ajaxParams: getFilters,
                pagination: "remote",
                paginationSize: parseInt(perPageEl.value || 25, 10),
                paginationSizeSelector: [10, 25, 50, 100, 200],
                paginationDataSent: {
                    page: "page",
                    size: "size"
                },
                paginationDataReceived: {
                    last_page: "last_page",
                    data: "data"
                },

                columns: [
                    // 1) Referencia
                    {
                        title: "Referencia",
                        field: "referencia",
                        minWidth: 130
                    },

                    // 2) Fecha Selección
                    {
                        title: "Fecha Selección",
                        field: "created_at",
                        width: 170,
                        hozAlign: "center",
                        formatter: (cell) => fmtDateTime(cell.getValue())
                    },

                    // 3) Documento
                    {
                        title: "Documento",
                        field: "documento",
                        minWidth: 130
                    },

                    // 4) Colaborador (toma el primero que exista)
                    {
                        title: "Colaborador",
                        field: "colaborador",
                        minWidth: 200,
                        formatter: (cell) => {
                            const d = cell.getRow().getData();
                            return d.colaborador ?? d.colaborador_nombre ?? d.nombre ?? '';
                        }
                    },

                    // 5) Teléfono
                    {
                        title: "Telefono",
                        field: "telefono",
                        minWidth: 130
                    },

                    // 6) Nombre Hijo (fallbacks)
                    {
                        title: "Nombre Hijo",
                        field: "nombre_hijo",
                        minWidth: 180,
                        formatter: (cell) => {
                            const d = cell.getRow().getData();
                            return d.nombre_hijo ?? d.child_nombre ?? '';
                        }
                    },

                    // 7) Género
                    {
                        title: "Genero",
                        field: "genero",
                        width: 110,
                        hozAlign: "center",
                        formatter: (cell) => {
                            const d = cell.getRow().getData();
                            const raw = d.genero_hijo ?? d.genero ?? '';
                            return fmtGenero(raw);
                        }
                    },

                    // 8) Rango Edad
                    {
                        title: "Rango Edad",
                        field: "rango_edad",
                        width: 120,
                        hozAlign: "center",
                        formatter: (cell) => {
                            const d = cell.getRow().getData();
                            return d.rango_edad ?? d.edad_rango ?? d.rango ?? '';
                        }
                    },

                    // 9) Dirección
                    {
                        title: "Dirección",
                        field: "direccion",
                        minWidth: 220
                    },

                    // 10) Indicaciones (fallback a observaciones)
                    {
                        title: "Indicaciones",
                        field: "indicaciones",
                        minWidth: 220,
                        formatter: (cell) => {
                            const d = cell.getRow().getData();
                            return d.indicaciones ?? d.observaciones ?? '';
                        }
                    },

                    // 11) Codigo Ciudad
                    {
                        title: "Codigo Ciudad",
                        field: "ciudad",
                        width: 140,
                        formatter: (cell) => {
                            const d = cell.getRow().getData();
                            // prioriza código si viene como 'ciudad', si no prueba 'codigo_ciudad'
                            return d.ciudad ?? d.codigo_ciudad ?? '';
                        }
                    },

                    // 12) Departamento
                    {
                        title: "Departamento",
                        field: "departamento",
                        minWidth: 150,
                        formatter: (cell) => {
                            const d = cell.getRow().getData();
                            return d.departamento ?? d.depto ?? d.depart ?? '';
                        }
                    },

                    // 13) Sucursal
                    {
                        title: "Sucursal",
                        field: "sucursal",
                        minWidth: 140
                    },

                    // 14) Email
                    {
                        title: "Email",
                        field: "email",
                        minWidth: 200
                    },

                    // 15) Empresa (fallback a empresa_nombre o campaign_name)
                    {
                        title: "Empresa",
                        field: "empresa",
                        minWidth: 180,
                        formatter: (cell) => {
                            const d = cell.getRow().getData();
                            return d.empresa ?? d.empresa_nombre ?? d.campaign_name ?? '';
                        }
                    },
                ],

                langs: {
                    "default": {
                        "pagination": {
                            "page_size": " / pág.",
                            "page_title": "Mostrar página",
                            "first": "Primera",
                            "first_title": "Primera página",
                            "last": "Última",
                            "last_title": "Última página",
                            "prev": "Anterior",
                            "prev_title": "Página anterior",
                            "next": "Siguiente",
                            "next_title": "Página siguiente",
                        }
                    }
                },

                ajaxRequesting: function() {
                    resumenEl.textContent = "Cargando...";
                },
                // IMPORTANTE: devolver solo el arreglo de filas del paginador
                ajaxResponse: function(url, params, response) {
                    try {
                        const from = response.from ?? 0;
                        const to = response.to ?? (response.data?.length || 0);
                        const tot = response.total ?? response.data?.length ?? 0;
                        resumenEl.textContent = `Mostrando ${from}-${to} de ${tot} registros`;
                    } catch (e) {
                        resumenEl.textContent = "";
                    }
                    return Array.isArray(response?.data) ? response.data : [];
                },
                ajaxError: function(err) {
                    resumenEl.textContent = "Error cargando datos.";
                    console.error('Tabulator AJAX error:', err);
                },

                tooltips: true,
                placeholder: "No hay registros.",
            });

            // Cambiar tamaño de página
            perPageEl.addEventListener('change', function() {
                const n = parseInt(this.value || 25, 10);
                table.setPageSize(n);
                table.setPage(1).then(() => table.setData());
                updateExportHref();
            });

            // Aplicar filtros sin recargar
            formEl.addEventListener('submit', function(e) {
                e.preventDefault();
                table.setPage(1).then(() => table.setData());
                updateExportHref();
            });

            // Link de exportación inicial
            updateExportHref();
        })();
    </script>
@endpush
