@php
    $att = session('attendance');
@endphp
@if ($att)
<div class="modal-backdrop is-open" id="attendance-modal" role="dialog" aria-modal="true" aria-labelledby="attendance-title">
    <div class="modal">
        <div class="modal-head">
            <h2 id="attendance-title" class="mb0">{{ $att['title'] }}</h2>
            <button type="button" class="modal-close" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <p class="muted">Cambiaste tu situación en el partido. ¿Querés avisarle al grupo de WhatsApp?</p>
        <div class="share-box" id="attendance-msg">{{ $att['message'] }}</div>
        <div class="row mt wrap">
            @if (! empty($att['wa_group']))
                <a class="btn wa-link" target="_blank" rel="noopener" href="{{ $att['wa_group'] }}">📲 Abrir el grupo</a>
            @endif
            <a class="btn" target="_blank" rel="noopener" href="{{ 'https://wa.me/?text=' . rawurlencode($att['message']) }}">Enviar a un chat</a>
            <button type="button" class="btn" data-modal-copy>Copiar mensaje</button>
            <button type="button" class="btn btn-ghost" data-modal-close>Ahora no</button>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('attendance-modal');
    if (!modal) return;

    function close() { modal.classList.remove('is-open'); }

    modal.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function (e) { e.stopPropagation(); close(); });
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) close();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });

    var copy = modal.querySelector('[data-modal-copy]');
    if (!copy) return;

    copy.addEventListener('click', function () {
        var box = document.getElementById('attendance-msg');
        if (!box) return;

        function done() {
            var old = copy.textContent;
            copy.textContent = '¡Copiado!';
            setTimeout(function () { copy.textContent = old; }, 1500);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(box.textContent).then(done, function () {});
        } else {
            var range = document.createRange();
            range.selectNodeContents(box);
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
            document.execCommand('copy');
            sel.removeAllRanges();
            done();
        }
    });
})();
</script>
@endif