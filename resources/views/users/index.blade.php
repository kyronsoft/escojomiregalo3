@extends('layouts.admin.master')

@section('title', 'Usuarios')

@push('css')
    {{-- Tabulator CSS (recomendado) --}}
    <link rel="stylesheet" href="https://unpkg.com/tabulator-tables@5.5.2/dist/css/tabulator.min.css">
    <style>
        .badge-role {
            font-size: .85rem;
        }

        #users-table .tabulator-row {
            min-height: 48px;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <h3 class="mb-0">Usuarios</h3>
            <a href="{{ route('users.create') }}" class="btn btn-primary">Nuevo usuario</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div id="users-table"></div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    {{-- Tabulator JS --}}
    <script src="https://unpkg.com/tabulator-tables@5.5.2/dist/js/tabulator.min.js"></script>

    <script>
        (function() {
            const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                '{{ csrf_token() }}';

            const ROUTES = {
                data: `{{ route('users.data') }}`,
                edit: `{{ route('users.edit', ':id') }}`,
                destroy: `{{ route('users.destroy', ':id') }}`,
            };

            function roleLabel(rowData) {
                const raw = rowData.role_name || rowData.role || (Array.isArray(rowData.roles) ? rowData.roles[0] :
                    '') || '';
                const val = String(raw).trim();
                switch (val) {
                    case 'Admin':
                    case 'admin':
                        return 'Admin';
                    case 'Ejecutiva-Empresas':
                    case 'ejecutiva_empresas':
                        return 'Ejecutiva Empresas';
                    case 'RRHH-Cliente':
                    case 'business':
                        return 'RRHH-Cliente';
                    case 'Colaborador':
                    case 'colaborador':
                        return 'Colaborador';
                    default:
                        return val || '—';
                }
            }

            window.deleteUser = function(id) {
                if (!id) return;
                if (!confirm('¿Eliminar este usuario?')) return;
                fetch(ROUTES.destroy.replace(':id', id), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': CSRF,
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            _method: 'DELETE'
                        }),
                    })
                    .then(async r => {
                        if (r.ok) {
                            table.replaceData();
                        } else {
                            alert((await r.text()) || 'No fue posible eliminar.');
                        }
                    })
                    .catch(() => alert('Error de red'));
            };

            const columns = [{
                    title: "ID",
                    field: "id",
                    width: 90,
                    headerFilter: "input"
                },

                // 👇 NUEVA COLUMNA Documento
                {
                    title: "Documento",
                    field: "documento",
                    width: 160,
                    headerFilter: "input"
                },

                {
                    title: "Nombre",
                    field: "name",
                    minWidth: 220,
                    headerFilter: "input",
                    formatter: c =>
                        `<div class="text-truncate" title="${c.getValue() ?? ''}">${c.getValue() ?? ''}</div>`
                },
                {
                    title: "Correo",
                    field: "email",
                    minWidth: 220,
                    headerFilter: "input",
                    formatter: c => {
                        const v = c.getValue() || '';
                        return v ? `<a href="mailto:${v}">${v}</a>` : '—';
                    }
                },
                {
                    title: "Rol",
                    field: "role_name",
                    width: 200,
                    headerSort: false,
                    formatter: cell =>
                        `<span class="badge bg-primary badge-role">${roleLabel(cell.getRow().getData())}</span>`
                },
                {
                    title: "Actualizado",
                    field: "updated_at",
                    width: 180,
                    formatter: c => {
                        const v = c.getValue();
                        const d = v ? new Date(v) : null;
                        return d && !isNaN(d) ? d.toLocaleString() : (v ?? '');
                    }
                },
                {
                    title: "Acciones",
                    field: "_act",
                    width: 180,
                    hozAlign: "center",
                    headerSort: false,
                    formatter: (cell) => {
                        const id = cell.getRow().getData().id;
                        const editUrl = ROUTES.edit.replace(':id', encodeURIComponent(id));
                        return `
                      <div class="d-inline-flex gap-1">
                        <a href="${editUrl}" class="btn btn-sm btn-outline-primary">Editar</a>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${id})">Eliminar</button>
                      </div>`;
                    }
                },
            ];

            const table = new Tabulator("#users-table", {
                layout: "fitDataFill",
                height: "600px",
                responsiveLayout: "collapse",
                ajaxURL: ROUTES.data,
                ajaxConfig: "GET",
                ajaxResponse: (url, params, resp) => Array.isArray(resp) ? resp : (resp?.data ?? []),

                pagination: true,
                paginationMode: "local",
                paginationSize: 10,
                paginationSizeSelector: [10, 20, 50, 100],
                paginationCounter: "rows",

                sortMode: "local",
                filterMode: "local",

                columns,
                placeholder: "No hay usuarios",
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
                    }
                }
            });

            window.addEventListener('resize', () => table.redraw(true));
        })();
    </script>
@endpush
