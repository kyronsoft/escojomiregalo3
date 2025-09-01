@extends('layouts.admin.master')

@section('title', 'Hijos del colaborador')

@push('css')
    <link href="https://unpkg.com/tabulator-tables@5.5.2/dist/css/tabulator.min.css" rel="stylesheet">
@endpush

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Listado de Hijos</h5>
                <a href="{{ route('colaborador_hijos.create') }}" class="btn btn-primary btn-sm">Nuevo hijo</a>
            </div>
            <div class="card-body">
                {{-- Mostrar a quién estamos filtrando (si aplica) --}}
                @if ($identificacion)
                    <div class="alert alert-info py-2">
                        Mostrando hijos del colaborador: <strong>{{ $identificacion }}</strong>
                        <a class="ms-2" href="{{ route('colaborador_hijos.index') }}">Quitar filtro</a>
                    </div>
                @endif

                <div id="hijos-table"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/tabulator-tables@5.5.2/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('assets/js/blockui/jquery.blockUI.js') }}"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {

            const IDENT = @json($identificacion); // null o string del colaborador

            const columns = [{
                    title: "ID",
                    field: "id",
                    width: 80
                },
                {
                    title: "Identificación",
                    field: "identificacion",
                    headerFilter: "input"
                },
                {
                    title: "Nombre Hijo",
                    field: "nombre_hijo",
                    headerFilter: "input"
                },
                {
                    title: "Género",
                    field: "genero",
                    headerFilter: "input"
                },
                {
                    title: "Rango Edad",
                    field: "rango_edad",
                    headerFilter: "input"
                },
                {
                    title: "Campaña",
                    field: "idcampaign",
                    headerFilter: "input"
                },
                {
                    title: "Actualizado",
                    field: "updated_at",
                    formatter: (cell) => {
                        const v = cell.getValue();
                        const d = new Date(v);
                        return isNaN(d) ? (v || '') : d.toLocaleString();
                    }
                },
                {
                    title: "Acciones",
                    field: "id",
                    hozAlign: "center",
                    headerSort: false,
                    formatter: function(cell) {
                        const id = cell.getValue();

                        // Genera URLs correctas con el nombre de ruta
                        const editUrl = `{{ route('colaborador_hijos.edit', ':id') }}`.replace(':id', id);
                        const showUrl = `{{ route('colaborador_hijos.show', ':id') }}`.replace(':id', id);

                        return `
      <div class="d-flex gap-1 justify-content-center">
        <a href="${editUrl}" class="btn btn-sm btn-primary">
          <i class="fa fa-edit"></i>
        </a>
        <button class="btn btn-sm btn-danger" onclick="deleteHijo(${id})">
          <i class="fa fa-trash"></i>
        </button>
      </div>
    `;
                    }
                },
            ];

            const table = new Tabulator("#hijos-table", {
                layout: "fitColumns",
                height: "600px",
                rowHeight: 48,
                columns,
                placeholder: "No hay hijos registrados",
                ajaxURL: "{{ route('colaborador_hijos.data') }}",
                ajaxConfig: "GET",
                // Enviar el filtro de identificacion al backend:
                ajaxParams: IDENT ? {
                    identificacion: IDENT
                } : {},

                // Carga local (sin paginación)
                pagination: false,
                sortMode: "local",
                filterMode: "local",

                ajaxResponse: function(url, params, response) {
                    return Array.isArray(response) ? response : [];
                },
            });
        });
    </script>
    <script>
        function deleteHijo(id) {
            Swal.fire({
                title: '¿Eliminar registro?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then(res => {
                if (!res.isConfirmed) return;

                $.blockUI({
                    message: 'Eliminando...'
                });

                const url = `{{ route('colaborador_hijos.destroy', ':id') }}`.replace(':id', id);

                fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
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
                            table.replaceData(); // o table.setData(ajaxURL, ajaxParams)
                        } else {
                            const t = await r.text();
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: t || 'No se pudo eliminar.'
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
        }
    </script>
@endpush
