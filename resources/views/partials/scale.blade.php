@php $current = old($name, $value ?? 5); @endphp
<div class="scale" role="radiogroup" aria-label="{{ $label }}">
    @for ($i = 1; $i <= 10; $i++)
        <label class="scale-opt">
            <input type="radio" name="{{ $name }}" value="{{ $i }}" @checked((string) $current === (string) $i) required>
            <span>{{ $i }}</span>
        </label>
    @endfor
</div>