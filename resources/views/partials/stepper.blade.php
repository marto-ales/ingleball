<div class="stepper">
    <button type="button" class="stepper-btn" data-step="-1" aria-label="Quitar">−</button>
    <input type="number" class="stepper-input" name="{{ $name }}" value="{{ $value }}" min="{{ $min ?? 0 }}" max="{{ $max ?? 99 }}" inputmode="numeric">
    <button type="button" class="stepper-btn" data-step="1" aria-label="Agregar">+</button>
</div>