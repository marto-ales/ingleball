@extends('layouts.app')

@section('title', 'Inicio')

@section('content')
<div class="page-head">
    <div>
        <h1>Hola, {{ auth()->user()->name }}</h1>
        <p class="lead">Tus partidos próximos y su estado.</p>
    </div>
</div>

<div class="section">
    <h2>Próximos</h2>
    @forelse ($upcoming as $match)
        @php
            $dbPlayers = $match->teams_count > 0 ? $match->teams_count : $match->going_count;
            $dbCost = $match->costPerPlayer($dbPlayers);
        @endphp
        <div class="card row between">
            <div>
                <div class="row">
                    <strong>{{ $match->title }}</strong>
                    @include('partials.status-badge', ['match' => $match])
                </div>
                <div class="muted small">
                    {{ $match->played_at->format('D, d M Y H:i') }}
                    @if ($match->venue) · {{ $match->venue }} @endif
                </div>
                @if ($match->field_value !== null)
                    <div class="muted small">💰 @if ($dbCost !== null) Valor: ${{ number_format($dbCost, 0, ',', '.') }} por persona @else Valor de la cancha: ${{ number_format($match->field_value, 0, ',', '.') }} @endif</div>
                @endif
            </div>
            <div class="row">
                @if (($myEntries->get($match->id)?->role) === 'going')
                    <span class="chip">🟢 Vas</span>
                @elseif (($myEntries->get($match->id)?->role) === 'substitute')
                    <span class="chip">🟡 Suplente</span>
                @endif
                <a class="btn btn-primary btn-sm" href="{{ route('matches.show', $match) }}">Ver</a>
            </div>
        </div>
    @empty
        <div class="card empty">No hay partidos próximos. ¡A ver si se arma alguno!</div>
    @endforelse
</div>

<div class="section">
    <h2>Finalizados</h2>
    @forelse ($finished as $match)
        @php
            $dbPlayers = $match->teams_count > 0 ? $match->teams_count : $match->going_count;
            $dbCost = $match->costPerPlayer($dbPlayers);
        @endphp
        <div class="card row between">
            <div>
                <strong>{{ $match->title }}</strong>
                <span class="muted small"> · {{ $match->played_at->format('d M Y') }}</span>
                @if ($match->result)
                    <span class="chip">🏆 {{ $match->result->summary }}</span>
                @endif
                @if ($match->field_value !== null)
                    <div class="muted small">💰 @if ($dbCost !== null) Valor: ${{ number_format($dbCost, 0, ',', '.') }} por persona @else Valor de la cancha: ${{ number_format($match->field_value, 0, ',', '.') }} @endif</div>
                @endif
            </div>
            <a class="btn btn-sm" href="{{ route('matches.show', $match) }}">Ver</a>
        </div>
    @empty
        <div class="card empty">Aún no hay partidos terminados.</div>
    @endforelse
</div>
@endsection
