@extends('layouts.admin.app', ['page' => $menuAdmin['seller_management']['active'] ?? "", 'sub_page' => $menuAdmin['seller_management']['route']['earning_settlement']['sub_active']])

@section('title', __('labels.payment_return_settlements'))

@section('header_data')
    @php
        $page_title = __('labels.payment_return_settlements');
        $page_pretitle = __('labels.admin') . " " . __('labels.payment_return_settlements');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.payment_return_settlements'), 'url' => '']
    ];
@endphp

@section('admin-content')
    <div class="page-wrapper">
        <div class="page-body">
            @include('partials.earnings-summary', ['isAdmin' => true])
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('labels.earning_deductions') }}</h3>
                            <div class="card-actions">
                                <div class="row g-2 align-items-center">
                                    <div class="col-auto">
                                        <select class="form-select" id="dateRangeFilter" autocomplete="off">
                                            <option value="">{{ __('labels.all_time') }}</option>
                                            <option value="7">{{ __('labels.last_7_days') }}</option>
                                            <option value="15">{{ __('labels.last_15_days') }}</option>
                                            <option value="30">{{ __('labels.last_30_days') }}</option>
                                            <option value="90">{{ __('labels.last_90_days') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <select class="form-select"  id="storeFilter" placeholder="{{ __('labels.store') }}" autocomplete="off"></select>
                                    </div>
                                    <div class="col-auto">
                                        <a href="{{ route('admin.commissions.history') }}"
                                           class="btn btn-outline-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                 stroke-linecap="round" stroke-linejoin="round"
                                                 class="icon icon-tabler icons-tabler-outline icon-tabler-history">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path d="M12 8l0 4l2 2"/>
                                                <path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/>
                                            </svg>
                                            {{ __('labels.settlement_history') }}
                                        </a>
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
                                    @if($settlePermission)
                                    <div class="col-auto">
                                        <button class="btn btn-success" id="run-auto-settle-btn"
                                                data-url="{{ route('admin.commissions.auto-settle.run') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                 stroke-linecap="round" stroke-linejoin="round"
                                                 class="icon me-1 icon-tabler icons-tabler-outline icon-tabler-player-play">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path d="M7 4v16l13 -8z"/>
                                            </svg>
                                            {{ __('labels.run_auto_settle_now') }}
                                        </button>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row w-full p-3">
                                <ul class="nav nav-tabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#tab-credits" role="tab"
                                           aria-selected="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                 stroke-linecap="round" stroke-linejoin="round"
                                                 class="icon me-2 icon-tabler icons-tabler-outline icon-tabler-bell-dollar">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path
                                                    d="M13 17h-9a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6a2 2 0 1 1 4 0a7 7 0 0 1 3.911 5.17"/>
                                                <path d="M9 17v1a3 3 0 0 0 4.02 2.822"/>
                                                <path d="M21 15h-2.5a1.5 1.5 0 0 0 0 3h1a1.5 1.5 0 0 1 0 3h-2.5"/>
                                                <path d="M19 21v1m0 -8v1"/>
                                            </svg>{{ __('labels.payouts') }}</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link" data-bs-toggle="tab" href="#tab-debits" role="tab"
                                           aria-selected="false">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                 stroke-linecap="round" stroke-linejoin="round"
                                                 class="icon me-2 icon-tabler icons-tabler-outline icon-tabler-bell-minus">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path
                                                    d="M12.5 17h-8.5a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3c.047 .386 .149 .758 .3 1.107"/>
                                                <path d="M9 17v1a3 3 0 0 0 3.504 2.958"/>
                                                <path d="M16 19h6"/>
                                            </svg>{{ __('labels.returns_deductions') }}</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link" data-bs-toggle="tab" href="#tab-sellers" role="tab"
                                           aria-selected="false">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                 stroke-linecap="round" stroke-linejoin="round"
                                                 class="icon me-2 icon-tabler icons-tabler-outline icon-tabler-users-group">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path d="M10 13a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/>
                                                <path d="M8 21v-1a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v1"/>
                                                <path d="M15 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/>
                                                <path d="M17 10h2a2 2 0 0 1 2 2v1"/>
                                                <path d="M5 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/>
                                                <path d="M3 13v-1a2 2 0 0 1 2 -2h2"/>
                                            </svg>{{ __('labels.by_seller') }}</a>
                                    </li>
                                </ul>
                                <div class="tab-content pt-3">
                                    <div class="tab-pane active" id="tab-credits" role="tabpanel">
                                        @if($settlePermission)
                                            <div class="mb-3">
                                                <button class="btn btn-primary" id="settle-all-btn">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                         class="icon icon-tabler icons-tabler-outline icon-tabler-cash">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                        <rect x="7" y="9" width="14" height="10" rx="2"/>
                                                        <circle cx="14" cy="14" r="2"/>
                                                        <path
                                                            d="M17 9v-2a2 2 0 0 0 -2 -2h-10a2 2 0 0 0 -2 2v6a2 2 0 0 0 2 2h2"/>
                                                    </svg>
                                                    {{ __('labels.settle_all_commissions') }}
                                                </button>
                                            </div>
                                        @endif
                                        <x-datatable id="commissions-table" :columns="$columns"
                                                     route="{{ route('admin.commissions.datatable') }}"
                                                     :export-options="['module' => 'earnings', 'rowId' => 'id', 'context' => ['variant' => 'credits']]"
                                                     :options="['order' => [[0, 'desc']],'pageLength' => 10,]"/>
                                    </div>
                                    <div class="tab-pane" id="tab-debits" role="tabpanel">
                                        @if($settlePermission)
                                            <div class="mb-3">
                                                <button class="btn btn-primary" id="settle-all-debits-btn">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                         class="icon icon-tabler icons-tabler-outline icon-tabler-cash">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                        <rect x="7" y="9" width="14" height="10" rx="2"/>
                                                        <circle cx="14" cy="14" r="2"/>
                                                        <path
                                                            d="M17 9v-2a2 2 0 0 0 -2 -2h-10a2 2 0 0 0 -2 2v6a2 2 0 0 0 2 2h2"/>
                                                    </svg>
                                                    {{ __('labels.settle_all_debits') }}
                                                </button>
                                            </div>
                                        @endif
                                        <x-datatable id="debits-table" :columns="$columnsReturn"
                                                     route="{{ route('admin.commissions.debits.datatable') }}"
                                                     :export-options="['module' => 'earnings', 'rowId' => 'id', 'context' => ['variant' => 'debits']]"
                                                     :options="['order' => [[0, 'desc']],'pageLength' => 10,]"/>
                                    </div>
                                    <div class="tab-pane" id="tab-sellers" role="tabpanel">
                                        <div class="text-secondary small mb-3">{{ __('labels.by_seller_hint') }}</div>
                                        <div class="mb-3">
                                            <select class="form-select w-auto d-inline-block" id="rollupScope" autocomplete="off">
                                                <option value="pending">{{ __('labels.pending_only') }}</option>
                                                <option value="all">{{ __('labels.all_sellers') }}</option>
                                            </select>
                                        </div>
                                        <x-datatable id="sellers-rollup-table" :columns="$sellerColumns"
                                                     route="{{ route('admin.commissions.sellers.datatable') }}"
                                                     :options="['order' => [],'pageLength' => 10,]"/>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SETTLEMENT CONFIRMATION MODAL -->
    <div class="modal modal-blur fade" id="settlementModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
            <div class="modal-content">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-status bg-primary"></div>
                <div class="modal-body text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         class="icon icon-tabler icons-tabler-outline icon-tabler-circle-dashed-check icon-lg text-primary">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M8.56 3.69a9 9 0 0 0 -2.92 1.95"/>
                        <path d="M3.69 8.56a9 9 0 0 0 -.69 3.44"/>
                        <path d="M3.69 15.44a9 9 0 0 0 1.95 2.92"/>
                        <path d="M8.56 20.31a9 9 0 0 0 3.44 .69"/>
                        <path d="M15.44 20.31a9 9 0 0 0 2.92 -1.95"/>
                        <path d="M20.31 15.44a9 9 0 0 0 .69 -3.44"/>
                        <path d="M20.31 8.56a9 9 0 0 0 -1.95 -2.92"/>
                        <path d="M15.44 3.69a9 9 0 0 0 -3.44 -.69"/>
                        <path d="M9 12l2 2l4 -4"/>
                    </svg>
                    <h3>{{ __('labels.confirm_settlement') }}</h3>
                    <div class="text-secondary">{{ __('labels.confirm_settlement_message') }}</div>
                    <div class="mt-3">
                        <div class="text-muted">{{ __('labels.order_id') }}: <span id="settlement-order-id"></span>
                        </div>
                        <div class="text-muted">{{ __('labels.product') }}: <span id="settlement-product"></span></div>
                        <div class="text-muted">{{ __('labels.your_commission') }}: <span
                                id="settlement-commission"></span></div>
                        <div class="text-muted">{{ __('labels.amount_to_pay') }}: <span id="settlement-amount"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="w-100">
                        <div class="row">
                            <div class="col">
                                <button class="btn w-100"
                                        data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                            </div>
                            <div class="col">
                                <button class="btn btn-primary w-100" id="confirmSettlement"
                                        data-bs-dismiss="modal">{{ __('labels.confirm') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SETTLE ALL CONFIRMATION MODAL -->
    <div class="modal modal-blur fade" id="settleAllModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
            <div class="modal-content">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-status bg-primary"></div>
                <div class="modal-body text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         class="icon icon-tabler icons-tabler-outline icon-tabler-circle-dashed-check icon-lg text-primary">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M8.56 3.69a9 9 0 0 0 -2.92 1.95"/>
                        <path d="M3.69 8.56a9 9 0 0 0 -.69 3.44"/>
                        <path d="M3.69 15.44a9 9 0 0 0 1.95 2.92"/>
                        <path d="M8.56 20.31a9 9 0 0 0 3.44 .69"/>
                        <path d="M15.44 20.31a9 9 0 0 0 2.92 -1.95"/>
                        <path d="M20.31 15.44a9 9 0 0 0 .69 -3.44"/>
                        <path d="M20.31 8.56a9 9 0 0 0 -1.95 -2.92"/>
                        <path d="M15.44 3.69a9 9 0 0 0 -3.44 -.69"/>
                        <path d="M9 12l2 2l4 -4"/>
                    </svg>
                    <h3>{{ __('labels.settle_all_commissions') }}</h3>
                    <div class="text-secondary">{{ __('labels.settle_all_commissions_message') }}</div>
                </div>
                <div class="modal-footer">
                    <div class="w-100">
                        <div class="row">
                            <div class="col">
                                <button class="btn w-100"
                                        data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                            </div>
                            <div class="col">
                                <button class="btn btn-primary w-100" id="confirmSettleAll"
                                        data-bs-dismiss="modal">{{ __('labels.confirm') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- SETTLE SELLER CONFIRMATION MODAL -->
    <div class="modal modal-blur fade" id="settleSellerModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
            <div class="modal-content">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-status bg-primary"></div>
                <div class="modal-body text-center py-4">
                    <h3>{{ __('labels.settle_seller') }}</h3>
                    <div class="text-secondary">{{ __('labels.settle_seller_message') }}</div>
                    <div class="mt-3">
                        <div class="text-muted">{{ __('labels.seller') }}: <span id="settle-seller-name"></span></div>
                        <div class="text-muted">{{ __('labels.net_owed') }}: <span id="settle-seller-net"></span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="w-100">
                        <div class="row">
                            <div class="col">
                                <button class="btn w-100" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                            </div>
                            <div class="col">
                                <button class="btn btn-primary w-100" id="confirmSettleSeller">{{ __('labels.confirm') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- HOLD ENTRY MODAL -->
    <div class="modal modal-blur fade" id="holdModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
            <div class="modal-content">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-status bg-warning"></div>
                <div class="modal-body text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         class="icon icon-tabler icons-tabler-outline icon-tabler-lock icon-lg text-warning">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z"/>
                        <path d="M12 16m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/>
                        <path d="M8 11v-4a4 4 0 1 1 8 0v4"/>
                    </svg>
                    <h3>{{ __('labels.hold_entry') }}</h3>
                    <div class="text-secondary">{{ __('labels.hold_entry_message') }}</div>
                    <div class="mt-3 text-start">
                        <label class="form-label">{{ __('labels.hold_reason') }} <span class="text-secondary">({{ __('labels.optional') }})</span></label>
                        <textarea class="form-control" id="holdReasonInput" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="w-100">
                        <div class="row">
                            <div class="col">
                                <button class="btn w-100" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                            </div>
                            <div class="col">
                                <button class="btn btn-warning w-100" id="confirmHold">{{ __('labels.hold') }}</button>
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
