@extends('layouts.app')

@section('title', 'Estadísticas')

@section('content')
@php
    $attrLabels = ['speed' => 'Velocidad', 'skill' => 'Habilidad', 'passing' => 'Pase', 'shooting' => 'Definición', 'defense' => 'Defensa'];
@endphp
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
                <th class="num"><span class="tip">Calificaciones<span class="tipbox tipbox--col"><span class="tip-head">Calificaciones</span>Promedio de la calificación general que te pusieron tus compañeros en tus últimos {{ $formWindow }} partidos finalizados (sin contar tu autoevaluación).</span></span></th>
                <th class="num">Rendimiento</th>
                <th class="num">MVP</th>
                <th class="num">Ganados</th>
                <th class="num">Participación</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $row)
                <tr class="{{ $i === 0 ? 'top' : '' }}">
                    <td>{{ $i + 1 }}</td>
                    <td><a href="{{ route('stats.show', $row['user']) }}">{{ $row['user']->name }}</a></td>
                    <td class="num">
                        <span class="tip">{{ number_format($row['score'], 1) }}
                            <span class="tipbox tipbox--col">
                                <span class="tip-head">Puntaje</span>
                                <span class="tip-note">Suma ponderada de las características, ajustada por el rendimiento reciente.</span>
                                @foreach ($row['weights'] as $key => $weight)
                                    <span class="tip-row">
                                        <span class="tip-row-name">{{ $attrLabels[$key] ?? $key }}</span>
                                        <span class="tip-row-val">{{ number_format($row['profile'][$key] ?? 0, 1) }} · {{ (int) round($weight * 100) }}%</span>
                                    </span>
                                @endforeach
                                <span class="tip-row">
                                    <span class="tip-row-name">Rendimiento</span>
                                    <span class="tip-row-val">×{{ number_format($row['form'], 2) }}</span>
                                </span>
                            </span>
                        </span>
                    </td>
                    <td class="num">
                        <span class="tip">{{ $row['general'] !== null ? (($row['general'] - 5) > 0 ? '+' : '').number_format($row['general'] - 5, 1) : '—' }}
                            @if ($row['general'] !== null && $row['rendimiento']->isNotEmpty())
                                <span class="tipbox tipbox--col">
                                    <span class="tip-head">Calificaciones</span>
                                    @foreach ($row['rendimiento'] as $r)
                                        <span class="tip-row">
                                            <span class="tip-row-name">{{ $r['match']->played_at->format('d/m') }}</span>
                                            <span class="tip-row-val">{{ ($r['overall'] - 5) > 0 ? '+' : '' }}{{ (int) $r['overall'] - 5 }}</span>
                                        </span>
                                    @endforeach
                                </span>
                            @endif
                        </span>
                    </td>
                    <td class="num">×{{ number_format($row['form'], 2) }}</td>
                    <td class="num">{{ $row['mvp'] }}</td>
                    <td class="num">
                        @if ($row['winRate'] !== null)
                            <span class="tip">{{ $row['winRate'] }}%
                                <span class="tipbox tipbox--col">
                                    <span class="tip-head">Ganados</span>
                                    <span class="tip-row">
                                        <span class="tip-row-name">Partidos ganados</span>
                                        <span class="tip-row-val">{{ $row['wins'] }} de {{ $row['matches'] }}</span>
                                    </span>
                                </span>
                            </span>
                        @else
                            —
                        @endif
                    </td>
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
