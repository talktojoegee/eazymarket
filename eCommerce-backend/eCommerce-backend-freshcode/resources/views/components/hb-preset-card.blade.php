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

<x-home-builder.preset-card
    :val="$val"
    :value="$value"
    :label="$label"
    :image="$image"
    :image-path="$imagePath"
    :img-path="$imgPath"
    :img="$img"
    :src="$src"
    :icon="$icon"
    :active="$active"
    :selected="$selected"
    :thumb-class="$thumbClass"
    {{ $attributes }}
>
    {{ $slot }}
</x-home-builder.preset-card>
