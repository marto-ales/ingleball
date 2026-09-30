@extends('layouts.app')

@section('title', 'Registrarse')

@php $captcha = \App\Support\Captcha::make(); @endphp

@section('content')
<div class="auth-wrap">
    <div class="auth-brand">⚽ Ingleball</div>
    <div class="auth-sub">Sumate al grupo de fútbol</div>
    <div class="card">
        <h2>Crear cuenta</h2>
        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div class="field">
                <label for="name">Nombre</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required>
                @error('name')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="username">Usuario</label>
                <input id="username" type="text" name="username" value="{{ old('username') }}" required>
                @error('username')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required>
                    @error('email')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="phone">Teléfono <span class="muted small">(opcional)</span></label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone') }}" placeholder="+54 9 11 5555-5555">
                    @error('phone')<div class="field-error">{{ $message }}</div>@enderror
                </div>
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
            </div>
            <div class="field">
                <label for="password_confirmation">Repetir contraseña</label>
                <div class="password-wrap">
                    <input id="password_confirmation" type="password" name="password_confirmation" required>
                    <button type="button" class="password-toggle" aria-label="Mostrar contraseña" aria-pressed="false" tabindex="-1">
                        <svg class="pw-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="pw-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
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
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Crear cuenta</button>
        </form>
    </div>
    <div class="auth-alt">¿Ya tenés cuenta? <a href="{{ route('login.show') }}">Ingresar</a></div>
</div>
@endsection
