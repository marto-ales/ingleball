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
    <a class="btn btn-sm" href="{{ route('stats.index') }}">← Ranking</a>
</div>

<div class="grid grid-2">
    <div>
        <div class="card">
            <h2>Autoevaluación</h2>
            @if ($player->player)
                <ul class="list">
                    @foreach (['speed' => 'Velocidad', 'skill' => 'Habilidad', 'passing' => 'Pase', 'shooting' => 'Definición', 'defense' => 'Defensa', 'overall' => 'General'] as $key => $label)
                        <li><span>{{ $label }}</span><span class="score-tag">{{ $player->player->{$key} }}</span></li>
                    @endforeach
                </ul>
            @else
                <div class="empty">Sin autoevaluación registrada.</div>
            @endif
        </div>

        <div class="card">
            <h2>Logros</h2>
            <p class="mb0">🥅 {{ $goals->count() }} gol(s) · 🏅 {{ $mvp->count() }} MVP</p>
        </div>
    </div>

    <div>
        <div class="card">
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
    </div>
</div>
@endsection
