@extends('layouts.app')

@section('title', 'Novedades')

@section('content')
<div class="page-head">
    <div>
        <h1>Novedades</h1>
        <p class="lead">Lo último que cambió en el sitio.</p>
    </div>
    <a class="btn btn-sm" href="{{ route('dashboard') }}">← Volver</a>
</div>

@forelse ($entries as $entry)
    <div class="card">
        <div class="row between">
            <h2 class="mb0">{{ $entry['title'] }}</h2>
            <span class="muted small">{{ \Illuminate\Support\Carbon::parse($entry['date'])->isoFormat('D [de] MMMM [de] YYYY') }}</span>
        </div>
        <ul class="changelog-list">
            @foreach ($entry['changes'] as $change)
                <li>{{ $change }}</li>
            @endforeach
        </ul>
    </div>
@empty
    <div class="card empty">Todavía no hay novedades para mostrar.</div>
@endforelse
@endsection
