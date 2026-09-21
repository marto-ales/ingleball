@extends('layouts.app')

@section('title', 'Estadísticas')

@section('content')
<div class="page-head">
    <div>
        <h1>Estadísticas</h1>
        <p class="lead">Puntaje combinado: tu autoevaluación + las calificaciones de tus compañeros.</p>
    </div>
</div>

<div class="card card--stats">
    <div class="table-wrap">
        <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Jugador</th>
                <th class="num">Puntaje</th>
                <th class="num">Partidos</th>
                <th class="num">MVP</th>
                <th class="num">General</th>
                <th class="num">Rendimiento</th>
                <th class="num">Participación</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $row)
                <tr class="{{ $i === 0 ? 'top' : '' }}">
                    <td>{{ $i + 1 }}</td>
                    <td><a href="{{ route('stats.show', $row['user']) }}">{{ $row['user']->name }}</a></td>
                    <td class="num">{{ number_format($row['score'], 1) }}</td>
                    <td class="num">{{ $row['matches'] }}</td>
                    <td class="num">{{ $row['mvp'] }}</td>
                    <td class="num">{{ $row['general'] ? number_format($row['general'], 1) : '—' }}</td>
                    <td class="num">×{{ number_format($row['form'], 2) }}</td>
                    <td class="num">{{ $row['attendance'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">Todavía no hay jugadores.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
