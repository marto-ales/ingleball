@extends('layouts.app')

@section('title', 'Nuevo jugador')

@section('content')
<div class="page-head">
    <div><h1>Nuevo jugador</h1></div>
    <a class="btn btn-sm" href="{{ route('users.manage.index') }}">← Volver</a>
</div>

<div class="card" style="max-width: 560px;">
    <form method="POST" action="{{ route('users.manage.store') }}">
        @csrf
        <div class="field">
            <label for="name">Nombre</label>
            <input id="name" name="name" value="{{ old('name') }}" placeholder="Nombre y apellido" required>
            @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="phone">Teléfono / WhatsApp</label>
            <input id="phone" name="phone" value="{{ old('phone') }}" placeholder="+54 9 11 ..." maxlength="20">
            @error('phone')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="email">Correo (opcional)</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="amigo@correo.com" maxlength="120">
            @error('email')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <button class="btn btn-primary" type="submit">Crear jugador</button>
        <p class="muted small">El usuario se genera automáticamente; sin contraseña no puede entrar, pero anota, puntúa y juega igual que el resto.</p>
    </form>
</div>
@endsection