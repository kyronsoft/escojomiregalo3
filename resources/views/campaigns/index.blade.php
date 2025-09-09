@extends('layouts.admin.master')

@section('title', 'Campañas')

@push('css')
    <style>
        .thumb-cell img {
            max-height: 60px;
            max-width: 120px;
            object-fit: contain;
        }

        #campaigns-table .tabulator-row {
            min-height: 72px;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <h3 class="mb-0">Campañas</h3>

            {{-- Ocultar "Nueva campaña" para Ejecutiva-Empresas --}}
            @if (!auth()->user()->hasRole('Ejecutiva-Empresas'))
                <a href="{{ route('campaigns.create') }}" class="btn btn-primary">Nueva campaña</a>
            @endif
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div id="campaigns-table"></div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('assets/js/blockui/jquery.blockUI.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/tabulator-tables@5.5.2/dist/js/tabulator.min.js"></script>

    <script>
        (function() {
            const IS_EXEC = @json(auth()->user()->hasRole('Ejecutiva-Empresas'));
            const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                '{{ csrf_token() }}';
            const STORAGE_BASE = @json(asset('storage'));
            const FALLBACK_BANNER = @json(asset('assets/images/placeholder.png'));

            const ROUTES = {
                edit: `{{ route('campaigns.edit', ':id') }}`,
                destroy: `{{ route('campaigns.destroy', ':id') }}`,
                collaborators: `{{ route('campaigns.collaborators', ':id') }}`,
                toys: `{{ route('campaigns.toys', ':id') }}`, // Referencias
            };

            function imgUrl(path) {
                if (!path || path === 'ND') return FALLBACK_BANNER;
                if (/^https?:\/\//i.test(path)) return path;
                return `${STORAGE_BASE}/${path}`;
            }

            function thumbFormatter(cell) {
                const url = imgUrl(cell.getValue());
                return `<div class="thumb-cell"><img src="${url}" alt="banner" onerror="this.src='${FALLBACK_BANNER}'"></div>`;
            }

            // Si usas eliminar, implementa aquí tu lógica (oculto para Ejecutiva-Empresas vía UI)
            window.deleteCampaign = function(id) {
                Swal.fire({
                    title: '¿Eliminar campaña?',
                    text: 'Esta acción no se puede deshacer.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then(result => {
                    if (!result.isConfirmed) return;
                    $.ajax({
                        url: ROUTES.destroy.replace(':id', encodeURIComponent(id)),
                        type: 'POST',
                        data: {
                            _method: 'DELETE',
                            _token: CSRF
                        },
                        beforeSend: () => $.blockUI({
                            message: 'Eliminando...'
                        }),
                        complete: () => $.unblockUI(),
                        success: () => location.reload(),
                        error: () => Swal.fire('Error', 'No se pudo eliminar la campaña.', 'error'),
                    });
                });
            };

            const columns = [{
                    title: "ID",
                    field: "id",
                    width: 80,
                    headerFilter: "input"
                },
                {
                    title: "NIT",
                    field: "nit",
                    width: 120,
                    headerFilter: "input"
                },
                {
                    title: "Nombre",
                    field: "nombre",
                    minWidth: 200,
                    headerFilter: "input"
                },
                {
                    title: "Tipo",
                    field: "idtipo",
                    width: 90,
                    headerFilter: "input"
                },
                {
                    title: "Inicio",
                    field: "fechaini",
                    width: 170,
                    formatter: v => {
                        const d = new Date(v.getValue());
                        return isNaN(d) ? (v.getValue() || '') : d.toLocaleString();
                    }
                },
                {
                    title: "Fin",
                    field: "fechafin",
                    width: 170,
                    formatter: v => {
                        const d = new Date(v.getValue());
                        return isNaN(d) ? (v.getValue() || '') : d.toLocaleString();
                    }
                },
                {
                    title: "Demo",
                    field: "demo",
                    width: 90,
                    headerFilter: "input"
                },
                {
                    title: "Dashboard",
                    field: "dashboard",
                    width: 110,
                    hozAlign: "center",
                    headerSort: false,
                    formatter: c => c.getValue() ? '<span class="badge bg-success">Sí</span>' :
                        '<span class="badge bg-secondary">No</span>',
                },
                {
                    title: "Acciones",
                    field: "_actions",
                    width: IS_EXEC ? 260 : 360,
                    headerSort: false,
                    hozAlign: "center",
                    formatter: (cell) => {
                        const r = cell.getRow().getData();
                        const id = encodeURIComponent(r.id);
                        const editUrl = ROUTES.edit.replace(':id', id);
                        const collUrl = ROUTES.collaborators.replace(':id', id);
                        const refsUrl = ROUTES.toys.replace(':id', id); // "Referencias"

                        // Para Ejecutiva-Empresas: SOLO "Colaboradores" y "Referencias"
                        if (IS_EXEC) {
                            return `
                                <div class="d-flex flex-wrap gap-1 justify-content-center">
                                    <a href="${collUrl}" class="btn btn-sm btn-outline-secondary">Colaboradores</a>
                                    <a href="${refsUrl}" class="btn btn-sm btn-outline-info">Referencias</a>
                                </div>
                            `;
                        }

                        // Otros roles: todos los botones
                        return `
                            <div class="d-flex flex-wrap gap-1 justify-content-center">
                                <a href="${editUrl}" class="btn btn-sm btn-outline-primary">Editar</a>
                                <a href="${collUrl}" class="btn btn-sm btn-outline-secondary">Colaboradores</a>
                                <a href="${refsUrl}" class="btn btn-sm btn-outline-info">Referencias</a>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteCampaign(${r.id})">Eliminar</button>
                            </div>
                        `;
                    }
                },
            ];

            const table = new Tabulator("#campaigns-table", {
                layout: "fitColumns",
                height: "600px",
                rowHeight: 72,
                responsiveLayout: "collapse",
                placeholder: "No hay campañas registradas",
                ajaxURL: "{{ route('campaigns.data') }}",
                ajaxConfig: "GET",
                pagination: false,
                sortMode: "local",
                filterMode: "local",
                ajaxResponse: (url, params, resp) => Array.isArray(resp) ? resp : [],
                initialSort: [{
                    column: "updated_at",
                    dir: "desc"
                }],
                columns,
            });

            @if (session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'Éxito',
                    text: @json(session('success'))
                });
            @endif
            @if (session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: @json(session('error'))
                });
            @endif

            window.addEventListener('resize', () => table.redraw(true));
        })();
    </script>
@endpush
