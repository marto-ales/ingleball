@extends('layouts.app')

@section('title', 'Iniciar sesión')

@php $captcha = \App\Support\Captcha::make(); @endphp

@section('content')
<div class="auth-wrap">
    <div class="auth-brand">⚽ Ingleball</div>
    <div class="auth-sub">Organicemo el fulbito</div>
    <div class="card">
        <h2>Ingresar</h2>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="identity">Usuario o correo</label>
                <input id="identity" type="text" name="identity" value="{{ old('identity') }}" placeholder="tu_usuario o tu@correo" required autofocus>
                @error('identity')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="password">Contraseña</label>
                <div class="password-wrap">
                    <input id="password" type="password" name="password" required>
                    <button type="button" class="password-toggle" aria-label="Mostrar contraseña" aria-pressed="false" tabindex="-1">
                        <svg class="pw-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="pw-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
                @error('password')<div class="field-error">{{ $message }}</div>@enderror
                <a class="small muted" href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
            </div>
            <div class="field">
                <label style="display:flex;gap:8px;align-items:center;font-weight:500;">
                    <input type="checkbox" name="remember" style="width:auto;"> Recordarme
                </label>
            </div>
            @if (\App\Support\Captcha::enabled())
                <div class="field">
                    <label for="captcha">Seguridad: resolvé la operación de la imagen</label>
                    <img class="captcha-img" src="{{ $captcha->imageUri() }}" alt="Captcha">
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
