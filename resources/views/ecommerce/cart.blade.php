@extends('ecommerce.main')

@push('css')
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/select2.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/owlcarousel.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/range-slider.css') }}">
    @php
        // Normaliza los colores configurados por la empresa y cae a los
        // defaults históricos si el valor es inválido; garantiza contraste
        // legible del texto sobre cualquier color, incluidos blanco y negro.
        $cKidBoy     = normalizeHexColor($colorBotonNino,   '#BA895D');
        $cKidGirl    = normalizeHexColor($colorBotonNina,   '#1B4C43');
        $cKidNeutral = normalizeHexColor($colorBotonUnisex, '#000000');

        // Borde oscuro y opaco cuando el relleno es claro (para que no se funda
        // con el fondo blanco); borde sutil cuando el relleno ya contrasta.
        $borderContrast = fn(string $hex): string =>
            contrastColor($hex) === '#000000' ? 'rgba(0,0,0,.55)' : 'rgba(0,0,0,.15)';
    @endphp
    <style>
        :root {
            --kid-boy:          {{ $cKidBoy }};
            --kid-girl:         {{ $cKidGirl }};
            --kid-neutral:      {{ $cKidNeutral }};
            --kid-boy-text:     {{ contrastColor($cKidBoy) }};
            --kid-girl-text:    {{ contrastColor($cKidGirl) }};
            --kid-neutral-text: {{ contrastColor($cKidNeutral) }};
            --kid-boy-border:     {{ $borderContrast($cKidBoy) }};
            --kid-girl-border:    {{ $borderContrast($cKidGirl) }};
            --kid-neutral-border: {{ $borderContrast($cKidNeutral) }};
        }

        /* Borde negro para la tabla del carrito (y todas sus celdas) */
        .order-history .table-bordered {
            border: 1px solid #000 !important;
            border-collapse: collapse !important;
        }

        .order-history .table-bordered> :not(caption)>*>* {
            border: 1px solid #000 !important;
        }

        /* Botón de hijo(a) */
        .child-btn {
            display: inline-block;
            border: 2px solid transparent;
            border-radius: .5rem;
            padding: .35rem .65rem;
            font-weight: 600;
            line-height: 1.1;
            text-decoration: none;
            white-space: nowrap;
        }
        .child-btn-boy     { background: var(--kid-boy)     !important; border-color: var(--kid-boy-border)     !important; color: var(--kid-boy-text)     !important; }
        .child-btn-girl    { background: var(--kid-girl)    !important; border-color: var(--kid-girl-border)    !important; color: var(--kid-girl-text)    !important; }
        .child-btn-neutral { background: var(--kid-neutral) !important; border-color: var(--kid-neutral-border) !important; color: var(--kid-neutral-text) !important; }
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
                                    /* desktop: 1/3 altura */
                                }
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
                                                // Construir ruta imagen: campaign_toys/{idcampaign}/{imagenppal}
                                                // Los combos vienen con varias imágenes separadas por "+"
                                                // (ej: BM-61311.jpg+71D118.jpg) y se muestran una al lado de otra
                                                $imgRel = trim((string) ($row->imagenppal ?? ''));
                                                $imgRel = ltrim($imgRel, '/');
                                                $imgNames = $imgRel !== ''
                                                    ? array_filter(array_map('trim', explode('+', $imgRel)))
                                                    : [];
                                                $imgPaths = array_map(
                                                    fn($name) => Str::startsWith($name, 'campaign_toys/')
                                                        ? $name
                                                        : "campaign_toys/{$row->idcampaing}/{$name}",
                                                    $imgNames
                                                );

                                                // Normalizar género (acepta NIÑO/NIÑA/UNISEX y M/F)
                                                $genRaw = mb_strtoupper(trim((string) ($row->genero ?? '')), 'UTF-8');
                                                $genKey = match (true) {
                                                    in_array($genRaw, ['NIÑA', 'NINA', 'F', 'GIRL', 'FEMALE'], true)
                                                        => 'F',
                                                    in_array($genRaw, ['NIÑO', 'NINO', 'M', 'BOY', 'MALE'], true)
                                                        => 'M',
                                                    in_array($genRaw, ['UNISEX', 'U', 'UNI', 'NEUTRO', 'NEUTRAL'], true)
                                                        => 'U',
                                                    default => 'U',
                                                };

                                                // Clase de botón según género
                                                $childBtnClass = match ($genKey) {
                                                    'M' => 'child-btn-boy',
                                                    'F' => 'child-btn-girl',
                                                    default => 'child-btn-neutral',
                                                };

                                                $formId = "remove-{$row->id}";
                                            @endphp
                                            <tr>
                                                {{-- Producto: imagen + referencia + nombre --}}
                                                <td>
                                                    <div class="d-flex flex-column align-items-center text-center">
                                                        @if (count($imgPaths) > 0)
                                                            <div class="d-flex flex-row justify-content-center align-items-center gap-1 mb-2">
                                                                @foreach ($imgPaths as $imgPath)
                                                                    <img src="{{ Storage::url($imgPath) }}"
                                                                        alt="{{ $row->toy_nombre }}" class="img-fluid"
                                                                        style="max-width:{{ count($imgPaths) > 1 ? '75px' : '160px' }}">
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                        <div class="small text-muted">
                                                            Ref: <strong>{{ $row->referencia }}</strong>
                                                        </div>
                                                        <div class="fw-semibold">{{ $row->toy_nombre }}</div>
                                                    </div>
                                                </td>

                                                {{-- Hijo(a) con color por género --}}
                                                <td class="text-center">
                                                    @php
                                                        $childName = $row->child_nombre ?? ($row->nombre_hijo ?? '—');
                                                    @endphp
                                                    <span class="child-btn {{ $childBtnClass }}">
                                                        {{ $childName }}
                                                    </span>
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
                                                        <input type="hidden" name="idcampaing"
                                                            value="{{ $row->idcampaing }}">
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
                                                <a class="btn btn-secondary" href="{{ route('product') }}">
                                                    Seguir seleccionando
                                                </a>
                                                <form id="finishForm" action="{{ route('ecommerce.cart.finish') }}"
                                                    method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="button" class="btn btn-success" id="btnFinish"
                                                        @disabled($items->isEmpty())>
                                                        Finalizar
                                                    </button>
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
