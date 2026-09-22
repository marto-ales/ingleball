<!DOCTYPE html>
<html lang="es" data-theme="{{ auth()->user()?->theme ?? \App\Support\Themes::DEFAULT }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Ingleball')) · Ingleball</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @auth
    <header class="topbar">
        <div class="container topbar-inner">
            <a class="brand" href="{{ route('dashboard') }}">⚽ Ingleball</a>
            <nav class="nav">
                <a href="{{ route('dashboard') }}">Inicio</a>
                <a href="{{ route('stats.index') }}">Estadísticas</a>
                @if (auth()->user()->is_organizer)
                    <a href="{{ route('users.manage.index') }}">Usuarios</a>
                    <a href="{{ route('algorithm.index') }}">Algoritmo</a>
                    <a class="btn btn-primary btn-sm" href="{{ route('matches.create') }}">+ Nuevo partido</a>
                @endif
                <a href="{{ route('profile.edit') }}">{{ auth()->user()->name }}</a>
                <div class="theme-switch">
                    <button type="button" class="theme-btn" aria-haspopup="true" aria-expanded="false" aria-label="Cambiar tema de color" title="Tema de color">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3a9 9 0 0 0 0 18c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.41-1.01S12.7 17.64 12.7 17c0-.96.78-1.74 1.74-1.74h.87A3.7 3.7 0 0 0 19 11.56C19 6.95 15.99 3 12 3zM7.5 10a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3-3a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3.75-1.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3.75 2A1.5 1.5 0 1 1 18 6.5a1.5 1.5 0 0 1 0 3z"/></svg>
                    </button>
                    <div class="theme-menu" hidden>
                        @foreach (\App\Support\Themes::all() as $themeKey => $themeLabel)
                            <button type="button" class="theme-opt {{ (auth()->user()->theme ?? \App\Support\Themes::DEFAULT) === $themeKey ? 'current' : '' }}" data-theme="{{ $themeKey }}">
                                <span class="dot dot-{{ $themeKey }}"></span>{{ $themeLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm">Salir</button>
                </form>
            </nav>
        </div>
    </header>
    @endauth

    <main class="container content">
        @if (session('status'))
            <div class="flash flash-ok">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="flash flash-err">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    @auth
        @if (session('attendance'))
            @include('partials.attendance-modal')
        @endif
    @endauth

    <footer class="footer">
        <div class="container">Ingleball — organicemo el fulbito</div>
    </footer>
    <script>
        document.querySelectorAll('.stepper').forEach(function (box) {
            var input = box.querySelector('.stepper-input');
            box.querySelectorAll('.stepper-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var min = parseInt(input.min || '0', 10);
                    var max = parseInt(input.max || '999', 10);
                    var step = parseInt(btn.getAttribute('data-step'), 10);
                    var v = parseInt(input.value || '0', 10);
                    if (Number.isNaN(v)) v = min;
                    v = Math.max(min, Math.min(max, v + step));
                    input.value = v;
                });
            });
        });

        document.querySelectorAll('.slider input[type="range"]').forEach(function (range) {
            var out = range.parentElement.querySelector('output');

            function sync() {
                var min = parseFloat(range.min || '0');
                var max = parseFloat(range.max || '100');
                var value = parseFloat(range.value || '0');
                var pct = max > min ? (value - min) / (max - min) * 100 : 0;
                range.style.setProperty('--fill', pct + '%');
                var hue = pct / 100 * 105;
                range.style.setProperty('--fill-color', 'hsl(' + hue.toFixed(0) + ', 72%, 42%)');

                if (!out) return;
                if (range.hasAttribute('data-percent')) {
                    out.value = Math.round(value * 100) + '%';
                } else {
                    var raw = parseInt(range.value, 10);
                    out.value = Math.max(1, Number.isNaN(raw) ? 0 : raw);
                }
            }

            range.addEventListener('input', sync);
            sync();
        });

        document.querySelectorAll('.row-actions').forEach(function (wrap) {
            var btn = wrap.querySelector('.row-actions-btn');
            var menu = wrap.querySelector('.row-menu');
            if (!btn || !menu) return;

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var open = menu.hidden;
                document.querySelectorAll('.row-menu').forEach(function (m) { m.hidden = true; });
                document.querySelectorAll('.row-actions-btn').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
                menu.hidden = !open;
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');

                if (open) {
                    var menuH = menu.offsetHeight || 150;
                    var host = btn.closest('.table-wrap') || btn.closest('.card');
                    var bottom = btn.getBoundingClientRect().bottom;
                    var hostBottom = host ? host.getBoundingClientRect().bottom : window.innerHeight;
                    menu.classList.toggle('open-up', bottom + menuH > hostBottom - 6);
                }
            });
        });

        document.addEventListener('click', function (e) {
            if (e.target.closest('.row-actions')) return;
            document.querySelectorAll('.row-menu').forEach(function (m) { m.hidden = true; });
            document.querySelectorAll('.row-actions-btn').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
        });

        document.querySelector('.theme-btn') && (function () {
            var btn = document.querySelector('.theme-btn');
            var menu = document.querySelector('.theme-menu');
            var csrf = document.querySelector('meta[name="csrf-token"]');

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var open = menu.hidden;
                menu.hidden = !open;
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });

            document.addEventListener('click', function (e) {
                if (e.target.closest('.theme-switch')) return;
                menu.hidden = true;
                btn.setAttribute('aria-expanded', 'false');
            });

            menu.querySelectorAll('.theme-opt').forEach(function (opt) {
                opt.addEventListener('click', function () {
                    var theme = opt.getAttribute('data-theme');
                    if (opt.classList.contains('current') || !csrf) {
                        menu.hidden = true;
                        btn.setAttribute('aria-expanded', 'false');
                        return;
                    }
                    var fd = new FormData();
                    fd.append('theme', theme);
                    fetch('{{ route('profile.theme') }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf.content },
                        body: fd
                    }).then(function (r) {
                        if (!r.ok) throw new Error('fail');
                        document.documentElement.setAttribute('data-theme', theme);
                        menu.querySelectorAll('.theme-opt').forEach(function (o) {
                            o.classList.toggle('current', o === opt);
                        });
                        menu.hidden = true;
                        btn.setAttribute('aria-expanded', 'false');
                    }).catch(function () { location.reload(); });
                });
            });
        })();

        document.querySelectorAll('.tip').forEach(function (tip) {
            tip.addEventListener('click', function (e) {
                e.stopPropagation();
                var open = tip.classList.contains('open');
                document.querySelectorAll('.tip').forEach(function (t) { t.classList.remove('open'); });
                if (!open) tip.classList.add('open');
            });
        });

        document.addEventListener('click', function () {
            document.querySelectorAll('.tip').forEach(function (t) { t.classList.remove('open'); });
        });

        document.querySelectorAll('.password-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = btn.parentElement.querySelector('input');
                if (!input) return;
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.classList.toggle('visible', show);
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
                if (show) setTimeout(function () { input.focus(); }, 0);
            });
        });

        document.querySelectorAll('svg.radar[data-live]').forEach(function (svg) {
            var keys = (svg.getAttribute('data-keys') || '').split(',').filter(Boolean);
            if (!keys.length || !svg.querySelector('[data-shape]')) return;

            var cx = parseFloat(svg.getAttribute('data-cx'));
            var cy = parseFloat(svg.getAttribute('data-cy'));
            var radius = parseFloat(svg.getAttribute('data-radius'));
            var shape = svg.querySelector('[data-shape]');
            var initials = {};
            try { initials = JSON.parse(svg.getAttribute('data-values') || '{}'); } catch (e) { initials = {}; }

            var cos = {}, sin = {}, dots = {}, inputs = {};

            function clamp(v) { return Math.max(0, Math.min(10, v)); }

            keys.forEach(function (key, i) {
                var angle = -Math.PI / 2 + i * (2 * Math.PI / keys.length);
                cos[key] = Math.cos(angle);
                sin[key] = Math.sin(angle);
                dots[key] = svg.querySelector('[data-dot="' + key + '"]');
                inputs[key] = document.querySelector('[name="' + key + '"]');
            });

            var current = {}, target = {};
            keys.forEach(function (key) {
                var v = inputs[key] ? parseFloat(inputs[key].value) : parseFloat(initials[key]);
                if (Number.isNaN(v)) v = 5;
                v = clamp(v);
                current[key] = v;
                target[key] = v;
            });

            function render(values) {
                var points = [];
                keys.forEach(function (key) {
                    var r = radius * clamp(values[key]) / 10;
                    var x = cx + r * cos[key];
                    var y = cy + r * sin[key];
                    points.push(x.toFixed(1) + ',' + y.toFixed(1));
                    if (dots[key]) {
                        dots[key].setAttribute('cx', x.toFixed(1));
                        dots[key].setAttribute('cy', y.toFixed(1));
                    }
                });
                shape.setAttribute('points', points.join(' '));
            }

            var frame = null;
            function tick() {
                var moving = false;
                keys.forEach(function (key) {
                    var diff = target[key] - current[key];
                    if (Math.abs(diff) > 0.02) {
                        current[key] += diff * 0.18;
                        moving = true;
                    } else {
                        current[key] = target[key];
                    }
                });
                render(current);
                frame = moving ? requestAnimationFrame(tick) : null;
            }

            keys.forEach(function (key) {
                if (!inputs[key]) return;
                inputs[key].addEventListener('input', function () {
                    var v = parseFloat(inputs[key].value);
                    target[key] = Number.isNaN(v) ? 0 : clamp(v);
                    if (frame === null) frame = requestAnimationFrame(tick);
                });
            });

            render(current);
        });
    </script>
</body>
</html>
