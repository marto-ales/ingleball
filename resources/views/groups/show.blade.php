@extends('layouts.app')

@section('title', 'Mi grupo')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $group?->name ?? 'Todavía no tenés grupo' }}</h1>
        <p class="lead">Una sola cuenta para todos tus grupos. Lo que jugás, calificás y te evalúan en cada uno no se mezcla.</p>
    </div>
</div>

@if ($group !== null)
    <div class="card" style="max-width: 560px;">
        <div class="field">
            <label>Código de invitación de {{ $group->name }}</label>
            <p class="mb0"><strong style="font-size: 1.25rem; letter-spacing: 0.08em;">{{ $group->join_code }}</strong></p>
            <span class="muted small">Quien se registra con este código entra a este grupo.</span>
        </div>

        <p class="muted small">{{ $memberCount }} {{ $memberCount === 1 ? 'jugador' : 'jugadores' }} · {{ $matchCount }} {{ $matchCount === 1 ? 'partido' : 'partidos' }}</p>

        @if ($isOrganizer)
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
        @else
            <p class="muted small">El enlace de WhatsApp y el nombre los carga quien organiza el grupo.</p>
        @endif
    </div>

    @if (! $isOrganizer)
        <div class="card card--info" style="max-width: 560px;">
            <h2 class="h4">Salir de {{ $group->name }}</h2>
            <p class="muted small">Se borran tu perfil, tus notas y tus evaluaciones <em>de este grupo</em>. Tu cuenta y los otros grupos no se tocan.</p>
            <form method="POST" action="{{ route('groups.leave') }}"
                  onsubmit="return confirm('¿Salir de {{ $group->name }}? Perdés tu perfil y tus notas en este grupo.');">
                @csrf
                <button class="btn btn-ghost" type="submit">Salir del grupo</button>
            </form>
        </div>
    @endif

    <div class="card" style="max-width: 560px;">
        <h2 class="h4">Tus grupos</h2>
        <table class="table">
            <tbody>
                @forelse ($myGroups as $membership)
                    <tr>
                        <td>
                            {{ $membership->group->name }}
                            @if ($membership->is_organizer)<span class="badge badge-organizer">Org</span>@endif
                        </td>
                        <td class="num">
                            @if ($membership->group_id === $group->id)
                                <span class="muted small">Estás acá</span>
                            @else
                                <form method="POST" action="{{ route('groups.activate') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="group" value="{{ $membership->group_id }}">
                                    <button class="btn btn-sm" type="submit">Entrar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="muted">Todavía no pertenecés a ningún grupo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

<div class="card" style="max-width: 560px;">
    <h2 class="h4">Sumarte a otro grupo</h2>
    <p class="muted small">Pedile el código a quien lo organiza. Tu cuenta no cambia: entra un perfil nuevo, con sus notas y su ranking desde cero.</p>
    <form method="POST" action="{{ route('groups.join') }}">
        @csrf
        <div class="field">
            <label for="group_code">Código de invitación</label>
            <input id="group_code" name="group_code" value="{{ old('group_code') }}" required
                   style="text-transform: uppercase;" autocomplete="off" placeholder="ABC12345">
            @error('group_code')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <button class="btn btn-primary" type="submit">Sumarme</button>
    </form>
</div>
@endsection
