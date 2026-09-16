@extends('layouts.app')

@section('title', 'Editar jugador')

@section('content')
<div class="page-head">
    <div><h1>Editar jugador</h1></div>
    <a class="btn btn-sm" href="{{ route('users.manage.index') }}">← Volver</a>
</div>

<div class="card" style="max-width: 560px;">
    <form method="POST" action="{{ route('users.manage.update', $user) }}">
        @csrf
        @method('PATCH')
        <div class="field">
            <label for="name">Nombre</label>
            <input id="name" name="name" value="{{ old('name', $user->name) }}" required>
            @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="phone">Teléfono / WhatsApp</label>
            <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="20">
            @error('phone')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="email">Correo</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" maxlength="120">
            @error('email')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label class="checkbox-line">
                <input type="hidden" name="is_organizer" value="0">
                <input type="checkbox" name="is_organizer" value="1" {{ old('is_organizer', $user->is_organizer) ? 'checked' : '' }}>
                Es organizador
            </label>
        </div>
        <button class="btn btn-primary" type="submit">Guardar cambios</button>
    </form>
</div>
@endsection