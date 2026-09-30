@extends('layouts.app')

@section('title', 'Nuevo partido')

@section('content')
<div class="page-head">
    <div><h1>Nuevo partido</h1></div>
    <a class="btn btn-sm" href="{{ route('dashboard') }}">← Volver</a>
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
                <div class="segmented">
                    <label><input type="radio" name="size" value="4" @checked((old('size') ?? '5') == '4')><span>4v4</span></label>
                    <label><input type="radio" name="size" value="5" @checked((old('size') ?? '5') == '5')><span>5v5</span></label>
                    <label><input type="radio" name="size" value="6" @checked((old('size') ?? '5') == '6')><span>6v6</span></label>
                </div>
            </div>
        </div>
        <div class="field">
            <label for="venue">Cancha / lugar</label>
            <input id="venue" name="venue" value="{{ old('venue') }}" placeholder="Cancha del club">
            @error('venue')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="field_value">Valor de la cancha ($)</label>
            <input id="field_value" type="number" name="field_value" min="0" step="500" inputmode="numeric"
                value="{{ old('field_value') }}" placeholder="10000">
            @error('field_value')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <input type="hidden" name="recurring" id="recurring-value" value="{{ old('recurring') ? '1' : '0' }}" data-recurring-value>
            <button type="button" class="btn {{ old('recurring') ? 'btn-primary' : '' }}" data-recurring-toggle aria-pressed="{{ old('recurring') ? 'true' : 'false' }}">
                <span data-recurring-label>{{ old('recurring') ? '★ Partido recurrente: activado' : '★ Partido recurrente' }}</span>
            </button>
            <span class="muted small" style="margin-left: 10px;">Se abre solo cada semana con los mismos datos</span>
            <script>
                document.querySelector('[data-recurring-toggle]').addEventListener('click', function () {
                    var value = document.getElementById('recurring-value');
                    var on = value.value === '1';
                    value.value = on ? '0' : '1';
                    this.classList.toggle('btn-primary', !on);
                    this.setAttribute('aria-pressed', on ? 'false' : 'true');
                    this.querySelector('[data-recurring-label]').textContent = on ? '★ Partido recurrente' : '★ Partido recurrente: activado';
                });
            </script>
        </div>
        <button class="btn btn-primary" type="submit">Crear partido</button>
    </form>
</div>
@endsection
