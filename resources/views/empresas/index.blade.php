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
        // Datos del servidor tal cual te llegan
        const EMPRESAS = @json($empresas);

        // Base absoluta hacia /storage/empresas (respeta host/puerto actual)
        const EMP_BASE = @json(url('storage/empresas'));
        const FALLBACK_IMG = @json(asset('assets/images/placeholder.png'));

        function imgCell(src, fallbackPath) {
            const bust = Date.now(); // o usa un timestamp del registro si lo tienes
            return `
    <div class="thumb-cell">
      <img src="${src}?v=${bust}" alt="img"
           onerror="this.onerror=null;this.src='${FALLBACK_IMG}'">
    </div>`;
        }

        // Columnas
        const columns = [{
                title: "NIT",
                field: "nit",
                headerFilter: "input",
                width: 140,
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

            // Usamos el NIT de la fila para construir la URL de la imagen
            {
                title: "Logo",
                field: "logo",
                hozAlign: "center",
                width: 120,
                headerSort: false,
                formatter: cell => {
                    const r = cell.getRow().getData();
                    const url = r.logo && /^https?:\/\//i.test(r.logo) ?
                        r.logo :
                        `${EMP_BASE}/${encodeURIComponent(r.nit)}/logo.png`;
                    return imgCell(url, 'logo.png');
                }
            },
            {
                title: "Banner",
                field: "banner",
                hozAlign: "center",
                width: 120,
                headerSort: false,
                formatter: cell => {
                    const r = cell.getRow().getData();
                    const url = r.banner && /^https?:\/\//i.test(r.banner) ?
                        r.banner :
                        `${EMP_BASE}/${encodeURIComponent(r.nit)}/banner.jpeg`;
                    return imgCell(url, 'banner.jpeg');
                }
            },
            {
                title: "Login",
                field: "imagen_login",
                hozAlign: "center",
                width: 120,
                headerSort: false,
                formatter: cell => {
                    const r = cell.getRow().getData();
                    const url = r.imagen_login && /^https?:\/\//i.test(r.imagen_login) ?
                        r.imagen_login :
                        `${EMP_BASE}/${encodeURIComponent(r.nit)}/imagen_login.jpg`;
                    return imgCell(url, 'imagen_login.jpg');
                }
            },

            {
                title: "Primario",
                field: "color_primario",
                width: 140,
                headerSort: false,
                formatter: c => {
                    const v = c.getValue() || '';
                    return v ? `<span class="color-chip" style="background:${v}"></span> ${v}` : '';
                }
            },
            {
                title: "Secundario",
                field: "color_secundario",
                width: 140,
                headerSort: false,
                formatter: c => {
                    const v = c.getValue() || '';
                    return v ? `<span class="color-chip" style="background:${v}"></span> ${v}` : '';
                }
            },
            {
                title: "Terciario",
                field: "color_terciario",
                width: 140,
                headerSort: false,
                formatter: c => {
                    const v = c.getValue() || '';
                    return v ? `<span class="color-chip" style="background:${v}"></span> ${v}` : '';
                }
            },
            {
                title: "Acciones",
                field: "_actions",
                width: 120,
                hozAlign: "center",
                headerSort: false,
                formatter: cell => {
                    const nit = encodeURIComponent(cell.getRow().getData().nit);
                    const editUrl = `{{ route('empresas.edit', ':nit') }}`.replace(':nit', nit);
                    return `<a href="${editUrl}" class="btn btn-sm btn-outline-primary">Editar</a>`;
                }
            }
        ];

        // Inicializa Tabulator
        const table = new Tabulator("#empresas-table", {
            data: EMPRESAS,
            layout: "fitDataFill", // <- autoajuste por datos + relleno
            layoutColumnsOnNewData: true, // <- recalcula al cargar/cambiar datos
            height: "550px",
            responsiveLayout: "collapse",
            pagination: true,
            paginationSize: 10,
            paginationCounter: "rows",
            placeholder: "No hay empresas registradas",
            columns,
            initialSort: [{
                column: "updated_at",
                dir: "desc"
            }],
            locale: "es",
            langs: {
                es: {
                    pagination: {
                        first: "Primera",
                        first_title: "Primera página",
                        last: "Última",
                        last_title: "Última página",
                        prev: "Anterior",
                        prev_title: "Página anterior",
                        next: "Siguiente",
                        next_title: "Página siguiente",
                        page_size: "Registros por página",
                    },
                    headerFilters: {
                        default: "filtrar columna..."
                    },
                    ajax: {
                        loading: "Cargando...",
                        error: "Error al cargar"
                    },
                },
            },
        });

        window.addEventListener('resize', () => table.redraw(true));
    </script>
@endpush
