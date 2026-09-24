@extends('layouts.app')

@section('title', $player->name)

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $player->name }}</h1>
        <p class="lead">
            @user: {{ $player->username }}
            @if ($player->is_organizer) · organizador @endif
        </p>
    </div>
    <div class="row between" style="gap:8px;flex-wrap:wrap;">
        <a class="btn btn-sm" href="{{ route('stats.index') }}">← Estadísticas</a>
        @if (auth()->user()->is_organizer && ! auth()->user()->is($player))
            <a class="btn btn-primary btn-sm" href="{{ route('evaluation.edit', $player) }}">Evaluar jugador</a>
        @endif
    </div>
</div>

<div class="grid grid-2">
    <div>

        <div class="card card--profile">
            <h2>Perfil</h2>
            <p class="muted small">Autoevaluación + evaluaciones de organizadores, ajustado por el rendimiento reciente.</p>

            @include('partials.radar', [
                'values' => array_merge($profile, ['goalkeeping' => $goalkeeping]),
                'labels' => ['speed' => 'Velocidad', 'skill' => 'Habilidad', 'passing' => 'Pase', 'shooting' => 'Definición', 'defense' => 'Defensa', 'goalkeeping' => 'Arco'],
                'ariaLabel' => 'Perfil de ' . $player->name,
            ])

            <ul class="list">
                @foreach (['speed' => 'Velocidad', 'skill' => 'Habilidad', 'passing' => 'Pase', 'shooting' => 'Definición', 'defense' => 'Defensa'] as $key => $label)
                    <li>
                        <span>{{ $label }}</span>
                        <span class="score-tag tip" tabindex="0">
                            {{ number_format($profile[$key], 1) }}
                            <span class="tipbox" role="tooltip">
                                <span class="tipbox-num tip-self">{{ $player->player?->{$key} ?? '—' }}</span>
                                <span class="tipbox-num tip-org">{{ isset($orgEval[$key]) ? number_format($orgEval[$key], 1) : '—' }}</span>
                            </span>
                        </span>
                    </li>
                @endforeach
                <li>
                    <span>Arco</span>
                    <span class="score-tag tip" tabindex="0">
                        {{ number_format($goalkeeping, 1) }}
                        <span class="tipbox" role="tooltip">
                            <span class="tipbox-num tip-self">{{ $player->player?->goalkeeping ?? '—' }}</span>
                            <span class="tipbox-num tip-org">{{ isset($orgEval['goalkeeping']) ? number_format($orgEval['goalkeeping'], 1) : '—' }}</span>
                        </span>
                    </span>
                </li>
                <li><span>General (últ. 3 partidos)</span><span class="score-tag">{{ $form['general'] !== null ? (($form['general'] - 5) > 0 ? '+' : '').number_format($form['general'] - 5, 1) : '—' }}</span></li>
                <li><span>Rendimiento reciente</span><span class="score-tag">×{{ number_format($form['multiplier'], 2) }}</span></li>
                <li>
                    <span>¿Le gusta ir al arco?</span>
                    <span class="score-tag">{{ $player->player ? ($player->player->likes_goalie ? 'Sí' : 'No') : '—' }}</span>
                </li>
            </ul>
        </div>

    </div>

    <div>
        <div class="card card--history">
            <h2>Participaciones</h2>
            @forelse ($matches as $match)
                <div class="row" style="padding:6px 0;border-bottom:1px solid var(--line);">
                    <span class="who">{{ $match->title }}</span>
                    <span class="muted small">{{ $match->played_at->format('d M Y') }}</span>
                </div>
            @empty
                <div class="empty">Sin participaciones registradas.</div>
            @endforelse
        </div>
        <div class="card card--ranking">
            <h2>Logros</h2>
            <p class="mb0">🏅 {{ $mvp->count() }} MVP</p>
        </div>
    </div>
</div>
@endsection
