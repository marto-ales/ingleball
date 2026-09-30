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
    $existingMap = $existing->map(fn ($r) => [
        'overall' => $r->overall - 5,
    ])->toArray();
@endphp

<div class="card card--ranking" style="max-width: 640px;">
    @if ($participants->isEmpty())
        <div class="empty">No hay rivales para calificar todavía.</div>
    @else
        <form method="POST" action="{{ route('ratings.store', $match) }}">
            @csrf
            <div class="field">
                <label>¿A quién calificás? <span class="muted small">(tocá su tarjeta)</span></label>
                <div class="pick-grid" id="pick-rated">
                    @foreach ($participants as $p)
                        <button type="button" class="pick" data-token="{{ $p['token'] }}">{{ $p['name'] }}</button>
                    @endforeach
                </div>
                <input type="hidden" name="rated" id="rated" value="{{ old('rated') }}">
                @error('rated')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <p class="muted mb0">Estás calificando a <strong id="current-name">Elegí un jugador para empezar</strong></p>
            </div>

            <div class="field">
                <label>Calificación general</label>
                <p class="muted small mb0">¿Qué nivel mostró en este partido? (0 = nivel esperado)</p>
                @include('partials.scale-centered', ['name' => 'overall', 'label' => 'Calificación general', 'value' => old('overall', 5)])
            </div>

            <button class="btn btn-primary" type="submit">Guardar calificación</button>
        </form>
    @endif
</div>

<script>
    window.__existing = @json($existingMap);
    (function () {
        const hidden = document.getElementById('rated');
        const currentName = document.getElementById('current-name');
        const buttons = document.querySelectorAll('#pick-rated .pick');
        const fields = ['overall'];

        function setScale(name, val) {
            document.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
                if (el.type === 'range') {
                    el.value = val;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                } else {
                    el.checked = String(el.value) === String(val);
                }
            });
        }

        function apply(token) {
            const e = window.__existing[token];
            fields.forEach(function (f) {
                setScale(f, (e && e[f] != null) ? e[f] : 0);
            });
        }

        function selectButton(btn) {
            buttons.forEach(function (b) { b.classList.remove('selected'); });
            btn.classList.add('selected');
            hidden.value = btn.dataset.token;
            currentName.textContent = btn.textContent.trim();
            apply(btn.dataset.token);
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectButton(btn);
            });
        });

        Object.keys(window.__existing).forEach(function (token) {
            buttons.forEach(function (btn) {
                if (btn.dataset.token === token) btn.classList.add('rated');
            });
        });

        let target = null;
        if (hidden.value) {
            target = Array.prototype.find.call(buttons, function (b) { return b.dataset.token === hidden.value; }) || null;
        }
        if (!target) {
            target = Array.prototype.find.call(buttons, function (b) { return ! b.classList.contains('rated'); }) || buttons[0];
        }
        if (target) selectButton(target);
    })();
</script>
@endsection
