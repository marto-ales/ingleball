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
    </script>
</body>
</html>
