@extends('ecommerce.main')

@push('css')
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/select2.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/owlcarousel.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/range-slider.css') }}">
    <style>
        /* Borde negro para la tabla del carrito (y todas sus celdas) */
        .order-history .table-bordered {
            border: 1px solid #000 !important;
            border-collapse: collapse !important;
            /* evita doble borde */
        }

        .order-history .table-bordered> :not(caption)>*>* {
            border: 1px solid #000 !important;
        }
    </style>
@endpush

@section('content')
    @php
        use Illuminate\Support\Facades\Storage;
        use Illuminate\Support\Str;
    @endphp

    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card profile-header">
                    @if (!empty($campaignBannerUrl))
                        <style>
                            .campaign-banner-bleed {
                                width: 100vw;
                                margin-left: calc(50% - 50vw);
                                margin-right: calc(50% - 50vw);
                            }

                            .campaign-banner-bleed img {
                                display: block;
                                width: 100%;
                                height: 25vh;
                                /* mobile: 1/4 altura */
                                object-fit: cover;
                            }

                            @media (min-width: 992px) {
                                .campaign-banner-bleed img {
                                    height: 33.333vh;
                                }

                                /* desktop: 1/3 altura */
                            }
                        </style>
                        <div class="campaign-banner-bleed">
                            <img src="{{ $campaignBannerUrl }}" alt="Banner campaña" loading="lazy">
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <h5>Carrito</h5>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            <div class="order-history table-responsive wishlist">

                                <table class="table table-bordered align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 260px;">Producto</th>
                                            <th style="width: 220px;">Hijo(a)</th>
                                            <th style="width: 120px;">Cantidad</th>
                                            <th style="width: 140px;">Eliminar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($items as $row)
                                            @php
                                                // Construir ruta imagen: campaign_toys/{idcampaign}/{imagenppal} si no viene con prefijo
                                                $imgRel = trim((string) ($row->imagenppal ?? ''));
                                                $imgRel = ltrim($imgRel, '/');
                                                $imgPath =
                                                    $imgRel !== ''
                                                        ? (Str::startsWith($imgRel, 'campaign_toys/')
                                                            ? $imgRel
                                                            : "campaign_toys/{$row->idcampaing}/{$imgRel}")
                                                        : '';

                                                $formId = "remove-{$row->id}";
                                            @endphp

                                            <tr>
                                                {{-- Producto: imagen + referencia + nombre --}}
                                                <td>
                                                    <div class="d-flex flex-column align-items-center text-center">
                                                        @if ($imgPath !== '')
                                                            <img src="{{ Storage::url($imgPath) }}"
                                                                alt="{{ $row->toy_nombre }}" class="img-fluid mb-2"
                                                                style="max-width:160px">
                                                        @endif
                                                        <div class="small text-muted">Ref:
                                                            <strong>{{ $row->referencia }}</strong>
                                                        </div>
                                                        <div class="fw-semibold">{{ $row->toy_nombre }}</div>
                                                    </div>
                                                </td>

                                                {{-- Hijo(a) --}}
                                                <td class="text-center">
                                                    <div class="fw-semibold">
                                                        {{ $row->child_nombre ?? ($row->nombre_hijo ?? '—') }}
                                                    </div>
                                                </td>

                                                {{-- Cantidad (siempre 1) --}}
                                                <td class="text-center">
                                                    <span class="badge bg-secondary">1</span>
                                                </td>

                                                {{-- Eliminar con confirmación SweetAlert2 --}}
                                                <td class="text-center">
                                                    <form id="{{ $formId }}"
                                                        action="{{ route('ecommerce.cart.remove') }}" method="POST"
                                                        class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="idhijo" value="{{ $row->idhijo }}">
                                                        <input type="hidden" name="referencia"
                                                            value="{{ $row->referencia }}">
                                                        <button type="button"
                                                            class="btn btn-outline-danger btn-sm js-remove-btn"
                                                            data-form="{{ $formId }}">
                                                            <i class="icon-trash"></i> Eliminar
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-5">
                                                    No tienes productos en el carrito.
                                                </td>
                                            </tr>
                                        @endforelse

                                        <tr>
                                            <td colspan="4" class="text-end">
                                                <a class="btn btn-secondary" href="{{ route('product') }}">Seguir
                                                    comprando</a>
                                                <form id="finishForm" action="{{ route('ecommerce.cart.finish') }}"
                                                    method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="button" class="btn btn-success"
                                                        id="btnFinish">Finalizar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        @if (session('swal'))
            <script>
                Swal.fire(@json(session('swal')));
            </script>
        @endif
        <script>
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('.js-remove-btn');
                if (!btn) return;

                e.preventDefault();
                const formId = btn.getAttribute('data-form');
                const formEl = document.getElementById(formId);
                if (!formEl) return;

                Swal.fire({
                    title: '¿Eliminar del carrito?',
                    text: 'Esta acción no se puede deshacer.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#d33',
                }).then((result) => {
                    if (result.isConfirmed) formEl.submit();
                });
            });
        </script>

        <script>
            document.getElementById('btnFinish').addEventListener('click', function() {
                Swal.fire({
                    title: '¿Finalizar selección?',
                    text: 'Se cerrará tu sesión y verás la confirmación.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, finalizar',
                    cancelButtonText: 'Cancelar'
                }).then((r) => {
                    if (r.isConfirmed) document.getElementById('finishForm').submit();
                });
            });
        </script>
    @endpush
@endsection
