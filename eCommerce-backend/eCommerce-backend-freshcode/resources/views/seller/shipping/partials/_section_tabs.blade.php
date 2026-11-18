@php
    $tabs = [
        [
            'key'   => 'profiles',
            'label' => __('labels.shipping_profiles'),
            'icon'  => 'ti-truck-delivery',
            'url'   => route('seller.shipping.index'),
        ],
        [
            'key'   => 'packages',
            'label' => __('labels.shipping_packages'),
            'icon'  => 'ti-package',
            'url'   => route('seller.shipping.packages.index'),
        ],
    ];
@endphp

<ul class="nav nav-tabs nav-fill mb-3" role="tablist">
    @foreach ($tabs as $tab)
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ($active ?? '') === $tab['key'] ? 'active' : '' }}"
               href="{{ $tab['url'] }}">
                <i class="ti {{ $tab['icon'] }} me-1"></i>{{ $tab['label'] }}
            </a>
        </li>
    @endforeach
</ul>
