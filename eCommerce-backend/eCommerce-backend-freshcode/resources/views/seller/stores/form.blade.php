@extends('layouts.seller.app', [
    'page' => $menuSeller['stores']['active'] ?? "",
])

@section('title', __('labels.add_store'))

@section('header_data')
    @php
        $page_title = __('labels.add_store');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.stores'), 'url' => route('seller.stores.index')],
        ['title' => __('labels.add_store'), 'url' => '']
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper">
        @include('components.page_header', ['title' => empty($store) ? "Register New Store" : "Update ".$store->name." Store", 'step' => 2])

        <!-- BEGIN PAGE BODY -->
        <!-- resources/views/admin/sellers/form.blade.php -->
        <div class="page-body">
            <div class="container-xl">
                <div class="row g-5">
                    <div class="col-sm-2 d-none d-lg-block">
                        <div class="sticky-top">
                            <h3>{{ __('labels.menu') }}</h3>
                            <nav class="nav nav-vertical nav-pills" id="pills">
                                <a class="nav-link" href="#pills-basic">{{ __('labels.basic_details') }}</a>
                                @if (empty($store))
                                    <a class="nav-link" href="#pills-location">{{ __('labels.store_default_location') }}</a>
                                @endif
                                <a class="nav-link" href="#pills-logos">{{ __('labels.logo_and_banner') }}</a>
                                <a class="nav-link" href="#pills-reach">{{ __('labels.brand_reach') }}</a>
                                <a class="nav-link" href="#pills-documents">{{ __('labels.business_documents') }}</a>
                                <a class="nav-link" href="#pills-seo">{{ __('labels.seo_information') }}</a>
                            </nav>
                        </div>
                    </div>
                    <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                        <div class="row row-cards">
                            <div class="col-12">
                                @php
                                    $storeShiprocketConfig = app(\App\Services\SettingService::class)->shiprocketConfig();
                                    $storeShiprocketEnabled = empty($store) && (bool) ($storeShiprocketConfig['shiprocketEnabled'] ?? false);
                                    $storeShiprocketTitle = $storeShiprocketConfig['shiprocketTitle'] ?? 'Shiprocket';
                                @endphp
                                <form
                                    action="{{empty($store) ? route('seller.stores.store') : route('seller.stores.update', $store->id ?? '')}}"
                                    class="form-submit" method="post"
                                    @if ($storeShiprocketEnabled)
                                    data-shiprocket-confirm="1"
                                    data-confirm-title="{{ __('labels.shiprocket_confirm_address_title') }}"
                                    data-confirm-body="{{ __('labels.shiprocket_confirm_address_body', ['title' => $storeShiprocketTitle]) }}"
                                    data-confirm-yes="{{ __('labels.shiprocket_confirm_address_yes') }}"
                                    data-confirm-cancel="{{ __('labels.cancel') }}"
                                    @endif
                                    enctype="multipart/form-data">
                                    @csrf
                                    <!-- Basic Details -->
                                    <div class="card mb-4" id="pills-basic">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.basic_details') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.store_name') }}</label>
                                                <input type="text" class="form-control" name="name"
                                                       placeholder="{{ __('labels.enter_store_name') }}"
                                                       value="{{ old('name', $store->name ?? '') }} "/>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label required">{{ __('labels.contact_email') }}</label>
                                                        <input type="email" class="form-control" name="contact_email"
                                                               placeholder="{{ __('labels.enter_email_address') }}"
                                                               value="{{ old('contact_email', $store->contact_email ?? '') }}" />
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label required">{{ __('labels.contact_number') }}</label>
                                                        <input type="number" min="0" class="form-control" name="contact_number"
                                                               id="mobile"
                                                               placeholder="{{ __('labels.enter_mobile_number') }}"
                                                               value="{{ old('contact_number', $store->contact_number ?? '') }}" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    @if (empty($store))
                                        @php
                                            $googleApiKey = app(\App\Services\SettingService::class)->getSettingByVariable('web');
                                            $googleApiKey = $googleApiKey ? ($googleApiKey->toArray(request())['value']['googleMapKey'] ?? '') : '';
                                            $shiprocketEnabled = (bool) (app(\App\Services\SettingService::class)->shiprocketConfig()['shiprocketEnabled'] ?? false);
                                        @endphp
                                        <div class="card mb-4" id="pills-location">
                                            <div class="card-header">
                                                <h4 class="card-title">{{ __('labels.store_default_location') }}</h4>
                                            </div>
                                            <div class="card-body">
                                                <p class="text-secondary small">{{ __('labels.store_default_location_hint') }}</p>
                                                @if ($shiprocketEnabled)
                                                    <div class="alert alert-warning d-flex" role="alert">
                                                        <i class="ti ti-alert-triangle me-2 mt-1"></i>
                                                        <div>{{ __('labels.shiprocket_address_accuracy_warning', ['title' => $storeShiprocketTitle]) }}</div>
                                                    </div>
                                                @endif
                                                <div class="mb-3">
                                                    <label class="form-label">{{ __('labels.location_name') }}</label>
                                                    <input type="text" name="location[name]" class="form-control" maxlength="120"
                                                           value="{{ old('location.name', 'Shop location') }}">
                                                </div>
                                                @include('partials._location-address-fields', ['namePrefix' => 'location', 'loc' => null, 'googleApiKey' => $googleApiKey])
                                            </div>
                                        </div>
                                    @endif

                                    <div class="card mb-4" id="pills-logos">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.logo_and_banner') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.store_logo') }}</label>
                                                <x-filepond_image name="store_logo"
                                                                  imageUrl="{{$store->store_logo ?? null}}"/>
                                                @error('store_logo')
                                                <div class="text-danger mt-2">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="mb-3">
                                                <label
                                                    class="form-label">{{ __('labels.store_banner') }}</label>
                                                <x-filepond_image name="store_banner"
                                                                  imageUrl="{{$store->store_banner ?? null}}"/>
                                                @error('store_banner')
                                                <div class="text-danger mt-2">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card mb-4" id="pills-reach">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.brand_reach') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.enabled_markets') }}</label>
                                                <x-tom-select-market
                                                    name="market_ids"
                                                    multiple
                                                    required
                                                    :selected="old('market_ids', $selectedMarketIds ?? [])"
                                                />
                                                <small class="text-muted">
                                                    {{ __('labels.store_markets_hint') }}
                                                </small>
                                                @error('market_ids')
                                                    <div class="text-danger mt-1">{{ $message }}</div>
                                                @enderror
                                                @error('market_ids.*')
                                                    <div class="text-danger mt-1">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            </div>
                                    </div>

                                    <!-- Business Documents -->
                                    <div class="card mb-4" id="pills-documents">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.business_documents') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label required">
                                                    {{ __('labels.tax_name') }}
                                                    <x-form-info-tooltip :title="__('messages.store_tax_name_info_message')" />
                                                </label>
                                                <input type="text" class="form-control" name="tax_name"
                                                       placeholder="{{ __('labels.enter_tax_name') }}"
                                                       value="{{ old('tax_name', $store->tax_name ?? '') }}"/>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">
                                                    {{ __('labels.tax_number') }}
                                                    <x-form-info-tooltip :title="__('messages.store_tax_number_info_message')" />
                                                </label>
                                                <input type="text" class="form-control" name="tax_number"
                                                       placeholder="{{ __('labels.enter_tax_number') }}"
                                                       value="{{ old('tax_number', $store->tax_number ?? '') }}"/>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card mb-4" id="pills-seo">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.seo_information') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <x-seo-fields :metadata="$store->metadata ?? null"/>
                                        </div>
                                    </div>

                                    <div class="card-footer text-end">
                                    <div class="d-flex">
                                            <button type="submit"
                                                    class="btn btn-primary ms-auto">{{empty($store) ? "Submit" : "Update"}}</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        #map {
            height: 400px;
            width: 100%;
            margin-bottom: 20px;
        }

        .form-row {
            margin-bottom: 8px;
        }

        /* Additional styles for the city selector inside map */
        #city-selector-card gmp-place-autocomplete {
            width: 100%;
        }

        #infowindow-content .title {
            font-weight: bold;
        }

        #map #infowindow-content {
            display: inline;
        }
    </style>
@endsection

@if (empty($store))
    @push('scripts')
        <script src="{{ hyperAsset('assets/js/store-locations-form.js') }}"></script>
        <script src="{{ hyperAsset('assets/js/shiprocket-address-confirm.js') }}"></script>
    @endpush
@endif
