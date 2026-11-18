@props([
    'field' => null,
    'preset' => null,
    'options' => null,
    'value' => null,
    'current' => null,
])

<x-home-builder.preset
    :field="$field"
    :preset="$preset"
    :options="$options"
    :value="$value"
    :current="$current"
    {{ $attributes }}
>
    {{ $slot }}
</x-home-builder.preset>
