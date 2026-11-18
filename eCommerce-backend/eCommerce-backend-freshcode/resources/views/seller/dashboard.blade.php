@extends('layouts.seller.app', ['page' => $menuSeller['dashboard']['active'] ?? ""])

@section('title', __('labels.dashboard'))

@section('header_data')
    @php
        $page_title = __('labels.dashboard');
        $page_pretitle = __('labels.seller') . " " . __('labels.dashboard');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => '']
    ];
@endphp

@section('seller-content')
    @if($viewPermission ?? false)
        @if(($actionItems['total'] ?? 0) > 0)
            <div class="row row-cards mb-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">{{ __('labels.needs_your_attention') }}</h3></div>
                        <div class="list-group list-group-flush">
                            @foreach($actionItems['items'] as $item)
                                <a href="{{ route($item['action_route'], $item['action_params'] ?? []) }}" class="list-group-item list-group-item-action d-flex align-items-center">
                                    <span class="badge bg-{{ $item['severity'] === 'critical' ? 'red' : ($item['severity'] === 'warning' ? 'yellow' : 'blue') }}-lt me-2">{{ $item['count'] }}</span>
                                    <span>{{ $item['message'] }}</span>
                                    <i class="ti ti-chevron-right ms-auto text-muted"></i>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="mb-2 d-flex align-items-center">
            <h2 class="page-title mb-0">{{ __('labels.sales') }}</h2>
            <span class="badge bg-blue-lt ms-2">{{ __('labels.store_scoped') }}</span>
        </div>
        <div class="row row-deck row-cards mb-4">
            <div class="col-6 col-lg-3">
                <div class="card"><div class="card-body">
                    <div class="subheader">{{ __('labels.gross_sales') }}</div>
                    <div class="h1 mb-0 mt-1" id="ss-revenue">{{ $salesSummary['revenue'] }}</div>
                    <div class="text-secondary small mt-1" title="{{ __('labels.gross_sales_hint') }}">{{ $salesSummary['days'] }} {{ __('labels.days') }}</div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card"><div class="card-body">
                    <div class="subheader">{{ __('labels.avg_order_value') }}</div>
                    <div class="h1 mb-0 mt-1" id="ss-aov">{{ $salesSummary['aov'] }}</div>
                    <div class="text-secondary small mt-1">{{ __('labels.per_order') }}</div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card"><div class="card-body">
                    <div class="subheader">{{ __('labels.units_sold') }}</div>
                    <div class="h1 mb-0 mt-1" id="ss-units">{{ $salesSummary['units'] }}</div>
                    <div class="text-secondary small mt-1">{{ __('labels.delivered') }}</div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card"><div class="card-body">
                    <div class="subheader">{{ __('labels.orders') }}</div>
                    <div class="h1 mb-0 mt-1" id="ss-orders">{{ $salesSummary['orders'] }}</div>
                    <div class="text-secondary small mt-1">{{ $salesSummary['days'] }} {{ __('labels.days') }}</div>
                </div></div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.sales_trend') }}</h3>
                        <div class="ms-auto">
                            <div class="dropdown">
                                <a class="dropdown-toggle text-secondary sales-summary-period" href="#"
                                    data-bs-toggle="dropdown" data-period="30">{{ __('labels.last_30_days') }}</a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                    <a class="dropdown-item active" href="#" data-period="30">{{ __('labels.last_30_days') }}</a>
                                    <a class="dropdown-item" href="#" data-period="90">{{ __('labels.last_3_months') }}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body"><div id="seller-sales-chart" style="min-height:260px;"></div></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">{{ __('labels.sales_by_market') }}</h3></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <tbody>
                                    @forelse($salesByMarket['markets'] as $m)
                                        <tr>
                                            <td>{{ $m['name'] }}<div class="text-secondary small">{{ $m['orders'] }} {{ __('labels.orders') }}</div></td>
                                            <td class="text-end fw-medium">{{ $m['revenue'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td class="text-center text-muted py-3">{{ __('labels.no_data') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">{{ __('labels.top_selling_products') }}</h3></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('labels.product') }}</th>
                                        <th class="text-center">{{ __('labels.units_sold') }}</th>
                                        <th class="text-end">{{ __('labels.revenue') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($topProducts['products'] as $p)
                                        <tr>
                                            <td class="fw-medium">{{ $p['title'] }}</td>
                                            <td class="text-center">{{ $p['units'] }}</td>
                                            <td class="text-end">{{ $p['revenue'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center text-muted py-3">{{ __('labels.no_data') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-2 d-flex align-items-center">
            <h2 class="page-title mb-0">{{ __('labels.order_ops_and_products') }}</h2>
            <span class="badge bg-blue-lt ms-2">{{ __('labels.store_scoped') }}</span>
        </div>
        <div class="row row-deck row-cards mb-4">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">{{ __('labels.fulfillment_rate') }}</h3></div>
                    <div class="card-body">
                        <div class="d-flex align-items-baseline">
                            <div class="h1 mb-0">{{ $conversionRateData['rate'] ?? 0 }}%</div>
                            <div class="ms-2 text-{{ !empty($conversionRateData['is_increase']) ? 'green' : 'red' }} small">
                                {{ abs($conversionRateData['percentage_change'] ?? 0) }}%
                            </div>
                        </div>
                        <div class="text-secondary small mb-2">
                            {{ $conversionRateData['delivered_orders'] ?? 0 }}
                            {{ __('labels.delivered_out_of_total_orders') }}
                            {{ $conversionRateData['total_orders'] ?? 0 }}
                        </div>
                        <div class="progress progress-sm">
                            <div class="progress-bar bg-primary" style="width: {{ $conversionRateData['rate'] ?? 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.dead_stock') }}</h3>
                        <span class="badge bg-secondary-lt ms-2">{{ __('labels.seller_level') }}
                            <x-form-info-tooltip :title="__('labels.tooltip_seller_level_catalog')" placement="top"/>
                        </span>
                        <span class="ms-auto badge bg-yellow-lt">{{ $deadProducts['count'] }} {{ __('labels.total') }}</span>
                    </div>
                    <div class="card-body">
                        @forelse($deadProducts['products'] as $p)
                            <div class="d-flex align-items-center mb-1">
                                <i class="ti ti-package text-muted me-2"></i>
                                <span class="small text-truncate">{{ $p['title'] }}</span>
                            </div>
                        @empty
                            <div class="text-muted small">{{ __('labels.no_dead_stock') }}</div>
                        @endforelse
                        @if($deadProducts['count'] > count($deadProducts['products']))
                            <div class="text-secondary small mt-2">+{{ $deadProducts['count'] - count($deadProducts['products']) }} {{ __('labels.more') }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-2 d-flex align-items-center">
            <h2 class="page-title mb-0">{{ __('labels.customers_and_returns') }}</h2>
            <span class="badge bg-blue-lt ms-2">{{ __('labels.store_scoped') }}</span>
        </div>
        <div class="row row-deck row-cards mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.customer_insights') }}</h3>
                        <div class="ms-auto">
                            <div class="dropdown">
                                <a class="dropdown-toggle text-secondary customer-insights-period" href="#"
                                    data-bs-toggle="dropdown" data-period="30">{{ __('labels.last_30_days') }}</a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                    <a class="dropdown-item active" href="#" data-period="30">{{ __('labels.last_30_days') }}</a>
                                    <a class="dropdown-item" href="#" data-period="90">{{ __('labels.last_3_months') }}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="text-center p-2 rounded bg-primary-lt">
                                    <div class="h2 mb-0" id="sci-repeat-rate">{{ $customerInsights['repeat_rate'] }}%</div>
                                    <div class="text-muted small">{{ __('labels.repeat_rate') }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-2 rounded bg-secondary-lt">
                                    <div class="h2 mb-0" id="sci-total">{{ $customerInsights['total_customers'] }}</div>
                                    <div class="text-muted small">{{ __('labels.total_active_customers') }}</div>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3">
                        <div class="row">
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-primary badge-empty me-2"></span>
                                    <span class="small">{{ __('labels.new_customers') }}</span>
                                    <span class="ms-auto fw-bold" id="sci-new">{{ $customerInsights['new_customers'] }}</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-success badge-empty me-2"></span>
                                    <span class="small">{{ __('labels.returning_customers') }}</span>
                                    <span class="ms-auto fw-bold" id="sci-returning">{{ $customerInsights['returning_customers'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">{{ __('labels.return_analytics') }}</h3></div>
                    <div class="card-body">
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <div class="text-center p-2 rounded bg-danger-lt">
                                    <div class="h2 mb-0">{{ $returnAnalytics['return_rate'] }}%</div>
                                    <div class="text-muted small">{{ __('labels.return_rate') }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-2 rounded bg-secondary-lt">
                                    <div class="h2 mb-0">{{ $returnAnalytics['total_returns'] }}</div>
                                    <div class="text-muted small">{{ __('labels.total_returns') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-1">
                            <span class="small text-muted">{{ __('labels.fault_split') }}</span>
                            <span class="ms-auto small">
                                <span class="text-danger fw-bold">{{ $returnAnalytics['seller_fault'] }} {{ __('labels.seller_fault') }}</span>
                                <span class="text-muted"> / </span>
                                <span class="text-info fw-bold">{{ $returnAnalytics['customer_fault'] }} {{ __('labels.customer_fault') }}</span>
                            </span>
                        </div>
                        <div class="progress progress-separated mb-3">
                            <div class="progress-bar bg-danger" style="width: {{ $returnAnalytics['seller_fault_share'] }}%"></div>
                            <div class="progress-bar bg-info" style="width: {{ $returnAnalytics['customer_fault_share'] }}%"></div>
                        </div>
                        <div class="text-muted small mb-1">{{ __('labels.top_returned_products') }}</div>
                        @forelse($returnAnalytics['top_returned'] as $r)
                            <div class="d-flex align-items-center mb-1">
                                <span class="small text-truncate">{{ $r['title'] }}</span>
                                <span class="ms-auto badge bg-danger-lt">{{ $r['count'] }}</span>
                            </div>
                        @empty
                            <div class="text-muted small">{{ __('labels.no_data') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-2 d-flex align-items-center">
            <h2 class="page-title mb-0">{{ __('labels.earnings_and_wallet') }}</h2>
            <span class="badge bg-secondary-lt ms-2">{{ __('labels.account_level') }}
                <x-form-info-tooltip :title="__('labels.tooltip_account_level')" placement="top"/>
            </span>
        </div>
        <div class="row row-deck row-cards mb-4">
            <div class="col-6 col-lg-3">
                <div class="card"><div class="card-body">
                    <div class="subheader">{{ __('labels.wallet_balance') }}</div>
                    <div class="h1 mb-0 mt-1">{{ $currencyService->format($walletBalance) }}</div>
                    <div class="text-secondary small mt-1">{{ __('labels.withdrawable') }}</div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card"><div class="card-body">
                    <div class="subheader">{{ __('labels.blocked_balance') }}</div>
                    <div class="h1 mb-0 mt-1">{{ $currencyService->format($blockedBalance) }}</div>
                    <div class="text-secondary small mt-1">{{ __('labels.on_hold') }}</div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card"><div class="card-body">
                    <div class="subheader">{{ __('labels.unsettled_earnings') }}</div>
                    <div class="h1 mb-0 mt-1 text-warning">{{ $earnings['unsettled'] }}</div>
                    <div class="text-secondary small mt-1">{{ __('labels.awaiting_settlement') }}</div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card"><div class="card-body">
                    <div class="subheader">{{ __('labels.settled') }}</div>
                    <div class="h1 mb-0 mt-1 text-success">{{ $earnings['settled'] }}</div>
                    <div class="text-secondary small mt-1">{{ __('labels.paid_to_wallet') }}</div>
                </div></div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{asset('assets/vendor/apexcharts/dist/apexcharts.min.js')}}" defer></script>
    <script>
        var sellerDashboardData = {
            salesSummary: @json($salesSummary),
            topProducts: @json($topProducts)
        };
        var sellerDashboardDataUrl = "{{ route('seller.dashboard.data') }}";
    </script>
    <script src="{{hyperAsset('assets/js/seller-dashboard.js')}}" defer></script>
@endpush
