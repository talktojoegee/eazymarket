@props([
    'name'         => 'market_id',
    'id'           => null,
    'multiple'     => false,
    'required'     => false,
    'placeholder'  => null,
    'selected'     => null,
    'endpoint'     => null,
    'onlyActive'   => true,
    'preloadLimit' => 10,
    'includeAll'   => false,
    'allLabel'     => null,
])

@php
    $componentId = $id ?? 'ts-market-' . \Illuminate\Support\Str::random(6);
    $isMultiple  = (bool) $multiple;
    $selectedArr = is_array($selected)
        ? array_values($selected)
        : (filled($selected) ? [(string) $selected] : []);

    $defaultEndpoint = request()->is('seller/*')
        ? url('seller/markets/search-tom')
        : url('admin/markets/search-tom');
    $endpointUrl = $endpoint ?? $defaultEndpoint;
    $placeholderText = $placeholder ?? __('labels.select_market');
    $allLabelText = $allLabel ?? __('labels.all_markets');
@endphp

<select
    id="{{ $componentId }}"
    name="{{ $isMultiple ? rtrim($name, '[]') . '[]' : $name }}"
    class="form-select js-tom-select-market"
    placeholder="{{ $placeholderText }}"
    @if($isMultiple) multiple @endif
    @if($required) required @endif
    data-endpoint="{{ $endpointUrl }}"
    data-only-active="{{ $onlyActive ? '1' : '0' }}"
    data-preload-limit="{{ (int) $preloadLimit }}"
    data-placeholder="{{ $placeholderText }}"
    data-selected='@json($selectedArr)'
    data-include-all="{{ $includeAll ? '1' : '0' }}"
>@if($includeAll)<option value="" selected>{{ $allLabelText }}</option>@endif</select>
