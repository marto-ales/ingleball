@php
    $current = max(0, min(10, (int) old($name, $value ?? 5)));
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
        style="--fill: {{ $current / 10 * 100 }}%"
    >
    <output for="{{ $name }}">{{ $current }}</output>
</div>
