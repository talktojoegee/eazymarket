@extends('layouts.admin.app', ['page' => $menuAdmin['splash_screen']['active'] ?? 'splash-screen'])

@section('title', __('labels.splash_screen_builder'))

@section('header_data')
    @php
        $page_title = __('labels.splash_screen_builder');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.splash_screen_builder'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards splash-screen-builder">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.splash_screen_builder') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs" />
                    </div>
                </div>
                <div class="card-body pb-0">
                    <ul class="nav nav-tabs" role="tablist" aria-label="{{ __('labels.onboarding_app_tabs') }}">
                        <li class="nav-item" role="presentation"><button class="nav-link active" type="button" role="tab" aria-selected="true" data-splash-surface="customer_app">{{ __('labels.customer_app') }}</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" aria-selected="false" data-splash-surface="seller_app">{{ __('labels.seller_app') }}</button></li>
                    </ul>
                </div>
                @foreach(['customer_app' => __('labels.customer_app'), 'seller_app' => __('labels.seller_app')] as $surface => $surfaceLabel)
                    <section class="splash-surface-panel {{ $surface === 'customer_app' ? '' : 'd-none' }}" data-splash-panel="{{ $surface }}">
                        <div class="card-body">
                            <h3 class="mb-1">{{ $surfaceLabel }}</h3>
                            <p class="text-secondary mb-4">{{ __('labels.splash_screen_schedule_hint') }}</p>
                            <ul class="nav nav-pills splash-slot-tabs mb-3" role="tablist" aria-label="{{ __('labels.splash_schedule_tabs') }}">
                                <li class="nav-item" role="presentation"><button class="nav-link active" type="button" role="tab" aria-selected="true" data-splash-slot="current" data-surface="{{ $surface }}">{{ __('labels.current_splash') }}</button></li>
                                <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" aria-selected="false" data-splash-slot="staged" data-surface="{{ $surface }}">{{ __('labels.staged_splash') }}</button></li>
                            </ul>
                            <div class="row g-4">
                                <div class="col-12 col-xl-8">
                                    <div class="row g-3">
                                        @foreach(['current' => __('labels.current_splash'), 'staged' => __('labels.staged_splash')] as $slot => $slotLabel)
                                            <div class="col-12 {{ $slot === 'staged' ? 'd-none' : '' }}" data-splash-form-slot="{{ $slot }}" data-splash-form-surface="{{ $surface }}">
                                                @include('admin.splash-screen.partials.screen-form', [
                                                'surface' => $surface,
                                                'surfaceLabel' => $surfaceLabel,
                                                'slot' => $slot,
                                                'slotLabel' => $slotLabel,
                                                'screen' => $screens->get($surface.'_'.$slot),
                                                'canEdit' => $canEdit,
                                            ])
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-12 col-xl-4">
                                    <section class="card splash-preview-card" aria-label="{{ __('labels.mobile_preview') }}">
                                        <div class="card-header"><h3 class="card-title">{{ __('labels.mobile_preview') }}</h3></div>
                                        <div class="card-body">
                                            <div class="splash-phone mx-auto" data-splash-preview>
                                                <div class="splash-phone-status"><span>9:41</span><span>● ● ● 100%</span></div>
                                                <div class="splash-phone-content">
                                                    <video class="splash-preview-video d-none" muted loop autoplay playsinline></video>
                                                    <img class="splash-preview-background d-none" alt="">
                                                    <div class="splash-preview-overlay d-none"></div>
                                                </div>
                                                <div class="splash-phone-home-indicator"></div>
                                            </div>
                                            <p class="text-secondary text-center small mt-3 mb-0">{{ __('labels.splash_screen_preview_hint') }}</p>
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/splash-screen-builder.css') }}">
@endpush

@push('scripts')
    <script>
        window.splashScreenLabels = {
            image: @json(__('labels.image')),
            video: @json(__('labels.background_video')),
            lottie: @json(__('labels.lottie_animation')),
            color: @json(__('labels.background_color')),
            configured: @json(__('labels.configured')),
            confirmationUnavailable: @json(__('labels.confirmation_unavailable')),
            removeMediaConfirmation: @json(__('labels.remove_splash_media_confirmation')),
            removeMediaConfirm: @json(__('labels.remove_splash_media_confirm')),
            removeScreenConfirmation: @json(__('labels.remove_splash_screen_confirmation')),
            removeScreenConfirm: @json(__('labels.remove_splash_screen_confirm')),
            removeScreenTitle: @json(__('labels.remove_splash_screen')),
            screenRemoved: @json(__('labels.splash_screen_removed')),
            removeScreenFailed: @json(__('labels.splash_screen_remove_failed')),
            cancel: @json(__('labels.cancel')),
            removeBackgroundImage: @json(__('labels.remove_background_image')),
            removeOverlayImage: @json(__('labels.remove_overlay_image')),
            removeOverlayMedia: @json(__('labels.remove_overlay_media')),
            currentFile: @json(__('labels.current_file')),
            savedMediaAlt: @json(__('labels.current_image')),
        };
    </script>
    <script src="{{ hyperAsset('assets/js/splash-screen-builder.js') }}" defer></script>
@endpush
