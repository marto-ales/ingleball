@extends('layouts.app')

@section('title', 'Mi grupo')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $group?->name ?? 'Sin grupo' }}</h1>
        <p class="lead">Datos del grupo: los comparte quien lo organiza.</p>
    </div>
</div>

@if ($group === null)
    <div class="card">
        <p>Tu cuenta todavía no está en un grupo. Pedile el código a quien organiza.</p>
    </div>
@else
    <div class="card" style="max-width: 560px;">
        <div class="field">
            <label>Código de invitación</label>
            <p class="mb0"><strong style="font-size: 1.25rem; letter-spacing: 0.08em;">{{ $group->join_code }}</strong></p>
            <span class="muted small">Quien se registra con este código entra al grupo.</span>
        </div>

        <p class="muted small">{{ $memberCount }} {{ $memberCount === 1 ? 'jugador' : 'jugadores' }} · {{ $matchCount }} {{ $matchCount === 1 ? 'partido' : 'partidos' }}</p>

        @if (auth()->user()->is_organizer)
            <form method="POST" action="{{ route('groups.code') }}">
                @csrf
                <button class="btn btn-sm" type="submit">Renovar código</button>
                <span class="muted small">El código anterior deja de funcionar.</span>
            </form>

            <div class="divider"></div>

            <form method="POST" action="{{ route('groups.update') }}">
                @csrf
                @method('PATCH')
                <div class="field">
                    <label for="name">Nombre del grupo</label>
                    <input id="name" name="name" value="{{ old('name', $group->name) }}" required>
                    @error('name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="whatsapp_group">Enlace del grupo de WhatsApp <span class="muted small">(para recordatorios)</span></label>
                    <input id="whatsapp_group" name="whatsapp_group" value="{{ old('whatsapp_group', $group->whatsapp_group) }}" placeholder="https://chat.whatsapp.com/XXXXXXXX">
                    @error('whatsapp_group')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-primary" type="submit">Guardar</button>
            </form>

            <div class="divider"></div>

            <h2>Crear otro grupo</h2>
            <form method="POST" action="{{ route('groups.store') }}">
                @csrf
                <div class="field">
                    <label for="new_group">Nombre del nuevo grupo</label>
                    <input id="new_group" name="name" value="{{ old('name') }}" required>
                    @error('name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <button class="btn" type="submit">Crear y mudarme</button>
                <p class="muted small">Pasás a ser organizador del grupo nuevo con su propio código.</p>
            </form>
        @else
            <p class="muted small">El enlace de WhatsApp y el nombre los carga quien organiza el grupo.</p>
        @endif
    </div>
@endif
@endsection