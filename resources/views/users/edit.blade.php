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
            <label for="is-organizer-btn">Es organizador</label>
            <input type="hidden" name="is_organizer" id="is-organizer-input" value="{{ old('is_organizer', $user->is_organizer ? 1 : 0) ? 1 : 0 }}">
            <button type="button" class="switch-btn {{ old('is_organizer', $user->is_organizer ? 1 : 0) ? 'on' : '' }}" id="is-organizer-btn" aria-pressed="{{ old('is_organizer', $user->is_organizer ? 1 : 0) ? 'true' : 'false' }}">
                <span class="switch-dot"></span>
                <span>Organizador</span>
            </button>
        </div>
        <button class="btn btn-primary" type="submit">Guardar cambios</button>
    </form>
</div>
<script>
    (function () {
        var btn = document.getElementById('is-organizer-btn');
        var input = document.getElementById('is-organizer-input');
        if (!btn || !input) return;
        btn.addEventListener('click', function () {
            var on = input.value === '1';
            input.value = on ? '0' : '1';
            btn.classList.toggle('on', !on);
            btn.setAttribute('aria-pressed', on ? 'false' : 'true');
        });
    })();
</script>
@endsection