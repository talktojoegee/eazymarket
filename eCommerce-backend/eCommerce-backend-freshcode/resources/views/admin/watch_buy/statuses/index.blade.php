@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_content']['route']['statuses']['sub_active'] ?? '',
])

@section('title', __('labels.watch_buy_statuses'))

@section('header_data')
    @php
        $page_title = __('labels.watch_buy_statuses');
        $page_pretitle = __('labels.watch_buy_status_gallery');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.watch_buy_statuses'), 'url' => null],
    ];
    $deleteRoute = fn ($status) => route('admin.watch-and-buy.statuses.destroy', $status);
    $productRoute = fn ($product) => route('admin.products.show', $product->id);
@endphp

@section('admin-content')
    <div class="card mb-4">
        <div class="card-header watch-buy-status-toolbar">
            <div>
                <h3 class="card-title">{{ __('labels.watch_buy_status_gallery') }}</h3>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
            <div class="watch-buy-status-toolbar-actions">
                <form method="get" class="watch-buy-status-search" data-status-search-form data-error-message="{{ __('labels.something_went_wrong') }}">
                    <label class="visually-hidden" for="watch-buy-status-search">{{ __('labels.watch_buy_search_seller_profiles') }}</label>
                    <input class="form-control" id="watch-buy-status-search" type="search" name="search" value="{{ request('search') }}" placeholder="{{ __('labels.watch_buy_search_seller_profiles') }}">
                    <button class="btn btn-outline-secondary watch-buy-status-search-clear {{ request('search') ? '' : 'd-none' }}" type="button">
                        <i class="ti ti-x me-1"></i>{{ __('labels.clear') }}
                    </button>
                    <button class="btn btn-outline-primary" type="submit" aria-label="{{ __('labels.search') }}"><i class="ti ti-search"></i></button>
                </form>
                @if($createPermission && $watchBuyEnabled)
                    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#admin-watch-buy-status-composer">
                        <i class="ti ti-plus me-1"></i>{{ __('labels.watch_buy_add_status') }}
                    </button>
                @endif
            </div>
        </div>
        @unless($watchBuyEnabled)
            <div class="card-body">
                <div class="alert alert-warning mb-0">{{ __('labels.watch_buy_disabled') }}</div>
            </div>
        @endunless
    </div>

    <div data-status-results>
        @foreach($profiles as $profile)
            @include('watch_buy.statuses._profile_group', compact('profile', 'deletePermission', 'deleteRoute', 'productRoute'))
        @endforeach
        <div class="empty card py-5 {{ $profiles->isEmpty() ? '' : 'd-none' }}" data-status-empty>
            <div class="empty-icon"><i class="ti ti-clock-hour-4"></i></div>
            <p class="empty-title">{{ __('labels.watch_buy_no_active_statuses') }}</p>
            <p class="empty-subtitle text-secondary">{{ __('labels.watch_buy_no_active_statuses_hint') }}</p>
        </div>

        <div class="mt-3">{{ $profiles->links() }}</div>
    </div>

    @include('watch_buy.statuses._preview')

    @if($createPermission && $watchBuyEnabled)
        @include('watch_buy.statuses._composer', [
            'modalId' => 'admin-watch-buy-status-composer',
            'formAction' => route('admin.watch-and-buy.statuses.store'),
            'successUrl' => route('admin.watch-and-buy.statuses.index'),
            'isAdmin' => true,
        ])
    @endif
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/watch-buy-status.css') }}">
@endpush

@push('scripts')
    <script src="{{ hyperAsset('assets/js/watch-buy-status.js') }}" defer></script>
@endpush
