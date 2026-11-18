@extends('layouts.seller.app', ['page' => $menuSeller['shipping']['active'] ?? 'shipping'])

@php
    $isEdit = $action === 'edit';
@endphp

@section('title', $isEdit ? __('labels.edit_shipping_profile') : __('labels.add_shipping_profile'))

@section('header_data')
    @php
        $page_title = $isEdit ? __('labels.edit_shipping_profile') : __('labels.add_shipping_profile');
        $page_pretitle = __('labels.shipping_and_delivery');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.shipping_and_delivery'), 'url' => route('seller.shipping.index')],
        ['title' => $isEdit ? __('labels.edit') : __('labels.create'), 'url' => null],
    ];

    $formAction = $isEdit
        ? route('seller.shipping.update', ['profileId' => $profile->id])
        : route('seller.shipping.store');
    $listRoute = route('seller.shipping.index');
    $isDefault = (bool) ($profile->is_default ?? false);
@endphp

@section('seller-content')
    <div class="page-wrapper">
        <div class="page-header d-print-none">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        {{ $isEdit ? __('labels.edit_shipping_profile') : __('labels.add_shipping_profile') }}
                        @if ($isDefault)
                            <span class="badge bg-blue-lt border border-blue-subtle ms-2">
                                <i class="ti ti-star-filled me-1"></i>{{ __('labels.default_profile_badge') }}
                            </span>
                        @endif
                    </h2>
                    <x-breadcrumb :items="$breadcrumbs"/>
                </div>
            </div>
        </div>

        <div class="page-body">
            @include('seller.shipping.partials._section_tabs', ['active' => 'profiles'])

            <form id="shipping-profile-form"
                  action="{{ $formAction }}"
                  method="POST"
                  data-mode="{{ $isEdit ? 'edit' : 'create' }}"
                  data-action="{{ $formAction }}"
                  data-redirect="{{ $listRoute }}"
                  class="form-submit">
                @csrf

                <div class="row row-cards">
                    <div class="col-lg-7">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('labels.shipping_profile_basics') }}</h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label required">{{ __('labels.store') }}</label>
                                    <select name="store_id"
                                            class="form-select js-tom-select-static"
                                            data-placeholder="{{ __('labels.select_a_store') }}"
                                            {{ $isEdit ? 'disabled' : '' }}
                                            required>
                                        @foreach ($stores as $s)
                                            <option value="{{ $s->id }}"
                                                    @selected((int) old('store_id', $storeId) === (int) $s->id)>
                                                {{ $s->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($isEdit)
                                        <input type="hidden" name="store_id" value="{{ $storeId }}">
                                        <small class="form-hint">{{ __('labels.shipping_profile_store_lock_hint') }}</small>
                                    @endif
                                </div>

                                <div class="mb-3">
                                    <label class="form-label required">{{ __('labels.name') }}</label>
                                    <input type="text"
                                           name="name"
                                           class="form-control"
                                           value="{{ old('name', $profile->name) }}"
                                           required
                                           maxlength="120"
                                           {{ $isDefault ? 'readonly' : '' }}>
                                    @if ($isDefault)
                                        <small class="form-hint">{{ __('labels.default_profile_name_lock_hint') }}</small>
                                    @else
                                        <small class="form-hint">{{ __('labels.shipping_profile_name_hint') }}</small>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('labels.locations') }}</h3>
                            </div>
                            <div class="card-body">
                                <p class="text-secondary small mb-3">{{ __('labels.shipping_profile_locations_hint') }}</p>
                                <div class="mb-2">
                                    <label class="form-label">{{ __('labels.attached_locations') }}</label>
                                    <select name="store_location_ids[]"
                                            id="store-locations-select"
                                            class="form-select js-tom-select-static"
                                            data-placeholder="{{ __('labels.select_locations') }}"
                                            multiple>
                                        @foreach ($locations as $loc)
                                            <option value="{{ $loc->id }}"
                                                    @selected(in_array((int) $loc->id, array_map('intval', old('store_location_ids', $selectedLocationIds)), true))>
                                                {{ $loc->name }}@if ($loc->is_default) — {{ __('labels.default') }}@endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div id="location-priority-wrap"
                                     class="mt-3 {{ $rankedLocations->isEmpty() ? 'd-none' : '' }}">
                                    <label class="form-label">{{ __('labels.fulfillment_priority') }}</label>
                                    <p class="text-secondary small mb-2">{{ __('labels.fulfillment_priority_hint') }}</p>
                                    <ol class="list-group" id="location-priority-list">
                                        @foreach ($rankedLocations as $loc)
                                            <li class="list-group-item d-flex align-items-center" data-id="{{ $loc->id }}">
                                                <i class="ti ti-grip-vertical text-secondary me-2 js-drag-handle"></i>
                                                <span class="js-loc-name">{{ $loc->name }}@if ($loc->is_default) — {{ __('labels.default') }}@endif</span>
                                                <input type="hidden" name="store_location_priority[]" value="{{ $loc->id }}">
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3" id="shipping-zones-card"
                             @if($isEdit) data-profile-id="{{ $profile->id }}" @endif>
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h3 class="card-title">{{ __('labels.shipping_zones') }}</h3>
                                @if ($isEdit)
                                    <button type="button"
                                            class="btn btn-sm btn-primary js-open-zone-modal"
                                            data-mode="create">
                                        <i class="ti ti-plus me-1"></i>{{ __('labels.add_shipping_zone') }}
                                    </button>
                                @endif
                            </div>
                            <div class="card-body">
                                @if (! $isEdit)
                                    <p class="text-secondary mb-0">
                                        <i class="ti ti-info-circle me-1"></i>
                                        {{ __('labels.shipping_zones_after_save_hint') }}
                                    </p>
                                @elseif ($zones->isEmpty())
                                    <div class="empty py-3">
                                        <div class="empty-icon"><i class="ti ti-world"></i></div>
                                        <p class="empty-title mb-0">{{ __('labels.no_shipping_zones_yet') }}</p>
                                        <p class="empty-subtitle text-secondary small mt-1">
                                            {{ __('labels.shipping_zones_empty_hint') }}
                                        </p>
                                    </div>
                                @else
                                    <div class="list-group list-group-flush" id="shipping-zones-list">
                                        @foreach ($zones as $zone)
                                            @include('seller.shipping.partials._zone-row', ['zone' => $zone])
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('labels.products') }}</h3>
                            </div>
                            <div class="card-body">
                                <p class="text-secondary small mb-3">
                                    @if ($isDefault)
                                        {{ __('labels.default_profile_products_hint') }}
                                    @else
                                        {{ __('labels.shipping_profile_products_hint') }}
                                    @endif
                                </p>
                                <div class="mb-2">
                                    <label class="form-label">{{ __('labels.assigned_products') }}</label>
                                    <select name="product_ids[]"
                                            class="form-select js-tom-select-product"
                                            data-endpoint="{{ route('seller.shipping.products-search') }}"
                                            data-store-id="{{ $storeId }}"
                                            data-selected='@json(array_map('intval', old('product_ids', $selectedProductIds)))'
                                            data-placeholder="{{ $isDefault ? __('labels.default_profile_picker_placeholder') : __('labels.select_products') }}"
                                            multiple
                                            {{ $isDefault ? 'disabled' : '' }}></select>
                                    @if ($isDefault)
                                        <small class="form-hint">{{ __('labels.default_profile_picker_lock_hint') }}</small>
                                    @else
                                        <small class="form-hint">{{ __('labels.shipping_profile_products_unique_hint') }}</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ $listRoute }}" class="btn btn-link">{{ __('labels.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">
                        {{ $isEdit ? __('labels.submit') : __('labels.add_shipping_profile') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if ($isEdit)
        @include('seller.shipping.partials._zone-form-modal', [
            'profile' => $profile,
            'zoneCountries' => $zoneCountries ?? collect(),
        ])
        @include('seller.shipping.partials._delete-zone-modal')
    @endif
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/vendor/sortablejs/sortable.min.js') }}"></script>
    <script src="{{ hyperAsset('assets/js/shipping-location-priority.js') }}"></script>
    @if ($isEdit)
        <script>
            window.shippingZoneEndpoints = {
                base: '{{ route('seller.shipping.zones.store', ['profileId' => $profile->id]) }}',
                show: '{{ url('seller/shipping/' . $profile->id . '/zones') }}',
                geoStates: '{{ route('seller.shipping.geo.states') }}',
                geoCities: '{{ route('seller.shipping.geo.cities') }}',
            };
        </script>
        <script src="{{ hyperAsset('assets/js/shipping-zones-form.js') }}"></script>
    @endif
@endpush
