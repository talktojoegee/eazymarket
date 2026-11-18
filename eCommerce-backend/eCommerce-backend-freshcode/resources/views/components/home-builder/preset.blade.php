@props([
    'field' => null,
    'preset' => null,
    'options' => null,
    'value' => null,
    'current' => null,
])

@php
    $fieldName = $field ?? $preset;
    $currentValue = $value ?? $current;
@endphp

<div {{ $attributes->merge(['data-preset' => $fieldName])->class(['hb-preset']) }}>
    @if(is_iterable($options))
        @foreach($options as $key => $option)
            @php
                if (is_array($option)) {
                    $optVal = $option['val'] ?? $option['value'] ?? $option[0] ?? $key;
                    $optLabel = $option['label'] ?? $option[1] ?? $optVal;
                    $optImage = $option['image'] ?? $option['image_path'] ?? $option['imagePath'] ?? $option['img'] ?? $option['src'] ?? $option[2] ?? null;
                    $optIcon = $option['icon'] ?? $option[3] ?? null;
                } else {
                    $optVal = $key;
                    $optLabel = $option;
                    $optImage = null;
                    $optIcon = null;
                }
                $optActive = (string)$optVal === (string)$currentValue;
            @endphp
            <x-home-builder.preset-card
                :val="$optVal"
                :label="$optLabel"
                :image="$optImage"
                :icon="$optIcon"
                :active="$optActive"
            />
        @endforeach
    @else
        {{ $slot }}
    @endif
</div>
