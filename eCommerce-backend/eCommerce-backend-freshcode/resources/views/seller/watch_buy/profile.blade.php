@extends('layouts.seller.app', [
    'page' => $menuSeller['watch_and_buy']['active'] ?? '',
    'sub_page' => ($statusManagementOnly ?? false)
        ? ($menuSeller['watch_and_buy']['route']['statuses']['sub_active'] ?? '')
        : ($menuSeller['watch_and_buy']['route']['profile']['sub_active'] ?? ''),
])

@section('title', ($statusManagementOnly ?? false) ? __('labels.watch_buy_statuses') : __('labels.watch_buy_profile'))

@section('header_data')
    @php $page_title = ($statusManagementOnly ?? false) ? __('labels.watch_buy_statuses') : __('labels.watch_buy_profile'); @endphp
@endsection

@push('scripts')
    @if(session('watch_buy_profile_required'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Toast.fire({ icon: 'warning', title: @json(session('watch_buy_profile_required')) });
            });
        </script>
    @endif
@endpush

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => ($statusManagementOnly ?? false) ? __('labels.watch_buy_statuses') : __('labels.watch_buy_profile'), 'url' => null],
    ];
    $suspended = $profile?->status === 'suspended';
    $deletePermission = $statusDeletePermission;
    $deleteRoute = fn ($status) => route('seller.watch-and-buy.statuses.destroy', $status);
    $productRoute = fn ($product) => route('seller.products.show', $product->id);
@endphp

@section('seller-content')
    @unless($statusManagementOnly ?? false)
        <div class="row row-cards">
            <div class="col-12">
                <form class="form-submit" method="post" enctype="multipart/form-data" action="{{ route('seller.watch-and-buy.profile.update') }}">
                    @csrf
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">{{ __('labels.watch_buy_profile') }}</h3>
                                <x-breadcrumb :items="$breadcrumbs"/>
                            </div>
                        </div>
                        <div class="card-body">
                            @if($suspended)
                                <div class="alert alert-warning d-flex align-items-start gap-3" role="alert">
                                    <span class="avatar bg-warning-lt text-warning flex-shrink-0">
                                        <i class="ti ti-user-pause fs-2"></i>
                                    </span>
                                    <div>
                                        <div class="fw-semibold">{{ __('labels.watch_buy_profile_suspended') }}</div>
                                        <div class="text-secondary mt-1">{{ __('labels.watch_buy_profile_suspended_message') }}</div>
                                        @if($profile->suspension_reason)
                                            <div class="bg-surface-secondary border rounded p-3 mt-3">
                                                <div class="text-secondary small text-uppercase fw-semibold">{{ __('labels.reason') }}</div>
                                                <div class="mt-1">{{ $profile->suspension_reason }}</div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            @include('watch_buy._profile_fields', ['profile' => $profile, 'disabled' => $suspended])
                        </div>
                        @unless($suspended)
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">{{ __('labels.watch_buy_save_profile') }}</button>
                            </div>
                        @endunless
                    </div>
                </form>
            </div>
        </div>
    @endunless

    @if($profile && $statusViewPermission)
        <div class="card mt-4">
            <div class="card-header align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <span class="watch-buy-status-profile-ring">
                        @if($profile->photo_url)
                            <span class="avatar avatar-lg rounded-circle" style="background-image: url('{{ $profile->photo_url }}')"></span>
                        @else
                            <span class="avatar avatar-lg rounded-circle bg-primary-lt text-primary">{{ strtoupper(substr($profile->username, 0, 1)) }}</span>
                        @endif
                    </span>
                    <div>
                        <h3 class="card-title">{{ __('labels.watch_buy_your_statuses') }}</h3>
                        <div class="text-secondary small">{{ __('labels.watch_buy_statuses_expire_hint') }}</div>
                    </div>
                </div>
                <div class="card-actions">
                    <div class="d-flex gap-2 align-items-center">
                        @if($statusManagementOnly ?? false)
                            <form method="get" class="watch-buy-status-search" data-status-search-form data-error-message="{{ __('labels.something_went_wrong') }}">
                                <label class="visually-hidden" for="seller-watch-buy-status-search">{{ __('labels.search') }}</label>
                                <input class="form-control" id="seller-watch-buy-status-search" type="search" name="search" value="{{ request('search') }}" placeholder="{{ __('labels.search') }}">
                                <button class="btn btn-outline-secondary watch-buy-status-search-clear {{ request('search') ? '' : 'd-none' }}" type="button">
                                    <i class="ti ti-x me-1"></i>{{ __('labels.clear') }}
                                </button>
                                <button class="btn btn-outline-primary" type="submit" aria-label="{{ __('labels.search') }}"><i class="ti ti-search"></i></button>
                            </form>
                        @endif
                        @if($statusCreatePermission && $watchBuyEnabled && ! $suspended)
                            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#seller-watch-buy-status-composer">
                                <i class="ti ti-plus me-1"></i>{{ __('labels.watch_buy_add_status') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-body" data-status-results>
                @unless($watchBuyEnabled)
                    <div class="alert alert-warning mb-0">{{ __('labels.watch_buy_disabled') }}</div>
                @else
                    @unless($statuses->isEmpty())
                        @php $profile->setRelation('statuses', $statuses->getCollection()); @endphp
                        @include('watch_buy.statuses._profile_group', [
                            'profile' => $profile,
                            'deletePermission' => $deletePermission,
                            'deleteRoute' => $deleteRoute,
                            'productRoute' => $productRoute,
                            'embedded' => true,
                        ])
                        <div class="mt-3">{{ $statuses->links() }}</div>
                    @endunless
                    <div class="empty py-4 {{ $statuses->isEmpty() ? '' : 'd-none' }}" data-status-empty>
                        <div class="empty-icon"><i class="ti ti-clock-hour-4"></i></div>
                        <p class="empty-title">{{ __('labels.watch_buy_no_active_statuses') }}</p>
                        <p class="empty-subtitle text-secondary">{{ __('labels.watch_buy_no_active_statuses_hint') }}</p>
                    </div>
                @endunless
            </div>
        </div>

        @if($statusCreatePermission && $watchBuyEnabled && ! $suspended)
            @include('watch_buy.statuses._composer', [
                'modalId' => 'seller-watch-buy-status-composer',
                'formAction' => route('seller.watch-and-buy.statuses.store'),
                'successUrl' => ($statusManagementOnly ?? false)
                    ? route('seller.watch-and-buy.statuses.index')
                    : route('seller.watch-and-buy.profile.edit'),
                'isAdmin' => false,
                'profileOptionsEndpoint' => null,
            ])
        @endif

        @include('watch_buy.statuses._preview')
    @endif
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/watch-buy-status.css') }}">
@endpush

@push('scripts')
    <script src="{{ hyperAsset('assets/js/watch-buy-status.js') }}" defer></script>
@endpush
