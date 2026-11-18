@php
    $icons = ['text' => 'ti-forms', 'color' => 'ti-palette', 'image' => 'ti-photo'];
    $type = $attribute->swatche_type;
@endphp
<span class="badge bg-azure-lt text-capitalize">
    <i class="ti {{ $icons[$type] ?? 'ti-adjustments' }} me-1"></i>{{ __('labels.attribute_style_'.$type) }}
</span>
