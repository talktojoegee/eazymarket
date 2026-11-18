@extends('layouts.admin.app', ['page' => $menuAdmin['onboarding']['active'] ?? 'onboarding'])

@section('title', __('labels.onboarding_pages'))

@section('header_data')
    @php
        $page_title = __('labels.onboarding_pages');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.onboarding_pages'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.onboarding_pages') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs" />
                    </div>
                    <div class="card-actions d-flex gap-2">
                        @if($createPermission)
                            <button type="button" class="btn btn-outline-primary" id="add-onboarding-page" data-bs-toggle="modal" data-bs-target="#onboarding-page-modal">
                                <i class="ti ti-plus icon" aria-hidden="true"></i>
                                {{ __('labels.add_onboarding_page') }}
                            </button>
                        @endif
                        <button type="button" class="btn btn-outline-primary" id="refresh-onboarding-pages">
                            <i class="ti ti-refresh icon" aria-hidden="true"></i>
                            {{ __('labels.refresh') }}
                        </button>
                    </div>
                </div>
                <div class="card-body pb-0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <ul class="nav nav-tabs" role="tablist" aria-label="{{ __('labels.onboarding_app_tabs') }}">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" type="button" role="tab" aria-selected="true" data-onboarding-surface="customer_app">{{ __('labels.customer_app') }}</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" type="button" role="tab" aria-selected="false" data-onboarding-surface="seller_app">{{ __('labels.seller_app') }}</button>
                            </li>
                        </ul>
                        <div class="d-flex flex-wrap align-items-center gap-3 pb-2">
                            @foreach(['customer_app' => __('labels.customer_app'), 'seller_app' => __('labels.seller_app')] as $surface => $surfaceLabel)
                                <div class="form-check form-switch d-flex align-items-center gap-2 m-0 {{ $surface === 'customer_app' ? '' : 'd-none' }}" data-onboarding-toggle-wrapper="{{ $surface }}">
                                    <label class="form-check-label" for="onboarding-enabled-{{ $surface }}">{{ $surfaceLabel }}</label>
                                    <input class="form-check-input m-0" id="onboarding-enabled-{{ $surface }}" type="checkbox"
                                           role="switch" data-onboarding-enabled data-surface="{{ $surface }}"
                                           data-url="{{ route('admin.onboarding.enabled', ['surface' => $surface]) }}"
                                           aria-label="{{ __('labels.toggle_onboarding_for', ['app' => $surfaceLabel]) }}"
                                           @checked($onboardingEnabled[$surface]) @disabled(!$editPermission)>
                                    <span class="small text-secondary" data-onboarding-enabled-status="{{ $surface }}">
                                        {{ $onboardingEnabled[$surface] ? __('labels.enabled') : __('labels.disabled') }}
                                    </span>
                                </div>
                            @endforeach
                            @foreach(['customer_app' => __('labels.customer_app'), 'seller_app' => __('labels.seller_app')] as $surface => $surfaceLabel)
                                <div class="form-check form-switch d-flex align-items-center gap-2 m-0 {{ $surface === 'customer_app' ? '' : 'd-none' }}" data-onboarding-skip-wrapper="{{ $surface }}">
                                    <label class="form-check-label" for="onboarding-skip-enabled-{{ $surface }}">{{ __('labels.show_skip_button_for', ['app' => $surfaceLabel]) }}</label>
                                    <input class="form-check-input m-0" id="onboarding-skip-enabled-{{ $surface }}" type="checkbox" role="switch"
                                           data-onboarding-skip-enabled data-surface="{{ $surface }}" data-url="{{ route('admin.onboarding.skip-enabled') }}"
                                           @checked($onboardingSkipEnabled[$surface]) @disabled(!$editPermission)>
                                    <span class="small text-secondary" data-onboarding-skip-status="{{ $surface }}">
                                        {{ $onboardingSkipEnabled[$surface] ? __('labels.enabled') : __('labels.disabled') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="card-table">
                    <div class="row g-3 p-3 align-items-start">
                        <div class="col-12 col-lg-8">
                            <x-datatable id="onboarding-pages-table" :columns="$columns"
                                         route="{{ route('admin.onboarding.datatable', ['surface' => 'customer_app']) }}"
                                         :options="['order' => [[0, 'asc']], 'pageLength' => 10]" />
                        </div>
                        <div class="col-12 col-lg-4">
                            <section class="card onboarding-preview-card" aria-labelledby="onboarding-preview-heading">
                                <div class="card-header">
                                    <h3 class="card-title" id="onboarding-preview-heading">{{ __('labels.mobile_preview') }}</h3>
                                </div>
                                <div class="card-body">
                                    <p class="text-secondary text-center mb-3" id="onboarding-preview-hint">{{ __('labels.loading_onboarding_screens') }}</p>
                                    <div class="onboarding-phone-frame onboarding-table-phone-frame mx-auto" aria-label="{{ __('labels.mobile_preview') }}">
                                        <div class="onboarding-phone-screen" id="onboarding-phone-screen">
                                            <img class="onboarding-phone-background d-none" id="onboarding-preview-background" alt="">
                                            <div class="onboarding-phone-status" aria-hidden="true">
                                                <span>9:41</span>
                                                <span class="onboarding-phone-status-icons"><span class="onboarding-phone-signal"><i></i><i></i><i></i><i></i></span><span class="onboarding-phone-wifi">⌁</span><span class="onboarding-phone-battery"><i></i></span></span>
                                            </div>
                                            <div class="onboarding-iphone-dynamic-island" aria-hidden="true"></div>
                                            <div class="onboarding-phone-skip-row">
                                                <button type="button" id="onboarding-preview-skip">{{ __('labels.onboarding_preview_skip') }}</button>
                                            </div>
                                            <div class="onboarding-phone-illustration">
                                                <img id="onboarding-preview-image" class="d-none rounded-8" alt="">
                                            </div>
                                            <div class="onboarding-phone-copy">
                                                <h4 id="onboarding-preview-title">{{ __('labels.onboarding_preview_title') }}</h4>
                                                <p id="onboarding-preview-subtitle">{{ __('labels.onboarding_preview_subtitle') }}</p>
                                            </div>
                                            <div class="onboarding-phone-bottom">
                                                <div class="onboarding-phone-page-dots" id="onboarding-preview-dots" aria-label="{{ __('labels.onboarding_preview_pages') }}" aria-live="polite"></div>
                                                <button type="button" class="btn btn-outline-secondary" id="onboarding-preview-back" aria-label="{{ __('labels.back') }}">{{ __('labels.back') }}</button>
                                                <button type="button" class="btn btn-primary" id="onboarding-preview-next" aria-label="{{ __('labels.next') }}">{{ __('labels.next') }}</button>
                                            </div>
                                            <div class="onboarding-iphone-home-indicator" aria-hidden="true"></div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($createPermission || $editPermission)
        @include('admin.onboarding.partials.page-modal')
    @endif
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/onboarding-pages.css') }}">
@endpush

@push('scripts')
    <script>
        window.onboardingPageLabels = {
            create: @json(__('labels.create_onboarding_page')),
            edit: @json(__('labels.edit_onboarding_page')),
            deleteConfirm: @json(__('labels.onboarding_delete_confirmation')),
            cancel: @json(__('labels.cancel')),
            delete: @json(__('labels.delete')),
            confirmationUnavailable: @json(__('labels.confirmation_unavailable')),
            previewTitle: @json(__('labels.onboarding_preview_title')),
            previewSubtitle: @json(__('labels.onboarding_preview_subtitle')),
            previewEmpty: @json(__('labels.no_onboarding_screens_to_preview')),
            previewLoading: @json(__('labels.loading_onboarding_screens')),
            previous: @json(__('labels.previous')),
            next: @json(__('labels.next')),
            enabled: @json(__('labels.enabled')),
            disabled: @json(__('labels.disabled')),
            toggleFailed: @json(__('labels.onboarding_toggle_failed')),
            toggleUpdated: @json(__('labels.onboarding_toggle_updated')),
            skip: @json(__('labels.onboarding_preview_skip')),
            skipEnabled: @json($onboardingSkipEnabled),
            skipToggleFailed: @json(__('labels.onboarding_skip_toggle_failed')),
            skipToggleUpdated: @json(__('labels.onboarding_skip_toggle_updated')),
        };
        window.onboardingPageRoutes = {
            store: @json(route('admin.onboarding.store')),
            update: @json(route('admin.onboarding.update', ['id' => '__id__'])),
            delete: @json(route('admin.onboarding.delete', ['id' => '__id__'])),
            datatable: @json(route('admin.onboarding.datatable')),
            preview: @json(route('admin.onboarding.preview')),
            enabled: @json(route('admin.onboarding.enabled', ['surface' => '__surface__'])),
            skipEnabled: @json(route('admin.onboarding.skip-enabled')),
        };
    </script>
    <script src="{{ hyperAsset('assets/js/onboarding-pages.js') }}" defer></script>
@endpush
