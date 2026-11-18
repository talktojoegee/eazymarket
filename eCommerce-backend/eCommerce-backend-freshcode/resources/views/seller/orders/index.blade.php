@php
    use App\Enums\DateRangeFilterEnum;
    use App\Enums\Order\FulfillmentStatusEnum;
    use App\Enums\Order\OrderStatusEnum;
    use Illuminate\Support\Str;
    $orderTypeParam = request()->query('type', '');
@endphp
@extends('layouts.seller.app', ['page' => $menuSeller['orders']['active'] ?? ""])

@section('title', __('labels.orders'))

@section('header_data')
    @php
        $page_title = __('labels.orders');
        $page_pretitle = __('labels.seller') . " " . __('labels.orders');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.orders'), 'url' => '']
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper">
        <div class="page-body">
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('labels.orders') }} <span class="order-count"></span></h3>
                            <div class="card-actions">
                                <div class="row g-2">
                                    <div class="col-auto">
                                        <select class="form-select text-capitalize" id="statusFilter">
                                            <option value="">{{ __('labels.fulfillment_status') }}</option>
                                            @foreach(FulfillmentStatusEnum::values() as $value)
                                                <option value="{{$value}}">{{Str::replace("_", " ", $value)}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <select class="form-select text-capitalize" id="lifecycleFilter">
                                            <option value="">{{ __('labels.order_status') }}</option>
                                            @foreach(OrderStatusEnum::values() as $value)
                                                <option value="{{$value}}">{{Str::replace("_", " ", $value)}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <select class="form-select" id="rangeFilter">
                                            <option value="">{{ __('labels.date_range') }}</option>
                                            @foreach(DateRangeFilterEnum::values() as $value)
                                                <option value="{{$value}}">{{Str::replace("_", " ", $value)}}</option>
                                            @endforeach
                                        </select>
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
                            <ul class="nav nav-tabs mb-3" id="order-method-tabs" role="tablist">
                                @foreach($methodTabs as $tab)
                                    <li class="nav-item" role="presentation">
                                        <a href="#orders-pane-{{ $tab['key'] }}" class="nav-link @if($loop->first) active @endif"
                                           data-bs-toggle="tab" role="tab">
                                            {{ $tab['label'] }}
                                            <span class="badge bg-secondary-lt ms-1 order-tab-count" data-method="{{ $tab['key'] }}"></span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="tab-content">
                                @foreach($methodTabs as $tab)
                                    <div class="tab-pane @if($loop->first) active show @endif" id="orders-pane-{{ $tab['key'] }}" role="tabpanel">
                                        <x-datatable id="orders-list-table-{{ $tab['key'] }}" :columns="$orderColumns"
                                                     route="{{ route('seller.orders.list_datatable', array_filter(['order_type' => $orderTypeParam, 'fulfillment_method' => $tab['key']])) }}"
                                                     :export-options="['module' => 'orders', 'rowId' => '_export_id']"
                                                     :options="['order' => [[2, 'desc']],'pageLength' => 10,]"/>
                                    </div>
                                @endforeach
                            </div>
                            @php
                                $orderItemsLabels = json_encode([
                                    'loading' => __('labels.loading'),
                                    'error' => __('labels.failed_to_load_items'),
                                ], JSON_UNESCAPED_UNICODE);
                            @endphp
                            <div id="order-items-meta" hidden
                                 data-items-url="{{ route('seller.orders.list_items', ['id' => '__ID__']) }}"
                                 data-items-labels="{{ $orderItemsLabels }}"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('seller.orders.partials.order-cancel-item-modal')

@endsection

@push('scripts')
    <script>
        window.__ORDER_STORE_CONFIG__ = {
            searchUrl: '{{ route("seller.stores.search") }}',
            initialStoreId: '{{ request()->query("store_id", "") }}',
        };
    </script>
    <script src="{{hyperAsset('assets/js/order.js')}}" defer></script>
    <script src="{{hyperAsset('assets/js/seller-orders.js')}}" defer></script>
@endpush
