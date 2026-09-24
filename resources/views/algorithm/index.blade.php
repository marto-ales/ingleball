@extends('layouts.app')

@section('title', 'Algoritmo')

@php
    $labels = [
        'speed' => 'Velocidad',
        'skill' => 'Habilidad',
        'passing' => 'Pase',
        'shooting' => 'Definición',
        'defense' => 'Defensa',
    ];
@endphp

@section('content')
<div class="page-head">
    <div><h1>Algoritmo</h1></div>
</div>

<p class="muted">Definí cómo se arman los equipos. El orden de los atributos decide su peso: el primero pesa 0,30, el segundo 0,25 y el resto 0,15.</p>

<form class="card card--info" method="POST" action="{{ route('algorithm.update') }}">
    @csrf
    @method('PATCH')

    <h2>Orden de peso</h2>
    <p class="muted small">El primero es el que más pesa. Usá las flechas para reordenar.</p>

    <ul class="order-list" id="order-list" data-weights="{{ implode(',', $weightSequence) }}">
        @foreach ($order as $attribute)
            <li class="order-row">
                <input type="hidden" name="order[]" value="{{ $attribute }}">
                <span class="order-name">{{ $labels[$attribute] ?? $attribute }}</span>
                <span class="badge badge-info" data-weight-badge>Peso {{ number_format($weightSequence[$loop->index] ?? 0.15, 2, ',', '.') }}</span>
                <span class="order-actions">
                    <button type="button" class="btn btn-sm" data-move="up" aria-label="Subir">↑</button>
                    <button type="button" class="btn btn-sm" data-move="down" aria-label="Bajar">↓</button>
                </span>
            </li>
        @endforeach
    </ul>

    <div class="divider"></div>

    <h2>Opciones</h2>

    <div class="field">
        <label>Resolver los empates al azar</label>
        <div class="segmented">
            <label><input type="radio" name="random_tie_break" value="1" @checked($randomTieBreak)><span>Sí</span></label>
            <label><input type="radio" name="random_tie_break" value="0" @checked(! $randomTieBreak)><span>No</span></label>
        </div>
    </div>

    <div class="field">
        <label>Repartir un arquero por equipo</label>
        <div class="segmented">
            <label><input type="radio" name="spread_goalies" value="1" @checked($spreadGoalies)><span>Sí</span></label>
            <label><input type="radio" name="spread_goalies" value="0" @checked(! $spreadGoalies)><span>No</span></label>
        </div>
        <p class="muted small mb0">Busca que a cada equipo le toque al menos un jugador que le guste atajar.</p>
    </div>

    <div class="divider"></div>

    <h2>Perfil</h2>

    <div class="field">
        <label for="self_weight">Peso de la autoevaluación</label>
        <div class="slider">
            <input id="self_weight" type="range" name="self_weight" min="0" max="1" step="0.05" value="{{ old('self_weight', $selfWeight) }}" data-percent="1" aria-label="Peso de la autoevaluación">
            <output for="self_weight">{{ (int) round((float) old('self_weight', $selfWeight) * 100) }}%</output>
        </div>
        <div class="row between muted small" style="gap:6px;"><span>0% · solo organizadores</span><span>100% · solo el jugador</span></div>
        <p class="muted small mb0 mt">El resto se reparte entre las evaluaciones de los organizadores.</p>
    </div>

    <div class="field">
        <label>Ajustar por rendimiento reciente</label>
        <div class="segmented">
            <label><input type="radio" name="weight_by_form" value="1" @checked($weightByForm)><span>Sí</span></label>
            <label><input type="radio" name="weight_by_form" value="0" @checked(! $weightByForm)><span>No</span></label>
        </div>
        <p class="muted small mb0">Escala el perfil con la calificación general recibida en los últimos 3 partidos.</p>
    </div>

    <div class="field" id="form-span-field" @if(! $weightByForm) hidden @endif>
        <label for="form_span">Incidencia de las calificaciones por partido</label>
        <div class="slider">
            <input id="form_span" type="range" name="form_span" min="0.05" max="0.5" step="0.05" value="{{ old('form_span', $formSpan) }}" data-percent="1" aria-label="Incidencia de las calificaciones por partido">
            <output for="form_span">{{ (int) round((float) old('form_span', $formSpan) * 100) }}%</output>
        </div>
        <div class="row between muted small" style="gap:6px;"><span>±5%</span><span>±50%</span></div>
        <p class="muted small mb0">Cuánto pueden afectar las calificaciones al puntaje del perfil, hacia arriba o hacia abajo.</p>
    </div>

    <button type="submit" class="btn btn-primary">Guardar</button>
</form>

<script>
    (function () {
        var formSpanField = document.getElementById('form-span-field');
        var radios = document.querySelectorAll('input[name="weight_by_form"]');
        if (formSpanField && radios.length) {
            function sync() {
                var on = document.querySelector('input[name="weight_by_form"]:checked');
                formSpanField.hidden = !(on && on.value === '1');
            }

            radios.forEach(function (radio) {
                radio.addEventListener('change', sync);
            });

            sync();
        }
    })();

    (function () {
        var list = document.getElementById('order-list');
        if (!list) return;

        var weights = (list.getAttribute('data-weights') || '').split(',').map(Number);

        function refresh() {
            var rows = Array.prototype.slice.call(list.children);

            rows.forEach(function (row, index) {
                row.querySelector('[data-move="up"]').disabled = index === 0;
                row.querySelector('[data-move="down"]').disabled = index === rows.length - 1;

                var badge = row.querySelector('[data-weight-badge]');
                if (badge && !Number.isNaN(weights[index])) {
                    badge.textContent = 'Peso ' + weights[index].toFixed(2).replace('.', ',');
                }
            });
        }

        list.addEventListener('click', function (event) {
            var button = event.target.closest('[data-move]');
            if (!button) return;

            var row = button.closest('.order-row');
            var rows = Array.prototype.slice.call(list.children);
            var index = rows.indexOf(row);

            if (button.getAttribute('data-move') === 'up' && index > 0) {
                list.insertBefore(row, rows[index - 1]);
            } else if (button.getAttribute('data-move') === 'down' && index < rows.length - 1) {
                list.insertBefore(rows[index + 1], row);
            }

            refresh();
        });

        refresh();
    })();
</script>
@endsection
