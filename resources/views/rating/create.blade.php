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
        'overall' => $r->overall,
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
                <label>Calificación general</label>
                <p class="muted small mb0">¿Qué nivel mostró en este partido? Es un solo puntaje, del 0 al 10.</p>
                @include('partials.scale', ['name' => 'overall', 'label' => 'Calificación general', 'value' => old('overall', 5)])
            </div>

            <button class="btn btn-primary" type="submit">Guardar calificación</button>
        </form>
    @endif
</div>

<script>
    window.__existing = @json($existingMap);
    (function () {
        const hidden = document.getElementById('rated');
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
                setScale(f, (e && e[f] != null) ? e[f] : 5);
            });
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                buttons.forEach(function (b) { b.classList.remove('selected'); });
                btn.classList.add('selected');
                hidden.value = btn.dataset.token;
                apply(btn.dataset.token);
            });
        });

        const prev = hidden.value;
        if (prev) {
            buttons.forEach(function (b) {
                if (b.dataset.token === prev) b.classList.add('selected');
            });
        }
    })();
</script>
@endsection