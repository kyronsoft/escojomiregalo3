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
            <a href="{{ route('campaigns.create') }}" class="btn btn-primary">Nueva campaña</a>
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
            const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                '{{ csrf_token() }}';
            const STORAGE_BASE = @json(asset('storage'));
            const FALLBACK_BANNER = @json(asset('assets/images/placeholder.png'));

            // Rutas usadas en los botones
            const ROUTES = {
                edit: `{{ route('campaigns.edit', ':id') }}`,
                destroy: `{{ route('campaigns.destroy', ':id') }}`,
                collaborators: `{{ route('campaigns.collaborators', ':id') }}` // <-- añade esta ruta en web.php
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

            window.deleteCampaign = function(id) {
                Swal.fire({
                    title: '¿Eliminar campaña?',
                    text: 'Esta acción no se puede deshacer.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then(res => {
                    if (!res.isConfirmed) return;
                    $.blockUI({
                        message: '<div class="p-3"><div class="spinner-border" role="status"></div><div class="mt-2">Eliminando...</div></div>',
                        css: {
                            border: 'none',
                            padding: '15px',
                            background: '#000',
                            opacity: 0.6,
                            color: '#fff',
                            borderRadius: '8px'
                        },
                        baseZ: 2000
                    });
                    fetch(ROUTES.destroy.replace(':id', id), {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': CSRF,
                                'Accept': 'application/json'
                            },
                            body: new URLSearchParams({
                                _method: 'DELETE'
                            })
                        })
                        .then(async r => {
                            $.unblockUI();
                            if (r.ok) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Eliminada'
                                });
                                table.replaceData();
                            } else {
                                const t = await r.text();
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error al eliminar',
                                    text: t || 'Intenta nuevamente.'
                                });
                            }
                        })
                        .catch(() => {
                            $.unblockUI();
                            Swal.fire({
                                icon: 'error',
                                title: 'Error de red'
                            });
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
                        const val = v.getValue();
                        const d = new Date(val);
                        return isNaN(d) ? (val || '') : d.toLocaleString();
                    }
                },
                {
                    title: "Fin",
                    field: "fechafin",
                    width: 170,
                    formatter: v => {
                        const val = v.getValue();
                        const d = new Date(val);
                        return isNaN(d) ? (val || '') : d.toLocaleString();
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
                    formatter: c => c.getValue() ? '<span class="badge bg-success">Sí</span>' :
                        '<span class="badge bg-secondary">No</span>',
                    headerSort: false
                },
                {
                    title: "Actualizado",
                    field: "updated_at",
                    width: 170,
                    formatter: v => {
                        const val = v.getValue();
                        const d = new Date(val);
                        return isNaN(d) ? (val || '') : d.toLocaleString();
                    }
                },
                {
                    title: "Acciones",
                    field: "_actions",
                    width: 260,
                    headerSort: false,
                    hozAlign: "center",
                    formatter: (cell) => {
                        const r = cell.getRow().getData();
                        const editUrl = ROUTES.edit.replace(':id', encodeURIComponent(r.id));
                        const collabUrl = ROUTES.collaborators.replace(':id', encodeURIComponent(r.id));
                        return `
                            <div class="d-flex flex-wrap gap-1 justify-content-center">
                                <a href="${editUrl}" class="btn btn-sm btn-outline-primary">Editar</a>
                                <a href="${collabUrl}" class="btn btn-sm btn-outline-secondary">Colaboradores</a>
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
