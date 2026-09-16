@if ($match->isCancelled())
    <span class="badge badge-cancelled">Cancelado</span>
@elseif ($match->isOpen())
    <span class="badge badge-open">Abierto</span>
@elseif ($match->isLocked())
    <span class="badge badge-locked">Lista cerrada</span>
@else
    <span class="badge badge-finished">Finalizado</span>
@endif
