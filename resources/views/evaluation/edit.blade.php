@extends('layouts.app')

@section('title', 'Evaluar a ' . $player->name)

@section('content')
<div class="page-head">
    <div>
        <h1>Evaluar a {{ $player->name }}</h1>
        <p class="lead">Tu evaluación se combina con la autoevaluación para armar el perfil. Podés editarla cuando quieras.</p>
    </div>
    <a class="btn btn-sm" href="{{ route('stats.show', $player) }}">← Volver</a>
</div>

@php
    $e = $evaluation;
    $radarKeys = ['speed' => 'Velocidad', 'skill' => 'Habilidad', 'passing' => 'Pase', 'shooting' => 'Definición', 'defense' => 'Defensa', 'goalkeeping' => 'Arco'];
    $radarValues = [];
    foreach ($radarKeys as $key => $label) {
        $radarValues[$key] = (int) old($key, $e?->{$key} ?? $player->player?->{$key} ?? 5);
    }
@endphp

<div class="card card--profile" style="max-width: 640px;">
    <form method="POST" action="{{ route('evaluation.update', $player) }}">
        @csrf
        @method('PATCH')

        @include('partials.radar', [
            'values' => $radarValues,
            'labels' => $radarKeys,
            'live' => true,
            'ariaLabel' => 'Vista previa de la evaluación de ' . $player->name,
        ])

        @foreach ($radarKeys as $key => $label)
            <div class="field">
                <label>{{ $label }}</label>
                @include('partials.scale', ['name' => $key, 'label' => $label, 'value' => old($key, $e?->{$key} ?? $player->player?->{$key} ?? 5)])
            </div>
        @endforeach

        <button class="btn btn-primary" type="submit">Guardar evaluación</button>
    </form>
</div>
@endsection
