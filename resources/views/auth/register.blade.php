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
                    <label for="email">Email <span class="muted small">(opcional)</span></label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}">
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
                <input id="password" type="password" name="password" required>
                @error('password')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Repetir contraseña</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required>
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
