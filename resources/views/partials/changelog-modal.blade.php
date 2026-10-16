@php
    $entries = $announcements ?? [];
@endphp
@if (! empty($entries))
<div class="modal-backdrop is-open" id="changelog-modal" role="dialog" aria-modal="true" aria-labelledby="changelog-title">
    <div class="modal">
        <form method="POST" action="{{ route('changelog.dismiss') }}" id="changelog-form">
            @csrf
            <div class="modal-head">
                <h2 id="changelog-title" class="mb0">👋 Novedades del sitio</h2>
                <button type="submit" class="modal-close" aria-label="Cerrar">✕</button>
            </div>
            <p class="muted">Esto es lo último que cambió en Ingleball:</p>
            @foreach ($entries as $entry)
                <div class="changelog-entry">
                    <div class="row between">
                        <strong>{{ $entry['title'] }}</strong>
                        <span class="muted small">{{ \Illuminate\Support\Carbon::parse($entry['date'])->isoFormat('D [de] MMMM') }}</span>
                    </div>
                    <ul class="changelog-list">
                        @foreach ($entry['changes'] as $change)
                            <li>{{ $change }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
            <div class="row mt wrap">
                <button type="submit" class="btn btn-primary">¡Entendido!</button>
                <button type="submit" name="next" value="all" class="btn btn-ghost">Ver todas</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('changelog-modal');
    var form = document.getElementById('changelog-form');
    if (!modal || !form) return;

    modal.addEventListener('click', function (e) {
        if (e.target === modal) form.submit();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') form.submit();
    });
})();
</script>
@endif
