@extends('layouts.seller.app', ['page' => $menuSeller['locations']['active'] ?? 'locations'])

@php
    $isEdit = $action === 'edit';
@endphp

@section('title', $isEdit ? __('labels.edit_location') : __('labels.add_location'))

@section('header_data')
    @php
        $page_title = $isEdit ? __('labels.edit_location') : __('labels.add_location');
        $page_pretitle = __('labels.locations');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.locations'), 'url' => route('seller.locations.index')],
        ['title' => $isEdit ? __('labels.edit') : __('labels.create'), 'url' => null],
    ];

    $formAction = $isEdit
        ? route('seller.locations.update', ['locationId' => $location->id ?? ""])
        : route('seller.locations.store');
    $listRoute = route('seller.locations.index');
    $shiprocketConfig = app(\App\Services\SettingService::class)->shiprocketConfig();
    $shiprocketEnabled = (bool) ($shiprocketConfig['shiprocketEnabled'] ?? false);
    $shiprocketTitle = $shiprocketConfig['shiprocketTitle'] ?? 'Shiprocket';
@endphp

@section('seller-content')
    <div class="page-wrapper">
        <div class="page-header d-print-none">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">{{ $isEdit ? __('labels.edit_location') : __('labels.add_location') }}</h2>
                    <x-breadcrumb :items="$breadcrumbs"/>
                </div>
            </div>
        </div>

        <div class="page-body">
            <form id="store-location-form"
                  action="{{ $formAction }}"
                  method="POST"
                  data-mode="{{ $isEdit ? 'edit' : 'create' }}"
                  data-action="{{ $formAction }}"
                  data-redirect="{{ $listRoute }}"
                  data-google-api-key="{{ $googleApiKey ?? '' }}"
                  @if ($shiprocketEnabled)
                  data-shiprocket-confirm="1"
                  data-confirm-title="{{ __('labels.shiprocket_confirm_address_title') }}"
                  data-confirm-body="{{ __('labels.shiprocket_confirm_address_body', ['title' => $shiprocketTitle]) }}"
                  data-confirm-yes="{{ __('labels.shiprocket_confirm_address_yes') }}"
                  data-confirm-cancel="{{ __('labels.cancel') }}"
                  @endif
                  class="form-submit">
                @csrf
                @if ($shiprocketEnabled)
                    <div class="alert alert-warning d-flex" role="alert">
                        <i class="ti ti-alert-triangle me-2 mt-1"></i>
                        <div>{{ __('labels.shiprocket_address_accuracy_warning', ['title' => $shiprocketTitle]) }}</div>
                    </div>
                @endif

                <div class="row row-cards">
                    <div class="col-lg-6">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('labels.location_basic_details') }}</h3>
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
                                        <small class="form-hint">{{ __('labels.location_store_lock_hint') }}</small>
                                    @endif
                                </div>

                                <div class="mb-3">
                                    <label class="form-label required">{{ __('labels.name') }}</label>
                                    <input type="text"
                                           name="name"
                                           class="form-control"
                                           value="{{ old('name', $location->name) }}"
                                           required
                                           maxlength="120">
                                    <small class="form-hint">{{ __('labels.location_name_hint') }}</small>
                                </div>

                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox"
                                           name="is_active"
                                           value="1"
                                           class="form-check-input"
                                           id="loc-is-active"
                                           {{ old('is_active', $location->is_active ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="loc-is-active">{{ __('labels.is_active') }}</label>
                                    <div class="form-hint">{{ __('labels.is_active_hint') }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('labels.location_capabilities') }}</h3>
                            </div>
                            <div class="card-body">
                                <p class="text-secondary small mb-3">{{ __('labels.location_capabilities_hint') }}</p>

                                @php
                                    $toggles = [
                                        ['key' => 'fulfills_online_orders', 'label' => 'fulfills_online_orders', 'hint' => 'fulfills_online_orders_hint'],
                                        ['key' => 'is_physical_storefront', 'label' => 'is_physical_storefront', 'hint' => 'is_physical_storefront_hint'],
                                        ['key' => 'offers_local_delivery',  'label' => 'offers_local_delivery',  'hint' => 'offers_local_delivery_hint'],
                                        ['key' => 'offers_local_pickup',    'label' => 'offers_local_pickup',    'hint' => 'offers_local_pickup_hint'],
                                        ['key' => 'offers_pos',             'label' => 'offers_pos',             'hint' => 'offers_pos_hint'],
                                    ];
                                @endphp

                                @foreach ($toggles as $t)
                                    <div class="form-check form-switch mb-2">
                                        <input type="hidden" name="{{ $t['key'] }}" value="0">
                                        <input type="checkbox"
                                               name="{{ $t['key'] }}"
                                               value="1"
                                               class="form-check-input"
                                               id="loc-{{ $t['key'] }}"
                                               {{ old($t['key'], $location->{$t['key']} ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="loc-{{ $t['key'] }}">{{ __('labels.' . $t['label']) }}</label>
                                        <div class="form-hint">{{ __('labels.' . $t['hint']) }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('labels.location_address') }}</h3>
                            </div>
                            <div class="card-body">
                                @include('partials._location-address-fields', ['namePrefix' => '', 'loc' => $location, 'googleApiKey' => $googleApiKey ?? ''])
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ $listRoute }}" class="btn btn-link">{{ __('labels.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">
                        {{ $isEdit ? __('labels.submit') : __('labels.add_location') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/store-locations-form.css') }}">
@endpush

@push('scripts')
    <script src="{{ hyperAsset('assets/js/store-locations-form.js') }}"></script>
    <script src="{{ hyperAsset('assets/js/shiprocket-address-confirm.js') }}"></script>
@endpush
