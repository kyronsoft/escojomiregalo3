@extends('layouts.admin.master')

@section('title')
    Empresas - Listado
@endsection

@push('css')
    <style>
        .thumb-cell img {
            height: 48px;
            max-width: 96px;
            object-fit: contain;
        }

        .tabulator {
            font-size: 0.92rem;
        }

        .color-chip {
            display: inline-block;
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 1px solid #ddd;
            vertical-align: middle;
            margin-right: 6px;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <h3 class="mb-0">Empresas</h3>
            <a href="{{ route('empresas.create') }}" class="btn btn-primary">Crear Empresa</a>
        </div>

        {{-- Mensajes flash opcionales (también puedes usar SweetAlert aquí si gustas) --}}
        @if (session('success'))
            <div class="alert alert-success mb-3">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger mb-3">{{ session('error') }}</div>
        @endif

        <div id="empresas-table"></div>
    </div>
@endsection

@push('scripts')
    {{-- jQuery si lo usas en el layout; Tabulator no lo requiere --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    {{-- Tabulator JS (CDN) --}}
    <script src="https://unpkg.com/tabulator-tables@5.5.2/dist/js/tabulator.min.js"></script>

    <script>
        // Datos provenientes del controlador
        const EMPRESAS = @json($empresas);

        // Helpers para construir URLs de imágenes
        const STORAGE_BASE = @json(asset('storage')); // => /storage
        const FALLBACK_IMG = @json(asset('assets/images/placeholder.png')); // ajusta un placeholder si tienes

        function imageUrl(path) {
            if (!path) return FALLBACK_IMG;
            // Si ya viene una URL absoluta (por ejemplo si migraste a S3 y guardas URL completa)
            if (/^https?:\/\//i.test(path)) return path;
            // Si es ruta relativa tipo "images/xxx.jpg" (lo que guarda store('images','public'))
            return `${STORAGE_BASE}/${path}`;
        }

        // Formatter de miniatura
        function thumbFormatter(cell) {
            const val = cell.getValue();
            const url = imageUrl(val);
            return `<div class="thumb-cell">
                    <img src="${url}" alt="img" onerror="this.src='${FALLBACK_IMG}'">
                </div>`;
        }

        // Formatter color (muestra chip + valor)
        function colorFormatter(cell) {
            const v = cell.getValue() || '';
            const safe = String(v).replace(/"/g, '&quot;');
            return v ?
                `<span class="color-chip" style="background:${safe}"></span><span>${safe}</span>` :
                '';
        }

        // Botón Editar
        function actionsFormatter(cell) {
            const data = cell.getRow().getData();
            const nit = encodeURIComponent(data.nit);
            const editUrl = `{{ route('empresas.edit', ':nit') }}`.replace(':nit', nit);
            return `
            <a href="${editUrl}" class="btn btn-sm btn-outline-primary">
                Editar
            </a>
        `;
        }

        // Definición de columnas
        const columns = [{
                title: "NIT",
                field: "nit",
                headerFilter: "input",
                width: 140,
                hozAlign: "left",
                frozen: true
            },
            {
                title: "Nombre",
                field: "nombre",
                headerFilter: "input",
                minWidth: 200
            },
            {
                title: "Ciudad",
                field: "ciudad",
                headerFilter: "input",
                width: 120
            },
            {
                title: "Dirección",
                field: "direccion",
                headerFilter: "input",
                minWidth: 220
            },

            {
                title: "Logo",
                field: "logo",
                formatter: thumbFormatter,
                hozAlign: "center",
                width: 120,
                headerSort: false
            },
            {
                title: "Banner",
                field: "banner",
                formatter: thumbFormatter,
                hozAlign: "center",
                width: 120,
                headerSort: false
            },
            {
                title: "Login",
                field: "imagen_login",
                formatter: thumbFormatter,
                hozAlign: "center",
                width: 120,
                headerSort: false
            },

            {
                title: "Primario",
                field: "color_primario",
                formatter: colorFormatter,
                width: 140,
                headerSort: false
            },
            {
                title: "Secundario",
                field: "color_secundario",
                formatter: colorFormatter,
                width: 140,
                headerSort: false
            },
            {
                title: "Terciario",
                field: "color_terciario",
                formatter: colorFormatter,
                width: 140,
                headerSort: false
            },

            {
                title: "Username",
                field: "username",
                headerFilter: "input",
                width: 160
            },
            {
                title: "Cod. Vendedor",
                field: "codigoVendedor",
                headerFilter: "input",
                width: 140
            },

            {
                title: "Actualizado",
                field: "updated_at",
                width: 170,
                formatter: function(cell) {
                    const v = cell.getValue();
                    if (!v) return '';
                    // Formato legible
                    const d = new Date(v);
                    if (isNaN(d)) return v;
                    return d.toLocaleString();
                }
            },

            {
                title: "Acciones",
                field: "_actions",
                width: 120,
                hozAlign: "center",
                formatter: actionsFormatter,
                headerSort: false
            }
        ];

        // Inicializar Tabulator
        const table = new Tabulator("#empresas-table", {
            data: EMPRESAS,
            layout: "fitColumns",
            height: "550px", // para virtual DOM; ajusta a gusto
            responsiveLayout: "collapse", // colapsa columnas en pantallas pequeñas
            pagination: true,
            paginationSize: 10,
            placeholder: "No hay empresas registradas",
            columns: columns,
            initialSort: [{
                column: "updated_at",
                dir: "desc"
            }],
            locale: true,
        });

        // Opcional: ajustar tamaño al contenedor cuando cambia la ventana
        window.addEventListener('resize', () => table.redraw(true));
    </script>
@endpush
