@php
    $isAdminProductList = request()->routeIs('admin.*');
@endphp

<div class="border-bottom bg-light-subtle p-3" id="product-list-filters">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="product-quick-filters" role="group" aria-label="{{ __('labels.product_list_filters') }}">
            <button type="button" class="btn btn-sm active" data-product-quick-filter>{{ __('labels.all') }}</button>
            <button type="button" class="btn btn-sm" data-product-quick-filter data-target="#productStatusFilter" data-value="active">{{ __('labels.active') }}</button>
            <button type="button" class="btn btn-sm" data-product-quick-filter data-target="#productStatusFilter" data-value="draft">{{ __('labels.draft') }}</button>
            <button type="button" class="btn btn-sm" data-product-quick-filter data-target="#productVerificationStatusFilter" data-value="pending_verification">{{ __('labels.pending') }}</button>
            <button type="button" class="btn btn-sm" data-product-quick-filter data-target="#productVerificationStatusFilter" data-value="rejected">{{ __('labels.rejected') }}</button>
            <button type="button" class="btn btn-sm" data-product-quick-filter data-target="#productFilter" data-value="out_of_stock">{{ __('labels.out_of_stock') }}</button>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <x-product-view-toggle />
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#productAdvancedFilters" aria-expanded="false">
                <i class="ti ti-adjustments-horizontal me-1"></i>{{ __('labels.more_filters') }}
                <span class="badge bg-primary-lt d-none ms-1" id="product-active-filter-count"></span>
            </button>
            <button type="button" class="btn btn-sm btn-ghost-secondary" id="clearProductFilters">
                <i class="ti ti-filter-off me-1"></i>{{ __('labels.clear_filters') }}
            </button>
        </div>
    </div>
    <div class="collapse mt-3" id="productAdvancedFilters">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-lg-3 col-xl-2">
            <label class="form-label small mb-1" for="productStatusFilter">{{ __('labels.product_status') }}</label>
            <select class="form-select" id="productStatusFilter">
                <option value="">{{ __('labels.all_product_statuses') }}</option>
                @foreach(\App\Enums\Product\ProductStatusEnum::values() as $status)
                    <option value="{{ $status }}">{{ ucfirst(\Illuminate\Support\Str::replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-3 col-xl-2">
            <label class="form-label small mb-1" for="productVerificationStatusFilter">{{ __('labels.verification_status') }}</label>
            <select class="form-select" id="productVerificationStatusFilter">
                <option value="">{{ __('labels.all_verification_statuses') }}</option>
                @foreach(\App\Enums\Product\ProductVarificationStatusEnum::values() as $status)
                    <option value="{{ $status }}">{{ ucfirst(\Illuminate\Support\Str::replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-3 col-xl-2">
            <label class="form-label small mb-1" for="productFilter">{{ __('labels.inventory_and_flags') }}</label>
            <select class="form-select" id="productFilter">
                <option value="">{{ __('labels.all_inventory_and_flags') }}</option>
                @foreach(\App\Enums\Product\ProductFilterEnum::values() as $filter)
                    <option value="{{ $filter }}">{{ __('labels.'.$filter) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-3 col-xl-2">
            <label class="form-label small mb-1" for="productTypeFilter">{{ __('labels.product_type') }}</label>
            <select class="form-select" id="productTypeFilter">
                <option value="">{{ __('labels.all_product_types') }}</option>
                @foreach(\App\Enums\Product\ProductTypeEnum::values() as $type)
                    <option value="{{ $type }}">{{ ucfirst(\Illuminate\Support\Str::replace('_', ' ', $type)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6 col-lg-3 col-xl-2">
            <label class="form-label small mb-1" for="productCategoryFilter">{{ __('labels.category') }}</label>
            <select class="form-select" id="productCategoryFilter" data-placeholder="{{ __('labels.all_categories') }}"></select>
        </div>
        <div class="col-12 col-sm-6 col-lg-3 col-xl-2">
            <label class="form-label small mb-1" for="productBrandFilter">{{ __('labels.brand') }}</label>
            <select class="form-select" id="productBrandFilter" data-placeholder="{{ __('labels.all_brands') }}"></select>
        </div>
        @if($isAdminProductList)
            <div class="col-12 col-sm-6 col-lg-3 col-xl-2">
                <label class="form-label small mb-1" for="productSellerFilter">{{ __('labels.seller') }}</label>
                <select class="form-select" id="productSellerFilter" data-placeholder="{{ __('labels.all_sellers') }}"></select>
            </div>
            <div class="col-12 col-sm-6 col-lg-3 col-xl-2">
                <label class="form-label small mb-1" for="productStoreFilter">{{ __('labels.store') }}</label>
                <select class="form-select" id="productStoreFilter" data-placeholder="{{ __('labels.all_stores') }}"></select>
            </div>
            <div class="col-12 col-sm-6 col-lg-3 col-xl-2">
                <label class="form-label small mb-1" for="productBadgeFilter">{{ __('labels.badge') }}</label>
                <select class="form-select" id="productBadgeFilter" data-placeholder="{{ __('labels.all_badges') }}"></select>
            </div>
        @endif
        <div class="col-6 col-lg-3 col-xl-2">
            <label class="form-label small mb-1" for="productDateFrom">{{ __('labels.created_from') }}</label>
            <input type="date" class="form-control" id="productDateFrom">
        </div>
        <div class="col-6 col-lg-3 col-xl-2">
            <label class="form-label small mb-1" for="productDateTo">{{ __('labels.created_to') }}</label>
            <input type="date" class="form-control" id="productDateTo">
        </div>
    </div>
    </div>
</div>

<div id="product-list-meta" hidden data-search-placeholder="{{ __('labels.search_products_hint') }}"></div>
