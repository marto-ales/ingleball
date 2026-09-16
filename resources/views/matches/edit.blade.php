@extends('layouts.app')

@section('title', 'Editar partido')

@section('content')
<div class="page-head">
    <div><h1>Editar partido</h1></div>
    <a class="btn btn-sm" href="{{ route('matches.show', $match) }}">← Volver</a>
</div>

<div class="card" style="max-width: 560px;">
    <form method="POST" action="{{ route('matches.update', $match) }}">
        @csrf
        @method('PATCH')
        <div class="field">
            <label for="title">Título</label>
            <input id="title" name="title" value="{{ old('title', $match->title) }}" required>
            @error('title')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="form-row">
            <div class="field">
                <label for="played_at">Fecha y hora</label>
                <input id="played_at" type="datetime-local" name="played_at" value="{{ old('played_at', $match->played_at->format('Y-m-d\TH:i')) }}" required>
                @error('played_at')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="size">Jugadores por equipo</label>
                <div class="segmented">
                    <label><input type="radio" name="size" value="4" @checked((old('size', $match->size) ?? '5') == '4')><span>4v4</span></label>
                    <label><input type="radio" name="size" value="5" @checked((old('size', $match->size) ?? '5') == '5')><span>5v5</span></label>
                    <label><input type="radio" name="size" value="6" @checked((old('size', $match->size) ?? '5') == '6')><span>6v6</span></label>
                </div>
            </div>
        </div>
        <div class="field">
            <label for="venue">Cancha / lugar</label>
            <input id="venue" name="venue" value="{{ old('venue', $match->venue) }}" placeholder="Cancha del club">
            @error('venue')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <button class="btn btn-primary" type="submit">Guardar cambios</button>
    </form>
</div>
@endsection