@extends('layouts.admin.master')

@section('title', 'Juguetes / Combos de campaña')

@push('css')
    <style>
        #toys-table .tabulator-row {
            min-height: 68px;
        }

        .toy-thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: .5rem;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <h3 class="mb-0">Juguetes / Combos</h3>
            {{-- <div>
                <a href="{{ route('campaign_toys.create') }}" class="btn btn-primary">Nuevo</a>
            </div> --}}
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div id="toys-table"></div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('assets/js/blockui/jquery.blockUI.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/tabulator-tables@5.5.2/dist/js/tabulator.min.js"></script>
    <script>
        (function() {
            const CSRF = '{{ csrf_token() }}';

            window.deleteToy = function(id) {
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
                    const url = `{{ route('campaign_toys.destroy', ':id') }}`.replace(':id', id);
                    fetch(url, {
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
                                table.replaceData();
                            } else {
                                const t = await r.text();
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: t || 'No se pudo eliminar'
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
                },
                {
                    title: "Campaña",
                    field: "idcampaign",
                    width: 110,
                    headerFilter: "input"
                },
                {
                    title: "Ref.",
                    field: "referencia",
                    width: 140,
                    headerFilter: "input"
                },
                {
                    title: "Nombre",
                    field: "nombre",
                    minWidth: 240,
                    headerFilter: "input",
                    formatter: cell => {
                        const v = cell.getValue() || '';
                        return `<div class="text-truncate" title="${v}">${v}</div>`;
                    }
                },
                {
                    title: "Imagen",
                    field: "imagenppal",
                    width: 120,
                    hozAlign: "center",
                    headerSort: false,
                    formatter: function(cell) {
                        const raw = cell.getValue();
                        if (!raw) return '';

                        const row = cell.getRow().getData();
                        const campaignId = row.idcampaign; // <- viene en el dataset

                        // Para combos: "img1.jpg+img2.jpg+..."
                        const parts = String(raw).split('+').map(s => s.trim()).filter(Boolean);
                        const first = parts[0];

                        // Construye URL correcta: /storage/campaign_toys/{idcampaign}/{filename}
                        const base = `{{ asset('storage') }}/campaign_toys/${encodeURIComponent(campaignId)}`;
                        const src = `${base}/${encodeURIComponent(first)}`;

                        // Mini badge si es combo
                        const extra = parts.length > 1 ?
                            `<span class="badge bg-secondary ms-1 align-middle">+${parts.length - 1}</span>` :
                            '';

                        // Imagen con fallback si no existe el archivo
                        return `
      <div class="d-inline-flex align-items-center">
        <img src="${src}" class="toy-thumb"
             alt="thumb"
             onerror="this.onerror=null; this.src='{{ asset('assets/images/placeholder.png') }}';">
        ${extra}
      </div>
    `;
                    }
                },
                {
                    title: "Género",
                    field: "genero",
                    width: 110,
                    headerFilter: "input"
                },
                {
                    title: "Unid.",
                    field: "unidades",
                    width: 90,
                    hozAlign: "right",
                    headerFilter: "input"
                },
                {
                    title: "Precio",
                    field: "precio_unitario",
                    width: 110,
                    hozAlign: "right",
                    headerFilter: "input",
                    formatter: cell => {
                        const v = cell.getValue() ?? 0;
                        return new Intl.NumberFormat().format(v);
                    }
                },
                {
                    title: "% / Sel.",
                    field: "porcentaje",
                    width: 110,
                    headerFilter: "input",
                    formatter: cell => cell.getValue() || '0'
                },
                {
                    title: "Actualizado",
                    field: "updated_at",
                    width: 170,
                    formatter: c => {
                        const d = new Date(c.getValue());
                        return isNaN(d) ? (c.getValue() || '') : d.toLocaleString();
                    }
                },
                {
                    title: "Acciones",
                    field: "_act",
                    width: 260,
                    hozAlign: "center",
                    headerSort: false,
                    formatter: cell => {
                        const r = cell.getRow().getData();
                        const showUrl = `{{ route('campaign_toys.show', ':id') }}`.replace(':id', r.id);
                        const editUrl = `{{ route('campaign_toys.edit', ':id') }}`.replace(':id', r.id);
                        return `
          <div class="d-flex gap-1 justify-content-center flex-wrap">
            <a href="${showUrl}" class="btn btn-sm btn-outline-info">Ver</a>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteToy(${r.id})">Eliminar</button>
          </div>
        `;
                    }
                },
            ];

            const table = new Tabulator("#toys-table", {
                layout: "fitColumns",
                height: "600px",
                responsiveLayout: "collapse", // colapsa columnas en pantallas pequeñas
                rowHeight: 68,
                columns,
                placeholder: "No hay registros",
                ajaxURL: "{{ route('campaign_toys.data') }}", // asegúrate de tener este endpoint
                ajaxConfig: "GET",
                pagination: false,
                sortMode: "local",
                filterMode: "local",
                ajaxResponse: (url, params, resp) => Array.isArray(resp) ? resp : [],
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
        })();
    </script>
@endpush
