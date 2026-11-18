@extends('layouts.seller.app', ['page' => $menuSeller['earnings']['active'] ?? ""])

@section('title', __('labels.settlement_history'))

@section('header_data')
    @php
        $page_title = __('labels.settlement_history');
        $page_pretitle = __('labels.admin') . " " . __('labels.settlement_history');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.commission_settlements'), 'url' => route('seller.commissions.index')],
        ['title' => __('labels.settlement_history'), 'url' => '']
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper">
        <div class="page-body">
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('labels.settlement_history') }}</h3>
                            <div class="card-actions">
                                <div class="row g-2">
                                    <div class="col-auto">
                                        <a href="{{ route('seller.commissions.index') }}" class="btn btn-outline-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-left">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path d="M5 12l14 0"/>
                                                <path d="M5 12l6 6"/>
                                                <path d="M5 12l6 -6"/>
                                            </svg>
                                            {{ __('labels.back_to_unsettled') }}
                                        </a>
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-outline-primary" id="statementBtn">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                 stroke-linecap="round" stroke-linejoin="round"
                                                 class="icon icon-tabler icons-tabler-outline icon-tabler-file-invoice">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path d="M14 3v4a1 1 0 0 0 1 1h4"/>
                                                <path d="M5 21v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2h-3"/>
                                                <path d="M9 17h6"/>
                                                <path d="M9 13h6"/>
                                            </svg>
                                            {{ __('labels.statement') }}
                                        </button>
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
                        <div class="card-body">
                            <div class="row w-full p-3">
                                <x-datatable id="commissions-history-table" :columns="$columns"
                                             route="{{ route('seller.commissions.history.datatable') }}"
                                             :export-options="['module' => 'earnings', 'rowId' => 'id', 'context' => ['variant' => 'history']]"
                                             :options="['order' => [[0, 'desc']],'pageLength' => 10,]"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('partials.commission-detail-modal')
@endsection

@push('scripts')
    <script src="{{hyperAsset('assets/js/manage-seller.js')}}" defer></script>
@endpush
