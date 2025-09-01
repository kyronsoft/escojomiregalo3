@extends('layouts.admin.master')

@section('title', 'Colaboradores')

@push('css')
    <style>
        #colab-table .tabulator-row {
            min-height: 48px;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Colaboradores</h3>
            {{-- <a href="{{ route('colaboradores.create') }}" class="btn btn-primary">Nuevo colaborador</a> --}}
        </div>

        {{-- Flash (también saldrá en SweetAlert2) --}}
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div id="colab-table"></div>
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

            // Acciones
            window.deleteColab = function(documento) {
                Swal.fire({
                    title: '¿Eliminar colaborador?',
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
                    fetch(`{{ route('colaboradores.destroy', ':id') }}`.replace(':id', encodeURIComponent(
                            documento)), {
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
                                    title: 'Eliminado'
                                });
                                table.setData("{{ route('colaboradores.data') }}"); // recarga
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

            // Columnas
            const columns = [{
                    title: "Documento",
                    field: "documento",
                    width: 150,
                    headerFilter: "input"
                },
                {
                    title: "Nombre",
                    field: "nombre",
                    minWidth: 220,
                    headerFilter: "input"
                },
                {
                    title: "Email",
                    field: "email",
                    minWidth: 200,
                    headerFilter: "input"
                },
                {
                    title: "Teléfono",
                    field: "telefono",
                    width: 120,
                    headerFilter: "input"
                },
                {
                    title: "Ciudad",
                    field: "ciudad",
                    width: 110,
                    headerFilter: "input"
                },
                {
                    title: "NIT",
                    field: "nit",
                    width: 120,
                    headerFilter: "input"
                },
                {
                    title: "Enviado",
                    field: "enviado",
                    width: 110,
                    hozAlign: "center",
                    formatter: (cell) => cell.getValue() ? '<span class="badge bg-success">Sí</span>' :
                        '<span class="badge bg-secondary">No</span>',
                    headerFilter: "select",
                    headerFilterParams: {
                        values: {
                            "": "Todos",
                            "1": "Sí",
                            "0": "No"
                        }
                    },
                    headerSort: false
                },
                {
                    title: "Welcome",
                    field: "welcome",
                    width: 110,
                    hozAlign: "center",
                    formatter: (cell) => (cell.getValue() === 'S' || cell.getValue() === 'Y') ?
                        '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>',
                    headerFilter: "select",
                    headerFilterParams: {
                        values: {
                            "": "Todos",
                            "S": "Sí",
                            "N": "No",
                            "Y": "Sí"
                        }
                    },
                    headerSort: false
                },
                {
                    title: "Actualizado",
                    field: "updated_at",
                    width: 170,
                    formatter: (cell) => {
                        const v = cell.getValue();
                        const d = new Date(v);
                        return isNaN(d) ? (v || '') : d.toLocaleString();
                    }
                },
                {
                    title: "Acciones",
                    field: "_act",
                    width: 320,
                    hozAlign: "center",
                    headerSort: false,
                    formatter: (cell) => {
                        const r = cell.getRow().getData();
                        const showUrl = `{{ route('colaboradores.show', ':id') }}`.replace(':id',
                            encodeURIComponent(r.documento));
                        const editUrl = `{{ route('colaborador_hijos.edit', ':id') }}`.replace(':id',
                            encodeURIComponent(r.documento));
                        const hijosUrl = hijosIndexUrl(r.documento);
                        return `
        <div class="d-flex gap-1 justify-content-center flex-wrap">
          <a href="${showUrl}"  class="btn btn-sm btn-outline-info">Ver</a>
          <a href="${editUrl}"  class="btn btn-sm btn-outline-primary">Editar</a>
          <a href="${hijosUrl}" class="btn btn-sm btn-outline-secondary">Hijos</a>
          <button class="btn btn-sm btn-outline-danger" onclick="deleteColab('${r.documento}')">Eliminar</button>
        </div>
      `;
                    }
                },
            ];


            // Tabla (sin paginación; sort/filter locales)
            const table = new Tabulator("#colab-table", {
                layout: "fitColumns",
                height: "600px",
                rowHeight: 48,
                responsiveLayout: "collapse",
                placeholder: "No hay colaboradores registrados",

                ajaxURL: "{{ route('colaboradores.data') }}",
                ajaxConfig: "GET",

                pagination: false, // sin paginación
                sortMode: "local",
                filterMode: "local",

                ajaxResponse: function(url, params, response) {
                    // Debe ser un array plano
                    if (Array.isArray(response)) return response;
                    console.error('Respuesta inesperada:', response);
                    return [];
                },

                initialSort: [{
                    column: "updated_at",
                    dir: "desc"
                }],
                columns,
            });

            // SweetAlerts por flash
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
    <script>
        function hijosIndexUrl(identificacion) {
            // abre el index de hijos con filtro ?identificacion=<documento>
            const base = "{{ route('colaborador_hijos.index') }}";
            const qs = new URLSearchParams({
                identificacion: identificacion
            });
            return base + "?" + qs.toString();
        }
    </script>
@endpush
