@extends('layouts.admin.app', ['page' => $menuAdmin['home_builder']['active'] ?? 'home-builder'])

@section('title', __('labels.home_builder'))

@section('header_data')
    @php
        $page_title = __('labels.home_builder');
        $page_pretitle = __('labels.menu_section_marketing');
    @endphp
@endsection

<x-scroll-sort />

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/swiperjs/swiper-bundle.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/cropperjs/css/cropper.min.css') }}">
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/home-builder.css') }}">
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/home-categories.css') }}">
@endpush

@section('admin-content')
    @php
        $activeBuilderTab = request('active_tab', request('tab', request('platform', 'app')));
        $activeBuilderTab = in_array($activeBuilderTab, ['categories', 'home-categories', 'navbar', 'home-navbar'], true) ? 'homecats' : $activeBuilderTab;
        $activeBuilderTab = in_array($activeBuilderTab, ['homecats', 'app', 'web'], true) ? $activeBuilderTab : 'app';
    @endphp
    <div class="hb-workspace-switcher mb-3">
        <ul class="nav hb-workspace-tabs" id="hb-tabs" role="tablist" aria-label="Home builder workspace">
            <x-home-builder.workspace-tab
                pane="homecats"
                icon="ti-home-cog"
                title="Header & Navbar"
                description="Direct header and navigation settings"
                :active="$activeBuilderTab === 'homecats'"
            />
            <x-home-builder.workspace-tab
                pane="app"
                icon="ti-device-mobile"
                :title="__('labels.hb_app_layout')"
                description="Mobile storefront"
                :active="$activeBuilderTab === 'app'"
            />
            <x-home-builder.workspace-tab
                pane="web"
                icon="ti-device-desktop"
                :title="__('labels.hb_web_layout')"
                description="Desktop storefront"
                :active="$activeBuilderTab === 'web'"
            />
        </ul>
    </div>

    <div id="hb-pane-homecats" @class(['d-none' => $activeBuilderTab !== 'homecats'])>
        <div class="card hb-home-categories-shell">
            <div class="card-body">
                @include('admin.categories.partials.home-categories-manager')
            </div>
        </div>
    </div>

    <div id="hb-pane-app" @class(['d-none' => $activeBuilderTab !== 'app'])>
        @include('admin.home-builder.partials.builder', [
            'platform' => 'app',
            'options' => $appOptions,
            'templates' => $appTemplates,
            'themes' => $appThemes,
        ])
    </div>

    <div id="hb-pane-web" @class(['d-none' => $activeBuilderTab !== 'web'])>
        @include('admin.home-builder.partials.builder', [
            'platform' => 'web',
            'options' => $webOptions,
            'templates' => $webTemplates,
            'themes' => $webThemes,
        ])
    </div>

    @include('admin.home-builder.partials.confirm-modal')
    @include('admin.home-builder.partials.image-modal')
    @include('admin.home-builder.partials.templates-modal')
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendor/sortablejs/sortable.min.js') }}" defer></script>
    <script src="{{ asset('assets/vendor/swiperjs/swiper-bundle.min.js') }}" defer></script>
    <script src="{{ asset('assets/vendor/cropperjs/js/cropper.min.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/home-builder.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/home-categories.js') }}" defer></script>
@endpush
