@props([
    'id',
    'name',
    'label',
    'value' => 50,
    'min' => 0,
    'max' => 100,
    'step' => 1,
    'disabled' => false,
    'unit' => '%',
])

<div class="range-field">
    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
        <label class="form-label mb-0" for="{{ $id }}">{{ $label }}</label>
        <output class="small text-secondary" for="{{ $id }}" data-range-output>{{ $value }}{{ $unit }}</output>
    </div>
    <input class="form-range" id="{{ $id }}" type="range" name="{{ $name }}" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ $value }}" data-range-unit="{{ $unit }}" data-range-input {{ $disabled ? 'disabled' : '' }}>
</div>
