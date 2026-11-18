@extends('layouts.seller.app', ['page' => $menuSeller['shipping']['active'] ?? 'shipping'])

@section('title', __('labels.shipping_and_delivery'))

@section('header_data')
    @php
        $page_title = __('labels.shipping_and_delivery');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.shipping_and_delivery'), 'url' => null],
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper">
        <div class="page-header d-print-none">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">{{ __('labels.shipping_and_delivery') }}</h2>
                    <x-breadcrumb :items="$breadcrumbs"/>
                </div>
                @if ($createPermission)
                    <div class="col-auto">
                        <a href="{{ route('seller.shipping.create', ['store_id' => $selectedStoreId]) }}"
                           class="btn btn-primary">
                            <i class="ti ti-plus me-1"></i>{{ __('labels.add_shipping_profile') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <div class="page-body">

            @include('seller.shipping.partials._section_tabs', ['active' => 'profiles'])

            @if ($profiles->isEmpty())
                <div class="empty">
                    <div class="empty-icon"><i class="ti ti-truck-delivery"></i></div>
                    <p class="empty-title">{{ __('labels.no_shipping_profiles_yet') }}</p>
                    <p class="empty-subtitle text-secondary">
                        {{ __('labels.shipping_profile_empty_hint') }}
                    </p>
                </div>
            @else
                <div class="row row-cards">
                    @foreach ($profiles as $profile)
                        <div class="col-md-6 col-lg-4">
                            <div class="card shipping-profile-card" data-profile-id="{{ $profile->id }}">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between mb-2">
                                        <div>
                                            <h3 class="mb-1">{{ $profile->name }} <span class="badge bg-secondary-lt user-select-all">#{{ $profile->id }}</span></h3>
                                            <div class="text-secondary small">
                                                <i class="ti ti-building-store me-1"></i>{{ $profile->store->name ?? '—' }}
                                            </div>
                                        </div>
                                        @if ($profile->is_default)
                                            <span class="badge bg-blue-lt border border-blue-subtle">
                                                <i class="ti ti-star-filled me-1"></i>{{ __('labels.default_profile_badge') }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="d-flex flex-wrap gap-3 my-3 text-secondary small">
                                        <span title="{{ __('labels.locations') }}">
                                            <i class="ti ti-map-pin me-1"></i>{{ $profile->locations->count() }}
                                            {{ __('labels.locations') }}
                                        </span>
                                        <span title="{{ __('labels.shipping_zones') }}">
                                            <i class="ti ti-world me-1"></i>{{ $profile->zones->count() }}
                                            {{ __('labels.shipping_zones') }}
                                        </span>
                                        <span title="{{ $profile->is_default ? __('labels.default_profile_product_count_title') : __('labels.products') }}">
                                            <i class="ti ti-package me-1"></i>{{ $productCounts[$profile->id] ?? 0 }}
                                            {{ __('labels.products') }}
                                            @if ($profile->is_default)
                                                <span class="text-secondary">·</span>
                                                <small class="text-secondary">{{ __('labels.auto') }}</small>
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                <div class="card-footer d-flex gap-2">
                                    @if ($editPermission)
                                        <a href="{{ route('seller.shipping.edit', ['profileId' => $profile->id]) }}"
                                           class="btn btn-outline-primary btn-sm flex-fill">
                                            <i class="ti ti-pencil me-1"></i>{{ __('labels.edit') }}
                                        </a>
                                    @endif
                                    @if ($deletePermission && ! $profile->is_default)
                                        <button type="button"
                                                class="btn btn-outline-danger btn-sm js-delete-shipping-profile"
                                                data-action="{{ route('seller.shipping.delete', ['profileId' => $profile->id]) }}"
                                                data-profile-name="{{ $profile->name }}">
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
    @include('seller.shipping.partials._delete-profile-modal')
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/shipping-profiles-index.js') }}"></script>
@endpush
