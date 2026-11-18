@php
    $variantCount = (int) ($product->variants_count ?? 0);
    $minimumPrice = $product->variants_min_price;
    $maximumPrice = $product->variants_max_price;
    $showsPriceRange = $product->type === \App\Enums\Product\ProductTypeEnum::VARIANT();
    if ($showsPriceRange && $product->variants_min_sell_price !== null) {
        $minimumPrice = $product->variants_min_sell_price;
    }
    $hasUnlimitedInventory = (bool) $product->has_untracked_inventory;
    $inventoryLocationCount = (int) ($product->inventory_location_count ?? 0);
    $stock = (int) ($product->tracked_stock ?? 0);
    if (is_array($kitInventory ?? null)) {
        $hasUnlimitedInventory = (bool) ($kitInventory['has_unlimited_inventory'] ?? false);
        $stock = $kitInventory['stock'] === null ? 0 : (int) $kitInventory['stock'];
    }
@endphp

<div class="product-list-item">
    @if($variantCount > 0)
        <button type="button"
                class="btn btn-icon btn-sm btn-ghost-secondary expand-pricing product-list-expand"
                data-product-id="{{ $product->id }}"
                aria-expanded="false"
                aria-label="{{ __('labels.show_product_variants') }}"
                title="{{ __('labels.show_product_variants') }}">
            <i class="ti ti-chevron-right"></i>
        </button>
    @endif

    <a href="{{ $viewRoute }}" class="product-list-image" aria-label="{{ $product->title }}">
        @if($product->main_image)
            <img src="{{ $product->main_image }}" alt="{{ $product->title }}" loading="lazy">
        @else
            <i class="ti ti-photo-off"></i>
        @endif
    </a>

    <div class="product-list-content">
        <div class="d-flex align-items-start gap-2 flex-wrap">
            <a href="{{ $viewRoute }}" class="product-list-title">{{ $product->title }}</a>
            @if($product->badge)
                <span class="badge"
                      style="background-color:{{ $product->badge->bg_color }};color:{{ $product->badge->text_color }};{{ $product->badge->border_color ? 'border:1px solid '.$product->badge->border_color.';' : '' }}">
                    {{ $product->badge->label }}
                </span>
            @endif
        </div>

        <div class="product-list-meta">
            <span>#{{ $product->id }}</span>
            <span>{{ $variantCount }} {{ $variantCount === 1 ? __('labels.variant') : __('labels.variants') }}</span>
            <span>{{ \Illuminate\Support\Str::headline($product->type) }}</span>
            @if($showSeller)
                <span><i class="ti ti-user me-1"></i>{{ $product->seller?->user?->name ?? '—' }}</span>
            @endif
        </div>

        <div class="product-list-catalog">
            <span>{{ $product->category?->title ?? '—' }}</span>
            <i class="ti ti-chevron-right"></i>
            <span>{{ $product->brand?->title ?? '—' }}</span>
        </div>

        <div class="product-list-summary">
            @if($minimumPrice !== null)
                <span class="product-list-price{{ $showsPriceRange ? ' product-list-price-range' : '' }}">
                    <i class="ti ti-cash"></i>
                    @if($showsPriceRange)
                        {{ \App\Support\PricePresenter::forCurrent((float) $minimumPrice)['formatted'] }}
                        @if($maximumPrice !== null && (float) $maximumPrice !== (float) $minimumPrice)
                            – {{ \App\Support\PricePresenter::forCurrent((float) $maximumPrice)['formatted'] }}
                        @endif
                    @else
                        {{ \App\Support\PricePresenter::forCurrent((float) $minimumPrice)['formatted'] }}
                    @endif
                </span>
            @endif
            <span class="{{ $inventoryLocationCount === 0 || (! $hasUnlimitedInventory && $stock <= 0) ? 'text-danger' : '' }}">
                <i class="ti ti-packages"></i>
                @if($inventoryLocationCount === 0)
                    {{ __('labels.inventory_not_configured') }}
                @else
                    {{ $hasUnlimitedInventory ? __('labels.unlimited') : $stock.' '.__('labels.in_stock') }}
                @endif
            </span>
            {!! $status !!}
        </div>
    </div>
</div>
