@php
    $bg = $bannerUrl ?? null ? asset($bannerUrl ? '' : '') : null; // no tocar; lo pasamos desde el controlador
    $bg =
        $bannerUrl ??
        'data:image/svg+xml;charset=UTF-8,' .
            rawurlencode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#111"/><stop offset="100%" stop-color="#333"/></linearGradient></defs><rect width="100%" height="100%" fill="url(#g)"/></svg>',
            );

    $empresaNombre = $empresa->nombre ?? 'Empresa';
    $campNombre = $campaign->nombre ?? 'Campaña';
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>{{ $empresaNombre }} – {{ $campNombre }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        :root {
            --overlay: rgba(0, 0, 0, 0.35);
            --bar-bg: rgba(0, 0, 0, 0.7);
            --bar-fg: #fff;
            --bar-height: 72px;
            --hot-zone: 70px;
        }

        html,
        body {
            height: 100%;
        }

        body {
            margin: 0;
            color: #fff;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, "Helvetica Neue", Arial;
            background: #000 url("{{ $bg }}") center center / cover no-repeat fixed;
        }

        .backdrop {
            position: fixed;
            inset: 0;
            background:
                linear-gradient(to top, rgba(0, 0, 0, 0.65), transparent 40%),
                linear-gradient(to bottom, rgba(0, 0, 0, 0.35), transparent 30%),
                var(--overlay);
            pointer-events: none;
        }

        .content {
            position: relative;
            min-height: 100%;
            display: grid;
            place-items: center;
            text-align: center;
            padding: 24px;
        }

        .card {
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(4px);
            border-radius: 16px;
            padding: 24px 28px;
            max-width: 960px;
        }

        .card h1 {
            margin: 0 0 8px;
            font-weight: 700;
            font-size: clamp(22px, 3vw, 36px);
        }

        .card p {
            margin: 0;
            opacity: .9;
        }

        /* Barra inferior */
        .ingreso-bar {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            transform: translateY(100%);
            background: var(--bar-bg);
            color: var(--bar-fg);
            height: var(--bar-height);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform .28s ease;
            z-index: 30;
        }

        .ingreso-bar.active {
            transform: translateY(0);
        }

        .ingreso-link {
            color: #fff;
            text-decoration: none;
            display: block;
            width: 100%;
            height: 100%;
            line-height: var(--bar-height);
            text-align: center;
            font-weight: 700;
            letter-spacing: .4px;
            font-size: clamp(16px, 2.5vw, 20px);
        }

        .hot-zone {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            height: var(--hot-zone);
            z-index: 10;
        }

        /* Overlay login */
        .login-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .65);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 50;
            padding: 16px;
        }

        .login-overlay.active {
            display: flex;
        }

        .login-modal {
            width: 100%;
            max-width: 420px;
            background: #0e0e0e;
            color: #fff;
            border-radius: 16px;
            padding: 22px 22px 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .6);
        }

        .login-modal h2 {
            margin: 0 0 6px;
            font-size: 22px;
        }

        .login-modal .sub {
            margin: 0 0 16px;
            opacity: .9;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 12px;
        }

        label {
            display: block;
            font-size: 14px;
            margin-bottom: 6px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            border: 1px solid #333;
            background: #141414;
            color: #fff;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 15px;
            outline: none;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid transparent;
            padding: 10px 12px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            text-decoration: none;
            color: #fff;
        }

        .btn-primary {
            background: #2563eb;
        }

        .btn-secondary {
            background: transparent;
            border-color: #444;
        }

        .error {
            background: #7f1d1d;
            border: 1px solid #991b1b;
            color: #fff;
            border-radius: 10px;
            padding: 8px 10px;
            margin-bottom: 10px;
        }

        @media (hover: none) and (pointer: coarse) {
            .ingreso-bar {
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="backdrop" aria-hidden="true"></div>

    <main class="content" role="main">
        <div class="card" aria-label="Información de campaña">
            <h1>{{ $empresaNombre }}</h1>
            <p>{{ $campNombre }}</p>
        </div>
    </main>

    <!-- Barra inferior de ingreso -->
    <div class="ingreso-bar" id="ingresoBar">
        <a class="ingreso-link" id="openLogin" href="#">
            Ingresar
        </a>
    </div>
    <div class="hot-zone" id="hotZone" aria-hidden="true"></div>

    <!-- Overlay de Login -->
    <div class="login-overlay" id="loginOverlay" role="dialog" aria-modal="true" aria-labelledby="loginTitle">
        <div class="login-modal">
            <h2 id="loginTitle">Acceso a la campaña</h2>
            <p class="sub">{{ $empresaNombre }} — {{ $campNombre }}</p>

            @if ($errors->any())
                <div class="error">
                    @foreach ($errors->all() as $err)
                        <div>{{ $err }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('custom.login.auth') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="form-group">
                    <label for="identificacion">Documento</label>
                    <input type="text" name="identificacion" id="identificacion" value="{{ old('identificacion') }}"
                        required autocomplete="username">
                </div>

                {{-- Si quieres también email o PIN, descomenta y valida en backend
                <div class="form-group">
                    <label for="email">Correo (opcional)</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}">
                </div>
                --}}

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Ingresar</button>
                    <button type="button" class="btn btn-secondary" id="closeLogin">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            const bar = document.getElementById('ingresoBar');
            const hot = document.getElementById('hotZone');
            const open = document.getElementById('openLogin');
            const overlay = document.getElementById('loginOverlay');
            const closeBtn = document.getElementById('closeLogin');

            // Mostrar barra al acercar el ratón al borde inferior
            let hideTimer = null;

            function showBar() {
                bar.classList.add('active');
                if (hideTimer) clearTimeout(hideTimer);
                hideTimer = setTimeout(() => bar.classList.remove('active'), 3000);
            }

            function immediateShow() {
                bar.classList.add('active');
                if (hideTimer) clearTimeout(hideTimer);
            }
            hot.addEventListener('mouseenter', showBar);
            document.addEventListener('mousemove', (e) => {
                const threshold = 70;
                if ((window.innerHeight - e.clientY) <= threshold) showBar();
            });
            bar.addEventListener('mouseenter', immediateShow);
            bar.addEventListener('mouseleave', () => {
                if (hideTimer) clearTimeout(hideTimer);
                hideTimer = setTimeout(() => bar.classList.remove('active'), 800);
            });

            // Abrir / cerrar overlay
            function openLogin(e) {
                if (e) e.preventDefault();
                overlay.classList.add('active');
                setTimeout(() => {
                    document.getElementById('identificacion')?.focus();
                }, 50);
            }

            function closeLogin() {
                overlay.classList.remove('active');
            }
            open.addEventListener('click', openLogin);
            closeBtn.addEventListener('click', closeLogin);
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) closeLogin();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeLogin();
            });

            // Si el servidor devolvió errores, abrir overlay automáticamente
            @if ($errors->any())
                openLogin();
            @endif
        })();
    </script>
</body>

</html>
