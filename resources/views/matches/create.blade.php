@extends('layouts.app')

@section('title', 'Nuevo partido')

@section('content')
<div class="page-head">
    <div><h1>Nuevo partido</h1></div>
    <a class="btn btn-sm" href="{{ route('matches.index') }}">← Volver</a>
</div>

<div class="card" style="max-width: 560px;">
    <form method="POST" action="{{ route('matches.store') }}">
        @csrf
        <div class="field">
            <label for="title">Título</label>
            <input id="title" name="title" value="{{ old('title') }}" placeholder="Fútbol de los sábados" required>
            @error('title')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="form-row">
            <div class="field">
                <label for="played_at">Fecha y hora</label>
                <input id="played_at" type="datetime-local" name="played_at" value="{{ old('played_at') }}" required>
                @error('played_at')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="size">Jugadores por equipo</label>
                <select id="size" name="size">
                    <option value="5" {{ (old('size') ?? '5') == '5' ? 'selected' : '' }}>5 (5v5)</option>
                    <option value="4" {{ (old('size') ?? '') == '4' ? 'selected' : '' }}>4 (4v4)</option>
                    <option value="3" {{ (old('size') ?? '') == '3' ? 'selected' : '' }}>3 (3v3)</option>
                </select>
            </div>
        </div>
        <div class="field">
            <label for="venue">Cancha / lugar</label>
            <input id="venue" name="venue" value="{{ old('venue') }}" placeholder="Cancha del club">
            @error('venue')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <button class="btn btn-primary" type="submit">Crear partido</button>
    </form>
</div>
@endsection
