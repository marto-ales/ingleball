@extends('layouts.app')

@section('title', 'Partidos')

@section('content')
<div class="page-head">
    <div><h1>Partidos</h1></div>
    @if (auth()->user()->is_organizer)
        <a class="btn btn-primary" href="{{ route('matches.create') }}">+ Nuevo partido</a>
    @endif
</div>

<div class="section">
    <h2>Próximos / en curso</h2>
    @forelse ($matches->get('upcoming', collect()) as $match)
        <div class="card card--live row between">
            <div>
                <div class="row">
                    <strong>{{ $match->title }}</strong>
                    @include('partials.status-badge', ['match' => $match])
                    @if ($match->recurring) <span class="badge badge-info">Recurrente</span> @endif
                </div>
                <div class="muted small">{{ $match->played_at->format('D, d M Y H:i') }} · creó {{ $match->creator?->name }}</div>
                @if ($match->field_value !== null)
                    <div class="muted small">💰 Valor: ${{ number_format($match->costPerPerson(), 0, ',', '.') }} por persona</div>
                @endif
            </div>
            <a class="btn btn-primary btn-sm" href="{{ route('matches.show', $match) }}">Abrir</a>
        </div>
    @empty
        <div class="card empty">Sin partidos próximos.</div>
    @endforelse
</div>

@if ($matches->has('cancelled') && $matches->get('cancelled', collect())->isNotEmpty())
    <div class="section">
        <h2>Cancelados</h2>
        @foreach ($matches->get('cancelled') as $match)
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

<div class="section">
    <h2>Historial</h2>
    @forelse ($matches->get('finished', collect()) as $match)
        <div class="card card--history row between">
            <div>
                <div class="row">
                    <strong>{{ $match->title }}</strong>
                    <span class="badge badge-finished">Finalizado</span>
                </div>
                <div class="muted small">
                    {{ $match->played_at->format('d M Y') }}
                    @if ($match->result) · {{ $match->result->summary }} @endif
                </div>
                @if ($match->field_value !== null)
                    <div class="muted small">💰 Valor: ${{ number_format($match->costPerPerson(), 0, ',', '.') }} por persona</div>
                @endif
            </div>
            <a class="btn btn-sm" href="{{ route('matches.show', $match) }}">Ver</a>
        </div>
    @empty
        <div class="card empty">Sin historial todavía.</div>
    @endforelse
</div>
@endsection
