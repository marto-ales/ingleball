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
        @if ($match->field_value !== null)
            <p class="muted small" style="margin:6px 0 0;">
                💰 Valor: <strong>${{ number_format($match->costPerPerson(), 0, ',', '.') }} por persona</strong>
                · {{ $match->size }}v{{ $match->size }}
            </p>
        @endif
    </div>
    <div class="row">
        @include('partials.status-badge', ['match' => $match])
    </div>
</div>

@if (auth()->user()->is_organizer)
    <div class="card card--info row">
        <strong>Organización</strong>
        @if ($match->isCancelled())
            <form method="POST" action="{{ route('matches.reactivate', $match) }}" class="inline">
                @csrf @method('PATCH')
                <button class="btn" type="submit">Reactivar partido</button>
            </form>
        @else
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
        @endif
        @if ($match->isActive())
            <a class="btn" href="{{ route('matches.edit', $match) }}">Editar</a>
            <form method="POST" action="{{ route('matches.cancel', $match) }}" class="inline" onsubmit="return confirm('¿Cancelar este partido?');">
                @csrf @method('PATCH')
                <button class="btn btn-danger" type="submit">Cancelar partido</button>
            </form>
        @endif
        <form method="POST" action="{{ route('matches.remind', $match) }}" class="inline">
            @csrf
            <button class="btn" type="submit">Enviar recordatorios</button>
        </form>
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
        <div class="card card--live">
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
                        <button class="btn btn-sm" type="submit" {{ ($myEntry?->role === 'substitute') ? 'disabled' : '' }}>🟡 Soy suplente</button>
                    </form>
                    @if ($myEntry)
                        <form method="POST" action="{{ route('entries.destroy', $match) }}" class="inline" onsubmit="return confirm('¿Quitarme de la lista?');">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger btn-sm" type="submit">No voy</button>
                        </form>
                    @endif
                </div>
            @endif

            @forelse ($entriesGoing as $entry)
                <div class="row" style="padding:8px 0;border-bottom:1px solid var(--line);">
                    <span class="idx">{{ $loop->iteration }}</span>
                    <span class="who">{{ $entry->user?->name ?? $entry->guest?->name }}</span>
                    @if ($entry->guest)<span class="tag-guest">invitado</span>@endif
                    <span class="score-tag">{{ $entry->user ? number_format($scorer->scoreForUser($entry->user), 1) : ($entry->guest ? number_format($scorer->forGuest($entry->guest), 1) : '—') }}</span>
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
            <div class="card card--info">
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
                        <label>Calificación general</label>
                        @include('partials.scale', ['name' => 'overall', 'label' => 'Calificación general', 'value' => old('overall', 5)])
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
                        <form method="POST" action="{{ route('teams.swap', $match) }}" class="row swap-form" style="padding:6px 0;align-items:center;" data-swap>
                            @csrf
                            <input type="hidden" name="from" value="{{ $t->id }}">
                            <span class="who">{{ $t->user?->name ?? $t->guest?->name }}</span>
                            <span class="muted">⇄</span>
                            <div class="pick-grid" style="flex:1;justify-content:flex-start;">
                                <input type="hidden" name="to" value="">
                                @foreach ($teamB as $t2)
                                    <button type="button" class="pick pick-sm" data-to="{{ $t2->id }}">{{ $t2->user?->name ?? $t2->guest?->name }}</button>
                                @endforeach
                            </div>
                            <button class="btn btn-sm" type="submit" disabled>Cambiar</button>
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

        @if ($match->played_at->isPast() && ! $match->isCancelled())
            <div class="card card--ranking">
                <div class="row between">
                    <h2 class="mb0">Calificar</h2>
                    <a class="btn btn-primary btn-sm" href="{{ route('ratings.create', $match) }}">Calificar jugadores</a>
                </div>
                <p class="muted small mb0 mt">Cada jugador califica a los demás. Esto alimenta el ranking y el armado equilibrado.</p>
            </div>
        @endif
    </div>
</div>

<div class="section">
    <div class="card card--ranking">
        <h2>Resultado</h2>
        @if ($match->result)
            <div class="row" style="margin-bottom:14px;">
                <span class="score-big">{{ $match->result->summary }}</span>
            </div>
            @if ($match->result->mvpUser ?? $match->result->mvpGuest)
                <p class="muted">
                    🏅 MVP: {{ $match->result->mvpUser?->name ?? $match->result->mvpGuest?->name }}
                </p>
            @endif
        @else
            <p class="muted mb0">
                @if ($match->isCancelled())
                    Partido cancelado.
                @elseif ($match->isFinished())
                    Aún no se registró el resultado.
                @else
                    El resultado se podrá registrar recién cuando el partido esté finalizado.
                @endif
            </p>
        @endif

        @if (auth()->user()->is_organizer && $match->isFinished())
            <div class="divider"></div>
            @php
                $resultRow = $match->result;
                $winnerVal = old('winner', $resultRow ? ($resultRow->winner ?? 'tie') : 'A');
                $diffVal = old('diff', $resultRow?->diff ?? 0);
                $mvpToken = old('mvp', $resultRow && $resultRow->mvpUser ? 'user:' . $resultRow->mvpUser->id : ($resultRow && $resultRow->mvpGuest ? 'guest:' . $resultRow->mvpGuest->id : ''));
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
                    <label>MVP del partido <span class="muted small">(opcional, tocá su tarjeta)</span></label>
                    <div class="pick-grid" data-mvp>
                        <input type="hidden" name="mvp" id="mvp" value="{{ $mvpToken }}">
                        @foreach ($participants as $p)
                            <button type="button" class="pick {{ $mvpToken === $p['token'] ? 'selected' : '' }}" data-token="{{ $p['token'] }}">{{ $p['name'] }}</button>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm mt" data-mvp-clear>Quitar MVP</button>
                </div>
                <button class="btn btn-primary" type="submit">{{ $match->result ? 'Actualizar resultado' : 'Registrar resultado' }}</button>
            </form>
        @endif
    </div>
</div>

@auth
    <div class="section">
        <div class="card card--info">
            <h2>Compartir este partido</h2>
            <p class="muted small">Mensaje pre-escrito con los datos del partido y la lista actualizada por equipos, listo para reenviar al grupo.</p>
            <div class="share-box" id="share-msg">{{ $shareMessage }}</div>
            <div class="row mt">
                <a class="btn btn-primary" id="share-chat" target="_blank" rel="noopener" href="{{ 'https://wa.me/?text=' . rawurlencode($shareMessage) }}">📲 Enviar a un chat</a>
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

        (function () {
            var shareBtn = document.getElementById('share-chat');
            if (!shareBtn || !navigator.share) return;
            shareBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var box = document.getElementById('share-msg');
                navigator.share({ text: box.textContent }).catch(function () {});
            });
        })();

        (function () {
            var mvpGrid = document.querySelector('[data-mvp]');
            if (mvpGrid) {
                var mvpInput = document.getElementById('mvp');
                mvpGrid.querySelectorAll('.pick').forEach(function (b) {
                    b.addEventListener('click', function () {
                        mvpGrid.querySelectorAll('.pick').forEach(function (x) { x.classList.remove('selected'); });
                        b.classList.add('selected');
                        mvpInput.value = b.dataset.token;
                    });
                });
                var clearMvp = document.querySelector('[data-mvp-clear]');
                if (clearMvp) {
                    clearMvp.addEventListener('click', function () {
                        mvpGrid.querySelectorAll('.pick').forEach(function (x) { x.classList.remove('selected'); });
                        mvpInput.value = '';
                    });
                }
            }
            document.querySelectorAll('[data-swap]').forEach(function (form) {
                var toInput = form.querySelector('input[name="to"]');
                var submit = form.querySelector('button[type="submit"]');
                form.querySelectorAll('.pick').forEach(function (b) {
                    b.addEventListener('click', function () {
                        form.querySelectorAll('.pick').forEach(function (x) { x.classList.remove('selected'); });
                        b.classList.add('selected');
                        toInput.value = b.dataset.to;
                        submit.disabled = false;
                    });
                });
            });
        })();
    </script>
@endauth
@endsection
