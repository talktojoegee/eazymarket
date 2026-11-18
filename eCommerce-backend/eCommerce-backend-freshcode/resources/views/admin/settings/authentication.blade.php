@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? '', 'sub_page' => $menuAdmin['settings']['route']['authentication']['sub_active'] ?? ''])

@section('title', __('labels.authentication_settings'))

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/authentication-settings.css') }}">
@endpush

@section('header_data')
    @php
        $page_title = __('labels.authentication_settings');
        $page_pretitle = __('labels.admin').' '.__('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.authentication_settings'), 'url' => null],
    ];
    $statusClass = fn (bool $ready) => $ready ? 'bg-success-lt text-success' : 'bg-warning-lt text-warning';
    $statusLabel = fn (bool $ready) => $ready ? __('labels.ready') : __('labels.action_required');
    $selectedOtpGateway = in_array(($settings['smsGateway'] ?? ''), ['custom', 'firebase'], true)
        ? $settings['smsGateway']
        : 'custom';
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.authentication_settings') }}</h2>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-cards mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card h-100"><div class="card-body">
                        <div class="text-muted small mb-2">{{ __('labels.account_access') }}</div>
                        <div class="h3 mb-1">{{ __('labels.identifier_mode_'.($settings['identifierMode'] ?? 'both')) }}</div>
                        <a href="#account" data-auth-tab-link="account">{{ __('labels.manage') }}</a>
                    </div></div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card h-100"><div class="card-body">
                        <div class="text-muted small mb-2">{{ __('labels.mobile_otp') }}</div>
                        <div class="h3 mb-1">{{ __('labels.otp_gateway_'.$selectedOtpGateway) }}</div>
                        <a href="#otp" data-auth-tab-link="otp">{{ __('labels.configure') }}</a>
                    </div></div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card h-100"><div class="card-body">
                        <div class="text-muted small mb-2">{{ __('labels.firebase_setup') }}</div>
                        <div class="h3 mb-1">{{ $firebaseStatus['credentials']['project_id'] ?: __('labels.not_configured') }}</div>
                        <div class="d-flex gap-1 flex-wrap my-2">
                            <span class="badge {{ $statusClass($firebaseStatus['client_ready'] ?? false) }}">{{ __('labels.firebase_client_configuration') }}: {{ $statusLabel($firebaseStatus['client_ready'] ?? false) }}</span>
                            <span class="badge {{ $statusClass($firebaseStatus['server_ready'] ?? false) }}">{{ __('labels.firebase_server_credentials') }}: {{ $statusLabel($firebaseStatus['server_ready'] ?? false) }}</span>
                            <span class="badge {{ $statusClass($firebaseStatus['push_ready'] ?? false) }}">{{ __('labels.push_notifications') }}: {{ $statusLabel($firebaseStatus['push_ready'] ?? false) }}</span>
                        </div>
                        <a href="#firebase" data-auth-tab-link="firebase">{{ __('labels.review_setup') }}</a>
                    </div></div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card h-100"><div class="card-body">
                        <div class="text-muted small mb-2">{{ __('labels.social_login') }}</div>
                        <div class="d-flex gap-2 flex-wrap mb-2">
                            <span class="badge {{ !empty($settings['googleLogin']) ? 'bg-success-lt text-success' : 'bg-secondary-lt' }}">Google</span>
                            <span class="badge {{ !empty($settings['appleLogin']) ? 'bg-success-lt text-success' : 'bg-secondary-lt' }}">Apple</span>
                        </div>
                        <a href="#social" data-auth-tab-link="social">{{ __('labels.manage') }}</a>
                    </div></div>
                </div>
            </div>

            @if($firebaseStatus['project_mismatch'] ?? false)
                <div class="alert alert-danger align-items-start mb-4">
                    <i class="ti ti-alert-circle me-2"></i>
                    <div><strong>{{ __('labels.firebase_project_mismatch_title') }}</strong><div>{{ __('labels.firebase_project_mismatch') }}</div></div>
                </div>
            @elseif(!($firebaseStatus['client_ready'] ?? false) || !($firebaseStatus['server_ready'] ?? false))
                <div class="alert alert-warning align-items-start mb-4">
                    <i class="ti ti-alert-triangle me-2"></i>
                    <div>
                        <strong>{{ __('labels.firebase_setup_incomplete') }}</strong>
                        <div>{{ __('labels.firebase_setup_incomplete_hint') }}</div>
                        <a href="#firebase" data-auth-tab-link="firebase" class="alert-link">{{ __('labels.complete_firebase_setup') }}</a>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header border-bottom p-3">
                    <div class="auth-settings-tabs" id="authenticationTabs" role="tablist" aria-label="{{ __('labels.authentication_settings') }}">
                        @foreach([
                            'account' => ['label' => __('labels.account_access'), 'icon' => 'ti-user-shield'],
                            'otp' => ['label' => __('labels.mobile_otp'), 'icon' => 'ti-message-code'],
                            'firebase' => ['label' => __('labels.firebase_setup'), 'icon' => 'ti-brand-firebase'],
                            'social' => ['label' => __('labels.social_login'), 'icon' => 'ti-users'],
                            'tests' => ['label' => __('labels.test_center'), 'icon' => 'ti-flask'],
                        ] as $tab => $item)
                            <button type="button" class="auth-settings-tab {{ $tab === 'account' ? 'active' : '' }}" id="{{ $tab }}-tab" data-auth-tab-name="{{ $tab }}" role="tab" aria-controls="auth-{{ $tab }}" aria-selected="{{ $tab === 'account' ? 'true' : 'false' }}" tabindex="{{ $tab === 'account' ? '0' : '-1' }}">
                                <i class="ti {{ $item['icon'] }}" aria-hidden="true"></i>
                                <span>{{ $item['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="tab-content">
                    @include('admin.settings.authentication.account')
                    @include('admin.settings.authentication.otp')
                    @include('admin.settings.authentication.firebase')
                    @include('admin.settings.authentication.social')
                    @include('admin.settings.authentication.tests')
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="module" src="{{ hyperAsset('assets/js/authentication-settings.js') }}"></script>
@endpush
