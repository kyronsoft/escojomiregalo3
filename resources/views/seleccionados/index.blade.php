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
                        <a id="btnExportExcel"
                            href="{{ route('seleccionados.export', request()->query()) }}"
                            class="btn btn-success {{ empty($campaignId) ? 'disabled' : '' }}"
                            aria-disabled="{{ empty($campaignId) ? 'true' : 'false' }}"
                            title="{{ empty($campaignId) ? 'Seleccione una campaña antes de exportar' : 'Exportar Excel' }}">
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
        console.error('Tabulator no se cargó. Revisa que existan los archivos.');
        document.getElementById('seleccionadosTable').innerHTML =
            '<div class="text-danger">No se pudo cargar el grid.</div>';
        return;
    }

    const dataUrlBase = @json(route('seleccionados.data'));
    const exportUrlBase = @json(route('seleccionados.export'));
    const formEl = document.getElementById('filtersForm');
    const perPageEl = document.getElementById('perPage');
    const exportBtn = document.getElementById('btnExportExcel');
    const resumenEl = document.getElementById('tablaResumen');
    const campaignSelect = formEl.querySelector('select[name="idcampaign"]');

    function getFilters() {
        const fd = new FormData(formEl);
        const o = {};
        for (const [k, v] of fd.entries()) o[k] = v || '';
        o.size = parseInt(o.per_page || 25, 10);
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
        delete q.page;
        exportBtn.href = exportUrlBase + '?' + buildQuery(q);

        const hasCampaign = (campaignSelect.value || '').trim() !== '';
        exportBtn.classList.toggle('disabled', !hasCampaign);
        exportBtn.setAttribute('aria-disabled', hasCampaign ? 'false' : 'true');
        exportBtn.setAttribute('title', hasCampaign
            ? 'Exportar Excel'
            : 'Seleccione una campaña antes de exportar');
    }

    campaignSelect.addEventListener('change', updateExportHref);

    function fmtDateTime(val) {
        if (!val) return '';
        const d = new Date(String(val).replace(' ', 'T'));
        if (isNaN(d)) return val;
        const pad = n => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    function fmtGenero(val) {
        const g = String(val || '').trim().toUpperCase();
        if (['M','NIÑO','NINO','BOY','MALE'].includes(g)) return 'Niño';
        if (['F','NIÑA','NINA','GIRL','FEMALE'].includes(g)) return 'Niña';
        return 'Unisex';
    }

    // --- Grid vacío (sin URL inicial) ---
    const table = new Tabulator("#seleccionadosTable", {
        layout: "fitDataStretch",
        responsiveLayout: "collapse",
        height: "600px",
        placeholder: "Seleccione una campaña y haga clic en Filtrar",
        pagination: "remote",
        paginationSize: parseInt(perPageEl.value || 25, 10),
        paginationSizeSelector: [10, 25, 50, 100, 200],
        paginationDataSent: { page: "page", size: "size" },
        paginationDataReceived: { last_page: "last_page", data: "data" },
        columns: [
            { title: "Referencia", field: "referencia", minWidth: 130 },
            { title: "Fecha Selección", field: "created_at", width: 170, hozAlign: "center", formatter: (c)=>fmtDateTime(c.getValue()) },
            { title: "Documento", field: "documento", minWidth: 130 },
            { title: "Colaborador", field: "colaborador", minWidth: 200, formatter: (c)=>c.getRow().getData().colaborador ?? '' },
            { title: "Politica Datos", field: "politicadatos", minWidth: 140 },
            { title: "Telefono", field: "telefono", minWidth: 130 },
            { title: "Nombre Hijo", field: "nombre_hijo", minWidth: 180 },
            { title: "Genero", field: "genero", width: 110, hozAlign: "center", formatter: (c)=>fmtGenero(c.getValue()) },
            { title: "Rango Edad", field: "rango_edad", width: 120, hozAlign: "center" },
            { title: "Dirección", field: "direccion", minWidth: 220 },
            { title: "Indicaciones", field: "indicaciones", minWidth: 220 },
            { title: "Codigo Ciudad", field: "ciudad", width: 140 },
            { title: "Departamento", field: "departamento", minWidth: 150 },
            { title: "Sucursal", field: "sucursal", minWidth: 140 },
            { title: "Email", field: "email", minWidth: 200 },
            { title: "Empresa", field: "empresa", minWidth: 180 }
        ],
        ajaxRequesting: function() { resumenEl.textContent = "Cargando..."; },
        ajaxResponse: function(url, params, response) {
            const from = response.from ?? 0;
            const to = response.to ?? (response.data?.length || 0);
            const tot = response.total ?? response.data?.length ?? 0;
            resumenEl.textContent = `Mostrando ${from}-${to} de ${tot} registros`;
            return Array.isArray(response?.data) ? response.data : [];
        },
        ajaxError: function(err) {
            resumenEl.textContent = "Error cargando datos.";
            console.error('Tabulator AJAX error:', err);
        }
    });

    // Cambiar tamaño de página
    perPageEl.addEventListener('change', function() {
        const n = parseInt(this.value || 25, 10);
        table.setPageSize(n);
        if (table.modules.ajax.config.url) table.setData();
        updateExportHref();
    });

    // --- Filtrar (solo si hay campaña seleccionada) ---
    formEl.addEventListener('submit', function(e) {
        e.preventDefault();
        const idcamp = campaignSelect.value.trim();

        if (!idcamp) {
            alert('Por favor seleccione una campaña antes de filtrar.');
            return;
        }

        // Asignar la URL AJAX solo después de seleccionar campaña
        if (!table.modules.ajax.config.url) {
            table.setData(dataUrlBase, getFilters());
            table.setAjaxUrl(dataUrlBase);
        } else {
            table.setPage(1).then(() => table.setData(dataUrlBase, getFilters()));
        }

        updateExportHref();
    });

    // Inicializa export vacío
    updateExportHref();
})();
</script>
@endpush

