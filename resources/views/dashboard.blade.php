@extends('layouts.app')

@section('title', 'Inicio')

@section('content')
<div class="page-head">
    <div>
        <h1>Hola, {{ auth()->user()->name }}</h1>
        <p class="lead">Tus partidos próximos y su estado.</p>
    </div>
</div>

@if ($needsSelfEval)
    <div class="card card--ranking row between">
        <div>
            <strong>Completá tu autoevaluación</strong>
            <p class="muted small mb0 mt">Contanos tu nivel para que el armado de equipos te ubique mejor y las notas reflejen tu rendimiento real.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('profile.edit') }}">Completar ahora</a>
    </div>
@endif

@if ($needsEmail)
    <div class="card card--ranking row between">
        <div>
            <strong>Completá tu correo</strong>
            <p class="muted small mb0 mt">Sin un correo cargado no podemos mandarte los recordatorios de los partidos.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('profile.edit') }}">Completar ahora</a>
    </div>
@endif

<div class="section">
    <h2>Próximos</h2>
    @forelse ($upcoming as $match)
        <div class="card card--live row between">
            <div>
                <div class="row">
                    <strong>{{ $match->title }}</strong>
                    @include('partials.status-badge', ['match' => $match])
                    @if ($match->recurring) <span class="badge badge-info">Recurrente</span> @endif
                </div>
                <div class="muted small">
                    {{ ucfirst($match->played_at->isoFormat('dddd, D [de] MMMM [de] YYYY HH:mm')) }}
                    @if ($match->venue) · {{ $match->venue }} @endif
                </div>
                @if ($match->field_value !== null)
                    <div class="muted small">💰 Valor: ${{ number_format($match->costPerPerson(), 0, ',', '.') }} por persona</div>
                @endif
            </div>
            <div class="row">
                @if (($myEntries->get($match->id)?->role) === 'going')
                    <span class="chip">🟢 Vas</span>
                @elseif (($myEntries->get($match->id)?->role) === 'substitute')
                    <span class="chip">🟡 Suplente</span>
                @endif
                @if ($myEntries->has($match->id))
                    <a class="btn btn-sm" href="{{ route('matches.show', $match) }}">Detalle</a>
                @else
                    <a class="btn btn-primary btn-sm" href="{{ route('matches.show', $match) }}">Anotate</a>
                @endif
            </div>
        </div>
    @empty
        <div class="card empty">No hay partidos próximos. ¡A ver si se arma alguno!</div>
    @endforelse
</div>

<div class="section">
    <h2>Finalizados</h2>
    @forelse ($finished as $match)
        <div class="card card--history row between">
            <div>
                <strong>{{ $match->title }}</strong>
                <span class="muted small"> · {{ $match->played_at->format('d M Y') }}</span>
                @if ($match->result)
                    <span class="chip">🏆 {{ $match->result->summary }}</span>
                @endif
                @if ($match->field_value !== null)
                    <div class="muted small">💰 Valor: ${{ number_format($match->costPerPerson(), 0, ',', '.') }} por persona</div>
                @endif
            </div>
            <a class="btn btn-sm" href="{{ route('matches.show', $match) }}">Ver</a>
        </div>
    @empty
        <div class="card empty">Aún no hay partidos terminados.</div>
    @endforelse
</div>

@if ($cancelled->isNotEmpty())
    <div class="section">
        <h2>Cancelados</h2>
        @foreach ($cancelled as $match)
            <div class="card card--cancelled row between">
                <div>
                    <div class="row">
                        <strong>{{ $match->title }}</strong>
                        <span class="badge badge-cancelled">Cancelado</span>
                    </div>
                    <div class="muted small">{{ $match->played_at->format('d M Y') }}</div>
                    @if ($match->field_value !== null)
                        <div class="muted small">💰 Valor: ${{ number_format($match->costPerPerson(), 0, ',', '.') }} por persona</div>
                    @endif
                </div>
                <a class="btn btn-sm" href="{{ route('matches.show', $match) }}">Ver</a>
            </div>
        @endforeach
    </div>
@endif
@endsection
