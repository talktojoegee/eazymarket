@extends('layouts.seller.app', ['page' => $menuSeller['products']['active'] ?? "", 'sub_page' => $menuSeller['products']['route']['products']['sub_active']])

@section('title', __('labels.products'))

@section('header_data')
    @php
        $page_title = __('labels.products');
        $page_pretitle = __('labels.seller') . " " . __('labels.products');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.products'), 'url' => '']
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper">

        <div class="page-body">
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">{{ __('labels.products') }}</h3>
                                <x-breadcrumb :items="$breadcrumbs"/>
                            </div>
                            <div class="card-actions">
                                <div class="row g-2">
                                    @if($createPermission ?? false)
                                        <div class="col-auto">
                                            @if($productCreateLimitReached ?? false)
                                                <button type="button"
                                                        class="btn btn-outline-primary d-none d-sm-inline-block"
                                                        disabled>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24"
                                                         height="24"
                                                         viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                                         fill="none"
                                                         stroke-linecap="round" stroke-linejoin="round">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                        <path d="M12 5l0 14"/>
                                                        <path d="M5 12l14 0"/>
                                                    </svg>
                                                    {{ __('labels.bulk_upload') }}
                                                </button>
                                            @else
                                                <a href="{{ route('seller.products.bulk-upload.page') }}"
                                                   class="btn btn-outline-primary d-none d-sm-inline-block">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24"
                                                         height="24"
                                                         viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                                         fill="none"
                                                         stroke-linecap="round" stroke-linejoin="round">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                        <path d="M12 5l0 14"/>
                                                        <path d="M5 12l14 0"/>
                                                    </svg>
                                                    {{ __('labels.bulk_upload') }}
                                                </a>
                                            @endif
                                        </div>
                                        <div class="col-auto">
                                            @if($productCreateLimitReached ?? false)
                                                <button type="button" class="btn btn-primary d-none d-sm-inline-block"
                                                        disabled>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24"
                                                         height="24"
                                                         viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                                         fill="none"
                                                         stroke-linecap="round" stroke-linejoin="round">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                        <path d="M12 5l0 14"/>
                                                        <path d="M5 12l14 0"/>
                                                    </svg>
                                                    {{ __('labels.add_new_product') }}
                                                </button>
                                            @else
                                                <a href="{{ route('seller.products.create') }}"
                                                   class="btn btn-primary d-none d-sm-inline-block">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24"
                                                         height="24"
                                                         viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                                         fill="none"
                                                         stroke-linecap="round" stroke-linejoin="round">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                        <path d="M12 5l0 14"/>
                                                        <path d="M5 12l14 0"/>
                                                    </svg>
                                                    {{ __('labels.add_new_product') }}
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                    <div class="col-auto">
                                        <a href="{{ route('seller.products.bulk-upload.jobs.index') }}" class="btn btn-outline-info d-none d-sm-inline-block">
                                            {{ __('labels.bulk_upload_history') }}
                                        </a>
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-outline-primary" id="refresh">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                 stroke-linecap="round" stroke-linejoin="round"
                                                 class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/>
                                                <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                                            </svg>
                                            {{ __('labels.refresh') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if($productCreateLimitReached ?? false)
                            <div class="card-body border-top py-3">
                                <div class="alert alert-warning mb-0 mx-3" role="alert">
                                    {{ $productCreateLimitMessage }}
                                </div>
                            </div>
                        @endif
                        <div class="card-table">
                            @include('partials.product-list-filters')
                            <div class="row w-full p-3">
                                <x-datatable id="products-table" :columns="$columns"
                                             route="{{ route('seller.products.datatable') }}"
                                             :export-options="['module' => 'products', 'rowId' => 'id']"
                                             :options="['order' => [[1, 'desc']], 'pageLength' => 10, 'searchDelay' => 350, 'scrollX' => false, 'language' => ['loadingRecords' => '']]"/>
                                @php
                                    $productPricingLabels = json_encode([
                                        'variant' => __('labels.variant'),
                                        'store' => __('labels.store'),
                                        'location' => __('labels.location'),
                                        'price' => __('labels.price'),
                                        'special_price' => __('labels.special_price'),
                                        'cost' => __('labels.cost'),
                                        'stock' => __('labels.stock'),
                                        'sku' => __('labels.sku'),
                                        'inventory' => __('labels.inventory'),
                                        'availability' => __('labels.availability'),
                                        'available' => __('labels.available'),
                                        'unavailable' => __('labels.unavailable'),
                                        'unlimited' => __('labels.unlimited'),
                                        'locations' => __('labels.locations'),
                                        'location' => __('labels.location'),
                                        'selling' => __('labels.selling'),
                                        'sellingLocationsFor' => __('labels.selling_locations_for'),
                                        'inStock' => __('labels.in_stock'),
                                        'variants' => __('labels.variants'),
                                        'manage' => __('labels.manage'),
                                        'showVariants' => __('labels.show_product_variants'),
                                        'hideVariants' => __('labels.hide_product_variants'),
                                        'variantInventoryHint' => __('labels.variant_inventory_hint'),
                                        'loading' => __('labels.loading'),
                                        'empty' => __('labels.no_store_pricing_configured'),
                                        'error' => __('labels.failed_to_load_pricing'),
                                        'currency' => getCurrencySymbol(),
                                    ], JSON_UNESCAPED_UNICODE);
                                @endphp
                                <div id="product-pricing-meta" hidden
                                     data-pricing-url="{{ route('seller.products.pricing', ['id' => '__ID__']) }}"
                                     data-pricing-labels="{{ $productPricingLabels }}"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@push('scripts')
    <script src="{{hyperAsset('assets/js/product.js')}}" defer></script>
@endpush


@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/product-list.css') }}">
@endpush
