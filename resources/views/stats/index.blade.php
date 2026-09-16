@extends('layouts.app')

@section('title', 'Ranking')

@section('content')
<div class="page-head">
    <div>
        <h1>Ranking</h1>
        <p class="lead">Puntaje combinado: tu autoevaluación + las calificaciones de tus compañeros.</p>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Jugador</th>
                <th class="num">Puntaje</th>
                <th class="num">Partidos</th>
                <th class="num">Goles</th>
                <th class="num">Asist.</th>
                <th class="num">MVP</th>
                <th class="num">Arco</th>
                <th class="num">Media calif.</th>
                <th class="num">Acierto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $row)
                <tr class="{{ $i === 0 ? 'top' : '' }}">
                    <td>{{ $i + 1 }}</td>
                    <td><a href="{{ route('stats.show', $row['user']) }}">{{ $row['user']->name }}</a></td>
                    <td class="num">{{ number_format($row['score'], 1) }}</td>
                    <td class="num">{{ $row['matches'] }}</td>
                    <td class="num">{{ $row['goals'] }}</td>
                    <td class="num">{{ $row['assists'] }}</td>
                    <td class="num">{{ $row['mvp'] }}</td>
                    <td class="num">{{ $row['avg_goalkeeping'] ? number_format($row['avg_goalkeeping'], 1) : '—' }}</td>
                    <td class="num">{{ $row['avg_rating'] ? number_format($row['avg_rating'], 1) : '—' }}</td>
                    <td class="num">{{ $row['attendance'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="10" class="empty">Todavía no hay jugadores.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
