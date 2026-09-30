@if (auth()->user()->is_organizer && $match->isOpen())
    <div class="row-actions">
        <button type="button" class="btn-icon row-actions-btn" aria-haspopup="true" aria-expanded="false" aria-label="Acciones del jugador" title="Acciones">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
        </button>
        <div class="row-menu" hidden>
            <form method="POST" action="{{ route('entries.manage', $match) }}">
                @csrf
                <input type="hidden" name="{{ $entry->guest ? 'guest_id' : 'user_id' }}" value="{{ $entry->guest ? $entry->guest_id : $entry->user_id }}">
                <input type="hidden" name="role" value="{{ $roleAction }}">
                <button type="submit" class="row-opt" title="{{ $roleAction === 'substitute' ? 'Poner como suplente' : 'Pasar a titular' }}">
                    <span class="{{ $roleAction === 'substitute' ? 'icon-dot' : 'icon-dot-green' }}"></span>
                    {{ $roleAction === 'substitute' ? 'Suplente' : 'Titular' }}
                </button>
            </form>
            @if ($entry->guest)
                <form method="POST" action="{{ route('guests.destroy', [$match, $entry->guest]) }}" onsubmit="return confirm('¿Retirar invitado?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="row-opt danger"><span class="icon-x">✕</span>Quitar</button>
                </form>
            @else
                <form method="POST" action="{{ route('entries.manage', $match) }}" onsubmit="return confirm('¿Quitar a {{ $entry->user->name }} de la lista?');">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $entry->user_id }}">
                    <input type="hidden" name="role" value="out">
                    <button type="submit" class="row-opt danger"><span class="icon-x">✕</span>Quitar</button>
                </form>
            @endif
        </div>
    </div>
@endif