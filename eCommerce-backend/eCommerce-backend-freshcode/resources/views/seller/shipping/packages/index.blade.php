@extends('layouts.seller.app', ['page' => $menuSeller['shipping']['active'] ?? 'shipping'])

@section('title', __('labels.shipping_packages'))

@section('header_data')
    @php
        $page_title = __('labels.shipping_packages');
        $page_pretitle = __('labels.shipping_and_delivery');
    @endphp
@endsection

@php
    use App\Models\StorePackage;

    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.shipping_and_delivery'), 'url' => route('seller.shipping.index')],
        ['title' => __('labels.shipping_packages'), 'url' => null],
    ];

    $typeIcons = [
        StorePackage::TYPE_BOX       => 'ti-box',
        StorePackage::TYPE_ENVELOPE  => 'ti-mail',
        StorePackage::TYPE_SOFT_PACK => 'ti-package',
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper">
        <div class="page-header d-print-none">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">{{ __('labels.shipping_packages') }}</h2>
                    <x-breadcrumb :items="$breadcrumbs"/>
                </div>
                @if ($createPermission)
                    <div class="col-auto">
                        <a href="{{ route('seller.shipping.packages.create', ['store_id' => $selectedStoreId]) }}"
                           class="btn btn-primary">
                            <i class="ti ti-plus me-1"></i>{{ __('labels.add_store_package') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <div class="page-body">
            @include('seller.shipping.partials._section_tabs', ['active' => 'packages'])

            @if ($packages->isEmpty())
                <div class="empty">
                    <div class="empty-icon"><i class="ti ti-package"></i></div>
                    <p class="empty-title">{{ __('labels.no_store_packages_yet') }}</p>
                    <p class="empty-subtitle text-secondary">
                        {{ __('labels.store_packages_empty_hint') }}
                    </p>
                </div>
            @else
                <div class="row row-cards">
                    @foreach ($packages as $package)
                        <div class="col-md-6 col-lg-4">
                            <div class="card store-package-card" data-package-id="{{ $package->id }}">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between mb-2">
                                        <div>
                                            <h3 class="mb-1">{{ $package->name }}</h3>
                                            <div class="text-secondary small">
                                                <i class="ti ti-building-store me-1"></i>{{ $package->store->name ?? '—' }}
                                            </div>
                                            <div class="text-secondary small">
                                                <i class="ti {{ $typeIcons[$package->type] ?? 'ti-box' }} me-1"></i>{{ __('labels.package_type_' . $package->type) }}
                                            </div>
                                        </div>
                                        @if ($package->is_default)
                                            <span class="badge bg-blue-lt border border-blue-subtle">
                                                <i class="ti ti-star-filled me-1"></i>{{ __('labels.default_package_badge') }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="d-flex flex-wrap gap-3 my-3 text-secondary small">
                                        <span title="{{ __('labels.dimensions') }}">
                                            <i class="ti ti-ruler me-1"></i>
                                            {{ $package->length_cm }} × {{ $package->breadth_cm }} × {{ $package->height_cm }} cm
                                        </span>
                                        <span title="{{ __('labels.empty_weight') }}">
                                            <i class="ti ti-weight me-1"></i>{{ number_format((float) $package->empty_weight_kg, 3) }} kg
                                        </span>
                                        <span title="{{ __('labels.volumetric_weight') }}">
                                            <i class="ti ti-scale me-1"></i>{{ number_format($package->volumetricWeightKg(), 3) }} kg
                                        </span>
                                    </div>
                                </div>
                                <div class="card-footer d-flex gap-2">
                                    @if ($editPermission)
                                        <a href="{{ route('seller.shipping.packages.edit', ['packageId' => $package->id]) }}"
                                           class="btn btn-outline-primary btn-sm flex-fill">
                                            <i class="ti ti-pencil me-1"></i>{{ __('labels.edit') }}
                                        </a>
                                    @endif
                                    @if ($editPermission && ! $package->is_default)
                                        <button type="button"
                                                class="btn btn-sm js-promote-default-package"
                                                data-action="{{ route('seller.shipping.packages.promote-default', ['packageId' => $package->id]) }}"
                                                data-package-name="{{ $package->name }}">
                                            <i class="ti ti-star me-1"></i>{{ __('labels.promote_to_default') }}
                                        </button>
                                    @endif
                                    @if ($deletePermission && ! $package->is_default)
                                        <button type="button"
                                                class="btn btn-outline-danger btn-sm js-delete-store-package"
                                                data-action="{{ route('seller.shipping.packages.delete', ['packageId' => $package->id]) }}"
                                                data-package-name="{{ $package->name }}">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    @include('seller.shipping.packages.partials._promote-default-package-modal')
    @include('seller.shipping.packages.partials._delete-package-modal')
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/store-packages-index.js') }}"></script>
@endpush
