@extends('layouts.admin.app', ['page' => $menuAdmin['seller_management']['active'] ?? "", 'sub_page' => $menuAdmin['seller_management']['route']['add_sellers']['sub_active'] ?? "" ])

@section('title', __('labels.add_seller'))

@section('header_data')
    @php
        $page_title = __('labels.sellers');
        $page_pretitle = __('labels.' . (!empty($seller)) ? __('labels.edit_seller') : __('labels.add_seller'));
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.sellers'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-wrapper">
        @include('components.page_header', ['title' => (!empty($seller)) ? __('labels.edit_seller') : __('labels.add_seller'), 'step' => 2])

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
                                <a class="nav-link" href="#pills-location">{{ __('labels.location_details') }}</a>
                                <a class="nav-link" href="#pills-bank">{{ __('labels.bank_details') }}</a>
                                <a class="nav-link" href="#pills-documents">{{ __('labels.business_documents') }}</a>
                                <a class="nav-link" href="#pills-status">{{ __('labels.status') }}</a>
                            </nav>
                        </div>
                    </div>
                    <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                        <div class="row row-cards">
                            <div class="col-12">
                                <form
                                    action="{{ (!empty($seller)) ? route('admin.sellers.update',['id' => $seller->id]) : route('admin.sellers.store') }}"
                                    class="form-submit" method="post" novalidate data-toast-only-validation data-country-fields-url="{{ route('admin.sellers.dynamic-fields.country', ['countryIso2' => '__COUNTRY__']) }}" data-country-field-values='@json($dynamicFieldValues ?? [])'>
                                    @csrf
                                    <!-- Basic Details -->
                                    <div class="card mb-4" id="pills-basic">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.basic_details') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.seller_name') }}</label>
                                                <input type="text"
                                                       class="form-control @error('name') is-invalid @enderror"
                                                       name="name"
                                                       placeholder="{{ __('labels.enter_seller_name') }}"
                                                       value="{{ old('name', $seller->user->name ?? '') }}"
                                                />
                                                @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.country') }}</label>
                                                <select type="text"
                                                        class="form-select @error('country') is-invalid @enderror"
                                                        name="country"
                                                        id="select-countries" {{!empty($seller) && $seller->country ? "disabled" : ""}}>
                                                </select>
                                                <input type="hidden" id="selected-country"
                                                       value="{{!empty($seller) && $seller->country ? $seller->country : ""}}"/>
                                                @error('country')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.mobile') }}</label>
                                                <input type="tel"
                                                       class="form-control @error('mobile') is-invalid @enderror"
                                                       id="mobile" name="mobile"
                                                       placeholder="{{ __('labels.enter_mobile_number') }}"
                                                       value="{{ old('mobile', $seller->user->mobile ?? '') }}" {{!empty($seller) ? "readonly disabled" : ""}}/>
                                                @error('mobile')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label required">{{ __('labels.email') }}</label>
                                                        <input type="text"
                                                               class="form-control @error('email') is-invalid @enderror"
                                                               name="email"
                                                               placeholder="{{ __('labels.enter_email_address') }}"
                                                               value="{{ old('email', $seller->user->email ?? '') }}"
                                                            {{!empty($seller) ? "readonly disabled" : ""}}/>
                                                        @error('email')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                                @if(empty($seller))
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label
                                                                class="form-label required">{{ __('labels.password') }}</label>

                                                            <div class="input-group mb-2">
                                                                <input type="password"
                                                                       class="form-control @error('password') is-invalid @enderror"
                                                                       name="password"
                                                                       placeholder="{{ __('labels.enter_password') }}"
                                                                       autocomplete="off" 
                                                                       id="seller-password"/>
                                                                <span class="input-group-text">
                                                                    <a href="#" class="link-secondary"
                                                                       title="Show password" id="password-toggle"
                                                                       data-bs-toggle="tooltip"
                                                                       data-panel-password-toggle="seller-password">
                                                                        <!-- Download SVG icon from http://tabler.io/icons/icon/eye -->
                                                                        Show
                                                                      </a>
                                                                </span>

                                                                <button class="btn password-button" type="button">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                                         height="24"
                                                                         viewBox="0 0 24 24" fill="none"
                                                                         stroke="currentColor"
                                                                         stroke-width="2" stroke-linecap="round"
                                                                         stroke-linejoin="round"
                                                                         class="icon icon-tabler icons-tabler-outline icon-tabler-sparkles m-0">
                                                                        <path stroke="none" d="M0 0h24v24H0z"
                                                                              fill="none"/>
                                                                        <path
                                                                            d="M16 18a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm0 -12a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm-7 12a6 6 0 0 1 6 -6a6 6 0 0 1 -6 -6a6 6 0 0 1 -6 6a6 6 0 0 1 6 6z"/>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                            @error('password')
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card mb-4" id="pills-location">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.location_details') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.address') }}</label>
                                                <input type="text"
                                                       class="form-control @error('address') is-invalid @enderror"
                                                       name="address"
                                                       placeholder="{{ __('labels.enter_address') }}"
                                                       value="{{ old('address', $seller->address ?? '') }}"/>
                                                @error('address')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label required">{{ __('labels.city') }}</label>
                                                        <input type="text"
                                                               class="form-control @error('city') is-invalid @enderror"
                                                               name="city"
                                                               placeholder="{{ __('labels.enter_city') }}"
                                                               value="{{ old('city', $seller->city ?? '') }}"/>
                                                        @error('city')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label required">{{ __('labels.landmark') }}</label>
                                                        <input type="text"
                                                               class="form-control @error('landmark') is-invalid @enderror"
                                                               name="landmark"
                                                               placeholder="{{ __('labels.enter_landmark') }}"
                                                               value="{{ old('landmark', $seller->landmark ?? '') }}"
                                                        />
                                                        @error('landmark')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label required">{{ __('labels.state') }}</label>
                                                        <input type="text"
                                                               class="form-control @error('state') is-invalid @enderror"
                                                               name="state"
                                                               placeholder="{{ __('labels.enter_state') }}"
                                                               value="{{ old('state', $seller->state ?? '') }}"
                                                        />
                                                        @error('state')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label required">{{ __('labels.zipcode') }}</label>
                                                        <input type="text"
                                                               class="form-control @error('zipcode') is-invalid @enderror"
                                                               name="zipcode"
                                                               placeholder="{{ __('labels.enter_zipcode') }}"
                                                               value="{{ old('zipcode', $seller->zipcode ?? '') }}"
                                                        />
                                                        @error('zipcode')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card mb-4" id="pills-bank">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.bank_details') }}</h4>
                                            @if($canManageFields)<div class="card-actions"><a class="btn btn-outline-primary btn-sm" data-country-field-manager data-section="bank_details" href="{{ route('admin.sellers.dynamic-fields.index', ['section' => 'bank_details']) }}">{{ __('Manage fields') }}</a></div>@endif
                                        </div>
                                        <div class="card-body">
                                            <div class="js-country-dynamic-fields" data-section="bank_details">
                                                @include('admin.sellers.partials.dynamic-fields-group', ['fields' => $dynamicFieldGroups['bank_details'] ?? collect(), 'section' => 'bank_details', 'values' => $dynamicFieldValues ?? []])
                                            </div>
                                        </div>
                                    </div>                                    <div class="card mb-4" id="pills-documents">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.business_documents') }}</h4>
                                            @if($canManageFields)<div class="card-actions"><a class="btn btn-outline-primary btn-sm" data-country-field-manager data-section="business_documents" href="{{ route('admin.sellers.dynamic-fields.index', ['section' => 'business_documents']) }}">{{ __('Manage fields') }}</a></div>@endif
                                        </div>
                                        <div class="card-body">
                                            <div class="js-country-dynamic-fields" data-section="business_documents">
                                                @include('admin.sellers.partials.dynamic-fields-group', ['fields' => $dynamicFieldGroups['business_documents'] ?? collect(), 'section' => 'business_documents', 'values' => $dynamicFieldValues ?? []])
                                            </div>
                                        </div>
                                    </div>                                    <div class="card mb-4" id="pills-status">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.status') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.verification_status') }}</label>
                                                <select
                                                    class="form-select @error('verification_status') is-invalid @enderror"
                                                    name="verification_status">
                                                    <option
                                                        value="approved" {{ old('verification_status', $seller->verification_status ?? '') == 'approved' ? 'selected' : '' }}>
                                                        {{ __('labels.approved') }}
                                                    </option>
                                                    <option
                                                        value="not_approved" {{ old('verification_status', $seller->verification_status ?? '') == 'not_approved' ? 'selected' : '' }}>
                                                        {{ __('labels.not_approved') }}
                                                    </option>
                                                </select>
                                                @error('verification_status')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.visibility_status') }}</label>
                                                <select
                                                    class="form-select @error('visibility_status') is-invalid @enderror"
                                                    name="visibility_status">
                                                    <option
                                                        value="visible" {{ old('visibility_status', $seller->visibility_status ?? '') == 'visible' ? 'selected' : '' }}>
                                                        {{ __('labels.visible') }}
                                                    </option>
                                                    <option
                                                        value="draft" {{ old('visibility_status', $seller->visibility_status ?? '') == 'draft' ? 'selected' : '' }}>
                                                        {{ __('labels.draft') }}
                                                    </option>
                                                </select>
                                                @error('visibility_status')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer text-end">
                                        <div class="d-flex">
                                            <button type="submit"
                                                    class="btn btn-primary ms-auto">{{(!empty($seller)) ? __('labels.edit_seller') : __('labels.add_seller')}}</button>
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
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/dynamic-seller-country-fields.js') }}"></script>
@endpush
