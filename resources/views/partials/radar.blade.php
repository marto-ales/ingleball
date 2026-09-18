@php
    $values = $values ?? [];
    $labels = $labels ?? [];
    $live = $live ?? false;
    $attrs = array_keys($values);
    $n = max(1, count($attrs));

    $cx = 210;
    $cy = 160;
    $radius = 95;
    $labelGap = 24;

    $cos = [];
    $sin = [];
    foreach ($attrs as $i => $key) {
        $angle = -M_PI / 2 + $i * (2 * M_PI / $n);
        $cos[$key] = cos($angle);
        $sin[$key] = sin($angle);
    }

    $coord = function (float $r, float $c, float $s) use ($cx, $cy): string {
        return round($cx + $r * $c, 1) . ',' . round($cy + $r * $s, 1);
    };

    $ringPolygons = [];
    foreach ([2, 4, 6, 8, 10] as $level) {
        $ringPolygons[] = implode(' ', array_map(
            fn (string $key): string => $coord($radius * $level / 10, $cos[$key], $sin[$key]),
            $attrs,
        ));
    }

    $dataPoints = [];
    $labelPositions = [];
    $initialValues = [];
    foreach ($attrs as $key) {
        $value = max(0, min(10, (float) ($values[$key] ?? 5)));
        $initialValues[$key] = $value;
        $dataPoints[$key] = $coord($radius * $value / 10, $cos[$key], $sin[$key]);

        $labelPositions[$key] = [
            'x' => round($cx + ($radius + $labelGap) * $cos[$key], 1),
            'y' => round($cy + ($radius + $labelGap) * $sin[$key], 1),
            'anchor' => abs($cos[$key]) < 0.15 ? 'middle' : ($cos[$key] > 0 ? 'start' : 'end'),
        ];
    }
@endphp
<svg
    class="radar"
    viewBox="0 0 420 320"
    role="img"
    aria-label="{{ $ariaLabel ?? 'Perfil del jugador' }}"
    @if ($live)
        data-live="1"
        data-keys="{{ implode(',', $attrs) }}"
        data-cx="{{ $cx }}"
        data-cy="{{ $cy }}"
        data-radius="{{ $radius }}"
        data-values="{{ json_encode($initialValues) }}"
    @endif
>
    @foreach ($ringPolygons as $polygon)
        <polygon class="radar-grid" points="{{ $polygon }}" />
    @endforeach

    @foreach ($attrs as $key)
        <line class="radar-axis" x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ round($cx + $radius * $cos[$key], 1) }}" y2="{{ round($cy + $radius * $sin[$key], 1) }}" />
    @endforeach

    <polygon class="radar-shape" data-shape points="{{ implode(' ', $dataPoints) }}" />

    @foreach ($attrs as $key)
        @php $dot = explode(',', $dataPoints[$key]); @endphp
        <circle class="radar-dot" data-dot="{{ $key }}" cx="{{ $dot[0] }}" cy="{{ $dot[1] }}" r="3.5" />
    @endforeach

    @foreach ($attrs as $key)
        <text class="radar-label" x="{{ $labelPositions[$key]['x'] }}" y="{{ $labelPositions[$key]['y'] }}" text-anchor="{{ $labelPositions[$key]['anchor'] }}">{{ $labels[$key] ?? $key }}</text>
    @endforeach
</svg>
