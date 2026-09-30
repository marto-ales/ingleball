@extends('layouts.app')

@section('title', 'Calificar')

@section('content')
<div class="page-head">
    <div>
        <h1>Calificar jugadores</h1>
        <p class="lead">{{ $match->title }}</p>
    </div>
    <a class="btn btn-sm" href="{{ route('matches.show', $match) }}">← Volver</a>
</div>

@php
    $existingMap = $existing->mapWithKeys(fn ($r, $token) => [$token => $r->overall - 5]);

    $pendingKey = 'ingleball.ratings.'.$me->id.'.'.$match->id;
@endphp

<div class="card card--ranking" style="max-width: 640px;">
    @if ($participants->isEmpty())
        <div class="empty">No hay rivales para calificar todavía.</div>
    @else
        <p class="muted small">Elegí a un jugador y mové la barra. Podés calificar a varios, y cuando termines apretá el botón para guardarlos todos juntos.</p>

        <form method="POST" action="{{ route('ratings.store', $match) }}" id="ratings-form">
            @csrf

            <div class="field">
                <label>¿A quién querés calificar?</label>
                <div class="pick-grid" id="pick-rated">
                    @foreach ($participants as $p)
                        <button
                            type="button"
                            class="pick{{ $existingMap->has($p['token']) ? ' rated' : '' }}"
                            data-token="{{ $p['token'] }}"
                            data-name="{{ $p['name'] }}"
                        >{{ $p['name'] }}</button>
                    @endforeach
                </div>
            </div>

            <div class="field">
                <p class="muted mb0">Estás calificando a <strong id="current-name">elegí un jugador</strong></p>
            </div>

            <div class="field">
                <label>Calificación general</label>
                <p class="muted small mb0">¿Qué nivel mostró en este partido? (0 es el nivel de siempre)</p>
                @include('partials.scale-centered', [
                    'name' => 'overall',
                    'label' => 'Calificación general',
                    'value' => 5,
                ])
            </div>

            <div id="ratings-fields"></div>

            <div class="row" style="margin-top:18px;">
                <button class="btn btn-primary" type="submit">Guardar calificaciones</button>
            </div>

            @error('ratings')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </form>
    @endif
</div>

<script>
    (function () {
        const pendingKey = @json($pendingKey);
        const form = document.getElementById('ratings-form');
        if (!form) return;

        const existing = @json($existingMap->all());
        const buttons = Array.prototype.slice.call(form.querySelectorAll('#pick-rated .pick'));
        const slider = document.getElementById('overall');
        const currentName = document.getElementById('current-name');
        const fields = document.getElementById('ratings-fields');

        // token -> overall (-5..5)
        const values = Object.assign({}, existing);

        function readPending() {
            try {
                return JSON.parse(localStorage.getItem(pendingKey) || '{}');
            } catch (e) {
                return {};
            }
        }

        function savePending() {
            localStorage.setItem(pendingKey, JSON.stringify(values));
        }

        function markRated() {
            buttons.forEach(function (btn) {
                if (values[btn.dataset.token] != null) {
                    btn.classList.add('rated');
                }
            });
        }

        let selected = null;
        let syncing = false;

        function showScore(value) {
            syncing = true;
            slider.value = String(value);
            slider.dispatchEvent(new Event('input', { bubbles: true }));
            syncing = false;
        }

        function select(btn) {
            buttons.forEach(function (b) { b.classList.remove('selected'); });
            btn.classList.add('selected');
            selected = btn.dataset.token;
            currentName.textContent = btn.dataset.name;
            showScore(values[selected] == null ? 0 : values[selected]);
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () { select(btn); });
        });

        slider.addEventListener('input', function () {
            if (syncing || selected === null) return;
            values[selected] = parseInt(slider.value, 10);
            markRated();
            savePending();
        });

        Object.assign(values, readPending());
        markRated();

        const first = buttons.filter(function (b) { return values[b.dataset.token] == null; })[0] || buttons[0];
        select(first);

        form.addEventListener('submit', function (event) {
            const tokens = Object.keys(values);
            if (tokens.length === 0) {
                event.preventDefault();
                window.alert('Calificá al menos a un jugador.');
                return;
            }

            fields.textContent = '';
            tokens.forEach(function (token) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ratings[' + token + ']';
                input.value = String(values[token]);
                fields.appendChild(input);
            });
        });
    })();
</script>
@endsection
