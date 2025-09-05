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
            background: #f3f3f3;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
            <h3 class="mb-0">Juguetes / Combos</h3>
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
            const STORAGE_BASE = @json(asset('storage')); // /storage -> public/storage
            const PLACEHOLDER = @json(asset('assets/images/placeholder.png'));

            // Genera una lista de URLs candidatas para una imagen
            function buildCandidates(firstName, campaignId) {
                const name = String(firstName || '').trim();
                const out = [];

                if (!name) return out;

                // URL absoluta (OneDrive, CDN, etc.)
                if (/^https?:\/\//i.test(name)) {
                    out.push(name);
                    return out;
                }

                // Si ya viene con prefijo campaign_toys/...
                if (/^campaign_toys\//i.test(name)) {
                    out.push(`${STORAGE_BASE}/${encodeTail(name)}`);
                    // Variante en minúscula del filename
                    out.push(`${STORAGE_BASE}/${encodeTail(lowercaseFilename(name))}`);
                    return out;
                }

                // Si viene "229/archivo.jpg"
                if (/^\d+\//.test(name)) {
                    const p = `campaign_toys/${name}`;
                    out.push(`${STORAGE_BASE}/${encodeTail(p)}`);
                    out.push(`${STORAGE_BASE}/${encodeTail(lowercaseFilename(p))}`);
                    return out;
                }

                // Solo filename: asumimos campaign_toys/{id}/filename
                const p = `campaign_toys/${campaignId}/${name}`;
                out.push(`${STORAGE_BASE}/${encodeTail(p)}`);
                out.push(`${STORAGE_BASE}/${encodeTail(lowercaseFilename(p))}`);

                return out;
            }

            // Codifica SOLO el último segmento (filename), para no romper las carpetas
            function encodeTail(path) {
                const segs = String(path).split('/');
                if (segs.length === 0) return path;
                const tail = segs.pop();
                // encodeURIComponent preserva puntos; bien para "Mi foto 1.jpg"
                const encTail = encodeURIComponent(tail);
                segs.push(encTail);
                return segs.join('/');
            }

            // Devuelve misma ruta pero con filename en minúscula (por si el FS es case-sensitive)
            function lowercaseFilename(path) {
                const segs = String(path).split('/');
                if (segs.length === 0) return path;
                const tail = segs.pop();
                segs.push(tail.toLowerCase());
                return segs.join('/');
            }

            // onerror en cadena: va probando candidatos hasta agotar; luego pone placeholder y desactiva onerror
            function toyImgFallback(img) {
                try {
                    const list = JSON.parse(img.dataset.altList || '[]');
                    let idx = parseInt(img.dataset.altIdx || '0', 10);
                    if (Number.isNaN(idx)) idx = 0;

                    if (idx < list.length) {
                        img.dataset.altIdx = String(idx + 1);
                        img.src = list[idx];
                        return;
                    }
                    // sin más candidatos: placeholder y no más onerror
                    img.onerror = null;
                    if (img.src !== PLACEHOLDER) img.src = PLACEHOLDER;
                } catch (e) {
                    img.onerror = null;
                    img.src = PLACEHOLDER;
                }
            }
            window.toyImgFallback = toyImgFallback; // expone para el onerror inline

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
                    width: 80
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
                    field: "image_url", // <- usa el campo ya listo del backend
                    width: 120,
                    hozAlign: "center",
                    headerSort: false,
                    formatter: function(cell) {
                        const src = cell.getValue();
                        const partsCount = cell.getRow().getData().image_parts_count || 0;
                        const badge = partsCount > 1 ?
                            `<span class="badge bg-secondary ms-1 align-middle">+${partsCount-1}</span>` : '';
                        const placeholder = @json(asset('assets/images/placeholder.png'));
                        if (!src) return `<img src="${placeholder}" class="toy-thumb" alt="thumb">`;
                        return `
                            <div class="d-inline-flex align-items-center">
                                <img src="${src}" class="toy-thumb" alt="thumb"
                                    onerror="this.onerror=null; this.src='${placeholder}';">
                                ${badge}
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
                // {
                //     title: "Precio",
                //     field: "precio_unitario",
                //     width: 110,
                //     hozAlign: "right",
                //     headerFilter: "input",
                //     formatter: cell => new Intl.NumberFormat().format(cell.getValue() ?? 0)
                // },
                {
                    title: "Restantes",
                    field: "porcentaje",
                    width: 110,
                    headerFilter: "input",
                    formatter: cell => cell.getValue() || '0'
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
                responsiveLayout: "collapse",
                rowHeight: 68,
                columns,
                placeholder: "No hay registros",
                ajaxURL: "{{ route('campaign_toys.data') }}",
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
