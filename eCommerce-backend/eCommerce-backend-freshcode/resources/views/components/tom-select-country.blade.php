@php use Illuminate\Support\Str; @endphp
@props([
    'name'        => 'country',
    'id'          => null,
    'multiple'    => false,
    'required'    => false,
    'placeholder' => null,
    'selected'    => null,
    'valueField'  => 'iso2',
    'textField'   => 'text',
    'endpoint'    => url('countries'),
    'noFilter'    => false,
])

@php
    $componentId = $id ?? 'ts-country-' . Str::random(6);
    $isMultiple  = (bool) $multiple;
    $selectedArr = is_array($selected) ? array_values($selected) : (filled($selected) ? [(string) $selected] : []);
    $placeholderText = $placeholder ?? __('labels.search_country');
@endphp

<select
    id="{{ $componentId }}"
    name="{{ $isMultiple ? rtrim($name, '[]') . '[]' : $name }}"
    class="form-select js-tom-select-country"
    @if($isMultiple) multiple @endif
    @if($required) required @endif
    data-endpoint="{{ $endpoint }}"
    data-value-field="{{ $valueField }}"
    data-text-field="{{ $textField }}"
    data-placeholder="{{ $placeholderText }}"
    data-selected='@json($selectedArr)'
    @if($noFilter) data-no-filter="1" @endif
></select>
