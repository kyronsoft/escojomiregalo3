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
        body { position: relative; }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: -1;
            opacity: .5;
            background: linear-gradient(to bottom, var(--secondary) 0%, var(--secondary) 100%);
        }

        /* Topbar */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 1030;
            background: var(--primary);
            color: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .12);
            min-height: 100px;
            padding: .25rem 0;
        }
        @media (min-width:992px) { .topbar { min-height: 120px; padding: .5rem 0; } }

        .topbar-container {
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: .75rem;
        }
        .topbar-left, .topbar-right { display: flex; align-items: center; }
        .topbar-center { display: flex; align-items: center; justify-content: center; gap: .5rem; }

        .topbar .btn {
            --bs-btn-padding-y: .35rem;
            --bs-btn-padding-x: .9rem;
            --bs-btn-font-size: .95rem;
        }

        .topbar-logo-img {
            height: 100px;
            width: auto;
            object-fit: contain;
            display: block;
        }
        @media (min-width:992px) { .topbar-logo-img { height: 100px; } }

        .btn-top { background: var(--secondary) !important; border-color: var(--secondary) !important; color:#fff !important; }
        .btn-top:hover, .btn-top:focus { filter: brightness(.92); color:#fff !important; }

        .site-content { position: relative; }

        /* Colores botones por género */
        .btn-kid-girl { background:#1B4C43 !important; border-color:#1B4C43 !important; color:#fff !important; }
        .btn-kid-boy  { background:#BA895D !important; border-color:#BA895D !important; color:#fff !important; }
        .btn-kid-neutral { background:#000 !important; border-color:#000 !important; color:#fff !important; }
        .btn-kid-girl:hover, .btn-kid-girl:focus,
        .btn-kid-boy:hover, .btn-kid-boy:focus,
        .btn-kid-neutral:hover, .btn-kid-neutral:focus { filter: brightness(.95); }

        .child-btn { position: relative; border-width: 2px; transition: transform .12s ease, box-shadow .18s ease, filter .18s ease; white-space: normal; overflow: visible; line-height: 1.2; padding: .65rem .8rem; }
        .child-btn:active { transform: translateY(1px) scale(.985); }
        .child-btn:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
        .child-btn.active { filter: brightness(.95); box-shadow: 0 0 0 3px #fff, 0 0 0 6px rgba(0,0,0,.35); }
        .child-btn .check { display:none; font-weight:700; }
        .child-btn.active .check { display:inline-block; }

        .children-box { border:1px solid #e5e7eb; border-radius:12px; }
        .children-box .col .child-btn { border:2px solid rgba(0,0,0,.15); border-radius:10px; }

        /* ===== Banner full-bleed y responsive ===== */
        .campaign-banner-bleed{
            width:100vw;
            margin-left:calc(50% - 50vw);
            margin-right:calc(50% - 50vw);
            position:relative;
            isolation:isolate;
        }
        .campaign-banner-top{
            background-image: var(--banner-url);
            background-size: cover;
            background-repeat: no-repeat;
            background-position: 30% 50%;
            height: clamp(160px, 26vw, 360px);
        }
        @media (min-width:1200px){
            .campaign-banner-top{ height: clamp(200px, 22vw, 420px); }
        }
        /* Móviles: evitar recorte (contain + aspect-ratio) */
        @media (max-width:768px){
            .campaign-banner-top{
                background-size: contain;
                background-position: center top;
                background-color: var(--secondary);
                aspect-ratio: 1103 / 318;
                height: auto;
                min-height: 0;
            }
        }
        @supports not (aspect-ratio: 1) {
            @media (max-width:768px){
                .campaign-banner-top{
                    height: calc(100vw * 0.288575);
                }
            }
        }

        .badge-gender { background:#FFCD01 !important; color:#fff !important; }

        /* ==== Tarjetas de juguetes ==== */
        .toy-card .card { border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.04); }
        .product-img { height:220px; display:flex; align-items:center; justify-content:center; position:relative; padding:12px; }
        .product-img img { max-height:100%; width:auto; height:auto; object-fit:contain; display:block; }
        .product-img .combo-wrap img { max-width:120px; max-height:100%; }

        /* --- MODAL: control de tamaños (desktop y móvil) --- */
        .modal-content { overflow: hidden; }
        .modal-body { overflow: auto; max-height: calc(100dvh - 9rem); }
        @media (max-width: 576px) {
        .modal-body { max-height: calc(100dvh - 7.25rem); padding: .75rem; }
        }

        .modal-product-media {
            display:flex;
            align-items:center;
            justify-content:center;
            width:100%;
            padding: 6px;
        }

        .modal-product-media img{
            display:block;
            width:auto !important;
            height:auto !important;
            max-width: 100%;
            max-height: 58vh;
            object-fit: contain;
        }

        .modal-combo{
            display:flex;
            flex-wrap:wrap;
            gap: 10px;
            justify-content:center;
            width:100%;
        }
        .modal-combo img{
            display:block;
            width:auto !important;
            height:auto !important;
            max-width: 48%;
            max-height: 42vh;
            object-fit: contain;
        }

        @media (max-width: 576px){
            .modal-product-media img{ max-height: 40vh; max-width: 92%; }
            .modal-combo img{ max-width:100%; max-height: 26vh; }
        }

        @media (max-width: 576px) and (orientation:landscape){
            .modal-product-media img{ max-height: 48vw; }
            .modal-combo img{ max-height: 34vw; }
        }

        /* Responsive topbar (móvil) */
        @media (max-width:576px) {
            .topbar-container { grid-template-columns: 1fr; row-gap:.5rem; text-align:center; }
            .topbar-center { justify-content: stretch; flex-wrap:wrap; }
            .topbar-center .btn, .topbar-center form { width:100%; }
            .topbar-logo-img { height:50px; }
        }

        /* Toy cards más compactas en móvil */
        @media (max-width:576px) {
            .child-btn { font-size:.95rem; padding:.55rem .65rem; }
            .product-wrapper-grid #toyGrid { row-gap:.75rem; }
            .toy-card { padding-left:.25rem; padding-right:.25rem; }
            .toy-card .card { margin-bottom:.5rem; }
            .product-img { height:160px; padding:8px; }
            .product-details h4 { font-size:1rem; }
        }
    </style>
@endpush

@section('content')
    @php
        // Bandera booleana para mostrar/ocultar etiquetas de género
        $__mostrarGenero = !empty($mostrarGenero);
    @endphp

    {{-- Barra superior con Logo (izq) + Acciones (centro) + Logo (der) --}}
    <div class="topbar">
        <div class="container topbar-container">
            {{-- Izquierda: logo empresa --}}
            <div class="topbar-left">
                <img src="{{ asset('assets/images/moreproducts/logoemrgris.jpeg') }}" alt="Logo fijo" class="topbar-logo-img">
            </div>

            {{-- Centro: botones --}}
            <div class="topbar-center">
                <a href="{{ route('ecommerce.cart.index') }}" class="btn btn-top topbar-cart-btn">
                    <i class="icon-shopping-cart me-1"></i>
                    <span class="topbar-cart-label">Ver carrito</span>
                </a>
                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-top">
                        <i class="icon-power me-1"></i> Salir
                    </button>
                </form>
            </div>

            {{-- Derecha: logo fijo --}}
            <div class="topbar-right">
                @php $logoUrl = !empty($empresaLogoUrl) ? $empresaLogoUrl : asset('assets/images/placeholder.png'); @endphp
                <img src="{{ $logoUrl }}" alt="Logo empresa" class="topbar-logo-img">
            </div>
        </div>
    </div>

    <div class="site-content">
        @if (!empty($campaignBannerUrl))
        <div class="campaign-banner-bleed">
            <div class="campaign-banner-top" style="--banner-url: url('{{ $campaignBannerUrl }}')"></div>
        </div>
        @endif

        <div class="container product-wrapper">
            {{-- Nombre del colaborador como saludo/identificador --}}
            @php
                $colabName =
                    $colaboradorNombre ??
                    null ??
                    ($colaborador->nombre ??
                        null ??
                        (optional(auth()->user())->name ??
                            (optional(auth()->user())->nombre ?? (optional(auth()->user())->email ?? 'Colaborador'))));
            @endphp
            <div id="hintSelect" class="alert alert-info mt-3 text-white" style="background:black;">
                <strong>Hola, <i>{{ $colabName }}</i> seleccione su hijo(a)</strong>
            </div>

            {{-- Contenedor enmarcado de botones de hijos --}}
            <div class="card shadow-sm mb-4 children-box" role="group" aria-label="Selecciona un hijo(a)">
                <div class="card-body py-3">
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
                        @foreach ($resultado as $grupo)
                            @php
                                $gRaw = $grupo['hijo']['genero'] ?? '';
                                $g = strtoupper(trim((string) $gRaw));
                                $btnClass = match (true) {
                                    in_array($g, ['NIÑA', 'NINA', 'F']) => 'btn-kid-girl',
                                    in_array($g, ['NIÑO', 'NINO', 'M']) => 'btn-kid-boy',
                                    in_array($g, ['UNISEX', 'U', '']) => 'btn-kid-neutral',
                                    default => 'btn-kid-neutral',
                                };
                            @endphp
                            <div class="col d-grid">
                                <button class="btn child-btn {{ $btnClass }} w-100 js-child-btn" type="button"
                                        data-child-id="{{ $grupo['hijo']['id'] }}" aria-pressed="false">
                                    <span class="label">{{ $grupo['hijo']['nombre'] }}</span>
                                    <span class="check ms-2" aria-hidden="true">✓</span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Grid de juguetes --}}
            <div class="product-grid mt-4">
                <div class="product-wrapper-grid">
                    <div id="toyGrid" class="row">
                        @foreach ($resultado as $grupo)
                            @foreach ($grupo['juguetes'] as $toy)
                                {{-- Corte de seguridad: si no hay stock, no se muestra --}}
                                @continue(isset($toy['stock']) && (int)$toy['stock'] <= 0)

                                @php
                                    $idCampaign = $grupo['hijo']['idcampaign'] ?? null;
                                    $imgRel = trim((string) ($toy['imagenppal'] ?? ''));
                                    $imgRel = str_replace('\\', '/', $imgRel);
                                    $imgPaths = [];
                                    if ($imgRel !== '') {
                                        $parts = preg_split('/\s*\+\s*/', $imgRel);
                                        foreach ($parts as $p) {
                                            if ($p === '') continue;
                                            $p = ltrim($p, '/');
                                            if (preg_match('#^https?://#i', $p)) {
                                                $imgPaths[] = $p;
                                            } else {
                                                $path = \Illuminate\Support\Str::startsWith($p, 'campaign_toys/')
                                                    ? $p
                                                    : "campaign_toys/{$idCampaign}/{$p}";
                                                $imgPaths[] = $path;
                                            }
                                        }
                                    }
                                    $isCombo = count($imgPaths) > 1;
                                    $modalId = 'modalToy_' . ($grupo['hijo']['id'] ?? 'h') . '_' . \Illuminate\Support\Str::slug((string) $toy['referencia'], '_');
                                    $tg = strtoupper(trim((string) ($toy['genero'] ?? '')));
                                    $toyGenderBadge = $tg === 'F' ? 'Niña' : ($tg === 'M' ? 'Niño' : 'Unisex');
                                    $toyGenderClass = $tg === 'F' ? 'bg-pink' : ($tg === 'M' ? 'bg-primary' : 'bg-secondary');
                                @endphp

                                <div class="col-xl-3 col-sm-6 xl-4 toy-card d-none" data-child-id="{{ $grupo['hijo']['id'] }}">
                                    <div class="card">
                                        <div class="product-box">
                                            <div class="product-img">
                                                {{-- Imagen (simple o combo) en tarjeta --}}
                                                @if (!empty($imgPaths))
                                                    @if ($isCombo)
                                                        <div class="combo-wrap d-flex justify-content-center flex-wrap gap-2">
                                                            @foreach ($imgPaths as $p)
                                                                @php
                                                                    $url = preg_match('#^https?://#i', $p)
                                                                        ? $p
                                                                        : (Storage::disk('public')->exists($p) ? Storage::disk('public')->url($p) : asset('assets/images/placeholder.png'));
                                                                @endphp
                                                                <img src="{{ $url }}" alt="{{ $toy['nombre'] }}" class="img-fluid" onerror="this.onerror=null; this.src='{{ asset('assets/images/placeholder.png') }}';">
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        @php
                                                            $p = $imgPaths[0];
                                                            $url = preg_match('#^https?://#i', $p)
                                                                ? $p
                                                                : (Storage::disk('public')->exists($p) ? Storage::disk('public')->url($p) : asset('assets/images/placeholder.png'));
                                                        @endphp
                                                        <img src="{{ $url }}" alt="{{ $toy['nombre'] }}" class="img-fluid" onerror="this.onerror=null; this.src='{{ asset('assets/images/placeholder.png') }}';">
                                                    @endif
                                                @else
                                                    <img src="{{ asset('assets/images/placeholder.png') }}" alt="Placeholder" class="img-fluid">
                                                @endif

                                                <div class="product-hover">
                                                    <ul>
                                                        <li>
                                                            <form method="POST" action="{{ route('ecommerce.cart.add') }}" class="d-inline">
                                                                @csrf
                                                                <input type="hidden" name="idhijo" value="{{ $grupo['hijo']['id'] }}">
                                                                <input type="hidden" name="referencia" value="{{ $toy['referencia'] }}">
                                                                <button type="submit" class="btn p-0 border-0 bg-transparent" title="Agregar al carrito">
                                                                    <i class="icon-shopping-cart"></i>
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <button type="button" class="btn p-0 border-0 bg-transparent"
                                                                    data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                                                                <i class="icon-eye"></i>
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>

                                            {{-- Modal detalle (con límites para evitar desbordes) --}}
                                            <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-primary text-white">
                                                            <h5 class="modal-title">Descripción</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row g-3">
                                                                <div class="col-12 col-lg-6">
                                                                    <div class="modal-product-media">
                                                                        @if (!empty($imgPaths))
                                                                            @if ($isCombo)
                                                                                <div class="modal-combo">
                                                                                    @foreach ($imgPaths as $p)
                                                                                        @php
                                                                                            $url = preg_match('#^https?://#i', $p)
                                                                                                ? $p
                                                                                                : (Storage::disk('public')->exists($p) ? Storage::disk('public')->url($p) : asset('assets/images/placeholder.png'));
                                                                                        @endphp
                                                                                        <img src="{{ $url }}" alt="{{ $toy['nombre'] }}" class="img-fluid" onerror="this.onerror=null; this.src='{{ asset('assets/images/placeholder.png') }}';">
                                                                                    @endforeach
                                                                                </div>
                                                                            @else
                                                                                @php
                                                                                    $p = $imgPaths[0];
                                                                                    $url = preg_match('#^https?://#i', $p)
                                                                                        ? $p
                                                                                        : (Storage::disk('public')->exists($p) ? Storage::disk('public')->url($p) : asset('assets/images/placeholder.png'));
                                                                                @endphp
                                                                                <img src="{{ $url }}" alt="{{ $toy['nombre'] }}" class="img-fluid" onerror="this.onerror=null; this.src='{{ asset('assets/images/placeholder.png') }}';">
                                                                            @endif
                                                                        @else
                                                                            <img src="{{ asset('assets/images/placeholder.png') }}" alt="Placeholder" class="img-fluid">
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-lg-6 text-start">
                                                                    <h4 class="mb-2">{{ $toy['nombre'] }}</h4>

                                                                    {{-- Badge de género en modal (oculto si mostrargenero = 0) --}}
                                                                    @if ($__mostrarGenero)
                                                                        <span class="badge {{ $toyGenderClass }} mb-2">{{ $toyGenderBadge }}</span>
                                                                    @endif

                                                                    <h6 class="f-w-600">Descripción</h6>
                                                                    <p class="mb-0">{{ $toy['descripcion'] }}</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer d-flex justify-content-between">
                                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                            <form method="POST" action="{{ route('ecommerce.cart.add') }}" class="d-inline">
                                                                @csrf
                                                                <input type="hidden" name="idhijo" value="{{ $grupo['hijo']['id'] }}">
                                                                <input type="hidden" name="referencia" value="{{ $toy['referencia'] }}">
                                                                <button type="submit" class="btn btn-success">
                                                                    <i class="icon-shopping-cart me-1"></i> Seleccionar
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="product-details">
                                                <a href="javascript:void(0)"><h4 class="mb-1">{{ $toy['nombre'] }}</h4></a>

                                                {{-- Badge de género debajo de la foto (oculto si mostrargenero = 0) --}}
                                                @if ($__mostrarGenero)
                                                    <span class="badge badge-gender">{{ $toyGenderBadge }}</span>
                                                @endif

                                                {{-- Debug opcional de stock:
                                                <span class="badge bg-dark ms-2">Stock: {{ (int)($toy['stock'] ?? -1) }}</span>
                                                --}}
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
    <div class="modal fade" id="modalPoliticaDatos" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Política de tratamiento de datos</h5></div>
                <div class="modal-body"><div style="max-height:60vh; overflow:auto;">{!! $politicaHtml !!}</div></div>
                <div class="modal-footer">
                    <form id="formPolitica" action="{{ route('product.aceptarPolitica') }}" method="POST" class="ms-auto">
                        @csrf
                        <input type="hidden" name="politicadatos" value="Y">
                        <button type="submit" class="btn btn-primary" id="btnAceptarPolitica">Acepto la política</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Bienvenida (Welcome) --}}
    <div class="modal fade" id="modalWelcome" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">¡Bienvenido(a)!</h5></div>
                <div class="modal-body"><div style="max-height:60vh; overflow:auto;">{!! $welcomeMsg !!}</div></div>
                <div class="modal-footer">
                    <form id="formWelcome" action="{{ route('product.aceptarWelcome') }}" method="POST" class="ms-auto">
                        @csrf
                        <input type="hidden" name="welcome" value="Y">
                        <button type="submit" class="btn btn-primary" id="btnAceptarWelcome">Aceptar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- Dependencias varias (jQuery, etc.) --}}
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

    {{-- Apertura segura de modales por data-bs-target (si Bootstrap está) --}}
    <script>
        document.addEventListener('click', function(e) {
            const t = e.target.closest('[data-bs-toggle="modal"]');
            if (!t) return;
            e.preventDefault();
            const sel = t.getAttribute('data-bs-target');
            const el = document.querySelector(sel);
            if (el && window.bootstrap) new bootstrap.Modal(el).show();
        });
    </script>

    {{-- Selección de hijo --}}
    <script>
        $(function() {
            $('.js-child-btn').on('click', function(e) {
                e.preventDefault();
                const id = String($(this).data('child-id'));

                $('.js-child-btn').removeClass('active').attr('aria-pressed', 'false');
                $(this).addClass('active').attr('aria-pressed', 'true');

                const $all = $('.toy-card');
                const $show = $all.filter('[data-child-id="' + id + '"]');

                $all.addClass('d-none');
                $show.removeClass('d-none');

                $('#hintSelect').addClass('d-none');
                $('#noResults').toggleClass('d-none', $show.length > 0);

                const $grid = $('#toyGrid');
                if ($grid.length) {
                    window.scrollTo({ top: $grid.offset().top - 80, behavior: 'smooth' });
                }
            });
        });
    </script>

    {{-- Mostrar Welcome / Política (solo una a la vez; prioridad Welcome) --}}
    <script>
        (function() {
            const SHOULD_SHOW_WELCOME = {!! json_encode(!empty($welcomeMsg) && !empty($showWelcome) && $showWelcome) !!};
            const SHOULD_SHOW_POLITICA = {!! json_encode(!empty($showPolitica) && $showPolitica) !!};

            function ensureBootstrap(callback) {
                if (window.bootstrap && typeof window.bootstrap.Modal === 'function') { callback(); return; }
                var s = document.createElement('script');
                s.src = "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js";
                s.defer = true;
                s.onload = callback;
                document.head.appendChild(s);
            }

            function showModal(id) {
                const el = document.getElementById(id);
                if (!el) return;
                const modal = new bootstrap.Modal(el, { backdrop: 'static', keyboard: false });
                modal.show();
            }

            function start() {
                if (SHOULD_SHOW_WELCOME)      showModal('modalWelcome');
                else if (SHOULD_SHOW_POLITICA) showModal('modalPoliticaDatos');
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => ensureBootstrap(start));
            } else {
                ensureBootstrap(start);
            }
        })();
    </script>

    {{-- Auto-acciones por sesión --}}
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
@endpush
