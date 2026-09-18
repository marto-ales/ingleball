@extends('layouts.app')

@section('title', 'Restablecer contraseña')

@section('content')
<div class="auth-wrap">
    <div class="auth-brand">⚽ Ingleball</div>
    <div class="auth-sub">Te enviamos un enlace por correo</div>
    <div class="card">
        <h2>Restablecer contraseña</h2>
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="field">
                <label for="email">Correo</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="tu@correo.com" required autofocus>
                @error('email')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Enviar enlace</button>
        </form>
        <p class="muted small mt" style="text-align:center;">Usamos el correo de tu perfil. Si no tenés uno, pedile a un organizador.</p>
    </div>
    <div class="auth-alt">¿Volviste? <a href="{{ route('login.show') }}">Ingresar</a></div>
</div>
@endsection