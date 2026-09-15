@extends('layouts.app')

@section('title', 'Iniciar sesión')

@php $captcha = \App\Support\Captcha::make(); @endphp

@section('content')
<div class="auth-wrap">
    <div class="auth-brand">⚽ Ingleball</div>
    <div class="auth-sub">Organiza el fútbol de tus amigos</div>
    <div class="card">
        <h2>Ingresar</h2>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="username">Usuario</label>
                <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus>
                @error('username')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="password">Contraseña</label>
                <input id="password" type="password" name="password" required>
                @error('password')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label style="display:flex;gap:8px;align-items:center;font-weight:500;">
                    <input type="checkbox" name="remember" style="width:auto;"> Recordarme
                </label>
            </div>
            @if (\App\Support\Captcha::enabled())
                <div class="field">
                    <label for="captcha">Seguridad: ¿cuánto es <strong>{{ $captcha->question() }}</strong>?</label>
                    <img class="captcha-img" src="{{ $captcha->imageUri() }}" alt="Captcha: {{ $captcha->question() }} = ?">
                    <input id="captcha" type="text" name="captcha" inputmode="numeric" autocomplete="off" required>
                    @error('captcha')<div class="field-error">{{ $message }}</div>@enderror
                    <span class="muted small">¿No se ve? Recargá la página para generar otro.</span>
                </div>
            @endif
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Entrar</button>
        </form>
    </div>
    <div class="auth-alt">¿No tenés cuenta? <a href="{{ route('register.show') }}">Registrate</a></div>
</div>
@endsection
