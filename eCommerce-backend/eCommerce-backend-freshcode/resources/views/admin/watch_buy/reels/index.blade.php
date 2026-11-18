@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_content']['route']['reels']['sub_active'] ?? '',
])

@section('title', __('labels.watch_buy_reels'))

@section('header_data')
    @php
        $page_title = __('labels.watch_buy_reels');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.watch_buy_reels'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.watch_buy_reels') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                    <div class="card-actions">
                        <div class="row g-2">
                            @if($createPermission)
                                <div class="col-auto">
                                    <a href="{{ route('admin.watch-and-buy.reels.create') }}" class="btn btn-primary">
                                        <i class="ti ti-plus me-1"></i>{{ __('labels.watch_buy_add_reel') }}
                                    </a>
                                </div>
                            @endif
                            <div class="col-auto">
                                <button class="btn btn-outline-primary" id="refresh">
                                    <i class="ti ti-refresh me-1"></i>{{ __('labels.refresh') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body border-bottom py-3" data-watch-buy-filters data-table-id="watch-buy-reels-table">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label mb-1">{{ __('labels.profile') }}</label>
                            <select class="form-select js-tom-select-remote" data-watch-buy-filter="profile_id"
                                    data-endpoint="{{ route('admin.watch-and-buy.profiles.options', ['scope' => 'filter']) }}"
                                    data-placeholder="{{ __('labels.all') }}"
                                    data-preload="focus"
                                    data-preload-limit="15">
                                <option value="">{{ __('labels.all') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">{{ __('labels.source') }}</label>
                            <select class="form-select js-tom-select-static" data-watch-buy-filter="source" data-placeholder="{{ __('labels.all') }}">
                                <option value="">{{ __('labels.all') }}</option>
                                <option value="admin_direct">{{ __('labels.watch_buy_admin_direct') }}</option>
                                <option value="seller_behalf">{{ __('labels.watch_buy_seller_behalf') }}</option>
                                <option value="seller">{{ __('labels.seller') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">{{ __('labels.status') }}</label>
                            <select class="form-select js-tom-select-static" data-watch-buy-filter="status" data-placeholder="{{ __('labels.all') }}">
                                <option value="">{{ __('labels.all') }}</option>
                                @foreach(['published', 'unpublished', 'blocked'] as $status)
                                    <option value="{{ $status }}">{{ Str::headline($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn w-100" data-watch-buy-filter-reset>{{ __('labels.reset') }}</button>
                        </div>
                    </div>
                </div>
                <div class="card-table">
                    <div class="row w-full p-3">
                        <x-datatable id="watch-buy-reels-table" :columns="$columns"
                                     route="{{ route('admin.watch-and-buy.reels.datatable') }}"
                                     :options="['order' => [[6, 'desc']], 'pageLength' => 10]"/>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/watch-buy-management.js') }}" defer></script>
@endpush
