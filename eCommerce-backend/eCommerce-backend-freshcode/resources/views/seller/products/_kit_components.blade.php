@php
    $kitPack = isset($product) ? $product->comboPack : null;
    $kitItems = $kitPack?->items ?? collect();
    $kitStoreId = old('kit_store_id', $kitPack?->store_id ?? '');
    $kitProductPrice = isset($singleProductVariant) ? $singleProductVariant?->getRawOriginal('price') : '';
@endphp
<section class="card form-section d-none" id="section-kit" data-kit-section
         data-lookup-url="{{ route(($isAdminMode ? 'admin' : 'seller') . '.products.lookup.kit-components') }}"
         data-stores-url="{{ route($isAdminMode ? 'admin.stores.with-locations' : 'seller.stores.storesWithLocations') }}"
         data-seller-id="{{ $selectedSellerId ?? '' }}"
         data-store-id="{{ $kitStoreId }}"
         data-product-price="{{ $kitProductPrice }}"
         data-label-select-store="{{ __('labels.combo_pack_select_store_first') }}"
         data-label-search-error="{{ __('labels.combo_pack_lookup_failed') }}"
         data-label-store-placeholder="{{ __('labels.select_a_store') }}"
         data-label-components-required="{{ __('labels.combo_pack_components_required') }}"
         data-label-duplicate-component="{{ __('labels.kit_duplicate_component') }}"
         data-label-price-auto="{{ __('labels.kit_price_auto_hint') }}"
         data-label-price-manual="{{ __('labels.kit_price_manual_hint') }}">
    <div class="card-header">
        <div>
            <h3 class="card-title">{{ __('labels.kit_contents') }}</h3>
            <div class="text-secondary">{{ __('labels.kit_contents_hint') }}</div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-end mb-3">
            <div class="col-12 col-md-6">
                <label for="kit-store" class="form-label required">{{ __('labels.combo_pack_store') }}</label>
                <select id="kit-store" name="kit_store_id" class="form-select" disabled>
                    <option value="">{{ __('labels.select_a_store') }}</option>
                    @if($kitStoreId)
                        <option value="{{ $kitStoreId }}" selected>{{ $kitPack?->store?->name ?? ('#' . $kitStoreId) }}</option>
                    @endif
                </select>
                @error('kit_store_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6 d-flex justify-content-md-end">
                <button type="button" class="btn btn-primary" id="kit-add-component" disabled>
                    <i class="ti ti-plus me-1"></i>{{ __('labels.combo_pack_add_component') }}
                </button>
            </div>
        </div>
        <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3" role="status">
            <span id="kit-price-status">{{ __('labels.kit_price_auto_hint') }}</span>
            <strong id="kit-calculated-price" class="ms-auto"></strong>
            <button type="button" class="btn btn-sm btn-outline-primary" id="kit-recalculate-price" disabled>
                {{ __('labels.kit_price_recalculate') }}
            </button>
        </div>
        <div id="kit-components">
            @forelse($kitItems as $index => $item)
                <div class="row g-2 align-items-end mb-2 kit-component-row" data-component-price="{{ $item->variant?->getRawOriginal('price') ?? 0 }}">
                    <div class="col-12 col-md-8">
                        <label class="form-label">{{ __('labels.combo_pack_component') }}</label>
                        <select class="form-select kit-component-select" name="kit_components[{{ $index }}][product_variant_id]" data-placeholder="{{ __('labels.combo_pack_select_component') }}" required disabled>
                            <option value="{{ $item->product_variant_id }}" data-store-ids="{{ json_encode($item->variant?->storeProductVariants?->pluck('store_id')->map(fn ($id) => (int) $id)->all() ?? []) }}" selected>{{ $item->product?->title }} — {{ $item->variant?->title ?: __('labels.default_title') }}</option>
                        </select>
                        @error("kit_components.$index.product_variant_id")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-8 col-md-2">
                        <label class="form-label">{{ __('labels.combo_pack_quantity') }}</label>
                        <input type="number" min="1" max="999" class="form-control" name="kit_components[{{ $index }}][quantity]" value="{{ old("kit_components.$index.quantity", $item->quantity) }}" required disabled>
                        @error("kit_components.$index.quantity")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-4 col-md-2">
                        <button type="button" class="btn btn-outline-danger w-100 kit-remove-component" aria-label="{{ __('labels.combo_pack_remove_component') }}" disabled><i class="ti ti-trash me-1"></i>{{ __('labels.remove') }}</button>
                    </div>
                </div>
            @empty
                <div class="row g-2 align-items-end mb-2 kit-component-row">
                    <div class="col-12 col-md-8">
                        <label class="form-label">{{ __('labels.combo_pack_component') }}</label>
                        <select class="form-select kit-component-select" name="kit_components[0][product_variant_id]" data-placeholder="{{ __('labels.combo_pack_select_component') }}" required disabled></select>
                    </div>
                    <div class="col-8 col-md-2">
                        <label class="form-label">{{ __('labels.combo_pack_quantity') }}</label>
                        <input type="number" min="1" max="999" class="form-control" name="kit_components[0][quantity]" value="1" required disabled>
                    </div>
                    <div class="col-4 col-md-2">
                        <button type="button" class="btn btn-outline-danger w-100 kit-remove-component" aria-label="{{ __('labels.combo_pack_remove_component') }}" disabled><i class="ti ti-trash me-1"></i>{{ __('labels.remove') }}</button>
                    </div>
                </div>
            @endforelse
        </div>
        @error('kit_components')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</section>

<template id="kit-component-template">
    <div class="row g-2 align-items-end mb-2 kit-component-row">
        <div class="col-12 col-md-8">
            <label class="form-label">{{ __('labels.combo_pack_component') }}</label>
            <select class="form-select kit-component-select" data-placeholder="{{ __('labels.combo_pack_select_component') }}" required disabled></select>
        </div>
        <div class="col-8 col-md-2">
            <label class="form-label">{{ __('labels.combo_pack_quantity') }}</label>
            <input type="number" min="1" max="999" class="form-control" value="1" required disabled>
        </div>
            <div class="col-4 col-md-2">
                <button type="button" class="btn btn-outline-danger w-100 kit-remove-component" aria-label="{{ __('labels.combo_pack_remove_component') }}" disabled><i class="ti ti-trash me-1"></i>{{ __('labels.remove') }}</button>
        </div>
    </div>
</template>
