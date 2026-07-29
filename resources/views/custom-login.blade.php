@php
    $empresaNombre = $empresa->nombre ?? 'Empresa';
    $campNombre    = $campaign->nombre ?? 'Campaña';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $empresaNombre }} – {{ $campNombre }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        :root{
            --bar-fg: #F6C60F;
            --bar-height: 72px;
            --vh: 1vh; /* fallback para 100dvh */
        }

        html, body{ height:100%; }
        body{
            margin:0;
            color:#fff;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, "Helvetica Neue", Arial;
            background:#000;
        }

        /* ===== Fondo pantalla completa (SE ESTIRA para cubrir 100%) ===== */
        .bg-layer{
            position:fixed; inset:0; z-index:-2;
            width:100vw;
            height:100dvh;                      /* navegadores modernos */
            height:calc(var(--vh, 1vh) * 100);  /* fallback iOS/Android */
            overflow:hidden;
            background:#000;
        }
        .bg-stretch{
            /* La imagen se deforma si es necesario para cubrir TODO */
            position:absolute; inset:0;
            width:100vw;
            height:100dvh;
            height:calc(var(--vh, 1vh) * 100);
            display:block;
            object-fit: fill;        /* << clave: rellena sin respetar proporción */
            object-position: center; /* centrada mientras se estira */
            transform: translateZ(0);/* evita artefactos en móviles */
        }

        /* Sin velo de contraste — el fondo se mantiene tal cual la imagen original */
        .backdrop{
            position:fixed; inset:0; z-index:-1;
            background: transparent;
            pointer-events:none;
        }

        /* ===== Barra inferior fija ===== */
        .ingreso-bar{
            position:fixed; left:0; right:0; bottom:0;
            background:rgba(0,0,0,.45); color:var(--bar-fg);
            height:calc(var(--bar-height) + env(safe-area-inset-bottom, 0px));
            padding-bottom: env(safe-area-inset-bottom, 0px);
            display:flex; align-items:center; justify-content:center;
            z-index:30; box-shadow:0 -6px 18px rgba(0,0,0,.5);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        .ingreso-link{
            color:#fff; text-decoration:none; display:flex; align-items:center; justify-content:center;
            height:var(--bar-height); width:100%;
            font-weight:700; letter-spacing:.4px; font-size:clamp(16px, 2.5vw, 20px);
        }
        .hot-zone{ display:none; }

        /* ===== Modal de login ===== */
        .login-overlay{ position:fixed; inset:0; background:transparent; display:none; align-items:center; justify-content:center; z-index:50; padding:16px; }
        .login-overlay.active{ display:flex; }
        .login-modal{
            width:100%; max-width:420px; background:#0e0e0e; color:#fff;
            border-radius:16px; padding:22px 22px 16px; box-shadow:0 10px 30px rgba(0,0,0,.6);
        }
        .login-modal h2{ margin:0 0 6px; font-size:22px; }
        .login-modal .sub{ margin:0 0 16px; opacity:.9; font-size:14px; }

        .form-group{ margin-bottom:12px; }
        label{ display:block; font-size:14px; margin-bottom:6px; }
        input[type="text"], input[type="password"]{
            width:100%; border:1px solid #333; background:#141414; color:#fff;
            border-radius:10px; padding:10px 12px; font-size:15px; outline:none;
        }
        .actions{ display:flex; gap:12px; margin-top:10px; }
        .btn{ display:inline-flex; align-items:center; justify-content:center; gap:8px; border:1px solid transparent; padding:10px 12px; border-radius:10px; cursor:pointer; font-weight:700; text-decoration:none; color:#fff; }
        .btn-primary{ background:#2563eb; }
        .btn-secondary{ background:transparent; border-color:#444; }
        .error{ background:#7f1d1d; border:1px solid #991b1b; color:#fff; border-radius:10px; padding:8px 10px; margin-bottom:10px; }
    </style>
</head>
<body>
    <!-- Fondo 100% (se deforma si hace falta) -->
    <div class="bg-layer" aria-hidden="true">
        <img src="{{ $bgUrl }}" alt="" class="bg-stretch">
    </div>
    <div class="backdrop" aria-hidden="true"></div>

    <!-- Barra inferior -->
    <div class="ingreso-bar" id="ingresoBar">
        <a class="ingreso-link" id="openLogin" href="#">Ingresar</a>
    </div>
    <div class="hot-zone" id="hotZone" aria-hidden="true"></div>

    <!-- Overlay de Login -->
    <div class="login-overlay" id="loginOverlay" role="dialog" aria-modal="true" aria-labelledby="loginTitle">
        <div class="login-modal">
            <h2 id="loginTitle">Iniciar sesión</h2>
            <p class="sub">{{ $empresaNombre }} — {{ $campNombre }}</p>

            @if (session('status'))
                <div class="error">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="error">
                    @foreach ($errors->all() as $err)
                        <div>{{ $err }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="form-group">
                    <label for="documento">Documento</label>
                    <input type="text" name="documento" id="documento" value="{{ old('documento') }}" required
                           autocomplete="username" inputmode="numeric" placeholder="Ingresa tu documento">
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" name="password" id="password" required autocomplete="current-password"
                           placeholder="Ingresa tu contraseña">
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">Ingresar</button>
                    <button type="button" class="btn btn-secondary" id="closeLogin">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Fijar 100dvh real en móviles
        (function setRealVh(){
            const update = () => {
                const vh = window.innerHeight * 0.01;
                document.documentElement.style.setProperty('--vh', `${vh}px`);
            };
            update();
            window.addEventListener('resize', update);
            window.addEventListener('orientationchange', update);
        })();

        // Modal
        (function(){
            const overlay  = document.getElementById('loginOverlay');
            const backdrop = document.querySelector('.backdrop');
            const openBtn  = document.getElementById('openLogin');
            const closeBtn = document.getElementById('closeLogin');

            function openLogin(e){ if(e) e.preventDefault(); overlay.classList.add('active'); backdrop.classList.add('active'); setTimeout(()=>document.getElementById('documento')?.focus(), 50); }
            function closeLogin(){ overlay.classList.remove('active'); backdrop.classList.remove('active'); }

            openBtn.addEventListener('click', openLogin);
            closeBtn.addEventListener('click', closeLogin);
            overlay.addEventListener('click', e => { if(e.target === overlay) closeLogin(); });
            document.addEventListener('keydown', e => { if(e.key === 'Escape') closeLogin(); });

            @if ($errors->any() || session('status'))
                openLogin();
            @endif
        })();
    </script>
</body>
</html>
