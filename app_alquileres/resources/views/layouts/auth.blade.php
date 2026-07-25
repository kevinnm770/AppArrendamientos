<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Acceso') | {{ config('app.name', 'App Arrendamientos') }}</title>
    <meta name="robots" content="noindex,follow">
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
            --danger: #d9483f;
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
            --danger: #ff6f66;
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
                --danger: #ff6f66;
                --shadow: rgba(0, 0, 0, .45);
                --nav-bg: rgba(12, 17, 32, .85);
                --cta-bg: var(--secondary);
                --cta-ink: var(--secondary-contrast);
                --icon-bg: var(--secondary);
                --icon-ink: var(--secondary-contrast);
            }
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--ink);
            line-height: 1.55;
            transition: background-color .25s ease, color .25s ease;
            display: flex;
            flex-direction: column;
        }
        a { color: inherit; }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: var(--nav-bg);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .9rem clamp(1rem, 4vw, 2.5rem);
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
        .brand span.brand-name { font-weight: 700; font-size: 1.05rem; }

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

        .auth-shell {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1rem;
        }
        .auth-card {
            width: min(960px, 100%);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 22px;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1.05fr .95fr;
            box-shadow: 0 30px 60px -30px var(--shadow);
        }
        .auth-form { padding: clamp(1.8rem, 4vw, 3rem); }
        .auth-title { font-size: 1.7rem; margin: 0 0 .4rem; letter-spacing: -0.01em; }
        .auth-subtitle { color: var(--muted); margin: 0 0 1.8rem; }

        .field { margin-bottom: 1.1rem; }
        .field label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: .4rem; }
        .input-wrap { position: relative; }
        .input-wrap i {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: var(--muted); font-size: .9rem; pointer-events: none;
        }
        .input-wrap input {
            width: 100%;
            padding: .75rem .9rem .75rem 2.5rem;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--ink);
            font-size: .95rem;
            font-family: inherit;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .input-wrap input::placeholder { color: var(--muted); opacity: .8; }
        .input-wrap input:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(76, 210, 217, .25);
        }
        .input-wrap input.is-invalid { border-color: var(--danger); }
        .error-text { color: var(--danger); font-size: .82rem; margin: .35rem 0 0; }

        .checkbox-row {
            display: flex; align-items: center; gap: .5rem;
            font-size: .9rem; color: var(--muted);
            margin: .2rem 0 1.4rem;
            cursor: pointer;
        }
        .checkbox-row input { accent-color: var(--secondary-strong); width: 16px; height: 16px; }

        .forgot-link { text-align: right; margin: -.6rem 0 1.2rem; }
        .forgot-link a { font-size: .85rem; font-weight: 600; color: var(--secondary-strong); text-decoration: none; }
        .forgot-link a:hover { text-decoration: underline; }

        .role-toggle { display: flex; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; margin-bottom: 1.5rem; }
        .role-toggle input { display: none; }
        .role-toggle label {
            flex: 1; text-align: center; padding: .65rem .5rem;
            font-weight: 600; font-size: .9rem; cursor: pointer;
            color: var(--muted); background: var(--bg);
            transition: background-color .15s ease, color .15s ease;
        }
        .role-toggle label:first-of-type { border-right: 1px solid var(--border); }
        .role-toggle input:checked + label { background: var(--cta-bg); color: var(--cta-ink); }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            padding: .55rem 1.1rem;
            border-radius: 10px;
            font-size: .95rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
            font-family: inherit;
            position: relative;
            transition: transform .12s ease, box-shadow .12s ease, filter .12s ease;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background: var(--cta-bg); color: var(--cta-ink); }
        .btn-primary:hover { box-shadow: 0 10px 24px -10px var(--shadow); filter: brightness(1.05); }
        .btn-outline { background: transparent; border-color: var(--border); color: var(--ink); }
        .btn-outline:hover { border-color: var(--secondary); color: var(--secondary-strong); }
        .btn-lg { padding: .8rem 1.6rem; font-size: 1.02rem; }
        .btn-full { width: 100%; }
        .btn:disabled { cursor: default; filter: grayscale(.15); }
        .btn.is-loading { color: transparent !important; pointer-events: none; }
        .btn.is-loading::after {
            content: "";
            position: absolute;
            top: 50%; left: 50%;
            width: 18px; height: 18px;
            margin: -9px 0 0 -9px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, .45);
            border-top-color: #fff;
            animation: btn-spin .65s linear infinite;
        }
        @keyframes btn-spin { to { transform: rotate(360deg); } }

        .notice-icon {
            width: 64px; height: 64px;
            border-radius: 16px;
            background: var(--icon-bg);
            color: var(--icon-ink);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem;
            margin: 0 auto 1.2rem;
        }

        .alert {
            border-radius: 10px;
            padding: .75rem 1rem;
            font-size: .9rem;
            margin-bottom: 1.4rem;
        }
        .alert-success {
            background: rgba(76, 210, 217, .14);
            color: var(--secondary-strong);
            border: 1px solid rgba(76, 210, 217, .35);
        }

        .auth-footer-text { text-align: center; margin: 1.6rem 0 0; color: var(--muted); font-size: .92rem; }
        .auth-footer-text a { font-weight: 600; color: var(--secondary-strong); text-decoration: none; }
        .auth-footer-text a:hover { text-decoration: underline; }
        .text-center { text-align: center; }
        .stack-gap { display: grid; gap: .8rem; }

        .auth-panel {
            background: linear-gradient(160deg, var(--primary) 0%, #1f5f66 65%, var(--secondary) 100%);
            color: #fff;
            padding: clamp(1.8rem, 4vw, 3rem);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 1.2rem;
        }
        .auth-panel .eyebrow-inverse {
            display: inline-flex;
            align-self: flex-start;
            background: rgba(255, 255, 255, .16);
            padding: .35rem .8rem;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 700;
        }
        .auth-panel h2 { margin: 0; font-size: 1.5rem; line-height: 1.25; }
        .auth-panel p { margin: 0; color: rgba(255, 255, 255, .85); font-size: .95rem; }
        .panel-list { list-style: none; margin: 0; padding: 0; display: grid; gap: .8rem; }
        .panel-list li { display: flex; align-items: flex-start; gap: .7rem; font-size: .92rem; }
        .panel-list li i {
            width: 28px; height: 28px; flex-shrink: 0;
            border-radius: 8px;
            background: rgba(255, 255, 255, .18);
            display: flex; align-items: center; justify-content: center;
            font-size: .82rem;
        }

        @media (max-width: 860px) {
            .auth-card { grid-template-columns: 1fr; }
            .auth-panel { display: none; }
        }
    </style>
</head>
<body>

    <div class="topbar">
        <a class="brand" href="{{ url('/') }}">
            <span class="logo-badge">AA</span>
            <span class="brand-name">App Arrendamientos</span>
        </a>
        <button type="button" class="theme-toggle" id="themeToggle" aria-label="Cambiar tema claro u oscuro">
            <i class="fa-solid fa-moon"></i>
            <i class="fa-solid fa-sun"></i>
        </button>
    </div>

    <main class="auth-shell">
        <div class="auth-card">
            <div class="auth-form">
                @yield('contents_auth')
            </div>
            <div class="auth-panel">
                <span class="eyebrow-inverse">Costa Rica</span>
                <h2>Gestiona tus arrendamientos sin complicaciones</h2>
                <p>Contratos, adéndums, facturación electrónica ante Hacienda y notificaciones, todo en un mismo panel.</p>
                <ul class="panel-list">
                    <li><i class="fa-solid fa-building"></i> Registro y edición de propiedades</li>
                    <li><i class="fa-solid fa-file-signature"></i> Contratos y adéndums digitales</li>
                    <li><i class="fa-solid fa-file-invoice-dollar"></i> Facturación electrónica</li>
                </ul>
            </div>
        </div>
    </main>

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

        (function () {
            document.querySelectorAll('.auth-form form').forEach(function (form) {
                form.addEventListener('submit', function () {
                    if (form.dataset.submitted === '1') {
                        return;
                    }
                    form.dataset.submitted = '1';

                    var btn = form.querySelector('button[type="submit"]');
                    if (btn) {
                        btn.disabled = true;
                        btn.classList.add('is-loading');
                    }
                });
            });
        })();
    </script>

</body>
</html>
