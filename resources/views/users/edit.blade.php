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
            <label for="is-organizer-btn">Es organizador de {{ $activeGroup?->name ?? 'este grupo' }}</label>
            <span class="muted small">En otros grupos puede ser un jugador común.</span>
            <input type="hidden" name="is_organizer" id="is-organizer-input" value="{{ old('is_organizer', $membership->is_organizer ? 1 : 0) ? 1 : 0 }}">
            <button type="button" class="switch-btn {{ old('is_organizer', $membership->is_organizer ? 1 : 0) ? 'on' : '' }}" id="is-organizer-btn" aria-pressed="{{ old('is_organizer', $membership->is_organizer ? 1 : 0) ? 'true' : 'false' }}">
                <span class="switch-dot"></span>
                <span>Organizador</span>
            </button>
        </div>
        <div class="field">
            <label for="password">Contraseña nueva</label>
            <span class="muted small">Dejá en blanco para no cambiarla. La cuenta es la misma en todos sus grupos.</span>
            <div class="password-wrap">
                <input id="password" type="password" name="password" autocomplete="new-password" minlength="8">
                <button type="button" class="password-toggle" aria-label="Mostrar contraseña" aria-pressed="false" tabindex="-1">
                    <svg class="pw-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="pw-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
            </div>
            @error('password')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="password_confirmation">Repetir contraseña nueva</label>
            <div class="password-wrap">
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8">
                <button type="button" class="password-toggle" aria-label="Mostrar contraseña" aria-pressed="false" tabindex="-1">
                    <svg class="pw-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="pw-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
            </div>
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