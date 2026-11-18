@extends('layouts.seller.app', [
    'page' => $menuSeller['watch_and_buy']['active'] ?? '',
    'sub_page' => $menuSeller['watch_and_buy']['route']['reels']['sub_active'] ?? '',
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
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.watch_buy_reels'), 'url' => null],
    ];
@endphp

@section('seller-content')
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
                                    <a href="{{ route('seller.watch-and-buy.reels.create') }}" class="btn btn-primary">
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
                <div class="card-body border-bottom py-3" data-watch-buy-filters data-table-id="seller-watch-buy-reels-table">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
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
                        <x-datatable id="seller-watch-buy-reels-table" :columns="$columns"
                                     route="{{ route('seller.watch-and-buy.reels.datatable') }}"
                                     :options="['order' => [[4, 'desc']], 'pageLength' => 10]"/>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/watch-buy-management.js') }}" defer></script>
@endpush
