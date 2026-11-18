@props([
    'val' => null,
    'value' => null,
    'label' => null,
    'image' => null,
    'imagePath' => null,
    'imgPath' => null,
    'img' => null,
    'src' => null,
    'icon' => null,
    'active' => false,
    'selected' => false,
    'thumbClass' => '',
])

@php
    $cardVal = $val ?? $value;
    $imageSrc = $image ?? $imagePath ?? $imgPath ?? $img ?? $src;
    $isActive = (bool) ($active || $selected);

    if ($imageSrc && !str_starts_with($imageSrc, 'http://') && !str_starts_with($imageSrc, 'https://') && !str_starts_with($imageSrc, '/') && !str_starts_with($imageSrc, 'data:')) {
        $imageSrc = asset($imageSrc);
    }

    $cardLabel = $label ?? (trim($slot) !== '' ? $slot : null);
@endphp

<div {{ $attributes->merge(['data-val' => $cardVal])->class(['hb-preset-card', 'active' => $isActive]) }}>
    <div @class(['hb-thumb', $thumbClass => !empty($thumbClass)])>
        @if($imageSrc)
            <img src="{{ $imageSrc }}" alt="{{ strip_tags($cardLabel ?? 'Preset') }}" class="hb-preset-img">
        @elseif($icon)
            <i class="ti {{ str_starts_with($icon, 'ti') ? $icon : 'ti-'.$icon }} hb-preset-icon"></i>
        @endif
    </div>
    @if($label)
        <span>{{ $label }}</span>
    @elseif(trim($slot))
        {{ $slot }}
    @endif
</div>
