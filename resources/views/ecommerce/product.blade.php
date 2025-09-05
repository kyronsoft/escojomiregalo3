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
            z-index: -1;
            pointer-events: none;
        }

        /* Contenedor topbar por defecto: flex en pantallas medias/grandes */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 1030;
            background: var(--primary);
            color: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .12);
            min-height: 100px;
            /* mobile base */
            padding-top: .25rem;
            padding-bottom: .25rem;
        }

        /* En pantallas ≥992px incrementa altura para logo 200px */
        @media (min-width: 992px) {
            .topbar {
                min-height: 120px;
                padding-top: .5rem;
                padding-bottom: .5rem;
            }
        }

        .topbar .btn {
            --bs-btn-padding-y: .35rem;
            --bs-btn-padding-x: .75rem;
            --bs-btn-font-size: .9rem;
        }

        /* Imagen de logo responsive */
        .topbar-logo-img {
            height: 100px;
            width: auto;
            object-fit: contain;
            display: block;
        }

        @media (min-width: 992px) {
            .topbar-logo-img {
                height: 100px;
            }
        }

        .btn-top {
            background-color: var(--secondary) !important;
            border-color: var(--secondary) !important;
            color: #fff !important;
        }

        .btn-top:hover,
        .btn-top:focus {
            filter: brightness(0.92);
            color: #fff !important;
        }

        .site-content {
            position: relative;
        }

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

        .campaign-banner-bleed {
            position: relative;
        }

        .banner-welcome {
            position: absolute;
            right: 1rem;
            bottom: 1rem;
            z-index: 2;
            max-width: min(90vw, 560px);
        }

        .banner-welcome__box {
            background: rgba(0, 0, 0, .65);
            color: #fff;
            padding: 12px 16px;
            border-radius: 14px;
            font-weight: 700;
            line-height: 1.2;
            font-size: clamp(1.1rem, 2.5vw, 2em);
            box-shadow: 0 8px 20px rgba(0, 0, 0, .25);
        }

        .banner-welcome__box p {
            margin: 0;
        }

        .topbar-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: .5rem;
        }

        .badge-gender {
            background-color: #FFCD01 !important;
            color: #fff !important;
        }

        @media (max-width: 499px) {
            .topbar {
                min-height: 70px;
            }

            .topbar-container {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                align-items: center;
                gap: .5rem;
            }

            .topbar-slot {
                width: 100%;
            }

            .topbar-logo-img {
                height: 50px;
            }

            .topbar .btn,
            .topbar form,
            .topbar a {
                width: 100%;
            }

            .topbar-cart-label {
                display: none;
            }

            .topbar-cart-btn .icon-shopping-cart {
                margin-right: 0 !important;
            }

            .topbar-slot.text-end {
                text-align: center !important;
            }
        }
    </style>
@endpush

@section('content')
    {{-- Barra superior con Logo + Ver carrito / Salir --}}
    <div class="topbar">
        <div class="container topbar-container">
            {{-- 1) Logo empresa (izquierda) --}}
            <div class="topbar-slot">
                @php
                    $logoUrl = !empty($empresaLogoUrl) ? $empresaLogoUrl : asset('assets/images/placeholder.png');
                @endphp
                <img src="{{ $logoUrl }}" alt="Logo empresa" class="topbar-logo-img">
            </div>

            {{-- 2) Botón Ver carrito (texto se oculta <500px) --}}
            <div class="topbar-slot">
                <a href="{{ route('ecommerce.cart.index') }}" class="btn btn-top w-100 topbar-cart-btn">
                    <i class="icon-shopping-cart me-1"></i>
                    <span class="topbar-cart-label">Ver carrito</span>
                </a>
            </div>

            {{-- 3) Botón Salir --}}
            <div class="topbar-slot">
                <form action="{{ route('logout') }}" method="POST" class="d-inline w-100">
                    @csrf
                    <button type="submit" class="btn btn-top w-100">
                        <i class="icon-power me-1"></i> Salir
                    </button>
                </form>
            </div>

            {{-- 4) Logo fijo (derecha) --}}
            <div class="topbar-slot text-end">
                <img src="{{ asset('assets/images/moreproducts/loginpage.png') }}" alt="Logo fijo" class="topbar-logo-img">
            </div>
        </div>
    </div>

    <div class="site-content">
        @if (!empty($campaignBannerUrl))
            <style>
                .campaign-banner-bleed {
                    width: 100vw;
                    margin-left: calc(50% - 50vw);
                    margin-right: calc(50% - 50vw);
                    position: relative;
                }

                .campaign-banner-bleed img {
                    display: block;
                    width: 100%;
                    height: 25vh;
                    object-fit: cover;
                }

                @media (min-width: 992px) {
                    .campaign-banner-bleed img {
                        height: 33.333vh;
                    }
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
            <div id="hintSelect" class="alert alert-info mt-3" style="background: black;">
                Selecciona un hijo o hija para ver los juguetes que puedes escoger.
            </div>

            {{-- Botones por hijo(a) --}}
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

            <div class="product-grid mt-5">
                <div class="product-wrapper-grid">
                    <div id="toyGrid" class="row">
                        @foreach ($resultado as $grupo)
                            @foreach ($grupo['juguetes'] as $toy)
                                @php
                                    $idCampaign = $grupo['hijo']['idcampaign'] ?? null;

                                    // ====== SOPORTE COMBOS (imagenppal con '+') ======
                                    $imgRel = trim((string) ($toy['imagenppal'] ?? ''));
                                    $imgRel = str_replace('\\', '/', $imgRel);
                                    $imgPaths = [];

                                    if ($imgRel !== '') {
                                        $parts = preg_split('/\s*\+\s*/', $imgRel); // divide por '+'
                                        foreach ($parts as $p) {
                                            if ($p === '') {
                                                continue;
                                            }
                                            $p = ltrim($p, '/');
                                            $path = Str::startsWith($p, 'campaign_toys/')
                                                ? $p
                                                : "campaign_toys/{$idCampaign}/{$p}";
                                            $imgPaths[] = $path;
                                        }
                                    }

                                    $isCombo = count($imgPaths) > 1;

                                    $modalId =
                                        'modalToy_' .
                                        ($grupo['hijo']['id'] ?? 'h') .
                                        '_' .
                                        Str::slug((string) $toy['referencia'], '_');

                                    $tg = strtoupper(trim((string) ($toy['genero'] ?? '')));
                                    $toyGenderBadge = $tg === 'F' ? 'Niña' : ($tg === 'M' ? 'Niño' : 'Unisex');
                                    $toyGenderClass =
                                        $tg === 'F' ? 'bg-pink' : ($tg === 'M' ? 'bg-primary' : 'bg-secondary');
                                @endphp

                                <div class="col-xl-3 col-sm-6 xl-4 toy-card d-none"
                                    data-child-id="{{ $grupo['hijo']['id'] }}">
                                    <div class="card">
                                        <div class="product-box">
                                            <div class="product-img d-flex justify-content-center position-relative">
                                                {{-- ====== Imagen (simple o combo) en tarjeta ====== --}}
                                                @if (!empty($imgPaths))
                                                    @if ($isCombo)
                                                        <div class="d-flex justify-content-center flex-wrap gap-2">
                                                            @foreach ($imgPaths as $p)
                                                                @if (Storage::disk('public')->exists($p))
                                                                    <img src="{{ Storage::disk('public')->url($p) }}"
                                                                        alt="{{ $toy['nombre'] }}" class="img-fluid"
                                                                        style="max-width:120px">
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        @php $p = $imgPaths[0]; @endphp
                                                        @if (Storage::disk('public')->exists($p))
                                                            <img src="{{ Storage::disk('public')->url($p) }}"
                                                                alt="{{ $toy['nombre'] }}" class="img-fluid"
                                                                style="max-width:180px">
                                                        @endif
                                                    @endif
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
                                                                    {{-- ====== Imagen (simple o combo) en modal ====== --}}
                                                                    @if (!empty($imgPaths))
                                                                        @if ($isCombo)
                                                                            <div
                                                                                class="d-flex justify-content-center flex-wrap gap-3">
                                                                                @foreach ($imgPaths as $p)
                                                                                    @if (Storage::disk('public')->exists($p))
                                                                                        <img src="{{ Storage::disk('public')->url($p) }}"
                                                                                            alt="{{ $toy['nombre'] }}"
                                                                                            class="img-fluid"
                                                                                            style="max-width:180px">
                                                                                    @endif
                                                                                @endforeach
                                                                            </div>
                                                                        @else
                                                                            @php $p = $imgPaths[0]; @endphp
                                                                            @if (Storage::disk('public')->exists($p))
                                                                                <img src="{{ Storage::disk('public')->url($p) }}"
                                                                                    alt="{{ $toy['nombre'] }}"
                                                                                    class="img-fluid"
                                                                                    style="max-width:180px">
                                                                            @endif
                                                                        @endif
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

                                                        <div class="modal-footer d-flex justify-content-between">
                                                            <button type="button" class="btn btn-outline-secondary"
                                                                data-bs-dismiss="modal">
                                                                Cerrar
                                                            </button>

                                                            <form method="POST"
                                                                action="{{ route('ecommerce.cart.add') }}"
                                                                class="d-inline">
                                                                @csrf
                                                                <input type="hidden" name="idhijo"
                                                                    value="{{ $grupo['hijo']['id'] }}">
                                                                <input type="hidden" name="referencia"
                                                                    value="{{ $toy['referencia'] }}">
                                                                <button type="submit" class="btn btn-success">
                                                                    <i class="icon-shopping-cart me-1"></i> Seleccionar
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="product-details">
                                                <a href="javascript:void(0)">
                                                    <h4 class="mb-1">{{ $toy['nombre'] }}</h4>
                                                </a>
                                                <span class="badge badge-gender">{{ $toyGenderBadge }}</span>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>

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

                    $('.js-child-btn').removeClass('active');
                    $(this).addClass('active');

                    const $all = $('.toy-card');
                    const $show = $all.filter('[data-child-id="' + id + '"]');

                    $all.addClass('d-none');
                    $show.removeClass('d-none');

                    $('#hintSelect').addClass('d-none');
                    $('#noResults').toggleClass('d-none', $show.length > 0);

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
                @if (session('active_child_id'))
                    (function() {
                        const id = @json(session('active_child_id'));
                        const btn = document.querySelector('.js-child-btn[data-child-id="' + id + '"]');
                        if (btn) btn.click();
                    })();
                @endif

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
