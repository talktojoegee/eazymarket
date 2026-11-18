@php use App\Enums\Payment\FlutterwaveCurrencyCodeEnum;use App\Enums\Payment\MercadoPagoCurrencyCodeEnum;use App\Enums\Payment\PaymentTypeEnum;use App\Enums\Payment\PaystackCurrencyCodeEnum;use App\Enums\Payment\XenditCountryEnum;use App\Enums\Payment\XenditCurrencyCodeEnum; @endphp
@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => $menuAdmin['settings']['route']['payment']['sub_active'] ?? "" ])

@section('title', __('labels.payment_settings'))

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/payment-settings.css') }}">
@endpush

@section('header_data')
    @php
        $page_title = __('labels.payment_settings');
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.payment_settings'), 'url' => null],
    ];
    $paymentGateways = [
        'stripe' => [
            'anchor' => 'pills-stripe',
            'name' => __('labels.stripe_payment'),
            'logo' => asset('assets/Payments/stripe.png'),
            'enabled' => !empty($settings['stripePayment']),
            'toggle' => 'stripePayment',
            'mode' => $settings['stripePaymentMode'] ?? '',
            'mode_field' => 'stripePaymentMode',
        ],
        'razorpay' => [
            'anchor' => 'pills-razorpay',
            'name' => __('labels.razorpay_payment'),
            'logo' => asset('assets/Payments/razorpay.png'),
            'enabled' => !empty($settings['razorpayPayment']),
            'toggle' => 'razorpayPayment',
            'mode' => $settings['razorpayPaymentMode'] ?? '',
            'mode_field' => 'razorpayPaymentMode',
        ],
        'paystack' => [
            'anchor' => 'pills-paystack',
            'name' => __('labels.paystack_payment'),
            'logo' => asset('assets/Payments/paystack.png'),
            'enabled' => !empty($settings['paystackPayment']),
            'toggle' => 'paystackPayment',
            'mode' => $settings['paystackPaymentMode'] ?? '',
            'mode_field' => 'paystackPaymentMode',
        ],
        'flutterwave' => [
            'anchor' => 'pills-flutterwave',
            'name' => __('labels.flutterwave_payment'),
            'logo' => asset('assets/Payments/flutterwave.png'),
            'enabled' => !empty($settings['flutterwavePayment']),
            'toggle' => 'flutterwavePayment',
            'mode' => $settings['flutterwavePaymentMode'] ?? '',
            'mode_field' => 'flutterwavePaymentMode',
        ],
        'xendit' => [
            'anchor' => 'pills-xendit',
            'name' => __('labels.xendit_payment'),
            'logo' => asset('assets/Payments/xendit.png'),
            'enabled' => !empty($settings['xenditPayment']),
            'toggle' => 'xenditPayment',
            'mode' => $settings['xenditPaymentMode'] ?? '',
            'mode_field' => 'xenditPaymentMode',
        ],
        'mercado_pago' => [
            'anchor' => 'pills-mercado-pago',
            'name' => __('labels.mercado_pago_payment'),
            'logo' => asset('assets/Payments/mercado-pago.png'),
            'enabled' => !empty($settings['mercadoPagoPayment']),
            'toggle' => 'mercadoPagoPayment',
            'mode' => $settings['mercadoPagoPaymentMode'] ?? '',
            'mode_field' => 'mercadoPagoPaymentMode',
        ],
        'wallet' => [
            'anchor' => 'pills-wallet',
            'name' => __('labels.wallet_payment'),
            'icon' => 'ti-wallet',
            'enabled' => !empty($settings['wallet']),
            'toggle' => 'walletPayment',
        ],
        'cod' => [
            'anchor' => 'pills-cod',
            'name' => __('labels.cash_on_delivery'),
            'logo' => asset('assets/Payments/cod.png'),
            'enabled' => !empty($settings['cod']),
            'toggle' => PaymentTypeEnum::COD(),
        ],
    ];
    $enabledGatewayCount = collect($paymentGateways)->where('enabled', true)->count();
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.payment_settings') }}</h2>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
        </div>
    </div>
    <!-- BEGIN PAGE BODY -->
    <div class="page-body">
        <div class="container-xl">
            <div class="row g-5">
                <div class="col-lg-3 d-none d-lg-block">
                    <div class="sticky-top payment-settings-sidebar">
                        <div class="payment-settings-sidebar-title">{{ __('labels.payment_settings') }}</div>
                        <nav class="nav nav-vertical nav-pills payment-settings-nav" id="pills">
                            @foreach($paymentGateways as $gateway)
                                <a class="nav-link" href="#{{ $gateway['anchor'] }}">
                                    <span class="payment-nav-logo">
                                        @if(!empty($gateway['logo']))
                                            <img src="{{ $gateway['logo'] }}" alt="" loading="lazy">
                                        @else
                                            <i class="ti {{ $gateway['icon'] ?? 'ti-credit-card' }}"></i>
                                        @endif
                                    </span>
                                    <span class="payment-nav-name">{{ $gateway['name'] }}</span>
                                    <span class="payment-status-dot {{ $gateway['enabled'] ? 'is-enabled' : '' }}"
                                          data-payment-status-for="{{ $gateway['toggle'] }}"></span>
                                </a>
                            @endforeach
                        </nav>
                    </div>
                </div>
                <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                    <div class="row row-cards">
                        <div class="col-12">
                            <div class="payment-settings-overview mb-4">
                                <div>
                                    <div class="payment-settings-overview-icon"><i class="ti ti-credit-card-pay"></i></div>
                                    <div>
                                        <h3>{{ __('labels.payment_settings') }}</h3>
                                        <p><strong data-enabled-payment-count>{{ $enabledGatewayCount }}</strong> / {{ count($paymentGateways) }} {{ __('labels.enabled') }}</p>
                                    </div>
                                </div>
                                <div class="payment-settings-overview-logos" aria-hidden="true">
                                    @foreach($paymentGateways as $gateway)
                                        <span class="payment-overview-logo {{ $gateway['enabled'] ? 'is-enabled' : '' }}"
                                              data-payment-overview-for="{{ $gateway['toggle'] }}">
                                            @if(!empty($gateway['logo']))
                                                <img src="{{ $gateway['logo'] }}" alt="" loading="lazy">
                                            @else
                                                <i class="ti {{ $gateway['icon'] ?? 'ti-credit-card' }}"></i>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post">
                                @csrf
                                <input type="hidden" name="type" value="payment">
                                <div class="card mb-4 payment-gateway-card {{ $paymentGateways['stripe']['enabled'] ? 'is-enabled' : '' }}" id="pills-stripe" data-payment-card data-toggle-name="stripePayment">
                                    @include('admin.settings.partials.payment-gateway-header', ['gateway' => $paymentGateways['stripe']])
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="row">
                                                <span class="col">{{ __('labels.enable_stripe_payment') }}</span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="stripePayment" value="1" {{ isset($settings['stripePayment']) && $settings['stripePayment'] ? 'checked' : '' }} />
                                                    </label>
                                                </span>
                                            </label>
                                        </div>
                                        <div id="stripeFields"
                                             style="{{ isset($settings['stripePayment']) && $settings['stripePayment'] ? 'display: block;' : 'display: none;' }}">
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.stripe_payment_mode') }}</label>
                                                <select class="form-select" name="stripePaymentMode">
                                                    <option value=""
                                                            disabled {{ !isset($settings['stripePaymentMode']) ? 'selected' : '' }}>{{ __('labels.stripe_payment_mode_placeholder') }}</option>
                                                    <option
                                                        value="test" {{ isset($settings['stripePaymentMode']) && $settings['stripePaymentMode'] === 'test' ? 'selected' : '' }}>
                                                        Test
                                                    </option>
                                                    <option
                                                        value="live" {{ isset($settings['stripePaymentMode']) && $settings['stripePaymentMode'] === 'live' ? 'selected' : '' }}>
                                                        Live
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.stripe_publishable_key') }}</label>
                                                <input type="text" class="form-control" name="stripePublishableKey"
                                                       placeholder="{{ __('labels.stripe_publishable_key_placeholder') }}"
                                                       value="{{ $settings['stripePublishableKey'] ?? '' }}"/>
                                            </div>
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.stripe_secret_key') }}</label>
                                                <input type="text" class="form-control" name="stripeSecretKey"
                                                       placeholder="{{ __('labels.stripe_secret_key_placeholder') }}"
                                                       value="{{ $isDemoUser && !empty($settings['stripeSecretKey']) ? '••••••••' : ($settings['stripeSecretKey'] ?? '') }}"/>
                                            </div>
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.stripe_currency_code') }}</label>
                                                <select class="form-select" name="stripeCurrencyCode">
                                                    <option value=""
                                                            disabled {{ !isset($settings['stripeCurrencyCode']) ? 'selected' : '' }}>{{ __('labels.stripe_currency_code_placeholder') }}</option>
                                                    <option
                                                        value="USD" {{ isset($settings['stripeCurrencyCode']) && $settings['stripeCurrencyCode'] === 'USD' ? 'selected' : '' }}>
                                                        USD
                                                    </option>
                                                    <option
                                                        value="EUR" {{ isset($settings['stripeCurrencyCode']) && $settings['stripeCurrencyCode'] === 'EUR' ? 'selected' : '' }}>
                                                        EUR
                                                    </option>
                                                    <option
                                                        value="GBP" {{ isset($settings['stripeCurrencyCode']) && $settings['stripeCurrencyCode'] === 'GBP' ? 'selected' : '' }}>
                                                        GBP
                                                    </option>
                                                    <!-- Add more currencies as per StripeCurrencyCodeEnum -->
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.stripe_webhook_secret_key') }}</label>
                                                <input type="text" class="form-control"
                                                       name="stripeWebhookSecretKey"
                                                       placeholder="{{ __('labels.stripe_webhook_secret_key_placeholder') }}"
                                                       value="{{ $isDemoUser && !empty($settings['stripeWebhookSecretKey']) ? '••••••••' : ($settings['stripeWebhookSecretKey'] ?? '') }}"/>
                                            </div>
                                            <div class="mb-3">
                                                <label
                                                    class="form-label">{{ __('labels.stripe_payment_endpoint_url') }}</label>
                                                <input type="url" class="form-control"
                                                       name="stripePaymentEndpointUrl"
                                                       placeholder="{{ __('labels.stripe_payment_endpoint_url_placeholder') }}"
                                                       value="{{ route('stripe.webhook') }}"/>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4 payment-gateway-card {{ $paymentGateways['razorpay']['enabled'] ? 'is-enabled' : '' }}" id="pills-razorpay" data-payment-card data-toggle-name="razorpayPayment">
                                    @include('admin.settings.partials.payment-gateway-header', ['gateway' => $paymentGateways['razorpay']])
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="row">
                                                <span class="col">{{ __('labels.enable_razorpay_payment') }}</span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="razorpayPayment" value="1" {{ isset($settings['razorpayPayment']) && $settings['razorpayPayment'] ? 'checked' : '' }} />
                                                    </label>
                                                </span>
                                            </label>
                                        </div>
                                        <div id="razorpayFields"
                                             style="{{ isset($settings['razorpayPayment']) && $settings['razorpayPayment'] ? 'display: block;' : 'display: none;' }}">
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.razorpay_payment_mode') }}</label>
                                                <select class="form-select" name="razorpayPaymentMode">
                                                    <option value=""
                                                            disabled {{ !isset($settings['razorpayPaymentMode']) ? 'selected' : '' }}>{{ __('labels.razorpay_payment_mode_placeholder') }}</option>
                                                    <option
                                                        value="test" {{ isset($settings['razorpayPaymentMode']) && $settings['razorpayPaymentMode'] === 'test' ? 'selected' : '' }}>
                                                        Test
                                                    </option>
                                                    <option
                                                        value="live" {{ isset($settings['razorpayPaymentMode']) && $settings['razorpayPaymentMode'] === 'live' ? 'selected' : '' }}>
                                                        Live
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.razorpay_key_id') }}</label>
                                                <input type="text" class="form-control" name="razorpayKeyId"
                                                       placeholder="{{ __('labels.razorpay_key_id_placeholder') }}"
                                                       value="{{ $settings['razorpayKeyId'] ?? '' }}"/>
                                            </div>
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.razorpay_secret_key') }}</label>
                                                <input type="text" class="form-control" name="razorpaySecretKey"
                                                       placeholder="{{ __('labels.razorpay_secret_key_placeholder') }}"
                                                       value="{{ $isDemoUser && !empty($settings['razorpaySecretKey']) ? '••••••••' : ($settings['razorpaySecretKey'] ?? '') }}"/>
                                            </div>
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.razorpay_webhook_secret') }}</label>
                                                <input type="text" class="form-control" name="razorpayWebhookSecret"
                                                       placeholder="{{ __('labels.razorpay_webhook_secret_placeholder') }}"
                                                       value="{{ $isDemoUser && !empty($settings['razorpayWebhookSecret']) ? '••••••••' : ($settings['razorpayWebhookSecret'] ?? '') }}"/>
                                            </div>
                                            <div class="mb-3">
                                                <label
                                                    class="form-label">{{ __('labels.razorpay_webhook_url') }}</label>
                                                <p type="text"
                                                   class="form-control mb-1 user-select-all">{{ route('razorpay.webhook') }}</p>
                                                <small
                                                    class="form-text text-muted">{{ __('labels.razorpay_webhook_url_description') }}
                                                     {{__('labels.for_more_details')}} <a
                                                        href="https://razorpay.com/docs/webhooks/setup-edit-payments/#set-up-webhooks" target="_blank">{{__('labels.setup_razorpay_webhook')}}</a></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4 payment-gateway-card {{ $paymentGateways['paystack']['enabled'] ? 'is-enabled' : '' }}" id="pills-paystack" data-payment-card data-toggle-name="paystackPayment">
                                    @include('admin.settings.partials.payment-gateway-header', ['gateway' => $paymentGateways['paystack']])
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="row">
                                                <span class="col">{{ __('labels.enable_paystack_payment') }}</span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="paystackPayment" value="1" {{ isset($settings['paystackPayment']) && $settings['paystackPayment'] ? 'checked' : '' }} />
                                                    </label>
                                                </span>
                                            </label>
                                        </div>
                                        <div id="paystackFields"
                                             style="{{ isset($settings['paystackPayment']) && $settings['paystackPayment'] ? 'display: block;' : 'display: none;' }}">
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.paystack_payment_mode') }}</label>
                                                <select class="form-select" name="paystackPaymentMode">
                                                    <option value=""
                                                            disabled {{ !isset($settings['paystackPaymentMode']) ? 'selected' : '' }}>{{ __('labels.paystack_payment_mode_placeholder') }}</option>
                                                    <option
                                                        value="test" {{ isset($settings['paystackPaymentMode']) && $settings['paystackPaymentMode'] === 'test' ? 'selected' : '' }}>
                                                        Test
                                                    </option>
                                                    <option
                                                        value="live" {{ isset($settings['paystackPaymentMode']) && $settings['paystackPaymentMode'] === 'live' ? 'selected' : '' }}>
                                                        Live
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.paystack_public_key') }}</label>
                                                <input type="text" class="form-control" name="paystackPublicKey"
                                                       placeholder="{{ __('labels.paystack_public_key_placeholder') }}"
                                                       value="{{ $settings['paystackPublicKey'] ?? '' }}"/>
                                            </div>
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.paystack_secret_key') }}</label>
                                                <input type="text" class="form-control" name="paystackSecretKey"
                                                       placeholder="{{ __('labels.paystack_secret_key_placeholder') }}"
                                                       value="{{ $isDemoUser && !empty($settings['paystackSecretKey']) ? '••••••••' : ($settings['paystackSecretKey'] ?? '') }}"/>
                                            </div>
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.paystack_webhook_secret') }}</label>
                                                <input type="text" class="form-control" name="paystackWebhookSecret"
                                                       placeholder="{{ __('labels.paystack_webhook_secret_placeholder') }}"
                                                       value="{{ $isDemoUser && !empty($settings['paystackWebhookSecret']) ? '••••••••' : ($settings['paystackWebhookSecret'] ?? '') }}"/>
                                            </div>
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.paystack_currency_code') }}</label>
                                                <select class="form-select" name="paystackCurrencyCode">
                                                    @foreach(PaystackCurrencyCodeEnum::values() as $value)
                                                        <option
                                                            value="{{ $value }}" {{ isset($settings['paystackCurrencyCode']) && $settings['paystackCurrencyCode'] === $value ? 'selected' : '' }}>
                                                            {{ $value }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.paystack_webhook_url') }}</label>
                                                <p type="text"
                                                   class="form-control mb-1 user-select-all">{{ route('paystack.webhook') }}</p>
                                                <small
                                                    class="form-text text-muted">{{ __('labels.paystack_webhook_url_description') . " " . __('labels.for_more_details')}}<a
                                                        href="https://support.paystack.com/en/articles/2123458#webhook_url" target="_blank"> {{__('labels.setup_paystack_webhook')}}</a></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4 payment-gateway-card {{ $paymentGateways['flutterwave']['enabled'] ? 'is-enabled' : '' }}" id="pills-flutterwave" data-payment-card data-toggle-name="flutterwavePayment">
                                    @include('admin.settings.partials.payment-gateway-header', ['gateway' => $paymentGateways['flutterwave']])
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="row">
                                                <span class="col">{{ __('labels.enable_flutterwave_payment') }}</span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="flutterwavePayment" value="1"
                                                               {{ isset($settings['flutterwavePayment']) && $settings['flutterwavePayment'] ? 'checked' : '' }} />
                                                    </label>
                                                </span>
                                            </label>
                                        </div>

                                        <div id="flutterwaveFields"
                                             style="{{ isset($settings['flutterwavePayment']) && $settings['flutterwavePayment'] ? 'display: block;' : 'display: none;' }}">
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.flutterwave_payment_mode') }}</label>
                                                <select class="form-select" name="flutterwavePaymentMode">
                                                    <option value=""
                                                            disabled {{ !isset($settings['flutterwavePaymentMode']) ? 'selected' : '' }}>
                                                        {{ __('labels.flutterwave_payment_mode_placeholder') }}
                                                    </option>
                                                    <option value="test"
                                                        {{ isset($settings['flutterwavePaymentMode']) && $settings['flutterwavePaymentMode'] === 'test' ? 'selected' : '' }}>
                                                        Test
                                                    </option>
                                                    <option value="live"
                                                        {{ isset($settings['flutterwavePaymentMode']) && $settings['flutterwavePaymentMode'] === 'live' ? 'selected' : '' }}>
                                                        Live
                                                    </option>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.flutterwave_public_key') }}</label>
                                                <input type="text" class="form-control" name="flutterwavePublicKey"
                                                       placeholder="{{ __('labels.flutterwave_public_key_placeholder') }}"
                                                       value="{{ $settings['flutterwavePublicKey'] ?? '' }}"/>
                                            </div>

                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.flutterwave_secret_key') }}</label>
                                                <input type="text" class="form-control" name="flutterwaveSecretKey"
                                                       placeholder="{{ __('labels.flutterwave_secret_key_placeholder') }}"
                                                       value="{{ $isDemoUser && !empty($settings['flutterwaveSecretKey']) ? '••••••••' : ($settings['flutterwaveSecretKey'] ?? '') }}"/>
                                            </div>

                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.flutterwave_encryption_key') }}</label>
                                                <input type="text" class="form-control" name="flutterwaveEncryptionKey"
                                                       placeholder="{{ __('labels.flutterwave_encryption_key_placeholder') }}"
                                                       value="{{ $isDemoUser && !empty($settings['flutterwaveEncryptionKey']) ? '••••••••' : ($settings['flutterwaveEncryptionKey'] ?? '') }}"/>
                                            </div>

                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.flutterwave_webhook_secret') }}</label>
                                                <input type="text" class="form-control" name="flutterwaveWebhookSecret"
                                                       placeholder="{{ __('labels.flutterwave_webhook_secret_placeholder') }}"
                                                       value="{{ $isDemoUser && !empty($settings['flutterwaveWebhookSecret']) ? '••••••••' : ($settings['flutterwaveWebhookSecret'] ?? '') }}"/>
                                            </div>

                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.flutterwave_currency_code') }}</label>
                                                <select class="form-select" name="flutterwaveCurrencyCode">
                                                    @foreach(FlutterwaveCurrencyCodeEnum::values() as $value)
                                                        <option value="{{ $value }}"
                                                            {{ isset($settings['flutterwaveCurrencyCode']) && $settings['flutterwaveCurrencyCode'] === $value ? 'selected' : '' }}>
                                                            {{ __('labels.currency_data.' . $value) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.flutterwave_webhook_url') }}</label>
                                                <p type="text"
                                                   class="form-control mb-1 user-select-all">{{ route('flutterwave.webhook') }}</p>
                                                <small
                                                    class="form-text text-muted">{{ __('labels.flutterwave_webhook_url_description') . " " . __('labels.for_more_details')}}<a
                                                        href="https://developer.flutterwave.com/docs/webhooks#enabling-webhooks" target="_blank"> {{__('labels.setup_flutterwave_webhook')}}</a></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4 payment-gateway-card {{ $paymentGateways['xendit']['enabled'] ? 'is-enabled' : '' }}" id="pills-xendit" data-payment-card data-toggle-name="xenditPayment">
                                    @include('admin.settings.partials.payment-gateway-header', ['gateway' => $paymentGateways['xendit']])
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="row">
                                                <span class="col">{{ __('labels.enable_xendit_payment') }}</span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="xenditPayment" value="1"
                                                               {{ !empty($settings['xenditPayment']) ? 'checked' : '' }} />
                                                    </label>
                                                </span>
                                            </label>
                                        </div>

                                        <div id="xenditFields"
                                             style="{{ !empty($settings['xenditPayment']) ? 'display: block;' : 'display: none;' }}">
                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.xendit_payment_mode') }}</label>
                                                <select class="form-select" name="xenditPaymentMode">
                                                    <option value="test" {{ ($settings['xenditPaymentMode'] ?? 'test') === 'test' ? 'selected' : '' }}>Test</option>
                                                    <option value="live" {{ ($settings['xenditPaymentMode'] ?? '') === 'live' ? 'selected' : '' }}>Live</option>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.xendit_secret_key') }}</label>
                                                <input type="text" class="form-control" name="xenditSecretKey"
                                                       autocomplete="new-password"
                                                       placeholder="{{ __('labels.xendit_secret_key_placeholder') }}"
                                                       value="{{ $isDemoUser && !empty($settings['xenditSecretKey']) ? '••••••••' : ($settings['xenditSecretKey'] ?? '') }}"/>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.xendit_webhook_verification_token') }}</label>
                                                <input type="text" class="form-control" name="xenditWebhookVerificationToken"
                                                       autocomplete="new-password"
                                                       placeholder="{{ __('labels.xendit_webhook_verification_token_placeholder') }}"
                                                       value="{{ $isDemoUser && !empty($settings['xenditWebhookVerificationToken']) ? '••••••••' : ($settings['xenditWebhookVerificationToken'] ?? '') }}"/>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.xendit_country') }}</label>
                                                <select class="form-select" name="xenditCountry">
                                                    @foreach(XenditCountryEnum::cases() as $country)
                                                        <option value="{{ $country->value }}"
                                                            {{ ($settings['xenditCountry'] ?? '') === $country->value ? 'selected' : '' }}>
                                                            {{ __('labels.xendit_countries.' . $country->value) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.xendit_enabled_currencies') }}</label>
                                                <select class="form-select js-tom-select-static" name="xenditEnabledCurrencies[]" multiple size="7">
                                                    @foreach(XenditCurrencyCodeEnum::values() as $currency)
                                                        <option value="{{ $currency }}"
                                                            {{ in_array($currency, $settings['xenditEnabledCurrencies'] ?? [], true) ? 'selected' : '' }}>
                                                            {{ $currency }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <small class="form-text text-muted">{{ __('labels.xendit_enabled_currencies_description') }}</small>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.xendit_fallback_currency') }}</label>
                                                <select class="form-select" name="xenditFallbackCurrency">
                                                    @foreach(XenditCurrencyCodeEnum::values() as $currency)
                                                        <option value="{{ $currency }}"
                                                            {{ ($settings['xenditFallbackCurrency'] ?? '') === $currency ? 'selected' : '' }}>
                                                            {{ $currency }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <small class="form-text text-muted">{{ __('labels.xendit_fallback_currency_description') }}</small>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.xendit_webhook_url') }}</label>
                                                <p class="form-control mb-1 user-select-all">{{ route('xendit.webhook') }}</p>
                                                <small class="form-text text-muted">
                                                    {{ __('labels.xendit_webhook_url_description') }}
                                                    <a href="https://dashboard.xendit.co/settings/developers#webhooks" target="_blank" rel="noopener noreferrer">{{ __('labels.setup_xendit_webhook') }}</a>
                                                </small>
                                            </div>

                                            <hr class="my-3">
                                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                                                <small class="text-muted">{{ __('labels.test_payment_section_hint') }}</small>
                                                <button type="button" class="btn btn-outline-primary test-payment-button"
                                                        data-endpoint="{{ route('admin.settings.payment.test') }}"
                                                        data-gateway="xendit"
                                                        data-secret-field="xenditSecretKey"
                                                        data-missing="{{ __('labels.test_payment_missing_credentials') }}"
                                                        data-success-title="{{ __('labels.test_payment_section_title') }}"
                                                        data-failed-title="{{ __('labels.test_payment_section_title') }}">
                                                    <span>{{ __('labels.test_payment_button') }}</span>
                                                    <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4 payment-gateway-card {{ $paymentGateways['mercado_pago']['enabled'] ? 'is-enabled' : '' }}" id="pills-mercado-pago" data-payment-card data-toggle-name="mercadoPagoPayment">
                                    @include('admin.settings.partials.payment-gateway-header', ['gateway' => $paymentGateways['mercado_pago']])
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="row">
                                                <span class="col">{{ __('labels.enable_mercado_pago_payment') }}</span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="mercadoPagoPayment" value="1"
                                                               {{ !empty($settings['mercadoPagoPayment']) ? 'checked' : '' }} />
                                                    </label>
                                                </span>
                                            </label>
                                        </div>

                                        <div id="mercadoPagoFields"
                                             style="{{ !empty($settings['mercadoPagoPayment']) ? 'display: block;' : 'display: none;' }}">
                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.mercado_pago_payment_mode') }}</label>
                                                <select class="form-select" name="mercadoPagoPaymentMode">
                                                    <option value="test" {{ ($settings['mercadoPagoPaymentMode'] ?? 'test') === 'test' ? 'selected' : '' }}>Test</option>
                                                    <option value="live" {{ ($settings['mercadoPagoPaymentMode'] ?? '') === 'live' ? 'selected' : '' }}>Live</option>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">{{ __('labels.mercado_pago_public_key') }}</label>
                                                <input type="text" class="form-control" name="mercadoPagoPublicKey"
                                                       autocomplete="off"
                                                       placeholder="{{ __('labels.mercado_pago_public_key_placeholder') }}"
                                                       value="{{ $settings['mercadoPagoPublicKey'] ?? '' }}"/>
                                                <small class="form-text text-muted">{{ __('labels.mercado_pago_public_key_description') }}</small>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.mercado_pago_access_token') }}</label>
                                                <input type="text" class="form-control" name="mercadoPagoAccessToken"
                                                       autocomplete="new-password"
                                                       placeholder="{{ __('labels.mercado_pago_access_token_placeholder') }}"
                                                       value="{{ $isDemoUser && !empty($settings['mercadoPagoAccessToken']) ? '••••••••' : ($settings['mercadoPagoAccessToken'] ?? '') }}"/>
                                                <small class="form-text text-muted">{{ __('labels.mercado_pago_access_token_description') }}</small>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.mercado_pago_webhook_secret') }}</label>
                                                <input type="text" class="form-control" name="mercadoPagoWebhookSecret"
                                                       autocomplete="new-password"
                                                       placeholder="{{ __('labels.mercado_pago_webhook_secret_placeholder') }}"
                                                       value="{{ $isDemoUser && !empty($settings['mercadoPagoWebhookSecret']) ? '••••••••' : ($settings['mercadoPagoWebhookSecret'] ?? '') }}"/>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.mercado_pago_account_currency') }}</label>
                                                <select class="form-select" name="mercadoPagoAccountCurrency">
                                                    @foreach(MercadoPagoCurrencyCodeEnum::values() as $currency)
                                                        <option value="{{ $currency }}"
                                                            {{ ($settings['mercadoPagoAccountCurrency'] ?? '') === $currency ? 'selected' : '' }}>
                                                            {{ $currency }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <small class="form-text text-muted">{{ __('labels.mercado_pago_account_currency_description') }}</small>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.mercado_pago_webhook_url') }}</label>
                                                <p class="form-control mb-1 user-select-all">{{ route('mercado-pago.webhook') }}</p>
                                                <small class="form-text text-muted">
                                                    {{ __('labels.mercado_pago_webhook_url_description') }}
                                                    <a href="https://www.mercadopago.com/developers/panel/app" target="_blank" rel="noopener noreferrer">{{ __('labels.setup_mercado_pago_webhook') }}</a>
                                                </small>
                                            </div>

                                            <hr class="my-3">
                                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                                                <small class="text-muted">{{ __('labels.test_payment_section_hint') }}</small>
                                                <button type="button" class="btn btn-outline-primary test-payment-button"
                                                        data-endpoint="{{ route('admin.settings.payment.test') }}"
                                                        data-gateway="mercado_pago"
                                                        data-secret-field="mercadoPagoAccessToken"
                                                        data-missing="{{ __('labels.test_payment_missing_credentials') }}"
                                                        data-success-title="{{ __('labels.test_payment_section_title') }}"
                                                        data-failed-title="{{ __('labels.test_payment_section_title') }}">
                                                    <span>{{ __('labels.test_payment_button') }}</span>
                                                    <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4 payment-gateway-card {{ $paymentGateways['wallet']['enabled'] ? 'is-enabled' : '' }}" id="pills-wallet" data-payment-card data-toggle-name="walletPayment">
                                    @include('admin.settings.partials.payment-gateway-header', ['gateway' => $paymentGateways['wallet']])
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="row">
                                                <span class="col">{{ __('labels.enable_wallet_payment') }}</span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="walletPayment"
                                                               value="1" {{ isset($settings['wallet']) && $settings['wallet'] ? 'checked' : '' }} />
                                                    </label>
                                                </span>
                                                <small
                                                    class="form-text text-muted">{{ __('labels.wallet_payment_description') }}</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4 payment-gateway-card {{ $paymentGateways['cod']['enabled'] ? 'is-enabled' : '' }}" id="pills-cod" data-payment-card data-toggle-name="{{ PaymentTypeEnum::COD() }}">
                                    @include('admin.settings.partials.payment-gateway-header', ['gateway' => $paymentGateways['cod']])
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="row">
                                                <span class="col">{{ __('labels.enable_cash_on_delivery') }}</span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="{{PaymentTypeEnum::COD()}}"
                                                               value="1" {{ isset($settings['cod']) && $settings['cod'] ? 'checked' : '' }} />
                                                    </label>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer payment-settings-save-bar text-end">
                                    <div class="d-flex">
                                        @can('updateSetting', [\App\Models\Setting::class, 'payment'])
                                            <button type="submit"
                                                    class="btn btn-primary ms-auto">
                                                <i class="ti ti-device-floppy me-1"></i>{{ __('labels.save_settings') }}
                                            </button>
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
        const stripeToggle = document.querySelector('input[name="stripePayment"]');
        const stripeFields = document.getElementById('stripeFields');
        const razorpayToggle = document.querySelector('input[name="razorpayPayment"]');
        const razorpayFields = document.getElementById('razorpayFields');
        const paystackToggle = document.querySelector('input[name="paystackPayment"]');
        const paystackFields = document.getElementById('paystackFields');
        // const bankToggle = document.querySelector('input[name="directBankTransfer"]');
        // const bankFields = document.getElementById('bankFields');
        const flutterwaveToggle = document.querySelector('input[name="flutterwavePayment"]');
        const flutterwaveFields = document.getElementById('flutterwaveFields');
        const xenditToggle = document.querySelector('input[name="xenditPayment"]');
        const xenditFields = document.getElementById('xenditFields');
        const mercadoPagoToggle = document.querySelector('input[name="mercadoPagoPayment"]');
        const mercadoPagoFields = document.getElementById('mercadoPagoFields');

        const toggleStripeFields = () => {
            stripeFields.style.display = stripeToggle.checked ? 'block' : 'none';
        };
        const toggleRazorpayFields = () => {
            razorpayFields.style.display = razorpayToggle.checked ? 'block' : 'none';
        };
        const togglePaystackFields = () => {
            paystackFields.style.display = paystackToggle.checked ? 'block' : 'none';
        };
        const toggleFlutterwaveFields = () => {
            flutterwaveFields.style.display = flutterwaveToggle.checked ? 'block' : 'none';
        };
        const toggleXenditFields = () => {
            xenditFields.style.display = xenditToggle.checked ? 'block' : 'none';
        };
        const toggleMercadoPagoFields = () => {
            mercadoPagoFields.style.display = mercadoPagoToggle.checked ? 'block' : 'none';
        };
        // const toggleBankFields = () => {
        //     bankFields.style.display = bankToggle.checked ? 'block' : 'none';
        // };

        stripeToggle.addEventListener('change', toggleStripeFields);
        razorpayToggle.addEventListener('change', toggleRazorpayFields);
        paystackToggle.addEventListener('change', togglePaystackFields);
        flutterwaveToggle.addEventListener('change', toggleFlutterwaveFields);
        xenditToggle.addEventListener('change', toggleXenditFields);
        mercadoPagoToggle.addEventListener('change', toggleMercadoPagoFields);
        // bankToggle.addEventListener('change', toggleBankFields);
        toggleStripeFields();
        toggleRazorpayFields();
        togglePaystackFields();
        toggleFlutterwaveFields();
        toggleXenditFields();
        toggleMercadoPagoFields();
        // toggleBankFields();
    </script>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/payment-settings.js') }}" defer></script>
@endpush
