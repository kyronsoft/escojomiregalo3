@extends('layouts.admin.master')

@section('title', 'Juguetes de campaña')

@push('css')
    <link href="https://unpkg.com/tabulator-tables@5.5.2/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .thumb img {
            max-height: 70px;
            max-width: 120px;
            object-fit: contain;
        }

        #toys-table .tabulator-row {
            min-height: 80px;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <h3 class="mb-0">Juguetes — {{ $campaign->nombre }} (ID {{ $campaign->id }})</h3>
            <a href="{{ route('campaigns.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
        </div>

        <div id="toys-table"></div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@5.5.2/dist/js/tabulator.min.js"></script>
    <script>
        (function() {
            const STORAGE_BASE = @json(asset('storage'));
            const FALLBACK_IMAGE = @json(asset('assets/images/placeholder.png'));
            const DATA_URL = @json(route('campaigns.toys.data', $campaign->id));

            function buildImgUrl(imagenppal) {
                if (!imagenppal) return FALLBACK_IMAGE;

                // Si hay combo "a.jpg+b.jpg+...": tomar la primera parte
                const first = String(imagenppal).split('+')[0].trim();

                // Absoluta
                if (/^https?:\/\//i.test(first)) return first;

                // Ya viene con 'campaign_toys/...'
                if (/^campaign_toys\//i.test(first)) return `${STORAGE_BASE}/${first}`;

                // Si llega un path relativo genérico, igual intenta por storage
                return `${STORAGE_BASE}/${first}`;
            }

            const columns = [{
                    title: "ID",
                    field: "id",
                    width: 80,
                    headerFilter: "input"
                },
                {
                    title: "Imagen",
                    field: "imagenppal",
                    width: 140,
                    headerSort: false,
                    hozAlign: "center",
                    formatter: (cell) => {
                        const url = buildImgUrl(cell.getValue());
                        return `<div class="thumb"><img src="${url}" alt="thumb" onerror="this.src='${FALLBACK_IMAGE}'"></div>`;
                    }
                },
                {
                    title: "Referencia",
                    field: "referencia",
                    width: 160,
                    headerFilter: "input"
                },
                {
                    title: "Nombre",
                    field: "nombre",
                    minWidth: 220,
                    headerFilter: "input"
                },
                {
                    title: "Género",
                    field: "genero",
                    width: 110,
                    headerFilter: "input",
                    formatter: (c) => {
                        const g = String(c.getValue() || '').toUpperCase();
                        let badge = 'Unisex';
                        let cls = 'bg-secondary';
                        if (g === 'M') {
                            badge = 'Niño';
                            cls = 'bg-primary';
                        } else if (g === 'F') {
                            badge = 'Niña';
                            cls = 'bg-pink';
                        }
                        return `<span class="badge ${cls}">${badge}</span>`;
                    }
                },
                {
                    title: "Rango",
                    field: "desde",
                    width: 140,
                    headerSort: false,
                    formatter: (cell) => {
                        const r = cell.getRow().getData();
                        const d = r.desde ?? '';
                        const h = r.hasta ?? '';
                        return (d || h) ? `${d} - ${h}` : '';
                    }
                },
                {
                    title: "Unidades",
                    field: "unidades",
                    width: 110,
                    hozAlign: "right",
                    headerFilter: "input"
                },
                {
                    title: "% Selección",
                    field: "porcentaje",
                    width: 120,
                    hozAlign: "right",
                    headerFilter: "input",
                    formatter: (c) => c.getValue() ? `${c.getValue()}%` : ''
                },
                {
                    title: "Img",
                    field: "imgexists",
                    width: 90,
                    hozAlign: "center",
                    formatter: (c) => String(c.getValue() || 'N').toUpperCase() === 'S' ?
                        '<span class="badge bg-success">S</span>' :
                        '<span class="badge bg-secondary">N</span>'
                },
                {
                    title: "Escogidos",
                    field: "escogidos",
                    width: 110,
                    hozAlign: "right"
                },
                {
                    title: "Actualizado",
                    field: "updated_at",
                    width: 170,
                    formatter: (v) => {
                        const d = new Date(v.getValue());
                        return isNaN(d) ? (v.getValue() || '') : d.toLocaleString();
                    }
                },
            ];

            const table = new Tabulator("#toys-table", {
                layout: "fitColumns",
                height: "650px",
                rowHeight: 80,
                placeholder: "No hay referencias asignadas a esta campaña",
                ajaxURL: DATA_URL,
                ajaxConfig: "GET",
                ajaxResponse: (url, params, resp) => Array.isArray(resp) ? resp : [],
                initialSort: [{
                    column: "updated_at",
                    dir: "desc"
                }],
                columns,
            });

            window.addEventListener('resize', () => table.redraw(true));
        })();
    </script>
@endpush
