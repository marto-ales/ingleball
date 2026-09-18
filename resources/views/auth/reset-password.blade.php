@extends('layouts.app')

@section('title', 'Nueva contraseña')

@section('content')
<div class="auth-wrap">
    <div class="auth-brand">⚽ Ingleball</div>
    <div class="auth-sub">Elegí una contraseña nueva</div>
    <div class="card">
        <h2>Nueva contraseña</h2>
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="field">
                <label for="email">Correo</label>
                <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
                @error('email')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="password">Contraseña nueva</label>
                <input id="password" type="password" name="password" required autofocus>
                @error('password')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Repetir contraseña</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Guardar contraseña</button>
        </form>
    </div>
    <div class="auth-alt">¿Volviste? <a href="{{ route('login.show') }}">Ingresar</a></div>
</div>
@endsection