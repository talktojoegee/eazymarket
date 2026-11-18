@extends('layouts.seller.app', ['page' => 'reports', 'sub_page' => 'return_report'])

@section('title', __('labels.return_report'))

@section('header_data')
    @php
        $page_title = __('labels.return_report');
        $page_pretitle = __('labels.reports');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.reports'), 'url' => null],
        ['title' => __('labels.return_report'), 'url' => null],
    ];
@endphp

@section('seller-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-body">
                    <form id="report-filter-form" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label" for="filter-date-range">{{ __('labels.date_range') }}</label>
                            <select class="form-select js-tom-select-static" id="filter-date-range" name="date_range">
                                <option value="today">{{ __('labels.today') }}</option>
                                <option value="yesterday">{{ __('labels.yesterday') }}</option>
                                <option value="last_7_days">{{ __('labels.last_7_days') }}</option>
                                <option value="last_30_days" selected>{{ __('labels.last_30_days') }}</option>
                                <option value="this_month">{{ __('labels.this_month') }}</option>
                                <option value="last_month">{{ __('labels.last_month') }}</option>
                                <option value="custom">{{ __('labels.custom_range') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-none" id="custom-date-from-wrap">
                            <label class="form-label" for="filter-date-from">{{ __('labels.from_date') }}</label>
                            <input type="date" class="form-control" id="filter-date-from" name="date_from">
                        </div>
                        <div class="col-md-2 d-none" id="custom-date-to-wrap">
                            <label class="form-label" for="filter-date-to">{{ __('labels.to_date') }}</label>
                            <input type="date" class="form-control" id="filter-date-to" name="date_to">
                        </div>
                        @if(count($markets) > 1)
                        <div class="col-md-3">
                            <label class="form-label" for="filter-market">{{ __('labels.market') }}</label>
                            <select class="form-select js-tom-select-static" id="filter-market" name="market_ids[]" multiple data-placeholder="{{ __('labels.all_markets') }}">
                                @foreach($markets as $market)
                                    <option value="{{ $market->id }}">{{ $market->name }} ({{ $market->currency_code }})</option>
                                @endforeach
                            </select>
                        </div>
                        @elseif(count($markets) === 1)
                        <input type="hidden" id="filter-market" name="market_ids[]" value="{{ $markets->first()->id }}">
                        @endif
                        <div class="col-md-3 d-flex gap-2">
                            <button type="button" id="btn-apply-filter" class="btn btn-primary w-100">
                                <i class="ti ti-filter me-1"></i>{{ __('labels.apply_filter') }}
                            </button>
                            <button type="button" id="btn-reset-filter" class="btn btn-outline-secondary">
                                <i class="ti ti-rotate"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="row row-cards mb-3">
                <div class="col-sm-4">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-primary text-white avatar">
                                        <i class="ti ti-rotate-2 fs-2"></i>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="text-secondary small">{{ __('labels.returns') }}</div>
                                    <div class="h2 mb-0 font-weight-bold" id="kpi-total-returns">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-danger text-white avatar">
                                        <i class="ti ti-alert-triangle fs-2"></i>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="text-secondary small">{{ __('labels.seller') }} Fault</div>
                                    <div class="h2 mb-0 font-weight-bold text-danger" id="kpi-seller-fault">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-secondary text-white avatar">
                                        <i class="ti ti-user fs-2"></i>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="text-secondary small">{{ __('labels.customer') }} Fault</div>
                                    <div class="h2 mb-0 font-weight-bold" id="kpi-customer-fault">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 mb-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('labels.return_report') }} — {{ __('labels.reasons') }}</h3>
                </div>
                <div class="card-body">
                    <div id="return-chart" style="min-height: 280px;"></div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.return_report') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                    <div class="card-actions">
                        <div class="row g-2">
                            <div class="col-auto">
                                <button class="btn btn-outline-primary" id="refresh">
                                    <i class="ti ti-refresh me-1"></i>{{ __('labels.refresh') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body border-bottom p-0">
                    <x-datatable id="report-table" :columns="$columns" :route="route('seller.reports.returns.datatable')" :exportOptions="$exportOptions"/>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendor/apexcharts/dist/apexcharts.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let chartInstance = null;
            const chartEl = document.querySelector('#return-chart');
            const datatableRoute = "{{ route('seller.reports.returns.datatable') }}";
            const chartRoute = "{{ route('seller.reports.returns.chart') }}";

            $('#filter-date-range').on('change', function () {
                const isCustom = this.value === 'custom';
                $('#custom-date-from-wrap').toggleClass('d-none', !isCustom);
                $('#custom-date-to-wrap').toggleClass('d-none', !isCustom);
                if (!isCustom) {
                    reloadTable();
                    loadChart();
                }
            });

            $('#filter-market').on('change', function () {
                reloadTable();
                loadChart();
            });

            function getFilterParams() {
                return $('#report-filter-form').serialize();
            }

            function loadChart() {
                if (!chartEl) {
                    return;
                }
                fetch(chartRoute + '?' + getFilterParams())
                    .then(response => response.json())
                    .then(data => {
                        if (data.summary) {
                            $('#kpi-total-returns').text(Number(data.summary.total_returns || 0).toLocaleString());
                            $('#kpi-seller-fault').text(Number(data.summary.seller_fault_count || 0).toLocaleString());
                            $('#kpi-customer-fault').text(Number(data.summary.customer_fault_count || 0).toLocaleString());
                        }

                        const defaultReason = "{{ __('labels.no_reason_provided') }}";
                        const rawReasons = Array.isArray(data.reasons) ? data.reasons : [];
                        const rawCounts = Array.isArray(data.counts) ? data.counts : [];

                        const sanitizedReasons = rawReasons.map(r => (r !== null && r !== undefined && String(r).trim() !== '') ? String(r) : defaultReason);
                        const sanitizedCounts = rawCounts.map(c => Number(c) || 0);

                        const hasData = sanitizedReasons.length > 0 && sanitizedCounts.some(c => c > 0);
                        const finalCategories = hasData ? sanitizedReasons : ["{{ __('labels.no_data') }}"];
                        const finalData = hasData ? sanitizedCounts : [0];

                        const options = {
                            series: [{
                                name: "{{ __('labels.returns') }}",
                                data: finalData
                            }],
                            chart: {
                                type: 'bar',
                                height: 280,
                                toolbar: { show: false }
                            },
                            plotOptions: {
                                bar: { horizontal: true, borderRadius: 4 }
                            },
                            dataLabels: {
                                enabled: true,
                                formatter: function (val) {
                                    return Number(val).toLocaleString();
                                }
                            },
                            xaxis: { categories: finalCategories },
                            colors: ['#d63939'],
                            tooltip: {
                                y: {
                                    formatter: function (val) {
                                        return Number(val).toLocaleString();
                                    }
                                }
                            }
                        };

                        if (chartInstance) {
                            chartInstance.updateOptions(options);
                        } else {
                            chartInstance = new ApexCharts(chartEl, options);
                            chartInstance.render();
                        }
                    });
            }

            function reloadTable() {
                const newUrl = datatableRoute + '?' + getFilterParams();
                if ($.fn.DataTable.isDataTable('#report-table')) {
                    $('#report-table').DataTable().ajax.url(newUrl).load();
                }
            }

            $('#btn-apply-filter').on('click', function () {
                reloadTable();
                loadChart();
            });

            $('#btn-reset-filter').on('click', function () {
                document.getElementById('report-filter-form').reset();
                $('#filter-date-range').val('last_30_days').trigger('change');
                $('#custom-date-from-wrap').addClass('d-none');
                $('#custom-date-to-wrap').addClass('d-none');
                reloadTable();
                loadChart();
            });

            $('#refresh').on('click', function () {
                reloadTable();
                loadChart();
            });

            loadChart();
        });
    </script>
@endpush
