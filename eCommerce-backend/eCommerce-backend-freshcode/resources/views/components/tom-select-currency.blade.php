@php use Illuminate\Support\Str; @endphp
@props([
    'name'        => 'currency_code',
    'id'          => null,
    'multiple'    => false,
    'required'    => false,
    'placeholder' => null,
    'selected'    => null,
    'onlyActive'  => true,
    'endpoint'    => null,
])

@php
    $componentId = $id ?? 'ts-currency-' . Str::random(6);
    $isMultiple  = (bool) $multiple;
    $selectedArr = is_array($selected) ? array_values($selected) : (filled($selected) ? [(string) $selected] : []);
    $endpointUrl = $endpoint ?? route('currencies.search');
    $placeholderText = $placeholder ?? __('labels.search_currency');
@endphp

<select
    id="{{ $componentId }}"
    name="{{ $isMultiple ? rtrim($name, '[]') . '[]' : $name }}"
    class="form-select js-tom-select-currency"
    @if($isMultiple) multiple @endif
    @if($required) required @endif
    data-endpoint="{{ $endpointUrl }}"
    data-only-active="{{ $onlyActive ? '1' : '0' }}"
    data-placeholder="{{ $placeholderText }}"
    data-selected='@json($selectedArr)'
></select>
