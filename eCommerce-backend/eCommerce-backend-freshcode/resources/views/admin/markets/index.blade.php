@extends('layouts.admin.app', ['page' => $menuAdmin['markets']['active'] ?? '', 'sub_page' => $menuAdmin['markets']['route']['index']['sub_active'] ?? ''])

@section('title', __('labels.markets'))

@section('header_data')
    @php
        $page_title = __('labels.markets');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.markets'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.markets_list') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                    <div class="card-actions">
                        <div class="row g-2">
                            <div class="col-auto">
                                @if($createPermission ?? false)
                                    <div class="col text-end">
                                        <a href="{{ route('admin.markets.create') }}"
                                           class="btn btn-6 btn-outline-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                 stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                                <path d="M12 5l0 14"/>
                                                <path d="M5 12l14 0"/>
                                            </svg>
                                            {{ __('labels.add_market') }}
                                        </a>
                                    </div>
                                @endif
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-outline-primary" id="refresh">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                         stroke-linecap="round" stroke-linejoin="round"
                                         class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/>
                                        <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                                    </svg>
                                    {{ __('labels.refresh') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-table">
                    <div class="row w-full p-3">
                        <x-datatable id="markets-table" :columns="$columns"
                                     route="{{ route('admin.markets.datatable') }}"
                                     :options="['order' => [[0, 'desc']], 'pageLength' => 10]"/>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card" id="fx-rates-card"
                 data-rates-url="{{ route('admin.markets.fx.rates') }}"
                 data-refresh-url="{{ route('admin.markets.fx.refresh') }}">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.fx_rates_title') }}</h3>
                        <small class="text-muted" id="fx-meta">
                            @if($fx['base_currency'])
                                {{ __('labels.fx_meta_line', [
                                    'base'     => $fx['base_currency'],
                                    'provider' => $fx['provider'],
                                ]) }}
                                <span id="fx-last-updated"
                                      data-iso="{{ $fx['last_updated_at'] ?? '' }}"
                                      data-just-now="{{ __('labels.fx_just_now') }}">
                                    @if($fx['last_updated_at'])
                                        · {{ __('labels.fx_last_updated', [
                                            'at' => \Carbon\Carbon::parse($fx['last_updated_at'])->diffForHumans(),
                                        ]) }}
                                    @else
                                        · {{ __('labels.fx_never_refreshed') }}
                                    @endif
                                </span>
                            @else
                                {{ __('labels.fx_no_base_currency') }}
                            @endif
                        </small>
                    </div>
                    <div class="card-actions">
                        @if($editPermission ?? false)
                            <button type="button" class="btn btn-primary" id="fx-refresh-btn"
                                    data-loading-label="{{ __('labels.fx_refreshing') }}"
                                @disabled(empty($fx['base_currency']))>
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round"
                                     class="icon icon-tabler icons-tabler-outline icon-tabler-refresh me-1">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/>
                                    <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                                </svg>
                                <span class="fx-refresh-label">{{ __('labels.fx_refresh_now') }}</span>
                            </button>
                        @endif
                    </div>
                </div>
                <div class="card-body" id="fx-rates-body">
                    @include('admin.markets.partials.fx-rates-table', ['fx' => $fx])
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{ hyperAsset('assets/js/market.js') }}"></script>
@endpush
