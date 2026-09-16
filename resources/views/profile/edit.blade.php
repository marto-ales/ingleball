@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
<div class="page-head">
    <div>
        <h1>Mi perfil</h1>
        <p class="lead">Tus datos y cómo considerás que jugás.</p>
    </div>
</div>

@php
    $p = $profile ?? null;
    $likesGoalie = (bool) old('likes_goalie', $p?->likes_goalie ?? false);
@endphp

<div class="card" style="max-width: 640px;">
    <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PATCH')
        <div class="form-row">
            <div class="field">
                <label for="name">Nombre</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" required>
            </div>
            <div class="field">
                <label for="username">Usuario</label>
                <input id="username" value="{{ $user->username }}" disabled>
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="email">Email <span class="muted small">(para recordatorios)</span></label>
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}">
            </div>
            <div class="field">
                <label for="phone">Teléfono <span class="muted small">(para WhatsApp)</span></label>
                <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="+54 9 11 5555-5555">
            </div>
        </div>

        @if ($user->is_organizer)
            <div class="field">
                <label for="whatsapp_group">Enlace del grupo de WhatsApp <span class="muted small">(para recordatorios)</span></label>
                <input id="whatsapp_group" name="whatsapp_group" value="{{ old('whatsapp_group', $user->whatsapp_group) }}" placeholder="https://chat.whatsapp.com/XXXXXXXX">
            </div>
        @endif

        <div class="divider"></div>
        <h2>Cómo jugás (autoevaluación)</h2>
        <div class="field">
            <label>¿Te gusta ir al arco?</label>
            <div class="segmented">
                <label><input type="radio" name="likes_goalie" value="1" @checked($likesGoalie)><span>Sí</span></label>
                <label><input type="radio" name="likes_goalie" value="0" @checked(! $likesGoalie)><span>No</span></label>
            </div>
            <p class="muted small mb0">Al armar los equipos se busca que a cada uno le toque al menos un jugador que quiera atajar.</p>
        </div>
        @foreach (['speed' => 'Velocidad', 'skill' => 'Habilidad', 'passing' => 'Pase', 'shooting' => 'Definición', 'defense' => 'Defensa', 'goalkeeping' => 'Arco', 'overall' => 'General'] as $key => $label)
            <div class="field">
                <label>{{ $label }}</label>
                @include('partials.scale', ['name' => $key, 'label' => $label, 'value' => old($key, $p?->{$key} ?? 5)])
            </div>
        @endforeach

        <button class="btn btn-primary" type="submit">Guardar</button>
    </form>
</div>
@endsection
