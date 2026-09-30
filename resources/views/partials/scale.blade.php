@php
    $current = max(0, min(10, (int) old($name, $value ?? 5)));
    $pct = $current / 10 * 100;
    $hue = (int) round($current / 10 * 105);
@endphp
<div class="slider">
    <input
        type="range"
        id="{{ $name }}"
        name="{{ $name }}"
        min="0"
        max="10"
        step="1"
        value="{{ $current }}"
        aria-label="{{ $label }}"
        style="--fill: {{ $pct }}%; --fill-color: hsl({{ $hue }}, 72%, 42%);"
    >
</div>
<div class="scale-range"><span>Flojo</span><span>Crack</span></div>