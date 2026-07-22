<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'App Arrendamientos') }} | Gestión de arrendamientos en Costa Rica</title>
    <meta name="description" content="Administra propiedades, contratos, adéndums, facturación electrónica y notificaciones de arrendamiento en un solo lugar.">
    <meta name="robots" content="index,follow">
    <meta name="theme-color" content="#212C4C">

    <link rel="shortcut icon" href="{{ asset('/assets/compiled/svg/favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer">

    <script>
        (function () {
            var stored = localStorage.getItem('arrendamientos-theme');
            var resolved = stored || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', resolved);
        })();
    </script>

    <style>
        :root {
            color-scheme: light;
            --primary: #212C4C;
            --primary-strong: #161d33;
            --secondary: #4CD2D9;
            --secondary-strong: #33b4bb;
            --secondary-contrast: #0b2027;

            --bg: #f3f6fb;
            --surface: #ffffff;
            --surface-2: #eaeff8;
            --ink: #1a2138;
            --muted: #5b6478;
            --border: #dfe5f0;
            --shadow: rgba(33, 44, 76, .14);
            --nav-bg: rgba(255, 255, 255, .85);
            --cta-bg: var(--primary);
            --cta-ink: #ffffff;
            --icon-bg: var(--secondary);
            --icon-ink: var(--secondary-contrast);
        }

        :root[data-theme="dark"] {
            color-scheme: dark;
            --bg: #0c1120;
            --surface: #212C4C;
            --surface-2: #29365c;
            --ink: #eef1f8;
            --muted: #a7b0c8;
            --border: #34406b;
            --shadow: rgba(0, 0, 0, .45);
            --nav-bg: rgba(12, 17, 32, .85);
            --cta-bg: var(--secondary);
            --cta-ink: var(--secondary-contrast);
            --icon-bg: var(--secondary);
            --icon-ink: var(--secondary-contrast);
        }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                color-scheme: dark;
                --bg: #0c1120;
                --surface: #212C4C;
                --surface-2: #29365c;
                --ink: #eef1f8;
                --muted: #a7b0c8;
                --border: #34406b;
                --shadow: rgba(0, 0, 0, .45);
                --nav-bg: rgba(12, 17, 32, .85);
                --cta-bg: var(--secondary);
                --cta-ink: var(--secondary-contrast);
                --icon-bg: var(--secondary);
                --icon-ink: var(--secondary-contrast);
            }
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--ink);
            line-height: 1.55;
            transition: background-color .25s ease, color .25s ease;
        }
        a { color: inherit; }
        img { max-width: 100%; }
        .container { width: min(1160px, 92vw); margin: 0 auto; }

        header.site {
            position: sticky;
            top: 0;
            z-index: 20;
            background: var(--nav-bg);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
        }
        .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            padding: .9rem 0;
        }
        .brand { display: flex; align-items: center; gap: .6rem; text-decoration: none; }
        .brand .logo-badge {
            width: 32px; height: 32px;
            border-radius: 9px;
            background: var(--primary);
            display: flex; align-items: center; justify-content: center;
            color: var(--secondary);
            font-weight: 800;
            font-size: .95rem;
        }
        .brand span { font-weight: 700; font-size: 1.05rem; }
        .nav-links { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .55rem 1.1rem;
            border-radius: 10px;
            font-size: .95rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
            transition: transform .12s ease, box-shadow .12s ease, background-color .12s ease, border-color .12s ease, color .12s ease;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background: var(--cta-bg); color: var(--cta-ink); }
        .btn-primary:hover { box-shadow: 0 10px 24px -10px var(--shadow); filter: brightness(1.05); }
        .btn-outline { background: transparent; border-color: var(--border); color: var(--ink); }
        .btn-outline:hover { border-color: var(--secondary); color: var(--secondary-strong); }
        .btn-ghost { background: transparent; color: var(--muted); }
        .btn-ghost:hover { color: var(--ink); }
        .btn-lg { padding: .8rem 1.6rem; font-size: 1.02rem; }

        .theme-toggle {
            width: 40px; height: 40px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--ink);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .theme-toggle:hover { border-color: var(--secondary); color: var(--secondary-strong); }
        .theme-toggle .fa-sun { display: none; }
        :root[data-theme="dark"] .theme-toggle .fa-moon { display: none; }
        :root[data-theme="dark"] .theme-toggle .fa-sun { display: inline-block; }

        .hero { padding: 4rem 0 3.5rem; }
        .hero-inner { display: grid; grid-template-columns: 1.1fr .9fr; gap: 3rem; align-items: center; }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: var(--surface-2);
            color: var(--secondary-strong);
            font-weight: 700;
            font-size: .8rem;
            padding: .35rem .8rem;
            border-radius: 999px;
            margin-bottom: 1.1rem;
        }
        h1 { font-size: clamp(2rem, 4vw, 2.7rem); line-height: 1.15; margin: 0 0 1rem; letter-spacing: -0.01em; }
        .hero p.lead { font-size: 1.1rem; color: var(--muted); margin: 0 0 1.8rem; max-width: 46ch; }
        .hero-ctas { display: flex; flex-wrap: wrap; gap: .8rem; }

        .hero-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 1.6rem;
            box-shadow: 0 24px 48px -28px var(--shadow);
        }
        .hero-card h3 { margin: 0 0 .2rem; font-size: 1rem; }
        .hero-card p.small { margin: 0 0 1.1rem; color: var(--muted); font-size: .9rem; }
        .hero-card ul { list-style: none; margin: 0; padding: 0; display: grid; gap: .7rem; }
        .hero-card li { display: flex; align-items: flex-start; gap: .6rem; font-size: .92rem; }
        .hero-card li i {
            width: 26px; height: 26px; flex-shrink: 0;
            border-radius: 7px;
            background: var(--icon-bg);
            color: var(--icon-ink);
            display: flex; align-items: center; justify-content: center;
            font-size: .8rem;
        }

        section.split {
            background: var(--surface);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 3.5rem 0;
        }
        .section-head { text-align: center; max-width: 640px; margin: 0 auto 2.6rem; }
        .section-head h2 { font-size: clamp(1.5rem, 3vw, 2rem); margin: 0 0 .6rem; }
        .section-head p { color: var(--muted); margin: 0; }

        .features { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.2rem; }
        .feature {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.4rem;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .feature:hover { transform: translateY(-3px); box-shadow: 0 16px 32px -20px var(--shadow); }
        .feature .icon {
            width: 42px; height: 42px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 11px;
            background: var(--icon-bg);
            color: var(--icon-ink);
            margin-bottom: .9rem;
            font-size: 1.05rem;
        }
        .feature h3 { margin: 0 0 .4rem; font-size: 1.02rem; }
        .feature p { margin: 0; color: var(--muted); font-size: .9rem; }

        .audiences { padding: 3.8rem 0; }
        .audience-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.6rem; }
        .audience-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 1.8rem;
        }
        .audience-card .tag {
            display: inline-block;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--secondary-strong);
            margin-bottom: .5rem;
        }
        .audience-card h3 { margin: 0 0 .3rem; font-size: 1.25rem; }
        .audience-card p.desc { color: var(--muted); margin: 0 0 1.1rem; }
        .audience-card ul { list-style: none; margin: 0 0 1.3rem; padding: 0; display: grid; gap: .55rem; }
        .audience-card li { display: flex; align-items: flex-start; gap: .55rem; font-size: .93rem; }
        .audience-card li i { color: var(--secondary-strong); margin-top: .25rem; }

        .cta-band {
            background: linear-gradient(120deg, var(--primary) 0%, #1f5f66 55%, var(--secondary) 100%);
            color: #fff;
            padding: 3rem 0;
        }
        .cta-inner { display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; flex-wrap: wrap; }
        .cta-inner h2 { margin: 0 0 .3rem; font-size: 1.5rem; }
        .cta-inner p { margin: 0; color: rgba(255, 255, 255, .85); }
        .cta-band .btn-outline { background: transparent; border-color: rgba(255, 255, 255, .5); color: #fff; }
        .cta-band .btn-outline:hover { border-color: #fff; }
        .cta-band .btn-primary { background: #fff; color: var(--primary-strong); }
        .cta-band .btn-primary:hover { background: #f0f2f8; filter: none; }

        footer.site { padding: 2.2rem 0; border-top: 1px solid var(--border); background: var(--surface); }
        .footer-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            color: var(--muted);
            font-size: .88rem;
        }
        .footer-links { display: flex; gap: 1.2rem; }
        .footer-links a:hover { color: var(--secondary-strong); }

        @media (max-width: 900px) {
            .hero-inner { grid-template-columns: 1fr; }
            .features { grid-template-columns: repeat(2, 1fr); }
            .audience-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .features { grid-template-columns: 1fr; }
            .nav { flex-wrap: wrap; }
            .nav-links { width: 100%; justify-content: flex-start; }
            .cta-inner { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

    <header class="site">
        <div class="container nav">
            <a class="brand" href="{{ url('/') }}">
                <span class="logo-badge">AA</span>
                <span>App Arrendamientos</span>
            </a>
            <nav class="nav-links">
                <a class="btn btn-ghost" href="{{ route('public.properties.index') }}">
                    <i class="fa-solid fa-house-chimney"></i> Propiedades públicas
                </a>
                @auth
                    <a class="btn btn-primary" href="{{ Auth::user()->isLessor() ? route('admin.index') : route('tenant.index') }}">
                        Ir a mi panel
                    </a>
                @else
                    <a class="btn btn-outline" href="{{ route('auth.login') }}">Iniciar sesión</a>
                    <a class="btn btn-primary" href="{{ route('auth.register') }}">Crear cuenta</a>
                @endauth
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="Cambiar tema claro u oscuro">
                    <i class="fa-solid fa-moon"></i>
                    <i class="fa-solid fa-sun"></i>
                </button>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="container hero-inner">
                <div>
                    <span class="eyebrow"><i class="fa-solid fa-location-dot"></i> Hecho para Costa Rica</span>
                    <h1>Administra tus propiedades, contratos y cobros desde un solo lugar</h1>
                    <p class="lead">Una plataforma para arrendadores e inquilinos: contratos digitales, adéndums, facturación electrónica ante Hacienda y notificaciones, todo en un mismo panel.</p>
                    <div class="hero-ctas">
                        <a class="btn btn-primary btn-lg" href="{{ route('auth.login') }}">
                            <i class="fa-solid fa-right-to-bracket"></i> Iniciar sesión
                        </a>
                        <a class="btn btn-outline btn-lg" href="{{ route('auth.register') }}">
                            Crear una cuenta
                        </a>
                        <a class="btn btn-ghost btn-lg" href="{{ route('public.properties.index') }}">
                            Ver propiedades disponibles <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <div class="hero-card">
                    <h3>Todo tu arrendamiento, ordenado</h3>
                    <p class="small">Lo que encuentras al entrar a tu panel:</p>
                    <ul>
                        <li><i class="fa-solid fa-file-signature"></i> Contratos y adéndums con firma y seguimiento de estado</li>
                        <li><i class="fa-solid fa-file-invoice-dollar"></i> Facturación electrónica compatible con Hacienda Costa Rica</li>
                        <li><i class="fa-solid fa-building"></i> Registro y edición de propiedades con fotos y ubicación exacta</li>
                        <li><i class="fa-solid fa-bell"></i> Notificaciones de vencimientos, pagos y cancelaciones</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="split">
            <div class="container">
                <div class="section-head">
                    <span class="eyebrow">Funcionalidades</span>
                    <h2>Pensado para el día a día del arrendamiento</h2>
                    <p>Desde el registro de la propiedad hasta el cobro del mes, sin hojas de cálculo sueltas.</p>
                </div>
                <div class="features">
                    <div class="feature">
                        <div class="icon"><i class="fa-solid fa-building"></i></div>
                        <h3>Propiedades</h3>
                        <p>Registra propiedades con fotos, ubicación por provincia/cantón/distrito y disponibilidad.</p>
                    </div>
                    <div class="feature">
                        <div class="icon"><i class="fa-solid fa-file-signature"></i></div>
                        <h3>Contratos y adéndums</h3>
                        <p>Genera, edita, cancela y da seguimiento al estado de cada contrato o modificación.</p>
                    </div>
                    <div class="feature">
                        <div class="icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                        <h3>Facturación electrónica</h3>
                        <p>Emite y da seguimiento a facturas electrónicas ante el Ministerio de Hacienda.</p>
                    </div>
                    <div class="feature">
                        <div class="icon"><i class="fa-solid fa-bell"></i></div>
                        <h3>Notificaciones</h3>
                        <p>Recibe avisos de contratos por vencer, cancelaciones y actividad relevante.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="audiences">
            <div class="container">
                <div class="section-head">
                    <span class="eyebrow">Para cada rol</span>
                    <h2>Un panel distinto según lo que necesites</h2>
                </div>
                <div class="audience-grid">
                    <div class="audience-card">
                        <span class="tag">Arrendadores</span>
                        <h3>Gestiona tus propiedades e inquilinos</h3>
                        <p class="desc">Controla contratos, facturación y notificaciones de todas tus propiedades desde un panel de administración.</p>
                        <ul>
                            <li><i class="fa-solid fa-check"></i> Registro y edición de propiedades</li>
                            <li><i class="fa-solid fa-check"></i> Creación y cancelación de contratos y adéndums</li>
                            <li><i class="fa-solid fa-check"></i> Emisión de facturas electrónicas</li>
                        </ul>
                        <a class="btn btn-outline" href="{{ route('auth.register') }}">Registrarme como arrendador</a>
                    </div>
                    <div class="audience-card">
                        <span class="tag">Inquilinos</span>
                        <h3>Consulta y gestiona tu arrendamiento</h3>
                        <p class="desc">Revisa tus contratos, acepta adéndums y consulta tus facturas sin depender de correos o llamadas.</p>
                        <ul>
                            <li><i class="fa-solid fa-check"></i> Visualización y aceptación de contratos</li>
                            <li><i class="fa-solid fa-check"></i> Descarga de documentos firmados</li>
                            <li><i class="fa-solid fa-check"></i> Historial de facturas y notificaciones</li>
                        </ul>
                        <a class="btn btn-outline" href="{{ route('auth.login') }}">Ya tengo una cuenta</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta-band">
            <div class="container cta-inner">
                <div>
                    <h2>¿Listo para empezar?</h2>
                    <p>Inicia sesión con tu cuenta o crea una nueva para comenzar a gestionar tus arrendamientos.</p>
                </div>
                <div class="hero-ctas">
                    <a class="btn btn-primary btn-lg" href="{{ route('auth.login') }}">Iniciar sesión</a>
                    <a class="btn btn-outline btn-lg" href="{{ route('auth.register') }}">Crear cuenta</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="site">
        <div class="container footer-inner">
            <span>&copy; {{ date('Y') }} App Arrendamientos. Todos los derechos reservados.</span>
            <div class="footer-links">
                <a href="{{ route('public.properties.index') }}">Propiedades públicas</a>
                <a href="{{ route('auth.login') }}">Iniciar sesión</a>
                <a href="{{ route('auth.register') }}">Crear cuenta</a>
            </div>
        </div>
    </footer>

    <script>
        (function () {
            var root = document.documentElement;
            var toggle = document.getElementById('themeToggle');
            toggle.addEventListener('click', function () {
                var current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                var next = current === 'dark' ? 'light' : 'dark';
                root.setAttribute('data-theme', next);
                localStorage.setItem('arrendamientos-theme', next);
            });
        })();
    </script>

</body>
</html>
