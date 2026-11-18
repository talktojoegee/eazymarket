@php
    use App\Enums\Order\OrderItemStatusEnum;
    use App\Enums\Order\OrderStatusEnum;
    use App\Enums\Order\RefundStatusEnum;
    use App\Enums\PaymentStatusEnum;
    use Carbon\Carbon;
    use Illuminate\Support\Str;

    $rp = $panel . '.orders.';
    $isSeller = $isSeller ?? ($panel === 'seller');

    if ($isSeller) {
        $baseOrder = $order['order'] ?? [];
        $order['currency'] = $baseOrder['currency'] ?? ($order['currency'] ?? null);

        $headOrderId   = $baseOrder['order_number'] ?? $order['order_number'] ?? $order['id'] ?? $order['order_id'] ?? '—';
        $headUuid      = $baseOrder['uuid'] ?? null;
        $headBilling   = $baseOrder['billing_name'] ?? '—';
        $headPayMethod = $baseOrder['payment_method'] ?? '—';
        $headPayStatus = $baseOrder['payment_status'] ?? '';
        $headCreated   = $baseOrder['created_at'] ?? $order['created_at'] ?? '—';
        $headRush      = !empty($baseOrder['is_rush_order']);
        $headFlagged   = false;
        $headTotal     = $order['total_price'] ?? 0;
        $orderStatusVal = (string) ($baseOrder['status'] ?? '');
    } else {
        $baseOrder = $order;
        $headOrderId   = $order['order_number'] ?? $order['id'];
        $headUuid      = $order['uuid'] ?? null;
        $headBilling   = $order['billing_name'] ?? '—';
        $headPayMethod = $order['payment_method'] ?? '—';
        $headPayStatus = $order['payment_status'] ?? '';
        $headCreated   = $order['created_at'] ?? '—';
        $headRush      = !empty($order['is_rush_order']);
        $headFlagged   = !empty($order['is_flagged']);
        $headTotal     = $order['final_total'] ?? 0;
        $orderStatusVal = (string) ($order['status'] ?? '');
    }
@endphp

@once
    @push('styles')
        <style>
            .order-overview-min-w-0 {
                min-width: 0;
            }

            .order-fulfillment-card .card-header {
                gap: .5rem;
                flex-wrap: wrap;
            }

            .order-summary > .d-flex {
                display: grid !important;
                grid-template-columns: minmax(0, 1fr) minmax(0, auto) minmax(7rem, auto);
                align-items: start;
                column-gap: .75rem;
            }

            .order-summary > .d-flex > :last-child {
                min-width: 0 !important;
                text-align: end !important;
            }

            .order-summary > .d-flex.order-summary-total-card {
                grid-template-columns: minmax(0, 1fr) minmax(7rem, auto);
                align-items: center;
            }

            .order-refund-accordion .accordion-button {
                font-size: .8125rem;
                line-height: 1.3;
            }

            .order-refund-accordion .accordion-button::after {
                display: none;
            }

            .order-refund-toggle i {
                transition: transform .2s ease;
            }

            .order-refund-accordion .accordion-button[aria-expanded="true"] .order-refund-toggle i {
                transform: rotate(180deg);
            }

            .order-workspace-switcher {
                width: fit-content;
                max-width: 100%;
                padding: .3rem;
                overflow-x: auto;
                border: 1px solid color-mix(in srgb, var(--tblr-border-color), transparent 12%);
                border-radius: .8rem;
                background: color-mix(in srgb, var(--tblr-bg-surface-secondary), var(--tblr-bg-surface) 35%);
                box-shadow: 0 .15rem .65rem rgba(24, 36, 51, .06);
            }

            .order-workspace-tabs {
                display: grid;
                grid-template-columns: repeat(var(--order-tab-count), minmax(10.5rem, 1fr));
                gap: .25rem;
            }

            .order-workspace-tabs .nav-item { min-width: 0; }

            .order-workspace-tab {
                min-width: 0;
                min-height: 3.15rem;
                display: grid;
                grid-template-columns: 2rem minmax(0, 1fr) auto;
                align-items: center;
                gap: .55rem;
                padding: .42rem .58rem;
                border: 1px solid transparent;
                border-radius: .6rem;
                color: var(--tblr-secondary);
                transition: color .16s ease, background-color .16s ease, border-color .16s ease, box-shadow .16s ease, transform .16s ease;
            }

            .order-workspace-tab:hover {
                border-color: color-mix(in srgb, var(--tblr-primary), transparent 78%);
                background: color-mix(in srgb, var(--tblr-bg-surface), transparent 18%);
                color: var(--tblr-body-color);
                transform: translateY(-1px);
            }

            .order-workspace-tab.active {
                border-color: color-mix(in srgb, var(--tblr-primary), transparent 68%);
                background: var(--tblr-bg-surface);
                color: var(--tblr-body-color);
                box-shadow: 0 .2rem .7rem rgba(24, 36, 51, .1), inset 0 -2px 0 var(--tblr-primary);
            }

            .order-workspace-tab-icon {
                width: 2rem;
                height: 2rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: .52rem;
                background: color-mix(in srgb, var(--tblr-secondary), transparent 90%);
                color: var(--tblr-secondary);
                font-size: 1rem;
                transition: color .16s ease, background-color .16s ease, transform .16s ease;
            }

            .order-workspace-tab.active .order-workspace-tab-icon {
                background: color-mix(in srgb, var(--tblr-primary), transparent 86%);
                color: var(--tblr-primary);
                transform: scale(1.04);
            }

            .order-workspace-tab-copy {
                min-width: 0;
                display: flex;
                flex-direction: column;
                gap: .08rem;
            }

            .order-workspace-tab-copy strong,
            .order-workspace-tab-copy small {
                overflow: hidden;
                line-height: 1.2;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .order-workspace-tab-copy strong { font-size: .78rem; }
            .order-workspace-tab-copy small { color: var(--tblr-secondary); font-size: .62rem; }
            .order-workspace-tab-meta { display: inline-flex; align-items: center; justify-content: flex-end; gap: .35rem; }
            .order-workspace-tab-count { min-width: 1.3rem; display: inline-flex; align-items: center; justify-content: center; padding: .2rem .35rem; font-size: .625rem; line-height: 1; }
            .order-workspace-tab-arrow { color: color-mix(in srgb, var(--tblr-secondary), transparent 28%); font-size: .7rem; transition: transform .16s ease, color .16s ease; }
            .order-workspace-tab.active .order-workspace-tab-arrow { color: var(--tblr-primary); transform: translateX(2px); }
            .order-workspace-tab:focus-visible { outline: 2px solid var(--tblr-primary); outline-offset: 2px; }

            @media (max-width: 991.98px) {
                .order-workspace-switcher { width: 100%; }
                .order-workspace-tabs { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }

            @media (max-width: 575.98px) {
                .order-workspace-tab { min-height: 3rem; grid-template-columns: 1.75rem minmax(0, 1fr) auto; gap: .4rem; padding: .38rem .42rem; }
                .order-workspace-tab-icon { width: 1.75rem; height: 1.75rem; font-size: .9rem; }
                .order-workspace-tab-copy strong {
                    display: -webkit-box;
                    font-size: .7rem;
                    white-space: normal;
                    -webkit-box-orient: vertical;
                    -webkit-line-clamp: 2;
                }
                .order-workspace-tab-copy small,
                .order-workspace-tab-arrow { display: none; }

                .order-fulfillment-card .card-actions {
                    width: 100%;
                    margin-inline-start: 0;
                }

                .order-summary > .d-flex {
                    grid-template-columns: minmax(0, 1fr) auto;
                }

                .order-summary > .d-flex > :nth-child(2):not(:last-child) {
                    grid-column: 1;
                    grid-row: 2;
                    font-size: .75rem;
                }

                .order-summary > .d-flex > :last-child {
                    grid-column: 2;
                    grid-row: 1 / span 2;
                }
            }

            @media (max-width: 399.98px) {
                .order-workspace-tabs { grid-template-columns: 1fr; }
            }
        </style>
    @endpush
@endonce

<div class="page-wrapper">
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-lg-auto">
                    <div class="page-pretitle">{{ $isSeller ? __('labels.seller') : __('labels.admin') }} · {{ __('labels.orders') }}</div>
                    <h2 class="page-title d-flex align-items-center flex-wrap gap-2">
                        <span>{{ __('labels.order') }} #{{ $headOrderId }}</span>
                        <span class="badge {{ fulfillmentStatusBadgeClass($order['fulfillment_status'] ?? null) }} text-uppercase"
                              title="{{ __('labels.fulfillment_status') }}">
                            {{ $order['fulfillment_status_label'] ?? Str::ucfirst(Str::replace('_', ' ', $order['fulfillment_status'] ?? '')) }}
                        </span>
                        <span class="badge {{ $paymentBadgeClass }} text-uppercase"
                              title="{{ __('labels.payment_status') }}">
                            {{ $headPayStatus ?: Str::ucfirst(Str::replace('_', ' ', $headPayStatus)) }}
                        </span>
                        @if($headRush)
                            <span class="badge bg-orange-lt text-orange">
                                <i class="ti ti-bolt me-1"></i>{{ __('labels.is_rush_order') ?? 'Rush' }}
                            </span>
                        @endif
                        @if($headFlagged)
                            <span class="badge bg-danger-lt text-white"
                                  title="{{ __("labels.".implode(', ', array_keys((array)($order['escalation_reasons'] ?? [])))) }}"
                                  data-bs-toggle="tooltip">
                                <i class="ti ti-alert-triangle me-1"></i>{{ __('labels.flagged') }}
                            </span>
                        @endif
                    </h2>
                    <div class="text-secondary small mt-1 d-flex flex-wrap gap-3">
                        <span><i class="ti ti-user me-1"></i>{{ $headBilling }}</span>
                        <span><i class="ti ti-credit-card me-1"></i>{{ Str::upper($headPayMethod) }}</span>
                        <span><i class="ti ti-clock me-1"></i>{{ $headCreated }}</span>
                        @if($isSeller && !empty($order['shipping_zone']['name']))
                            <span><i class="ti ti-map-pin me-1"></i>{{ $order['shipping_zone']['name'] }}</span>
                        @elseif($isSeller && !empty($order['store_location']['name']))
                            <span><i class="ti ti-map-pin me-1"></i>{{ $order['store_location']['name'] }}</span>
                        @endif
                        @if(!empty($headUuid))
                            <span class="font-monospace text-truncate" title="{{ $headUuid }}">
                                <i class="ti ti-hash me-1"></i>{{ Str::limit($headUuid, 24, '…') }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="col-auto ms-auto d-flex align-items-center gap-3">
                    <div class="text-end">
                        <div class="text-secondary small">{{ __('labels.total') }}</div>
                        <div class="h2 mb-0">{{ formatOrderMoney($headTotal, $order['currency'] ?? null) }}</div>
                    </div>
                </div>

                <div class="col-12 col-lg-auto d-print-none">
                    <div class="btn-list" id="{{ $isSeller ? 'sellerOrderActions' : 'adminOrderActions' }}" data-order-id="{{ $headOrderId }}">
                        @if(!$isSeller && ($refundPermission ?? false) && $order->payment_status != PaymentStatusEnum::PENDING())
                            <a href="{{ route('admin.orders.refund', ['id' => $order['id']]) }}" class="btn btn-sm">
                                <i class="ti ti-receipt-refund me-1"></i>{{ __('labels.refund') }}
                            </a>
                        @endif

                        @php $canReturn = $isSeller ? true : ($refundPermission ?? false); @endphp
                        @if($canReturn)
                            @php $openReturn = collect($returns ?? [])->firstWhere('is_final', false); @endphp
                            @if($openReturn)
                                <a href="{{ $openReturn['review_url'] }}" class="btn btn-sm">
                                    <i class="ti ti-arrow-back-up me-1"></i>{{ __('labels.return') ?? 'Return' }}
                                </a>
                            @elseif(!$isSeller && !empty($returnableItems))
                                <a href="{{ route($rp.'returns.create_page', ['id' => $order['id']]) }}" class="btn btn-sm">
                                    <i class="ti ti-arrow-back-up me-1"></i>{{ __('labels.return') ?? 'Return' }}
                                </a>
                            @endif
                        @endif

                        @if(($editPermission ?? false) && !empty($restockableItems))
                            <button type="button" class="btn btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#restockModal">
                                <i class="ti ti-rotate-2 me-1"></i>{{ __('labels.restock') ?? 'Restock' }}
                            </button>
                        @endif

                        @unless($isSeller)
                            <a href="{{ route('admin.orders.cancel', ['id' => $order['id']]) }}" class="btn btn-sm">
                                <i class="ti ti-ban me-1"></i>{{ __('labels.cancel_order') }}
                            </a>
                        @endunless

                        <a href="{{ route($rp.'index') }}" class="btn">
                            <i class="ti ti-arrow-left me-1"></i>{{ __('labels.back_to_orders') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">

            @php
                $owedRefundTotal = $isSeller ? 0 : collect($order['refunds'] ?? [])
                    ->where('status', RefundStatusEnum::OWED())
                    ->whereNull('settled_by_refund_id')
                    ->sum('amount');

                $terminalStatuses = [
                    OrderItemStatusEnum::CANCELLED(),
                    OrderItemStatusEnum::RETURNED(),
                    OrderItemStatusEnum::REFUNDED(),
                ];
                if ($isSeller) {
                    $sellerItems = collect($order['items'] ?? [])->map(function ($it) {
                        $oi = $it['orderItem'] ?? [];
                        return [
                            'status'        => $oi['status'] ?? '',
                            'quantity'      => (int) ($it['quantity'] ?? ($oi['quantity'] ?? 0)),
                            'title'         => $it['title'] ?? ($oi['title'] ?? ($it['product']['title'] ?? 'N/A')),
                            'product_id'    => (int) ($oi['product_id'] ?? ($it['product_id'] ?? 0)),
                            'variant_title' => $it['variant_title'] ?? ($oi['variant_title'] ?? ($it['variant']['title'] ?? null)),
                            'price'         => (float) ($oi['price'] ?? 0),
                            'subtotal'      => (float) ($oi['subtotal'] ?? 0),
                            'is_restocked'  => (bool) ($oi['is_restocked'] ?? false),
                            'cancellation_reason' => $oi['cancellation_reason'] ?? null,
                            'variant'       => ['image' => $it['variant']['image'] ?? null],
                            'product'       => ['main_image' => $it['product']['main_image'] ?? null],
                            'id'            => $oi['id'] ?? ($it['id'] ?? 0),
                            'attachments'   => $it['attachments'] ?? [],
                            'addons'        => $it['addons'] ?? [],
                        ];
                    });
                    $allItems = $sellerItems;
                } else {
                    $allItems = collect($order['items'] ?? []);
                }
                $cancelledHistoryItems = collect($cancelledItemHistory ?? []);
                $cancelledHistoryItemIds = $cancelledHistoryItems->pluck('source_item_id')->map(fn ($id) => (int) $id);
                $storedTerminalItems = $allItems->filter(fn ($itm) => in_array($itm['status'] ?? '', $terminalStatuses, true));
                $terminalItems = $storedTerminalItems
                    ->reject(fn ($itm) => (string) ($itm['status'] ?? '') === (string) OrderItemStatusEnum::CANCELLED()
                        && $cancelledHistoryItemIds->contains((int) ($itm['id'] ?? 0)))
                    ->concat($cancelledHistoryItems);

                $returnsCount = count($returns ?? []);
                $refundsCount = (!$isSeller && !empty($order['refunds'])) ? count($order['refunds']) : 0;
                $auditCount = isset($auditLogs) ? $auditLogs->count() : 0;
                $earningsAvailable = !empty($earnings) && !empty($earnings['seller_orders']);
            @endphp

            @if($owedRefundTotal > 0)
                <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                    <i class="ti ti-alert-triangle me-2 fs-3"></i>
                    <div class="flex-fill">
                        <div class="fw-bold">{{ __('labels.refund_owed_to_customer_title') }}</div>
                        <div class="small">
                            {!! __('labels.refund_owed_to_customer_body', [
                                'amount' => '<strong>' . formatOrderMoney($owedRefundTotal, $order['currency'] ?? null) . '</strong>',
                            ]) !!}
                        </div>
                    </div>
                </div>
            @endif

            @php
                $onlinePaymentPending = !$isSeller
                    && strtolower((string) $headPayMethod) !== strtolower((string) \App\Enums\Payment\PaymentTypeEnum::COD())
                    && strtolower((string) $headPayStatus) === strtolower((string) PaymentStatusEnum::PENDING());
            @endphp
            @if($onlinePaymentPending)
                <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                    <i class="ti ti-alert-triangle me-2 fs-2"></i>
                    <div class="flex-fill">
                        <div class="fw-bold">{{ __('labels.online_payment_pending_title') }}</div>
                        <div class="small">{{ __('labels.online_payment_pending_body') }}</div>
                    </div>
                </div>
            @endif

            <div class="order-workspace-switcher mb-3">
                <ul class="nav order-workspace-tabs" data-bs-toggle="tabs" role="tablist"
                    style="--order-tab-count: {{ 2 + ($earningsAvailable ? 1 : 0) + (isset($auditLogs) ? 1 : 0) }}">
                                <li class="nav-item" role="presentation">
                                    <a href="#tab-order-overview" class="nav-link order-workspace-tab active" data-bs-toggle="tab" role="tab"
                                       aria-controls="tab-order-overview" aria-selected="true">
                                        <span class="order-workspace-tab-icon"><i class="ti ti-package"></i></span>
                                        <span class="order-workspace-tab-copy">
                                            <strong>{{ __('labels.overview') }}</strong>
                                            <small>{{ __('labels.order_details') }}</small>
                                        </span>
                                        <span class="order-workspace-tab-meta">
                                            <i class="ti ti-chevron-right order-workspace-tab-arrow"></i>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a href="#tab-order-returns" class="nav-link order-workspace-tab" data-bs-toggle="tab" role="tab"
                                       aria-controls="tab-order-returns" aria-selected="false">
                                        <span class="order-workspace-tab-icon"><i class="ti ti-arrow-back-up"></i></span>
                                        <span class="order-workspace-tab-copy">
                                            <strong>{{ __('labels.returns_and_refunds') }}</strong>
                                            <small>{{ __('labels.returns') }} · {{ __('labels.refund') }}</small>
                                        </span>
                                        <span class="order-workspace-tab-meta">
                                            @if(($returnsCount + $refundsCount) > 0)
                                                <span class="badge bg-secondary-lt order-workspace-tab-count">{{ $returnsCount + $refundsCount }}</span>
                                            @endif
                                            <i class="ti ti-chevron-right order-workspace-tab-arrow"></i>
                                        </span>
                                    </a>
                                </li>
                                @if($earningsAvailable)
                                    <li class="nav-item" role="presentation">
                                        <a href="#tab-order-earnings" class="nav-link order-workspace-tab" data-bs-toggle="tab" role="tab"
                                           aria-controls="tab-order-earnings" aria-selected="false">
                                            <span class="order-workspace-tab-icon"><i class="ti ti-coin"></i></span>
                                            <span class="order-workspace-tab-copy">
                                                <strong>{{ __('labels.earnings_settlement') }}</strong>
                                                <small>{{ __('labels.settlement') }}</small>
                                            </span>
                                            <span class="order-workspace-tab-meta">
                                                <i class="ti ti-chevron-right order-workspace-tab-arrow"></i>
                                            </span>
                                        </a>
                                    </li>
                                @endif
                                @if(isset($auditLogs))
                                    <li class="nav-item" role="presentation">
                                        <a href="#tab-order-activity" class="nav-link order-workspace-tab" data-bs-toggle="tab" role="tab"
                                           aria-controls="tab-order-activity" aria-selected="false">
                                            <span class="order-workspace-tab-icon"><i class="ti ti-history"></i></span>
                                            <span class="order-workspace-tab-copy">
                                                <strong>{{ __('labels.activity_and_notes') }}</strong>
                                                <small>{{ __('labels.timeline') }} · {{ __('labels.notes') }}</small>
                                            </span>
                                            <span class="order-workspace-tab-meta">
                                                <span class="badge bg-secondary-lt order-workspace-tab-count">{{ $auditCount }}</span>
                                                <i class="ti ti-chevron-right order-workspace-tab-arrow"></i>
                                            </span>
                                        </a>
                                    </li>
                                @endif
                </ul>
            </div>
            <div class="row g-3">
                <div class="col-12 col-xl-8">


                    <div class="tab-content">
                        <div class="tab-pane fade active show" id="tab-order-overview" role="tabpanel">
                            <div class="row">
                                @forelse($fulfillments ?? [] as $fulfillment)
                                    @include('seller.orders.partials._fulfillment-card', ['fulfillment' => $fulfillment, 'order' => $order])
                                @empty
                                    <div class="col-12">
                                        <div class="alert alert-secondary mb-0">
                                            <i class="ti ti-package-off me-1"></i>{{ __('labels.no_fulfillment_groups') ?? 'No fulfilment groups for this order.' }}
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                            @include('seller.orders.partials._fulfillment-modal')

                            @if($terminalItems->isNotEmpty())
                                <div class="card mb-3">
                                    <div class="card-header py-2">
                                        <span class="badge bg-red-lt text-red text-uppercase">
                                            <i class="ti ti-ban me-1"></i>{{ __('labels.cancelled_items') }}
                                        </span>
                                    </div>
                                    <div class="list-group list-group-flush">
                                        @foreach($terminalItems as $cItem)
                                            @php
                                                $st = $cItem['status'] ?? '';
                                                $statusBadge = match($st) {
                                                    OrderItemStatusEnum::CANCELLED() => ['bg-red-lt text-red', 'ti-ban', __('labels.cancelled')],
                                                    OrderItemStatusEnum::RETURNED()  => ['bg-orange-lt text-orange', 'ti-arrow-back-up', __('labels.returned')],
                                                    OrderItemStatusEnum::REFUNDED() => ['bg-azure-lt text-azure', 'ti-receipt-refund', __('labels.refunded')],
                                                    default => ['bg-secondary-lt text-secondary', 'ti-circle', $st],
                                                };
                                            @endphp
                                            <div class="list-group-item d-flex align-items-center gap-3 px-3 py-2"
                                                 data-terminal-item-id="{{ $cItem['source_item_id'] ?? ($cItem['id'] ?? '') }}"
                                                 data-terminal-quantity="{{ $cItem['quantity'] ?? 0 }}">
                                                <span class="avatar avatar-md">
                                                    @if(!empty($cItem['variant']['image']))
                                                        <img src="{{ $cItem['variant']['image'] }}" alt="" class="avatar-img">
                                                    @elseif(!empty($cItem['product']['main_image']))
                                                        <img src="{{ $cItem['product']['main_image'] }}" alt="" class="avatar-img">
                                                    @else
                                                        <i class="ti ti-shirt text-secondary"></i>
                                                    @endif
                                                </span>
                                                <div class="flex-fill">
                                                    @php
                                                        $cProductId = (int) ($cItem['product_id'] ?? ($cItem['product']['id'] ?? 0));
                                                        $cProductUrl = $cProductId > 0
                                                            ? route(($isSeller ? 'seller' : 'admin') . '.products.show', ['id' => $cProductId])
                                                            : null;
                                                    @endphp
                                                    <div class="fw-medium">
                                                        @if($cProductUrl)
                                                            <a href="{{ $cProductUrl }}" class="text-reset text-decoration-underline-hover">
                                                                {{ $cItem['title'] ?? ($cItem['product']['title'] ?? 'N/A') }}
                                                            </a>
                                                        @else
                                                            {{ $cItem['title'] ?? ($cItem['product']['title'] ?? 'N/A') }}
                                                        @endif
                                                    </div>
                                                    @if(!empty($cItem['variant_title']))
                                                        <div class="text-secondary small">{{ $cItem['variant_title'] }}</div>
                                                    @endif
                                                    <div class="mt-1">
                                                        <span class="badge {{ $statusBadge[0] }}">
                                                            <i class="ti {{ $statusBadge[1] }} me-1"></i>{{ $statusBadge[2] }}
                                                        </span>
                                                        @if($st === OrderItemStatusEnum::CANCELLED())
                                                            <span class="text-secondary small ms-2">
                                                                {{ ($cItem['is_restocked'] ?? false) ? __('labels.restocked') : __('labels.not_restocked') }}
                                                            </span>
                                                        @endif
                                                        @if(!empty($cItem['cancellation_reason']))
                                                            <div class="text-secondary small mt-1">
                                                                <i class="ti ti-message-2 me-1"></i>{{ $cItem['cancellation_reason'] }}
                                                            </div>
                                                        @endif
                                                        @if(!empty($cItem['addons']))
                                                            <div class="mt-1">
                                                                <span class="text-secondary small me-1">{{ __('labels.addons') }}:</span>
                                                                @foreach($cItem['addons'] as $cAddon)
                                                                    <span class="badge bg-secondary-lt me-1">
                                                                        {{ trim(($cAddon['group']['title'] ?? '') . ' — ' . ($cAddon['item']['title'] ?? ''), ' —') }}
                                                                        @if(($cAddon['price'] ?? 0) > 0)
                                                                            · {{ formatOrderMoney($cAddon['price'], $order['currency'] ?? null) }}
                                                                        @endif
                                                                    </span>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                        @if(!empty($cItem['attachments']))
                                                            @php
                                                                $cGallery = 'order-item-attachments-' . (int) ($cItem['id'] ?? 0);
                                                                $cImageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];
                                                            @endphp
                                                            <div class="mt-1">
                                                                @foreach($cItem['attachments'] as $attachmentUrl)
                                                                    @php
                                                                        $cExtension = strtolower(pathinfo(parse_url($attachmentUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                                                                        $cIsImage = in_array($cExtension, $cImageExtensions, true);
                                                                    @endphp
                                                                    <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener"
                                                                       @if($cIsImage) data-fslightbox="{{ $cGallery }}" @endif
                                                                       class="badge bg-orange-lt text-decoration-none me-1">
                                                                        <i class="ti {{ $cIsImage ? 'ti-photo' : ($cExtension === 'pdf' ? 'ti-file-type-pdf' : 'ti-file-description') }} me-1"></i>
                                                                        {{ __('labels.prescription') }}{{ $cExtension ? ' ' . strtoupper($cExtension) : '' }}
                                                                    </a>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="text-secondary small text-nowrap">
                                                    {{ formatOrderMoney($cItem['price'] ?? 0, $order['currency'] ?? null) }}
                                                    × {{ $cItem['quantity'] ?? 0 }}
                                                </div>
                                                <div class="fw-medium text-nowrap" style="min-width: 80px; text-align: right;">
                                                    {{ formatOrderMoney($cItem['subtotal'] ?? 0, $order['currency'] ?? null) }}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="tab-pane fade" id="tab-order-returns" role="tabpanel">
                            @include('orders.partials.returns-section')

                            @if(!$isSeller && !empty($order['refunds']) && count($order['refunds']) > 0)
                                @php
                                    $totalIssued = collect($order['refunds'])
                                        ->where('status', RefundStatusEnum::ISSUED())
                                        ->sum('amount');
                                    $totalOwed = collect($order['refunds'])
                                        ->where('status', RefundStatusEnum::OWED())
                                        ->whereNull('settled_by_refund_id')
                                        ->sum('amount');
                                @endphp
                                <div class="card mb-3" data-order-refund-list>
                                    <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-3">
                                        <div>
                                            <h3 class="card-title mb-0">
                                                <i class="ti ti-receipt-refund me-1"></i>{{ Str::plural(__('labels.refund'), 2) }}
                                                <span class="badge bg-secondary-lt ms-1">{{ count($order['refunds']) }}</span>
                                            </h3>
                                            <div class="text-secondary small">{{ __('labels.view_details') }}</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-3 small">
                                            <div>
                                                <div class="text-secondary">{{ __('labels.issued') }}</div>
                                                <div class="fw-semibold text-success">{{ formatOrderMoney($totalIssued, $order['currency'] ?? null) }}</div>
                                            </div>
                                            @if($totalOwed > 0)
                                                <div>
                                                    <div class="text-secondary">{{ __('labels.owed') }}</div>
                                                    <div class="fw-semibold text-warning">{{ formatOrderMoney($totalOwed, $order['currency'] ?? null) }}</div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="list-group list-group-flush">
                                        @foreach($order['refunds'] as $refundIndex => $refund)
                                            @php
                                                $rStatus = $refund['status']->value ?? (string) $refund['status'];
                                                $isSettledOwe = $rStatus === (string) RefundStatusEnum::OWED()
                                                    && !empty($refund['settled_by_refund_id']);
                                                $statusBadge = $isSettledOwe
                                                    ? ['bg-azure-lt text-azure', 'ti-check', __('labels.settled') ?? 'Settled']
                                                    : match($rStatus) {
                                                        (string) RefundStatusEnum::ISSUED() => ['bg-green-lt text-green', 'ti-check', __('labels.issued')],
                                                        (string) RefundStatusEnum::OWED() => ['bg-yellow-lt text-yellow', 'ti-clock', __('labels.owed')],
                                                        (string) RefundStatusEnum::FAILED() => ['bg-red-lt text-red', 'ti-x', __('labels.failed')],
                                                        default => ['bg-secondary-lt text-secondary', 'ti-circle', $rStatus],
                                                    };
                                            @endphp
                                            <div class="list-group-item p-3" data-order-refund-item>
                                                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
                                                    <div class="d-flex align-items-center gap-3 order-overview-min-w-0">
                                                        <span class="avatar avatar-sm {{ $statusBadge[0] }} flex-shrink-0">
                                                            <i class="ti {{ $statusBadge[1] }}"></i>
                                                        </span>
                                                        <div class="order-overview-min-w-0">
                                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                                <span class="fw-semibold">{{ __('labels.refund') }} #{{ $refundIndex + 1 }}</span>
                                                                <span class="badge {{ $statusBadge[0] }}">{{ $statusBadge[2] }}</span>
                                                                <span class="badge bg-secondary-lt text-uppercase">{{ $refund['method'] }}</span>
                                                            </div>
                                                            <div class="text-secondary small mt-1">
                                                                {{ Carbon::parse($refund['created_at'])->format('M j, Y · H:i') }}
                                                                @if(!empty($refund['issuer']['name']))
                                                                    · {{ __('labels.issued_by') }} {{ $refund['issuer']['name'] }}
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex align-items-center justify-content-between justify-content-sm-end gap-3 flex-shrink-0">
                                                        <span class="fw-semibold text-nowrap">{{ formatOrderMoney($refund['amount'], $order['currency'] ?? null) }}</span>
                                                        <a href="#orderRefundDetailsModal" data-bs-toggle="modal"
                                                           class="small text-decoration-underline text-nowrap">
                                                            {{ __('labels.view_details') }}
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if($earningsAvailable)
                            <div class="tab-pane fade" id="tab-order-earnings" role="tabpanel">
                                @include('admin.orders.partials.earnings-section')
                            </div>
                        @endif

                        @if(isset($auditLogs))
                            <div class="tab-pane fade" id="tab-order-activity" role="tabpanel">
                                <div class="card mb-3">
                                    <div class="card-header py-2 d-flex align-items-center justify-content-between gap-3">
                                        <div>
                                            <h3 class="card-title mb-0">
                                                <i class="ti ti-history me-1"></i>{{ __('labels.activity_and_notes') }}
                                                @if($auditLogs->isNotEmpty())
                                                    <span class="badge bg-secondary-lt ms-1">{{ $auditLogs->count() }}</span>
                                                @endif
                                            </h3>
                                            <div class="text-secondary small">{{ __('labels.timeline') }}</div>
                                        </div>
                                        @if(!$isSeller && ($editPermission ?? false))
                                            <button type="button" class="btn btn-primary btn-sm"
                                                    data-bs-toggle="modal" data-bs-target="#adminAddNoteModal"
                                                    adminAddNoteSubmit="{{ route('admin.orders.add_note', $order['id']) }}">
                                                <i class="ti ti-plus me-1"></i>{{ __('labels.add_note') }}
                                            </button>
                                        @endif
                                    </div>
                                    @if($auditLogs->isEmpty())
                                        <div class="card-body text-center py-4">
                                            <i class="ti ti-note-off fs-2 d-block mb-2 text-secondary"></i>
                                            <div class="text-secondary">{{ __('labels.audit_log_empty') }}</div>
                                        </div>
                                    @else
                                        <div class="list-group list-group-flush" data-order-activity-list>
                                            @foreach($auditLogs as $log)
                                                @include('admin.orders.partials.audit-log-row', ['log' => $log])
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>


                <div class="col-12 col-xl-4">
                    <div class="position-sticky" style="top: 1rem;">

                        <div class="card mb-3">
                            <div class="card-header py-2 d-flex align-items-center justify-content-between">
                                <h3 class="card-title mb-0">
                                    <i class="ti ti-receipt me-1"></i>{{ __('labels.order_summary') }}
                                </h3>
                                <div>
                                    <span class="badge {{ $paymentBadgeClass }}">
                                        {{ Str::ucfirst($headPayStatus ?: 'Pending') }}
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                @if($isSeller)
                                    @php
                                        $sLiveQty   = $allItems->sum('quantity') - $storedTerminalItems->sum('quantity');
                                        $sSubtotal  = (float) ($order['gross_subtotal'] ?? 0);
                                        $sShipping  = (float) ($order['shipping_charge_applied'] ?? 0);
                                        $sTax       = (float) ($order['tax_amount'] ?? 0);
                                        $sPromo     = (float) ($order['promo_share'] ?? 0);
                                        $sTotal     = (float) ($order['total_price'] ?? 0);
                                        $sCancelledAmount = $allItems
                                            ->filter(fn ($it) => (string) ($it['status'] ?? '') === (string) OrderItemStatusEnum::CANCELLED())
                                            ->sum(fn ($it) => (float) ($it['subtotal'] ?? 0));
                                        $sOriginalTotal = $sTotal + $sCancelledAmount;
                                    @endphp
                                    <div class="table table-sm mb-0 order-summary">
                                        @if($sCancelledAmount > 0)
                                            <div class="d-flex justify-content-between py-1">
                                                <span class="text-secondary">{{ __('labels.original_total') }}</span>
                                                <span></span>
                                                <span class="text-end text-secondary" style="min-width: 100px;">{{ formatOrderMoney($sOriginalTotal, $order['currency'] ?? null) }}</span>
                                            </div>
                                        @endif
                                        <div class="d-flex justify-content-between py-1">
                                            <span class="text-secondary">{{ __('labels.subtotal') }}</span>
                                            <span>{{ $sLiveQty }} {{ __('labels.items') }}</span>
                                            <span class="text-end" style="min-width: 100px;">{{ formatOrderMoney($sSubtotal, $order['currency'] ?? null) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between py-1">
                                            <span class="text-secondary">{{ __('labels.shipping_handling') }}</span>
                                            <span></span>
                                            <span class="text-end" style="min-width: 100px;">{{ formatOrderMoney($sShipping, $order['currency'] ?? null) }}</span>
                                        </div>
                                        @if($sTax > 0)
                                            <div class="d-flex justify-content-between py-1">
                                                <span class="text-secondary">{{ __('labels.taxes') }}</span>
                                                <span></span>
                                                <span class="text-end" style="min-width: 100px;">{{ formatOrderMoney($sTax, $order['currency'] ?? null) }}</span>
                                            </div>
                                        @endif
                                        @if($sPromo > 0)
                                            <div class="d-flex justify-content-between py-1 text-secondary">
                                                <span>{{ __('labels.promo_discount') }}</span>
                                                <span></span>
                                                <span class="text-end" style="min-width: 100px;">- {{ formatOrderMoney($sPromo, $order['currency'] ?? null) }}</span>
                                            </div>
                                        @endif
                                        <div class="d-flex justify-content-between py-2 border-top mt-2 fw-bold fs-5">
                                            <span>{{ __('labels.total_price') }}</span>
                                            <span></span>
                                            <span class="text-end" style="min-width: 100px;">{{ formatOrderMoney($sTotal, $order['currency'] ?? null) }}</span>
                                        </div>
                                    </div>
                                @else
                                    @php
                                        $originalSummary = data_get($order, 'order_summary.converted_currency', []);
                                        $originalSubtotal = (float) (data_get($originalSummary, 'items_total') ?? $order['subtotal'] ?? 0);
                                        $originalShipping = (float) (data_get($originalSummary, 'delivery_charge') ?? $order['delivery_charge'] ?? 0);
                                        $originalPlatformFee = (float) (data_get($originalSummary, 'platform_fee') ?? $order['platform_fee'] ?? 0);
                                        $originalCodFee = (float) (data_get($originalSummary, 'cod_fee') ?? $order['cod_fee'] ?? 0);
                                        $originalPromo = (float) (data_get($originalSummary, 'promo_discount') ?? $order['promo_discount'] ?? 0);
                                        $originalWallet = (float) (data_get($originalSummary, 'wallet_amount_used') ?? $order['wallet_balance'] ?? 0);
                                        $originalTax = (float) (data_get($originalSummary, 'tax_total') ?? $order['tax_total'] ?? 0);
                                        $originalTotal = (float) (data_get($originalSummary, 'order_total') ?? $order['final_total'] ?? 0);
                                        $originalPayable = (float) (data_get($originalSummary, 'payable_amount') ?? $order['total_payable'] ?? 0);
                                        $hasUpdatedSummary = round($originalTotal, 2) !== round((float) ($order['final_total'] ?? 0), 2);
                                    @endphp
                                    <div class="table table-sm mb-0 order-summary" data-original-order-total="{{ $originalTotal }}">
                                        <div class="d-flex justify-content-between py-1">
                                            <span class="text-secondary">{{ __('labels.subtotal') }}</span>
                                            <span></span>
                                            <span class="text-end" style="min-width: 100px;">{{ formatOrderMoney($originalSubtotal, $order['currency'] ?? null) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between py-1">
                                            <span class="text-secondary">{{ __('labels.shipping_handling') }}</span>
                                            <span></span>
                                            <span class="text-end" style="min-width: 100px;">{{ formatOrderMoney($originalShipping, $order['currency'] ?? null) }}</span>
                                        </div>
                                        @if($originalPlatformFee > 0)
                                            <div class="d-flex justify-content-between py-1">
                                                <span class="text-secondary">{{ __('labels.platform_fee') }}</span>
                                                <span></span>
                                                <span class="text-end" style="min-width: 100px;">{{ formatOrderMoney($originalPlatformFee, $order['currency'] ?? null) }}</span>
                                            </div>
                                        @endif
                                        @if($originalCodFee > 0)
                                            <div class="d-flex justify-content-between py-1">
                                                <span class="text-secondary">{{ __('labels.cod_fee') }}</span>
                                                <span></span>
                                                <span class="text-end" style="min-width: 100px;">{{ formatOrderMoney($originalCodFee, $order['currency'] ?? null) }}</span>
                                            </div>
                                        @endif
                                        @if($originalTax > 0)
                                            <div class="d-flex justify-content-between py-1">
                                                <span class="text-secondary">{{ __('labels.taxes') }}</span>
                                                <span></span>
                                                <span class="text-end" style="min-width: 100px;">{{ formatOrderMoney($originalTax, $order['currency'] ?? null) }}</span>
                                            </div>
                                        @endif
                                        @if($originalPromo > 0)
                                            <div class="d-flex justify-content-between py-1 text-secondary">
                                                <span>{{ __('labels.promo_discount') }}</span>
                                                <span></span>
                                                <span class="text-end" style="min-width: 100px;">- {{ formatOrderMoney($originalPromo, $order['currency'] ?? null) }}</span>
                                            </div>
                                        @endif
                                        <div class="d-flex justify-content-between py-2 border-top mt-2 fw-bold">
                                            <span>{{ __('labels.total') }}</span>
                                            <span></span>
                                            <span class="text-end" style="min-width: 100px;">{{ formatOrderMoney($originalTotal, $order['currency'] ?? null) }}</span>
                                        </div>
                                        @if($originalWallet > 0)
                                            <div class="d-flex justify-content-between py-1 text-secondary">
                                                <span>{{ __('labels.wallet_used') }}</span>
                                                <span></span>
                                                <span class="text-end" style="min-width: 100px;">- {{ formatOrderMoney($originalWallet, $order['currency'] ?? null) }}</span>
                                            </div>
                                        @endif
                                        <div class="d-flex justify-content-between py-2 fw-bold fs-5">
                                            <span>{{ __('labels.total_payable') }}</span>
                                            <span></span>
                                            <span class="text-end" style="min-width: 100px;">{{ formatOrderMoney($originalPayable, $order['currency'] ?? null) }}</span>
                                        </div>

                                        @if($hasUpdatedSummary)
                                            <div class="d-flex order-summary-total-card justify-content-between rounded bg-blue-lt px-3 py-2 mt-2"
                                                 data-current-order-total="{{ $order['final_total'] ?? 0 }}">
                                                <div>
                                                    <div class="fw-semibold text-blue">{{ __('labels.updated_total') }}</div>
                                                    <div class="text-secondary small">
                                                        {{ collect($order['items'])->sum('quantity') - $storedTerminalItems->sum('quantity') }} {{ __('labels.items') }}
                                                    </div>
                                                </div>
                                                <span class="fw-bold fs-4 text-blue">{{ formatOrderMoney($order['final_total'], $order['currency'] ?? null) }}</span>
                                            </div>
                                        @endif
                                        @if(!empty($order['amount_paid']))
                                            <div class="d-flex justify-content-between py-2 border-top mt-2 fw-bold text-success">
                                                <span><i class="ti ti-check me-1"></i>{{ __('labels.paid') }}</span>
                                                <span></span>
                                                <span class="text-end" style="min-width: 100px;">{{ formatOrderMoney($order['amount_paid'], $order['currency'] ?? null) }}</span>
                                            </div>
                                        @endif
                                        @if(!empty($order['refunds']) && count($order['refunds']) > 0)
                                            <div class="d-flex justify-content-between py-2 border-top mt-2 fw-bold">
                                                <span>
                                                    <a href="#orderRefundDetailsModal" data-bs-toggle="modal"
                                                       class="text-reset text-decoration-underline"
                                                       aria-label="{{ __('labels.refund') }} {{ __('labels.details') }}">
                                                        {{ __('labels.refund') }}
                                                    </a>
                                                </span>
                                                <span></span>
                                                <span class="text-end" style="min-width: 100px;">- {{ formatOrderMoney($order['amount_refunded'], $order['currency'] ?? null) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between py-2 border-top mt-2 fw-bold">
                                                <span>{{ __('labels.net_payment') }}</span>
                                                <span></span>
                                                <span class="text-end" style="min-width: 100px;">{{ formatOrderMoney(($order['amount_paid'] - $order['amount_refunded']), $order['currency'] ?? null) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div class="card-footer d-flex justify-content-end">
                                <div class="d-flex flex-wrap gap-2">
                                    @unless($isSeller)
                                        <a href="{{route('admin.orders.invoice', ['id'=> $order['uuid']])}}" class="btn btn-dark btn-sm">
                                            <i class="ti ti-file-invoice"></i>{{ __('labels.invoice') }}
                                        </a>
                                        @if(($paymentAcceptPermission ?? false) && $order['payment_status'] == PaymentStatusEnum::PENDING())
                                            <button type="button" class="btn btn-warning btn-sm" id="btnMarkPaymentReceived" data-order-id="{{ $order['id'] }}">
                                                <i class="ti ti-cash me-1"></i>{{ __('labels.mark_payment_received') }}
                                            </button>
                                        @endif
                                        @if(($editPermission ?? false) && (string)($order['status'] ?? '') === (string) OrderStatusEnum::ARCHIVED())
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnReopenOrder"
                                                    data-reopen-url="{{ route('admin.orders.reopen', ['id' => $order['id']]) }}">
                                                <i class="ti ti-lock-open me-1"></i>{{ __('labels.reopen_order') }}
                                            </button>
                                        @endif
                                    @endunless
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h3 class="card-title mb-0">{{ __('labels.customer') }}</h3>
                                @unless($isSeller)
                                    <div class="dropdown">
                                        <button class="btn btn-ghost-secondary btn-sm" data-bs-toggle="dropdown">
                                            <i class="ti ti-dots-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="{{route('admin.customers.show', ['id' => $order['user_id']])}}">{{ __('labels.view_profile') }}</a>
                                        </div>
                                    </div>
                                @endunless
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    @unless($isSeller)
                                        <a href="{{route('admin.customers.show', ['id' => $order['user_id']])}}" class="text-decoration-none fw-medium">{{ $headBilling }}</a>
                                    @else
                                        <span class="fw-medium text-capitalize">{{ $headBilling }}</span>
                                    @endunless
                                </div>

                                <div class="mb-3">
                                    <div class="text-secondary small text-uppercase fw-medium mb-1">{{ __('labels.contact_information') }}</div>
                                    @if(!empty($baseOrder['email']))
                                        <a href="mailto:{{ $baseOrder['email'] }}" class="text-decoration-none d-block">{{ $baseOrder['email'] }}</a>
                                    @endif
                                    @if($isSeller && !empty($baseOrder['billing_phone']))
                                        <a href="tel:{{ $baseOrder['billing_phone'] }}" class="text-decoration-none d-block small">
                                            <i class="ti ti-phone me-1"></i>{{ $baseOrder['billing_phone'] }}
                                        </a>
                                    @endif
                                </div>

                                <div class="mb-3">
                                    <div class="text-secondary small text-uppercase fw-medium mb-1">{{ __('labels.shipping_address') }}</div>
                                    <div class="small">
                                        <div class="fw-medium">{{ $baseOrder['shipping_name'] ?? $headBilling }}</div>
                                        <div class="text-secondary">
                                            {{ $baseOrder['shipping_address_1'] ?? '' }}
                                            @if(!empty($baseOrder['shipping_address_2']))
                                                , {{ $baseOrder['shipping_address_2'] }}
                                            @endif<br>
                                            {{ $baseOrder['shipping_city'] ?? '' }}
                                            @if(!empty($baseOrder['shipping_state']))
                                                , {{ $baseOrder['shipping_state'] }}
                                            @endif
                                            {{ $baseOrder['shipping_zip'] ?? '' }}<br>
                                            {{ $baseOrder['shipping_country'] ?? '' }}
                                        </div>
                                        @if(!empty($baseOrder['shipping_phone']))
                                            <a href="tel:{{ $baseOrder['shipping_phone'] }}" class="text-decoration-none small">{{ $baseOrder['shipping_phone'] }}</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h3 class="card-title mb-0">{{ __('labels.notes') }}</h3>
                            </div>
                            <div class="card-body">
                                @php $orderNote = $isSeller ? ($baseOrder['order_note'] ?? null) : ($order['order_note'] ?? null); @endphp
                                @if(!empty($orderNote))
                                    <p class="text-secondary mb-0">{{ $orderNote }}</p>
                                @else
                                    <p class="text-secondary mb-0">{{ __('labels.no_notes_from_customer') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(!$isSeller && !empty($order['refunds']) && count($order['refunds']) > 0)
    <div class="modal modal-blur fade" id="orderRefundDetailsModal" tabindex="-1" role="dialog"
         aria-labelledby="orderRefundDetailsTitle" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <div>
                        <h4 class="modal-title" id="orderRefundDetailsTitle">{{ __('labels.refund') }} {{ __('labels.details') }}</h4>
                        <div class="text-secondary small">{{ count($order['refunds']) }} {{ Str::plural(__('labels.refund'), count($order['refunds'])) }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.close') }}"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="accordion accordion-flush order-refund-accordion" id="orderRefundAccordion" data-refund-accordion>
                        @foreach($order['refunds'] as $refundIndex => $refund)
                            @php
                                $refundStatus = $refund['status']->value ?? (string) $refund['status'];
                                $refundStatusBadge = match($refundStatus) {
                                    (string) RefundStatusEnum::ISSUED() => ['bg-green-lt text-green', __('labels.issued')],
                                    (string) RefundStatusEnum::OWED() => ['bg-yellow-lt text-yellow', __('labels.owed')],
                                    (string) RefundStatusEnum::FAILED() => ['bg-red-lt text-red', __('labels.failed')],
                                    default => ['bg-secondary-lt text-secondary', Str::headline($refundStatus)],
                                };
                                $refundItems = $refund['items'] ?? [];
                                $refundPanelId = 'orderRefundPanel' . (int) ($refund['id'] ?? $refundIndex);
                            @endphp
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed px-3 py-2" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#{{ $refundPanelId }}"
                                            aria-expanded="false" aria-controls="{{ $refundPanelId }}">
                                        <span class="d-flex align-items-center justify-content-between gap-3 w-100 me-2">
                                            <span>
                                                <span class="fw-semibold d-block">{{ __('labels.refund') }} #{{ $refundIndex + 1 }}</span>
                                                <span class="text-secondary d-block">{{ Carbon::parse($refund['created_at'])->format('M j, Y') }}</span>
                                                <span class="order-refund-toggle text-primary d-inline-flex align-items-center gap-1 mt-1">
                                                    {{ __('labels.view_details') }} <i class="ti ti-chevron-down"></i>
                                                </span>
                                            </span>
                                            <span class="text-end">
                                                <span class="fw-semibold d-block text-nowrap">{{ formatOrderMoney($refund['amount'], $order['currency'] ?? null) }}</span>
                                                <span class="badge {{ $refundStatusBadge[0] }}">{{ $refundStatusBadge[1] }}</span>
                                            </span>
                                        </span>
                                    </button>
                                </h2>
                                <div id="{{ $refundPanelId }}" class="accordion-collapse collapse" data-bs-parent="#orderRefundAccordion">
                                    <div class="accordion-body px-3 py-2">
                                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                                            <span class="badge bg-secondary-lt text-uppercase">{{ $refund['method'] }}</span>
                                            @if(!empty($refund['issuer']['name']))
                                                <span class="text-secondary">{{ __('labels.issued_by') }}: {{ $refund['issuer']['name'] }}</span>
                                            @endif
                                        </div>
                                        <div class="small mt-2">
                                            <span class="text-secondary">{{ __('labels.reason') }}:</span>
                                            {{ $refund['reason'] ?: __('labels.no_reason_provided') }}
                                        </div>
                                        @if(!empty($refundItems))
                                            <div class="small text-secondary mt-2">{{ __('labels.items_refunded') }}</div>
                                            <div class="border-top mt-1">
                                                @foreach($refundItems as $refundItem)
                                                    @php
                                                        $refundProductId = (int) ($refundItem['product_id'] ?? 0);
                                                        $refundProductUrl = $refundProductId > 0
                                                            ? route('admin.products.show', ['id' => $refundProductId])
                                                            : null;
                                                    @endphp
                                                    <div class="d-flex justify-content-between align-items-center gap-3 py-1">
                                                        <div class="order-overview-min-w-0">
                                                            @if($refundProductUrl)
                                                                <a href="{{ $refundProductUrl }}" class="fw-medium text-reset text-decoration-underline-hover">
                                                                    {{ $refundItem['title'] ?? '—' }}
                                                                </a>
                                                            @else
                                                                <span class="fw-medium">{{ $refundItem['title'] ?? '—' }}</span>
                                                            @endif
                                                            <span class="text-secondary small">× {{ $refundItem['pivot']['quantity'] ?? 0 }}</span>
                                                        </div>
                                                        <span class="fw-medium text-nowrap">{{ formatOrderMoney($refundItem['pivot']['amount'] ?? 0, $order['currency'] ?? null) }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('labels.close') }}</button>
                </div>
            </div>
        </div>
    </div>
@endif

@if(($editPermission ?? false) && !empty($restockableItems))
    @include('admin.orders.partials.restock-modal')
@endif
@if(!$isSeller && ($editPermission ?? false))
    @include('admin.orders.partials.add-note-modal')
    @include('admin.orders.partials.admin-action-modals')
@endif
@if($isSeller)
    @include('seller.orders.partials.order-cancel-item-modal')
@endif
