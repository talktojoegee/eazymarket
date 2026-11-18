@php
    $panel = request()->is('seller/*') ? 'seller' : 'admin';
    $sellerId = $panel === 'seller' ? auth()->user()?->seller()?->id : null;
    $authRoute = $panel === 'seller' ? 'seller.broadcasting.auth' : 'admin.broadcasting.auth';
    $ordersRoute = $panel === 'seller' ? 'seller.orders.index' : 'admin.orders.index';
    $statusRoute = $panel === 'seller' ? 'seller.orders.popup_status' : 'admin.orders.popup_status';
    $realtime = app(\App\Services\BroadcastSettingsService::class)->clientConfig(route($authRoute));
@endphp

<link href="{{ hyperAsset('assets/css/panel-order-notifications.css') }}" rel="stylesheet">

<div class="modal modal-blur fade" id="panel-new-order-modal" tabindex="-1" aria-labelledby="panel-new-order-title" aria-hidden="true"
     data-panel="{{ $panel }}"
     data-user-id="{{ auth()->id() }}"
     data-seller-id="{{ $sellerId ?? '' }}"
     data-orders-url="{{ route($ordersRoute) }}"
     data-status-url="{{ route($statusRoute) }}"
     data-realtime-config="{{ json_encode($realtime) }}"
     data-count-one="{{ __('labels.new_order_popup_count_one') }}"
     data-count-many="{{ __('labels.new_order_popup_count_many') }}"
     data-heading-one="{{ __('labels.new_order_popup_heading_one') }}"
     data-heading-many="{{ __('labels.new_order_popup_heading_many') }}"
     data-order-label="{{ __('labels.order') }}"
     data-now-label="{{ __('labels.new_order_popup_just_now') }}"
     data-received-label="{{ __('labels.new_order_popup_received') }}"
     data-new-label="{{ __('labels.new_order_popup_new') }}"
     data-items-label="{{ __('labels.items') }}"
     data-more-label="{{ __('labels.new_order_popup_more') }}"
     data-all-orders-label="{{ __('labels.new_order_popup_all_orders') }}"
     data-latest-order-label="{{ __('labels.new_order_popup_latest_order') }}"
     data-customer-label="{{ __('labels.customer') }}"
     data-view-label="{{ __('labels.view') }}"
     data-product-placeholder="{{ asset('assets/images/product-placeholder.jpg') }}">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="panel-new-order-title">
                    <span class="panel-order-icon"><i class="ti ti-shopping-bag"></i></span>
                    <span class="panel-order-heading-copy">
                        <span id="panel-new-order-heading">{{ __('labels.new_order_popup_title') }}</span>
                        <span class="panel-order-subtitle">{{ __('labels.new_order_popup_subtitle') }}</span>
                    </span>
                </h3>
                <span class="panel-order-now">{{ __('labels.new_order_popup_just_now') }}</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="visually-hidden" id="panel-new-order-count" aria-live="polite"></p>
                <ul class="list-unstyled mb-0" id="panel-new-order-list" aria-live="polite"></ul>
                <a class="panel-order-more d-none" id="panel-new-order-more" href="{{ route($ordersRoute) }}"></a>
            </div>
            <div class="modal-footer">
                <label class="form-check panel-order-dismiss-choice">
                    <input class="form-check-input" type="checkbox" id="panel-new-order-dismiss-checked">
                    <span class="form-check-label">{{ __('labels.new_order_popup_dismiss_label') }}</span>
                </label>
                <a class="btn btn-outline-secondary" id="panel-new-order-view-orders" href="{{ route($ordersRoute) }}">
                    {{ __('labels.new_order_popup_all_orders') }}
                </a>
                <a class="btn btn-warning" id="panel-new-order-view-latest" href="{{ route($ordersRoute) }}">
                    {{ __('labels.new_order_popup_latest_order') }}
                </a>
            </div>
        </div>
    </div>
</div>

@push('script')
    <script src="{{ asset('assets/vendor/pusher/pusher.min.js') }}" defer></script>
    <script src="{{ asset('assets/vendor/laravel-echo/echo.iife.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/panel-order-notifications.js') }}" defer></script>
@endpush
