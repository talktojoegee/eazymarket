@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => $menuAdmin['settings']['route']['system']['sub_active'] ?? ""])

@section('title', __('labels.system_settings'))

@section('header_data')
    @php
        $page_title = __('labels.system_settings');
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.system_settings'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.system_settings') }}</h2>
                <x-breadcrumb :items="$breadcrumbs" />
            </div>
        </div>
    </div>
    <!-- BEGIN PAGE BODY -->
    <div class="page-body">
        <div class="container-xl">
            <div class="row g-5">
                <div class="col-sm-2 d-none d-lg-block">
                    <div class="sticky-top">
                        <h3>{{ __('labels.menu') }}</h3>
                        <nav class="nav nav-vertical nav-pills" id="pills">
                            <a class="nav-link" href="#pills-general">{{ __('labels.general') }}</a>
                            <a class="nav-link" href="#pills-support">{{ __('labels.support_information') }}</a>
                            <a class="nav-link" href="#pills-cart">{{ __('labels.cart_inventory_settings') }}</a>
                            <a class="nav-link" href="#pills-product-approval">{{ __('labels.product_approval_settings') }}</a>
                            <a class="nav-link" href="#pills-wallet">{{ __('labels.wallet_settings') }}</a>
                            <a class="nav-link" href="#pills-maintenance">{{ __('labels.maintenance_mode') }}</a>
                            <a class="nav-link" href="#pills-demomode">{{ __('labels.demo_mode') }}</a>
                            <a class="nav-link" href="#pills-referral">{{ __('labels.referral_earn_program') }}</a>
                            <a class="nav-link" href="#pills-order-number">{{ __('labels.order_number_settings') }}</a>
                        </nav>
                    </div>
                </div>
                <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                    <div class="row row-cards">
                        <div class="col-12">
                            <form action="{{route('admin.settings.store')}}" class="form-submit" method="post"
                                enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="type" value="system">
                                <div class="card mb-4" id="pills-general">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.general') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.app_name') }}</label>
                                            <input type="text" class="form-control" name="appName"
                                                placeholder="{{ __('labels.app_name_placeholder') }}"
                                                value="{{$settings['appName'] ?? ''}}" />
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.system_timezone') }}</label>
                                            @php
                                                $currentTimezone = $settings['systemTimezone'] ?? '';
                                                $timezoneIdentifiers = \DateTimeZone::listIdentifiers();
                                            @endphp
                                            <select class="form-select" name="systemTimezone" id="select-timezone"
                                                placeholder="{{ __('labels.system_timezone_placeholder') }}">
                                                <option value=""></option>
                                                @foreach($timezoneIdentifiers as $timezone)
                                                    <option value="{{ $timezone }}" {{ $currentTimezone === $timezone ? 'selected' : '' }}>
                                                        {{ $timezone }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.copyright_details') }}</label>
                                            <input type="text" class="form-control" name="copyrightDetails"
                                                placeholder="{{ __('labels.copyright_details_placeholder') }}"
                                                value="{{$settings['copyrightDetails'] ?? ''}}" />
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.currency') }}</label>
                                            <x-tom-select-currency
                                                id="select-currency"
                                                name="currency"
                                                :selected="$settings['currency'] ?? null"
                                                required
                                            />
                                            <div class="alert alert-info alert-dismissible mt-2" role="alert">
                                                <div class="alert-icon">
                                                    <!-- Download SVG icon from http://tabler.io/icons/icon/info-circle -->
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon alert-icon icon-2">
                                                        <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path>
                                                        <path d="M12 9h.01"></path>
                                                        <path d="M11 12h1v4h1"></path>
                                                    </svg>
                                                </div>
                                                {{ __('labels.currency_change_warning') }}
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.default_country') }}</label>
                                            <x-tom-select-country
                                                id="select-default-country"
                                                name="defaultISO2"
                                                :selected="$settings['defaultISO2'] ?? null"
                                                required
                                            />
                                            <div class="form-text">{{ __('labels.default_country_help') }}</div>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <div class="form-label required">{{ __('labels.logo') }}</div>
                                                    <input type="file" class="form-control" name="logo"
                                                        data-image-url="{{ $settings['logo'] }}" />
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <div class="form-label">{{ __('labels.dark_logo') }}</div>
                                                    <input type="file" class="form-control" name="darkLogo"
                                                        data-image-url="{{ $settings['darkLogo'] ?? '' }}" />
                                                    <div class="form-text">{{ __('labels.dark_logo_help') }}</div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <div class="form-label required">{{ __('labels.favicon') }}</div>
                                                    <input type="file" name="favicon"
                                                        data-image-url="{{ $settings['favicon'] }}" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Company Address</label>
                                            <textarea class="form-control" name="companyAddress" rows="3"
                                                placeholder="Enter company address shown on invoice">{{ $settings['companyAddress'] ?? '' }}</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Admin Signature (Authorized Signatory)</label>
                                            <input type="file" name="adminSignature"
                                                data-image-url="{{ $settings['adminSignature'] ?? '' }}" />
                                            <small class="form-hint">Upload a signature image to display on
                                                invoices.</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4" id="pills-support">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.support_information') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.seller_support_email') }}</label>
                                            <div>
                                                <input type="email" class="form-control" name="sellerSupportEmail"
                                                    aria-describedby="emailHelp"
                                                    placeholder="{{ __('labels.seller_support_email_placeholder') }}"
                                                    value="{{$settings['sellerSupportEmail'] ?? ''}}" />
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.seller_support_number') }}</label>
                                            <div>
                                                <input type="tel" class="form-control" name="sellerSupportNumber"
                                                    aria-describedby="numberHelp"
                                                    placeholder="{{ __('labels.seller_support_number_placeholder') }}"
                                                    value="{{$settings['sellerSupportNumber'] ?? ''}}" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4" id="pills-cart">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.cart_inventory_settings') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label
                                                class="form-label required">{{ __('labels.select_checkout_type') }}</label>
                                            <div>
                                                <label class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="checkoutType"
                                                        value="single_store" {{!empty($settings['checkoutType']) && $settings['checkoutType'] === 'single_store' ? 'checked' : ''}}>
                                                    <span class="form-check-label">{{ __('labels.single_store') }}</span>
                                                </label>
                                                <label class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="checkoutType"
                                                        value="multi_store" {{!empty($settings['checkoutType']) && $settings['checkoutType'] === 'multi_store' ? 'checked' : ''}}>
                                                    <span class="form-check-label">{{ __('labels.multi_store') }}</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label required">{{ __('labels.minimum_cart_amount') }}</label>
                                            <input type="number" step="1" min="0" class="form-control"
                                                name="minimumCartAmount"
                                                placeholder="{{ __('labels.minimum_cart_amount_placeholder') }}"
                                                value="{{$settings['minimumCartAmount'] ?? ''}}" />
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label required">{{ __('labels.maximum_items_allowed_in_cart') }}</label>
                                            <input type="number" min="1" class="form-control"
                                                name="maximumItemsAllowedInCart"
                                                placeholder="{{ __('labels.maximum_items_allowed_in_cart_placeholder') }}"
                                                value="{{$settings['maximumItemsAllowedInCart'] ?? ''}}" />
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.order_auto_archive_days') }}
                                                <x-form-info-tooltip :title="__('messages.order_auto_archive_days_info')"/>
                                            </label>
                                            <input type="number" min="1" max="365" class="form-control"
                                                name="orderAutoArchiveDays"
                                                placeholder="{{ __('labels.order_auto_archive_days_placeholder') }}"
                                                value="{{$settings['orderAutoArchiveDays'] ?? 10}}" />
                                        </div>
                                        <div class="mb-3">
                                            <label class="row">
                                                <span class="col">{{ __('labels.auto_settle_enabled') }}
                                                    <x-form-info-tooltip :title="__('messages.auto_settle_enabled_info')"/>
                                                </span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                            name="autoSettleEnabled" value="1" {{ isset($settings['autoSettleEnabled']) && $settings['autoSettleEnabled'] ? 'checked' : '' }} />
                                                    </label>
                                                </span>
                                            </label>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.settlement_clearing_days') }}
                                                <x-form-info-tooltip :title="__('messages.settlement_clearing_days_info')"/>
                                            </label>
                                            <input type="number" min="0" max="365" class="form-control"
                                                name="settlementClearingDays"
                                                placeholder="{{ __('labels.settlement_clearing_days_placeholder') }}"
                                                value="{{$settings['settlementClearingDays'] ?? 0}}" />
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.low_stock_limit') }}</label>
                                            <input type="number" min="0" class="form-control" name="lowStockLimit"
                                                placeholder="{{ __('labels.low_stock_limit_placeholder') }}"
                                                value="{{$settings['lowStockLimit'] ?? ''}}" />
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4" id="pills-wallet">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.wallet_settings') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.welcome_wallet_balance_amount') }}</label>
                                            <input type="number" step="0.01" min="0" class="form-control"
                                                name="welcomeWalletBalanceAmount"
                                                placeholder="{{ __('labels.welcome_wallet_balance_amount_placeholder') }}"
                                                value="{{$settings['welcomeWalletBalanceAmount'] ?? '0'}}" />
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required" for="wallet-policy">
                                                {{ __('labels.wallet_policy_document') }}
                                            </label>
                                            <textarea class="hugerte-mytextarea" id="wallet-policy" name="walletPolicy"
                                                rows="8">{{ $settings['walletPolicy'] ?? \App\Types\Settings\SystemSettingType::DEFAULT_WALLET_POLICY }}</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required" for="wallet-recharge-preset-amounts">
                                                {{ __('labels.wallet_recharge_preset_amounts') }}
                                            </label>
                                            @php
                                                $walletRechargePresetAmounts = $settings['walletRechargePresetAmounts'] ?? \App\Types\Settings\SystemSettingType::DEFAULT_WALLET_RECHARGE_PRESET_AMOUNTS;
                                            @endphp
                                            <select class="form-select" id="wallet-recharge-preset-amounts"
                                                name="walletRechargePresetAmounts[]" multiple
                                                data-placeholder="{{ __('labels.wallet_recharge_preset_amounts_placeholder') }}">
                                                @foreach($walletRechargePresetAmounts as $amount)
                                                    <option value="{{ $amount }}" selected>{{ $amount }}</option>
                                                @endforeach
                                            </select>
                                            <div class="form-hint">{{ __('labels.wallet_recharge_preset_amounts_hint') }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4" id="pills-product-approval">
                                    <div class="card-header">
                                        <div>
                                            <h4 class="card-title">{{ __('labels.product_approval_settings') }}</h4>
                                            <div class="card-subtitle">{{ __('labels.product_approval_settings_hint') }}</div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        @php
                                            $selectedApprovalFields = $settings['productApprovalCriticalFields'] ?? \App\Types\Settings\SystemSettingType::DEFAULT_PRODUCT_APPROVAL_CRITICAL_FIELDS;
                                        @endphp
                                        <div class="row g-3">
                                            @foreach(($productApprovalFieldOptions ?? []) as $field => $label)
                                                <div class="col-sm-6 col-xl-4">
                                                    <label class="form-check form-check-card h-100 m-0">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="productApprovalCriticalFields[]" value="{{ $field }}"
                                                            @checked(in_array($field, $selectedApprovalFields, true))>
                                                        <span class="form-check-label">
                                                            <span class="form-check-description fw-medium">{{ $label }}</span>
                                                        </span>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="alert alert-info mt-3 mb-0" role="alert">
                                            <i class="ti ti-info-circle me-1"></i>{{ __('labels.product_approval_settings_notice') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4" id="pills-maintenance">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.maintenance_mode') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="row">
                                                <span class="col">{{ __('labels.seller_app_maintenance_mode') }}</span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                            name="sellerAppMaintenanceMode" value="1" {{ isset($settings['sellerAppMaintenanceMode']) && $settings['sellerAppMaintenanceMode'] ? 'checked' : '' }} />
                                                    </label>
                                                </span>
                                            </label>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.seller_app_maintenance_message') }}</label>
                                            <input type="text" class="form-control" name="sellerAppMaintenanceMessage"
                                                placeholder="{{ __('labels.seller_app_maintenance_message_placeholder') }}"
                                                value="{{$settings['sellerAppMaintenanceMessage'] ?? ''}}" />
                                        </div>
                                        <div class="mb-3">
                                            <label class="row">
                                                <span class="col">{{ __('labels.web_maintenance_mode') }}</span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                            name="webMaintenanceMode" value="1" {{ isset($settings['webMaintenanceMode']) && $settings['webMaintenanceMode'] ? 'checked' : '' }} />
                                                    </label>
                                                </span>
                                            </label>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.web_maintenance_message') }}</label>
                                            <input type="text" class="form-control" name="webMaintenanceMessage"
                                                placeholder="{{ __('labels.web_maintenance_message_placeholder') }}"
                                                value="{{$settings['webMaintenanceMessage'] ?? ''}}" />
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4" id="pills-demomode">

                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.demo_mode') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="row">
                                                <span class="col">{{ __('labels.enable_demo_mode') }}</span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox" name="demoMode"
                                                            value="1" {{ isset($settings['demoMode']) && $settings['demoMode'] ? 'checked' : '' }} />
                                                    </label>
                                                </span>
                                            </label>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.admin_demo_mode_message') }}</label>
                                            <input type="text" class="form-control" name="adminDemoModeMessage"
                                                placeholder="{{ __('labels.admin_demo_mode_message_placeholder') }}"
                                                value="{{$settings['adminDemoModeMessage'] ?? ''}}" />
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.seller_demo_mode_message') }}</label>
                                            <input type="text" class="form-control" name="sellerDemoModeMessage"
                                                placeholder="{{ __('labels.seller_demo_mode_message_placeholder') }}"
                                                value="{{$settings['sellerDemoModeMessage'] ?? ''}}" />
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.customer_demo_mode_message') }}</label>
                                            <input type="text" class="form-control" name="customerDemoModeMessage"
                                                placeholder="{{ __('labels.customer_demo_mode_message_placeholder') }}"
                                                value="{{$settings['customerDemoModeMessage'] ?? ''}}" />
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.customer_location_demo_mode_message') }}</label>
                                            <input type="text" class="form-control" name="customerLocationDemoModeMessage"
                                                placeholder="{{ __('labels.customer_location_demo_mode_message_placeholder') }}"
                                                value="{{$settings['customerLocationDemoModeMessage'] ?? ''}}" />
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.delivery_boy_demo_mode_message') }}</label>
                                            <input type="text" class="form-control" name="deliveryBoyDemoModeMessage"
                                                placeholder="{{ __('labels.delivery_boy_demo_mode_message_placeholder') }}"
                                                value="{{$settings['deliveryBoyDemoModeMessage'] ?? ''}}" />
                                        </div>
                                    </div>
                                </div>

                                <div class="card mb-4" id="pills-referral">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.referral_earn_program') }}</h4>
                                        <button type="button" class="alert-icon btn ms-2 p-2 rounded-5"
                                            data-bs-toggle="tooltip" data-bs-placement="right"
                                            data-bs-title="{{ __('labels.refer_earn_hint') }}">
                                            !
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="row">
                                                <span class="col">{{ __('labels.enable_referral_program') }}</span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox" value="1"
                                                            name="referEarnStatus" role="switch" id="referEarnToggle" {{ isset($settings['referEarnStatus'])
        && $settings['referEarnStatus'] ? 'checked' : '' }} />
                                                    </label>
                                                </span>
                                            </label>
                                        </div>
                                        <div id="referEarnFields">
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('labels.user_bonus_method') }}</label>
                                                <select class="form-select" name="referEarnMethodUser">
                                                    <option value="fixed" {{
        isset($settings['referEarnMethodUser']) &&
        $settings['referEarnMethodUser'] === 'fixed' ? 'selected' : ''
                                                                                                            }}>
                                                        {{ __('labels.fixed') }}
                                                    </option>
                                                    <option value="percentage" {{
        isset($settings['referEarnMethodUser']) &&
        $settings['referEarnMethodUser'] === 'percentage' ? 'selected'
        : '' }}>
                                                        {{ __('labels.percentage') }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label" for="referEarnBonusUser">
                                                    {{ __('labels.user_bonus') }}
                                                    <small
                                                        class="text-muted d-block font-weight-normal mt-1">{{ __('labels.user_bonus_hint') }}</small>
                                                </label>
                                                <input type="number" class="form-control" name="referEarnBonusUser"
                                                    placeholder="{{ __('labels.user_bonus_placeholder') }}"
                                                    value="{{$settings['referEarnBonusUser'] ?? ''}}" />
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('labels.max_bonus_amount_user') }}</label>
                                                <input type="number" min="0" step="0.01" class="form-control"
                                                    name="referEarnMaximumBonusAmountUser"
                                                    placeholder="{{ __('labels.max_bonus_amount_user_placeholder') }}"
                                                    value="{{$settings['referEarnMaximumBonusAmountUser'] ?? ''}}" />
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">{{
        __('labels.referral_bonus_method') }}</label>
                                                <select class="form-select" name="referEarnMethodReferral">
                                                    <option value="fixed" {{
        isset($settings['referEarnMethodReferral']) &&
        $settings['referEarnMethodReferral'] === 'fixed' ? 'selected' : ''
                                                                                                            }}>
                                                        {{ __('labels.fixed') }}
                                                    </option>
                                                    <option value="percentage" {{
        isset($settings['referEarnMethodReferral']) &&
        $settings['referEarnMethodReferral'] === 'percentage'
        ? 'selected' : '' }}>
                                                        {{ __('labels.percentage') }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label" for="referEarnBonusReferral">
                                                    {{ __('labels.referral_bonus') }}
                                                    <small
                                                        class="text-muted d-block font-weight-normal mt-1">{{ __('labels.referral_bonus_hint') }}</small>
                                                </label>
                                                <input type="number" class="form-control" name="referEarnBonusReferral"
                                                    placeholder="{{ __('labels.referral_bonus_placeholder') }}"
                                                    value="{{$settings['referEarnBonusReferral'] ?? ''}}" />
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">{{
        __('labels.max_bonus_amount_referral') }}</label>
                                                <input type="number" min="0" step="0.01" class="form-control"
                                                    name="referEarnMaximumBonusAmountReferral"
                                                    placeholder="{{ __('labels.max_bonus_amount_referral_placeholder') }}"
                                                    value="{{$settings['referEarnMaximumBonusAmountReferral'] ?? ''}}" />
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">{{
        __('labels.minimum_order_amount_for_bonus') }}</label>
                                                <input type="number" min="0" step="0.01" class="form-control"
                                                    name="referEarnMinimumOrderAmount"
                                                    placeholder="{{ __('labels.minimum_order_amount_for_bonus_placeholder') }}"
                                                    value="{{$settings['referEarnMinimumOrderAmount'] ?? ''}}" />
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">{{
        __('labels.number_of_times_bonus_applicable') }} <span
                                                        class="text-body-tertiary">({{ __('labels.max_bonus_applicable_hint') }})</span>
                                                </label>
                                                <input type="number" min="0" class="form-control"
                                                    name="referEarnNumberOfTimesBonus"
                                                    placeholder="{{ __('labels.number_of_times_bonus_applicable_placeholder') }}"
                                                    value="{{$settings['referEarnNumberOfTimesBonus'] ?? ''}}" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4" id="pills-order-number">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.order_number_settings') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-12 col-md-6">
                                                <label class="form-label" for="orderNumberPrefix">{{ __('labels.order_number_prefix') }}</label>
                                                <input id="orderNumberPrefix" type="text" class="form-control" name="orderNumberPrefix" maxlength="20" value="{{ old('orderNumberPrefix', $settings['orderNumberPrefix'] ?? 'ORD-') }}" aria-describedby="orderNumberPrefixHelp">
                                                <div id="orderNumberPrefixHelp" class="form-hint">{{ __('labels.order_number_prefix_hint') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-end">
                                    <div class="d-flex">
                                        @can('updateSetting', [\App\Models\Setting::class, 'system'])
                                            <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-secondary">{{ __('labels.cancel') }}</a>
                                            <button type="submit"
                                                class="btn btn-primary ms-auto">{{ __('labels.submit') }}</button>
                                        @endcan
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END PAGE BODY -->
    <script>
        const toggle = document.getElementById('referEarnToggle');
        const fields = document.getElementById('referEarnFields');
        const toggleFields = () => {
            fields.style.display = toggle.checked ? 'block' : 'none';
        };
        toggle.addEventListener('change', toggleFields);
        toggleFields();
    </script>
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/admin-system-settings.js') }}" defer></script>
@endpush
