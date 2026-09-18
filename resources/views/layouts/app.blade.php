<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Ingleball')) · Ingleball</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @auth
    <header class="topbar">
        <div class="container topbar-inner">
            <a class="brand" href="{{ route('dashboard') }}">⚽ Ingleball</a>
            <nav class="nav">
                <a href="{{ route('dashboard') }}">Inicio</a>
                <a href="{{ route('matches.index') }}">Partidos</a>
                <a href="{{ route('stats.index') }}">Ranking</a>
                @if (auth()->user()->is_organizer)
                    <a href="{{ route('users.manage.index') }}">Jugadores</a>
                    <a href="{{ route('algorithm.index') }}">Algoritmo</a>
                    <a class="btn btn-primary btn-sm" href="{{ route('matches.create') }}">+ Nuevo partido</a>
                @endif
                <a href="{{ route('profile.edit') }}">{{ auth()->user()->name }}</a>
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

    <footer class="footer">
        <div class="container">Ingleball — fútbol de amigos</div>
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

                if (!out) return;
                if (range.hasAttribute('data-percent')) {
                    out.value = Math.round(value * 100) + '%';
                } else {
                    out.value = range.value;
                }
            }

            range.addEventListener('input', sync);
            sync();
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
