@php
    $current = max(-5, min(5, (int) old($name, ($value ?? 5) - 5)));
    $pct = ($current + 5) / 10 * 100;
    $hue = (int) round(($current + 5) / 10 * 105);
@endphp
<div class="slider">
    <input
        type="range"
        id="{{ $name }}"
        name="{{ $name }}"
        min="-5"
        max="5"
        step="1"
        value="{{ $current }}"
        aria-label="{{ $label }}"
        style="--fill: {{ $pct }}%; --fill-color: hsl({{ $hue }}, 72%, 42%);"
    >
</div>
<div class="scale-range">
    <span>Flojo</span>
    <output for="{{ $name }}">{{ $current > 0 ? '+'.$current : $current }}</output>
    <span>Crack</span>
</div>
<script>
    (function () {
        const input = document.getElementById('{{ $name }}');
        const out = document.querySelector('output[for="{{ $name }}"]');
        if (!input || !out) return;

        const sync = function () {
            const value = input.value;
            out.textContent = value > 0 ? '+' + value : value;
        };

        input.addEventListener('input', sync);
        sync();
    })();
</script>