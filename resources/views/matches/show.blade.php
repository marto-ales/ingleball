@extends('layouts.app')

@section('title', $match->title)

@section('content')
@php $scorer = app(\App\Services\Scorer::class); @endphp

<div class="page-head">
    <div>
        <h1>{{ $match->title }}</h1>
        <p class="lead">
            {{ $match->played_at->format('D, d M Y · H:i') }}
            @if ($match->venue) · {{ $match->venue }} @endif
            · {{ $goingCount }} anotados ({{ $autoSize }}v{{ $autoSize }})
        </p>
    </div>
    <div class="row">
        @include('partials.status-badge', ['match' => $match])
    </div>
</div>

@if (auth()->user()->is_organizer)
    <div class="card row">
        <strong>Herramientas de organización</strong>
        @if ($match->isOpen())
            <form method="POST" action="{{ route('matches.lock', $match) }}" class="inline">
                @csrf @method('PATCH')
                <button class="btn" type="submit">Cerrar lista</button>
            </form>
        @elseif ($match->isLocked())
            <form method="POST" action="{{ route('matches.unlock', $match) }}" class="inline">
                @csrf @method('PATCH')
                <button class="btn" type="submit">Reabrir lista</button>
            </form>
        @endif
        <form method="POST" action="{{ route('matches.remind', $match) }}" class="inline">
            @csrf
            <button class="btn" type="submit">Enviar recordatorios</button>
        </form>
        @unless ($match->isFinished())
            <form method="POST" action="{{ route('matches.finish', $match) }}" class="inline">
                @csrf @method('PATCH')
                <button class="btn btn-ghost" type="submit">Marcar finalizado</button>
            </form>
        @endunless
        <form method="POST" action="{{ route('matches.recurring', $match) }}" class="inline">
            @csrf
            <button class="btn {{ $match->recurring ? 'btn-primary' : '' }}" type="submit">
                {{ $match->recurring ? '★ Recurrente: activado' : 'Activar recurrencia semanal' }}
            </button>
        </form>
    </div>
@endif

<div class="grid grid-2">
    <div class="section" style="min-width:0;">
        <div class="card">
            <h2>Lista de anotados</h2>
            @if ($match->isOpen())
                <div class="row" style="margin-bottom:14px;">
                    <form method="POST" action="{{ route('entries.update', $match) }}" class="inline">
                        @csrf @method('PATCH')
                        <input type="hidden" name="role" value="going">
                        <button class="btn btn-primary btn-sm" type="submit" {{ ($myEntry?->role === 'going') ? 'disabled' : '' }}>🟢 Voy</button>
                    </form>
                    <form method="POST" action="{{ route('entries.update', $match) }}" class="inline">
                        @csrf @method('PATCH')
                        <input type="hidden" name="role" value="substitute">
                        <button class="btn btn-sm" type="submit" {{ ($myEntry?->role === 'substitute') ? 'disabled' : '' }}>🟡 Suplente</button>
                    </form>
                    @if ($myEntry)
                        <form method="POST" action="{{ route('entries.destroy', $match) }}" class="inline" onsubmit="return confirm('¿Quitar de la lista?');">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger btn-sm" type="submit">Quitar</button>
                        </form>
                    @endif
                </div>
            @endif

            @forelse ($entriesGoing as $entry)
                <div class="row" style="padding:8px 0;border-bottom:1px solid var(--line);">
                    <span class="idx">{{ $loop->iteration }}</span>
                    <span class="who">{{ $entry->user?->name ?? $entry->guest?->name }}</span>
                    @if ($entry->guest)<span class="tag-guest">invitado</span>@endif
                    <span class="score-tag">{{ number_format($entry->user ? $scorer->forUser($entry->user) : $scorer->forGuest($entry->guest), 1) }}</span>
                    @if (auth()->user()->is_organizer && $entry->guest)
                        <form method="POST" action="{{ route('guests.destroy', [$match, $entry->guest]) }}" class="inline" onsubmit="return confirm('¿Retirar invitado?');">
                            @csrf @method('DELETE')
                            <button class="btn btn-ghost btn-sm" type="submit">✕</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="empty">Nadie se anotó todavía.</div>
            @endforelse

            @if ($entriesSubstitute->count())
                <h3 class="mt">Suplentes</h3>
                @foreach ($entriesSubstitute as $entry)
                    <div class="row" style="padding:6px 0;">
                        <span class="muted">{{ $entry->user?->name ?? $entry->guest?->name }}</span>
                        @if ($entry->guest)<span class="tag-guest">invitado</span>@endif
                    </div>
                @endforeach
            @endif
        </div>

        @if (auth()->user()->is_organizer && $match->isOpen())
            <div class="card">
                <h2>Añadir invitado</h2>
                <form method="POST" action="{{ route('guests.store', $match) }}">
                    @csrf
                    <div class="form-row">
                        <div class="field">
                            <label>Nombre</label>
                            <input name="name" value="{{ old('name') }}" required>
                        </div>
                        <div class="field">
                            <label>Teléfono</label>
                            <input name="phone" value="{{ old('phone') }}">
                        </div>
                    </div>
                    <div class="field">
                        <label>Calificación general (1-10, opcional)</label>
                        <input type="number" name="overall" min="1" max="10" value="{{ old('overall', 6) }}">
                    </div>
                    <button class="btn btn-primary" type="submit">Añadir a la lista</button>
                </form>
            </div>
        @endif

    </div>

    <div class="section" style="min-width:0;">
        @if ($teamA->count() > 0)
            <div class="card">
                <div class="row between">
                    <h2>Equipos</h2>
                    @if (auth()->user()->is_organizer && $match->isLocked())
                        <form method="POST" action="{{ route('teams.generate', $match) }}" class="inline" onsubmit="return confirm('¿Regenerar equipos? Se reemplazará el armado actual.');">
                            @csrf
                            <button class="btn btn-sm" type="submit">🔄 Regenerar</button>
                        </form>
                    @endif
                </div>
                <div class="teams">
                    <div class="team team-a">
                        <h3>Equipo A <span class="muted small">{{ $teamA->count() }}</span></h3>
                        @foreach ($teamA as $t)
                            <div class="row" style="padding:6px 0;border-bottom:1px solid var(--line);">
                                <span class="who">{{ $t->user?->name ?? $t->guest?->name }}</span>
                                @if ($t->guest)<span class="tag-guest">inv.</span>@endif
                            </div>
                        @endforeach
                    </div>
                    <div class="team team-b">
                        <h3>Equipo B <span class="muted small">{{ $teamB->count() }}</span></h3>
                        @foreach ($teamB as $t)
                            <div class="row" style="padding:6px 0;border-bottom:1px solid var(--line);">
                                <span class="who">{{ $t->user?->name ?? $t->guest?->name }}</span>
                                @if ($t->guest)<span class="tag-guest">inv.</span>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
                @if (auth()->user()->is_organizer && $match->isLocked())
                    <div class="divider"></div>
                    <h3>Intercambiar jugadores</h3>
                    @foreach ($teamA as $t)
                        <form method="POST" action="{{ route('teams.swap', $match) }}" class="row" style="padding:6px 0;">
                            @csrf
                            <input type="hidden" name="from" value="{{ $t->id }}">
                            <span class="who">{{ $t->user?->name ?? $t->guest?->name }}</span>
                            <span class="muted">⇄</span>
                            <select name="to">
                                @foreach ($teamB as $t2)
                                    <option value="{{ $t2->id }}">{{ $t2->user?->name ?? $t2->guest?->name }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm" type="submit">Cambiar</button>
                        </form>
                    @endforeach
                @endif
            </div>
        @else
            <div class="card">
                <h2>Equipos</h2>
                <p class="muted mb0">
                    @if ($match->isOpen())
                        Los equipos se arman recién cuando el organizador cierra la lista.
                    @else
                        El armado todavía no se generó.
                    @endif
                </p>
                @if (auth()->user()->is_organizer && $match->isLocked() && $goingCount >= 4)
                    <div class="row mt">
                        <form method="POST" action="{{ route('teams.generate', $match) }}" class="inline">
                            @csrf
                            <button class="btn btn-primary" type="submit">⚙️ Armar equipos ({{ $autoSize }}v{{ $autoSize }})</button>
                        </form>
                    </div>
                @endif
            </div>
        @endif

        <div class="card">
            <div class="row between">
                <h2 class="mb0">Calificar</h2>
                <a class="btn btn-primary btn-sm" href="{{ route('ratings.create', $match) }}">Calificar jugadores</a>
            </div>
            <p class="muted small mb0 mt">Cada jugador califica a los demás (velocidad, habilidad, pase, definición, defensa). Esto alimenta el ranking y el armado equilibrado.</p>
        </div>
    </div>
</div>

<div class="section">
    <div class="card">
        <h2>Resultado</h2>
        @if ($match->result)
            <div class="row" style="margin-bottom:14px;">
                <span class="score-big">{{ $match->result->summary }}</span>
            </div>
            @if ($match->result->mvpUser ?? $match->result->mvpGuest)
                <p class="muted">
                    🏅 MVP: {{ $match->result->mvpUser?->name ?? $match->result->mvpGuest?->name }}
                    @if ($match->goals->count()) · {{ $match->goals->count() }} gol(s) registrados @endif
                </p>
            @endif
        @else
            <p class="muted mb0">Aún no se registró el resultado.</p>
        @endif

        @if (auth()->user()->is_organizer)
            <div class="divider"></div>
            @php
                $resultRow = $match->result;
                $winnerVal = old('winner', $resultRow ? ($resultRow->winner ?? 'tie') : 'A');
                $diffVal = old('diff', $resultRow?->diff ?? 0);
            @endphp
            <form method="POST" action="{{ route('result.store', $match) }}">
                @csrf
                <div class="form-row">
                    <div class="field">
                        <label>Ganador</label>
                        <div class="segmented">
                            <label><input type="radio" name="winner" value="A" @checked($winnerVal === 'A')><span>Equipo A</span></label>
                            <label><input type="radio" name="winner" value="B" @checked($winnerVal === 'B')><span>Equipo B</span></label>
                            <label><input type="radio" name="winner" value="tie" @checked($winnerVal === 'tie')><span>Empate</span></label>
                        </div>
                    </div>
                    <div class="field">
                        <label>Diferencia de goles <span class="muted small">(opcional)</span></label>
                        @include('partials.stepper', ['name' => 'diff', 'value' => $diffVal, 'min' => 0, 'max' => 20])
                    </div>
                </div>
                <div class="field">
                    <label>MVP del partido</label>
                    <select name="mvp">
                        <option value="">— Sin MVP —</option>
                        @foreach ($participants as $p)
                            <option value="{{ $p['token'] }}">{{ $p['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">{{ $match->result ? 'Actualizar resultado' : 'Registrar resultado' }}</button>
            </form>
        @endif
    </div>
</div>

@auth
    <div class="section">
        <div class="card">
            <h2>Compartir este partido</h2>
            <p class="muted small">Mensaje pre-escrito con los datos del partido y la lista actualizada por equipos, listo para reenviar al grupo.</p>
            <div class="share-box" id="share-msg">{{ $shareMessage }}</div>
            <div class="row mt">
                <a class="btn btn-primary" target="_blank" rel="noopener" href="{{ 'https://wa.me/?text=' . rawurlencode($shareMessage) }}">📲 Enviar a un chat</a>
                <button type="button" class="btn" id="copy-share">Copiar mensaje</button>
                @if ($waGroup)
                    <a class="btn wa-link" target="_blank" rel="noopener" href="{{ $waGroup }}">Abrir el grupo</a>
                @elseif (auth()->user()->is_organizer)
                    <span class="muted small">Definí el enlace del grupo en tu <a href="{{ route('profile.edit') }}">perfil</a>.</span>
                @endif
            </div>
        </div>
    </div>

    <script>
        (function () {
            var btn = document.getElementById('copy-share');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var box = document.getElementById('share-msg');
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(box.textContent).then(function () {
                        var old = btn.textContent;
                        btn.textContent = '¡Copiado!';
                        setTimeout(function () { btn.textContent = old; }, 1500);
                    });
                } else {
                    var range = document.createRange();
                    range.selectNodeContents(box);
                    var sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                    document.execCommand('copy');
                    sel.removeAllRanges();
                    btn.textContent = '¡Copiado!';
                    setTimeout(function () { btn.textContent = 'Copiar mensaje'; }, 1500);
                }
            });
        })();
    </script>
@endauth
@endsection
