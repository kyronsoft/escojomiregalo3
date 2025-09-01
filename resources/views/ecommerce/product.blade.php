@extends('ecommerce.main')

@push('css')
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/select2.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/owlcarousel.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/range-slider.css') }}">
    <style>
        :root {
            --primary: {{ $primaryColor }};
            --secondary: {{ $secondaryColor }};
        }

        /* Fondo con 50% de opacidad detrás del contenido */
        body {
            position: relative;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: linear-gradient(to bottom,
                    var(--primary) 0%,
                    var(--primary) 50%,
                    var(--secondary) 50%,
                    var(--secondary) 100%);
            opacity: .5;
            /* 50% */
            z-index: -1;
            /* detrás */
            pointer-events: none;
            /* no interfiere */
        }

        /* Capa de contenido por encima del fondo */
        .site-content {
            position: relative;
        }

        /* Botones por género (forzar colores) */
        .btn-kid-girl {
            background-color: #e91e63 !important;
            border-color: #e91e63 !important;
            color: #fff !important;
        }

        .btn-kid-girl:hover,
        .btn-kid-girl:focus {
            background-color: #d81b60 !important;
            border-color: #d81b60 !important;
            color: #fff !important;
        }

        .btn-kid-boy {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
            color: #fff !important;
        }

        .btn-kid-boy:hover,
        .btn-kid-boy:focus {
            background-color: #0b5ed7 !important;
            border-color: #0a58ca !important;
            color: #fff !important;
        }

        .btn-kid-neutral {
            background-color: #000 !important;
            border-color: #000 !important;
            color: #fff !important;
        }

        .btn-kid-neutral:hover,
        .btn-kid-neutral:focus {
            background-color: #111 !important;
            border-color: #111 !important;
            color: #fff !important;
        }
    </style>
    <style>
        /* El contenedor del banner debe ser relativo para poder posicionar el overlay */
        .campaign-banner-bleed {
            position: relative;
        }

        /* Caja flotante en la esquina inferior derecha */
        .banner-welcome {
            position: absolute;
            right: 1rem;
            bottom: 1rem;
            z-index: 2;
            max-width: min(90vw, 560px);
        }

        .banner-welcome__box {
            background: rgba(0, 0, 0, .65);
            /* fondo semitransparente para buena lectura */
            color: #fff;
            padding: 12px 16px;
            border-radius: 14px;
            font-weight: 700;
            line-height: 1.2;
            /* 2em en desktop; más pequeño en móviles automáticamente */
            font-size: clamp(1.1rem, 2.5vw, 2em);
            box-shadow: 0 8px 20px rgba(0, 0, 0, .25);
        }

        .banner-welcome__box p {
            margin: 0;
        }

        /* si el texto viene envuelto en <p> */
    </style>
@endpush

@section('content')
    <div class="site-content">
        @if (!empty($campaignBannerUrl))
            <style>
                .campaign-banner-bleed {
                    width: 100vw;
                    margin-left: calc(50% - 50vw);
                    margin-right: calc(50% - 50vw);
                    position: relative;
                    /* importante para el overlay */
                }

                .campaign-banner-bleed img {
                    display: block;
                    width: 100%;
                    height: 25vh;
                    /* mobile: 1/4 de alto de pantalla */
                    object-fit: cover;
                }

                @media (min-width: 992px) {
                    .campaign-banner-bleed img {
                        height: 33.333vh;
                    }

                    /* desktop: 1/3 */
                }
            </style>

            <div class="campaign-banner-bleed">
                <img src="{{ $campaignBannerUrl }}" alt="Banner campaña" loading="lazy">

                @if (!empty($welcomeMsg))
                    <div class="banner-welcome">
                        <div class="banner-welcome__box">
                            {!! $welcomeMsg !!}
                        </div>
                    </div>
                @endif
            </div>
        @endif
        <div class="container product-wrapper">
            <div id="hintSelect" class="alert alert-info mt-3">
                Selecciona un hijo o hija para ver los juguetes que puedes escoger.
            </div>
            {{-- Botones por hijo(a) con género F/M/NULL (NULL => neutro) --}}
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
                @foreach ($resultado as $grupo)
                    @php
                        $g = strtoupper(trim((string) ($grupo['hijo']['genero'] ?? '')));
                        $btnClass = $g === 'F' ? 'btn-kid-girl' : ($g === 'M' ? 'btn-kid-boy' : 'btn-kid-neutral');
                    @endphp
                    <div class="col">
                        <button class="btn {{ $btnClass }} w-100 text-truncate js-child-btn"
                            data-child-id="{{ $grupo['hijo']['id'] }}">
                            {{ $grupo['hijo']['nombre'] }}
                        </button>
                    </div>
                @endforeach
            </div>

            <div class="d-flex justify-content-end mt-2">
                <a href="{{ route('ecommerce.cart.index') }}" class="btn btn-primary">
                    <i class="icon-shopping-cart me-2"></i> Ver carrito
                </a>
            </div>

            <div class="product-grid mt-5">
                <div class="product-wrapper-grid">
                    <div id="toyGrid" class="row">
                        @foreach ($resultado as $grupo)
                            @foreach ($grupo['juguetes'] as $toy)
                                @php
                                    $idCampaign = $grupo['hijo']['idcampaign'] ?? null;

                                    // Imagen principal
                                    $imgRel = trim((string) ($toy['imagenppal'] ?? ''));
                                    $imgPath = '';
                                    if ($imgRel !== '') {
                                        $imgRel = ltrim($imgRel, '/');
                                        $imgPath = Str::startsWith($imgRel, 'campaign_toys/')
                                            ? $imgRel
                                            : "campaign_toys/{$idCampaign}/{$imgRel}";
                                    }

                                    // ID del modal
                                    $modalId =
                                        'modalToy_' .
                                        ($grupo['hijo']['id'] ?? 'h') .
                                        '_' .
                                        Str::slug((string) $toy['referencia'], '_');

                                    // Género del juguete: F, M o NULL => Unisex
                                    $tg = strtoupper(trim((string) ($toy['genero'] ?? '')));
                                    $toyGenderBadge = $tg === 'F' ? 'Niña' : ($tg === 'M' ? 'Niño' : 'Unisex');
                                    $toyGenderClass =
                                        $tg === 'F' ? 'bg-pink' : ($tg === 'M' ? 'bg-primary' : 'bg-secondary');
                                @endphp

                                {{-- Oculta por defecto y etiqueta con el ID del hijo --}}
                                <div class="col-xl-3 col-sm-6 xl-4 toy-card d-none"
                                    data-child-id="{{ $grupo['hijo']['id'] }}">
                                    <div class="card">
                                        <div class="product-box">
                                            <div class="product-img d-flex justify-content-center position-relative">
                                                @if ($imgPath !== '')
                                                    <img src="{{ Storage::url($imgPath) }}" alt="{{ $toy['nombre'] }}"
                                                        class="img-fluid" style="max-width:180px">
                                                @endif
                                                <div class="product-hover">
                                                    <ul>
                                                        <li>
                                                            <form method="POST" action="{{ route('ecommerce.cart.add') }}"
                                                                class="d-inline">
                                                                @csrf
                                                                <input type="hidden" name="idhijo"
                                                                    value="{{ $grupo['hijo']['id'] }}">
                                                                <input type="hidden" name="referencia"
                                                                    value="{{ $toy['referencia'] }}">
                                                                <button type="submit"
                                                                    class="btn p-0 border-0 bg-transparent"
                                                                    title="Agregar al carrito">
                                                                    <i class="icon-shopping-cart"></i>
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <button type="button" class="btn p-0 border-0 bg-transparent"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#{{ $modalId }}">
                                                                <i class="icon-eye"></i>
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>

                                            {{-- Modal detalle --}}
                                            <div class="modal fade" id="{{ $modalId }}" tabindex="-1"
                                                aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-primary text-white">
                                                            <h5 class="modal-title">Descripción</h5>
                                                            <button type="button" class="btn-close btn-close-white"
                                                                data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-lg-6 d-flex justify-content-center">
                                                                    @if ($imgPath !== '')
                                                                        <img src="{{ Storage::url($imgPath) }}"
                                                                            alt="{{ $toy['nombre'] }}" class="img-fluid"
                                                                            style="max-width:180px">
                                                                    @endif
                                                                </div>
                                                                <div class="col-lg-6 text-start">
                                                                    <h4 class="mb-2">{{ $toy['nombre'] }}</h4>
                                                                    <span
                                                                        class="badge {{ $toyGenderClass }} mb-2">{{ $toyGenderBadge }}</span>
                                                                    <h6 class="f-w-600">Descripción</h6>
                                                                    <p class="mb-0">{{ $toy['descripcion'] }}</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Card footer --}}
                                            <div class="product-details">
                                                <a href="javascript:void(0)">
                                                    <h4 class="mb-1">{{ $toy['nombre'] }}</h4>
                                                </a>
                                                <span class="badge {{ $toyGenderClass }}">{{ $toyGenderBadge }}</span>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>

                    {{-- Mensaje si no hay juguetes para el hijo seleccionado --}}
                    <div id="noResults" class="text-muted text-center py-5 d-none">
                        No hay juguetes disponibles para este hijo(a).
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Política de Datos --}}
    <div class="modal fade" id="modalPoliticaDatos" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
        data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Política de tratamiento de datos</h5>
                </div>

                <div class="modal-body">
                    <div style="max-height:60vh; overflow:auto;">
                        {!! $politicaHtml !!}
                    </div>
                </div>

                <div class="modal-footer">
                    <form action="{{ route('product.aceptarPolitica') }}" method="POST" class="ms-auto">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            Acepto la política
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('assets/js/jquery-3.5.1.min.js') }}"></script>
        <script src="{{ asset('assets/js/range-slider/ion.rangeSlider.min.js') }}"></script>
        <script src="{{ asset('assets/js/range-slider/rangeslider-script.js') }}"></script>
        <script src="{{ asset('assets/js/touchspin/vendors.min.js') }}"></script>
        <script src="{{ asset('assets/js/touchspin/touchspin.js') }}"></script>
        <script src="{{ asset('assets/js/touchspin/input-groups.min.js') }}"></script>
        <script src="{{ asset('assets/js/owlcarousel/owl.carousel.js') }}"></script>
        <script src="{{ asset('assets/js/select2/select2.full.min.js') }}"></script>
        <script src="{{ asset('assets/js/select2/select2-custom.js') }}"></script>
        <script src="{{ asset('assets/js/tooltip-init.js') }}"></script>
        <script src="{{ asset('assets/js/product-tab.js') }}"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            document.addEventListener('click', function(e) {
                const t = e.target.closest('[data-bs-toggle="modal"]');
                if (!t) return;
                e.preventDefault();
                const sel = t.getAttribute('data-bs-target');
                const el = document.querySelector(sel);
                if (el) new bootstrap.Modal(el).show();
            });
        </script>
        <script>
            $(function() {
                $('.js-child-btn').on('click', function(e) {
                    e.preventDefault();

                    const id = String($(this).data('child-id'));

                    // Estado visual del botón activo
                    $('.js-child-btn').removeClass('active');
                    $(this).addClass('active');

                    // Filtra tarjetas
                    const $all = $('.toy-card');
                    const $show = $all.filter('[data-child-id="' + id + '"]');

                    $all.addClass('d-none');
                    $show.removeClass('d-none');

                    // Mensajes
                    $('#hintSelect').addClass('d-none');
                    $('#noResults').toggleClass('d-none', $show.length > 0);

                    // Scroll al grid (opcional)
                    const $grid = $('#toyGrid');
                    if ($grid.length) {
                        window.scrollTo({
                            top: $grid.offset().top - 80,
                            behavior: 'smooth'
                        });
                    }
                });
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Reaplicar el hijo activo si viene en sesión
                @if (session('active_child_id'))
                    (function() {
                        const id = @json(session('active_child_id'));
                        const btn = document.querySelector('.js-child-btn[data-child-id="' + id + '"]');
                        if (btn) btn.click();
                    })();
                @endif

                // SweetAlert de respuesta del addcart
                @if (session('swal'))
                    Swal.fire(@json(session('swal')));
                @endif
            });
        </script>
        @if (!empty($showPolitica) && $showPolitica)
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var el = document.getElementById('modalPoliticaDatos');
                    if (el && typeof bootstrap !== 'undefined') new bootstrap.Modal(el).show();
                });
            </script>
        @endif
    @endpush
@endsection
