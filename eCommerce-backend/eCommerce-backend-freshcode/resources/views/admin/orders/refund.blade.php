@extends('layouts.admin.app', ['page' => $menuAdmin['orders']['active'] ?? ''])

@section('title', __('labels.refund'))

@section('header_data')
    @php
        $page_title = __('labels.refund');
        $page_pretitle = __('labels.order') . ' #' . ($order->order_number ?? $order->id);
    @endphp
@endsection

@push('styles')
    <style>
        .refund-workflow .card-header { min-height: auto; }
        .refund-item-row { grid-template-columns: 3rem minmax(0, 1fr) auto auto auto; align-items: center; }
        .refund-item-row + .refund-item-row { border-top: 1px solid var(--tblr-border-color); }
        .refund-item-info { min-width: 0; }
        .refund-item-controls { display: flex; align-items: center; gap: .5rem; }
        .refund-item-total { min-width: 5.5rem; text-align: right; }
        .order-action-guide { border-color: color-mix(in srgb, var(--tblr-primary), transparent 72%); background: color-mix(in srgb, var(--tblr-primary-lt), var(--tblr-bg-surface) 45%); }
        .order-action-steps { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; }
        .order-action-step { display: inline-flex; align-items: center; gap: .35rem; color: var(--tblr-secondary); font-size: .75rem; font-weight: 600; }
        .order-action-step span { width: 1.4rem; height: 1.4rem; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--tblr-bg-surface); color: var(--tblr-primary); box-shadow: 0 0 0 1px color-mix(in srgb, var(--tblr-primary), transparent 72%); }
        @media (max-width: 767.98px) {
            .refund-item-row { grid-template-columns: 2.5rem minmax(0, 1fr) auto; align-items: start; }
            .refund-item-row .avatar { width: 2.5rem; height: 2.5rem; }
            .refund-item-info { grid-column: 2 / -1; }
            .refund-item-unit { grid-column: 2; }
            .refund-item-total { grid-column: 3; min-width: 0; }
            .refund-item-controls { grid-column: 1 / -1; justify-content: flex-end; flex-wrap: wrap; }
        }
    </style>
@endpush

@section('admin-content')
    @php
        use App\Enums\Order\OrderItemStatusEnum;

        $publicId       = $order->order_number ?? $order->id;
        $currencySym    = $currency['symbol'] ?? '';
        $currencyDec    = (int) ($currency['decimals'] ?? 2);

        $shippingTotal  = round((float) ($shippingRemaining ?? ($summary['shipping_total'] ?? 0)), $currencyDec);
        $shippingDone   = $shippingRefunded ?? 0;
        $maxRefundable  = round((float) $maxRefundable, $currencyDec);

        $buckets = [];
        foreach ($rows as $row) {
            $locationKey = $row['store_location_id'] ?: 'none';
            $fulfillmentKey = $row['fulfillment'] ?: 'unfulfilled';
            $key = ($row['store_id'] ?: 'none') . ':' . $locationKey . ':' . $fulfillmentKey;
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'store_name' => $row['store_name'] ?? __('labels.store'),
                    'store_location_name' => $row['store_location_name'] ?? null,
                    'fulfillment' => $row['fulfillment'] ?? '',
                    'is_fulfilled' => $row['is_fulfilled'] ?? false,
                    'requires_return' => $row['requires_return'] ?? false,
                    'has_approved_return' => false,
                    'items' => [],
                ];
            }
            if (!empty($row['has_approved_return'])) {
                $buckets[$key]['has_approved_return'] = true;
            }
            if (!empty($row['requires_return'])) {
                $buckets[$key]['requires_return'] = true;
            }
            $buckets[$key]['items'][] = $row;
        }
    @endphp

    <div class="refund-workflow">
                <div class="card card-sm order-action-guide mb-3" data-order-action-guide>
                    <div class="card-body p-2 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                        <div class="fw-semibold"><i class="ti ti-receipt-refund me-1 text-primary"></i>{{ __('labels.refund') }}</div>
                        <div class="order-action-steps">
                            <div class="order-action-step"><span>1</span>{{ __('labels.items') }}</div>
                            <div class="order-action-step"><span>2</span>{{ __('labels.summary') }}</div>
                            <div class="order-action-step"><span>3</span>{{ __('labels.confirm') }}</div>
                        </div>
                        <a href="{{ $backUrl }}" class="btn btn-sm btn-outline-secondary">
                            <i class="ti ti-arrow-left me-1"></i>{{ __('labels.back') }}
                        </a>
                    </div>
                </div>
                <form class="form-submit"
                      method="POST"
                      action="{{ $submitUrl }}"
                      data-redirect="{{ $backUrl }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-lg-8">
                            @php $rowIndex = 0; @endphp
                            @foreach($buckets as $bucket)
                                @php
                                    $fulfillment = $bucket['fulfillment'];
                                    $statusBadgeClass = match($fulfillment) {
                                        'fulfilled' => 'bg-green-lt text-green',
                                        'partially_fulfilled' => 'bg-blue-lt text-blue',
                                        'in_progress' => 'bg-azure-lt text-azure',
                                        'on_hold' => 'bg-yellow-lt text-yellow',
                                        default => 'bg-secondary-lt text-secondary',
                                    };
                                @endphp
                                <div class="card mb-3" data-fulfillment-group="{{ $fulfillment ?: 'unfulfilled' }}">
                                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
                                        <div>
                                            <h3 class="card-title mb-0">
                                                <i class="ti ti-building-store me-1"></i>{{ $bucket['store_name'] ?: __('labels.store') }}
                                            </h3>
                                            @if(!empty($bucket['store_location_name']))
                                                <div class="text-secondary small">
                                                    <i class="ti ti-map-pin me-1"></i>{{ $bucket['store_location_name'] }}
                                                </div>
                                            @endif
                                        </div>
                                        <span class="badge {{ $statusBadgeClass }} text-uppercase">
                                            <i class="ti ti-package me-1"></i>{{ Str::ucfirst(Str::replace('_', ' ', $fulfillment)) ?: __('labels.unfulfilled') }}
                                        </span>
                                    </div>

                                    @if($bucket['requires_return'] && empty($bucket['has_approved_return']))
                                        <div class="px-3 pt-3">
                                            <div class="alert alert-info py-2 small mb-0">
                                                <i class="ti ti-info-circle me-1"></i>
                                                {{ __('labels.refund_fulfilled_create_return_first') }}
                                            </div>
                                        </div>
                                    @endif

                                    <div class="card-body">
                                        @foreach($bucket['items'] as $row)
                                            @php
                                                $i = $rowIndex++;
                                                $isTerminal = in_array($row['status'], [
                                                    (string) OrderItemStatusEnum::CANCELLED(),
                                                    (string) OrderItemStatusEnum::RETURNED(),
                                                    (string) OrderItemStatusEnum::REFUNDED(),
                                                ], true);
                                                $disabled = !$row['refundable'] || $isTerminal;
                                                $statusBadge = match($row['status']) {
                                                    (string) OrderItemStatusEnum::CANCELLED() => ['bg-red-lt text-red', 'ti-ban', __('labels.cancelled')],
                                                    (string) OrderItemStatusEnum::RETURNED()  => ['bg-orange-lt text-orange', 'ti-arrow-back-up', __('labels.returned')],
                                                    (string) OrderItemStatusEnum::REFUNDED() => ['bg-azure-lt text-azure', 'ti-receipt-refund', __('labels.refunded')],
                                                    default => null,
                                                };
                                            @endphp
                                            <div class="refund-item-row d-grid gap-3 py-3 @class(['opacity-75' => $isTerminal])" data-refund-item-row>
                                                <span class="avatar avatar-md">
                                                    @if(!empty($row['image']))
                                                        <img src="{{ $row['image'] }}" alt="" class="avatar-img">
                                                    @else
                                                        <i class="ti ti-shirt text-secondary"></i>
                                                    @endif
                                                </span>
                                                <div class="refund-item-info">
                                                    <div class="fw-medium">{{ $row['title'] }}</div>
                                                    @if($row['variant_title'])
                                                        <div class="text-secondary small">{{ $row['variant_title'] }}</div>
                                                    @endif
                                                    <div class="text-secondary small mt-1">
                                                        {{ __('labels.ordered') }}: {{ $row['quantity'] }}
                                                        · {{ __('labels.shipped') }}: {{ $row['shipped_quantity'] }}
                                                        · {{ __('labels.delivered') }}: {{ $row['delivered_quantity'] }}
                                                        · {{ __('labels.remaining') }}: {{ $row['remaining_quantity'] }}
                                                        @if(!empty($row['has_approved_return']))
                                                            · {{ __('labels.returned') }}: {{ $row['return_qty'] }}
                                                        @endif
                                                    </div>
                                                    @if(!empty($row['addons']) && $row['addons']->isNotEmpty())
                                                        <div class="mt-1 small text-secondary">
                                                            <span class="me-1">{{ __('labels.addons') }}:</span>
                                                            @foreach($row['addons'] as $addon)
                                                                <span class="badge bg-secondary-lt me-1">
                                                                    {{ $addon->addonGroup?->title }} — {{ $addon->addonItem?->title }}
                                                                    @if((float) $addon->price > 0)
                                                                        · {{ formatOrderMoney($addon->price, $currency) }}
                                                                    @endif
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                    @if(!empty($row['has_approved_return']))
                                                        <div class="mt-1">
                                                            <span class="badge bg-blue-lt text-blue"><i class="ti ti-arrow-back-up me-1"></i>{{ __('labels.returned') ?? 'Returned' }}</span>
                                                            @if(count($row['approved_returns']) === 1 && $row['approved_returns'][0]['shipment_id'])
                                                                <span class="badge bg-azure-lt">{{ __('labels.shipment') }} #{{ $row['approved_returns'][0]['shipment_id'] }}</span>
                                                            @endif
                                                            @if(($row['return_shipping_charge'] ?? 0) > 0)
                                                                <span class="text-secondary small ms-1">
                                                                    {{ __('labels.return_shipping') ?? 'Return shipping' }}: {{ formatOrderMoney($row['return_shipping_charge'], $currency) }}
                                                                    <span class="text-muted">({{ __('labels.deduct_manually') ?? 'deduct manually' }})</span>
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    @if($statusBadge)
                                                        <div class="mt-1">
                                                            <span class="badge {{ $statusBadge[0] }}">
                                                                <i class="ti {{ $statusBadge[1] }} me-1"></i>{{ $statusBadge[2] }}
                                                            </span>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="refund-item-unit text-secondary text-nowrap">
                                                    {{ formatOrderMoney($row['price'], $currency) }}
                                                </div>
                                                <div class="refund-item-controls">
                                                    <input type="hidden" name="lines[{{ $i }}][order_item_id]" value="{{ $row['id'] }}">
                                                    @if(count($row['approved_returns']) === 1)
                                                        <input type="hidden" name="lines[{{ $i }}][return_id]" value="{{ $row['approved_returns'][0]['id'] }}">
                                                    @elseif(count($row['approved_returns']) > 1)
                                                        <select class="form-select form-select-sm js-refund-return" name="lines[{{ $i }}][return_id]" aria-label="{{ __('labels.select_return_for_refund') }}">
                                                            @foreach($row['approved_returns'] as $approvedReturn)
                                                                <option value="{{ $approvedReturn['id'] }}" data-quantity="{{ $approvedReturn['quantity'] }}">
                                                                    {{ __('labels.return') }} #{{ $approvedReturn['id'] }}
                                                                    @if($approvedReturn['shipment_id'])
                                                                        — {{ __('labels.shipment') }} #{{ $approvedReturn['shipment_id'] }}
                                                                    @endif
                                                                    @if($approvedReturn['tracking_number'])
                                                                        ({{ $approvedReturn['tracking_number'] }})
                                                                    @endif
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                    @php
                                                        $maxQty = (int) ($row['refund_max_qty'] ?? $row['quantity']);
                                                        $defaultQty = !empty($row['has_approved_return']) && !$disabled ? $maxQty : 0;
                                                    @endphp
                                                    <input type="number"
                                                           class="form-control text-center js-refund-qty @class(['bg-light' => $disabled])"
                                                           name="lines[{{ $i }}][refund_qty]"
                                                           value="{{ $defaultQty }}"
                                                           min="0"
                                                           max="{{ $maxQty }}"
                                                           style="width: 70px;"
                                                           data-max="{{ $maxQty }}"
                                                           data-unit-subtotal="{{ $row['unit_subtotal'] }}"
                                                           data-unit-promo="{{ $row['unit_promo_discount'] }}"
                                                           data-unit-tax="{{ $row['unit_tax_amount'] }}"
                                                           data-title="{{ $row['title'] }}"
                                                           @readonly($disabled)>
                                                    <span class="text-secondary small">/ <span class="js-refund-max">{{ $maxQty }}</span></span>
                                                    <label class="form-check form-check-inline m-0 ms-1" title="{{ __('labels.restock') }}">
                                                        <input type="checkbox" class="form-check-input"
                                                               name="lines[{{ $i }}][restock]" value="1"
                                                               @disabled($disabled)>
                                                        <span class="form-check-label small">{{ __('labels.restock') }}</span>
                                                    </label>
                                                </div>
                                                <div class="refund-item-total fw-semibold text-nowrap js-line-total">
                                                    {{ formatOrderMoney(0, $currency) }}
                                                </div>
                                            </div>
                                        @endforeach

                                    </div>
                                </div>
                            @endforeach

                            @if($shippingTotal > 0)
                                <div class="card mb-3">
                                    <div class="card-header py-2"><h3 class="card-title"><i class="ti ti-truck me-1"></i>{{ __('labels.refund_shipping') }}</h3></div>
                                    <div class="card-body p-3">
                                        <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3">
                                            <label class="form-check form-check-inline m-0">
                                                <input type="checkbox" class="form-check-input" id="refundShippingToggle">
                                                <span class="form-check-label">{{ __('labels.shipping') }}</span>
                                            </label>
                                            <div class="input-group" style="max-width: 220px;">
                                                <span class="input-group-text">{{ $currencySym }}</span>
                                                <input type="number"
                                                       step="0.01"
                                                       min="0"
                                                       max="{{ $shippingTotal }}"
                                                       class="form-control text-end"
                                                       id="refundShippingAmount"
                                                       name="shipping_refund"
                                                       value="0"
                                                       placeholder="{{ number_format($shippingTotal, $currencyDec) }}"
                                                       disabled>
                                            </div>
                                            <span class="text-secondary small">
                                                {{ __('labels.shipping_total_available', ['amount' => formatOrderMoney($shippingTotal, $currency)]) }}
                                                @if($shippingDone > 0)
                                                    <span class="d-block text-azure">{{ __('labels.shipping_already_refunded', ['amount' => formatOrderMoney($shippingDone, $currency)]) }}</span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="card mb-3">
                                <div class="card-body">
                                    <label class="form-label" for="refundReason">{{ __('labels.reason_for_refund') }} <span class="text-danger">*</span></label>
                                    <textarea id="refundReason" name="reason" rows="2" maxlength="1000" minlength="3" required
                                              class="form-control"
                                              placeholder="{{ __('labels.reason') }}"></textarea>
                                    <div class="invalid-feedback" data-field="reason"></div>
                                    <small class="text-secondary">{{ __('labels.only_visible_to_staff') }}</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card sticky-top" style="top: 1rem;">
                                <div class="card-header py-2"><h3 class="card-title"><i class="ti ti-calculator me-1"></i>{{ __('labels.summary') }}</h3></div>
                                <div class="card-body p-3">
                                    <div id="summaryNoItems" class="text-secondary small mb-3">
                                        {{ __('labels.no_items_selected') }}
                                    </div>
                                    <div id="summaryItemsRow" class="d-none">
                                        <div id="sumItemLines" class="mb-2 small"></div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-secondary">
                                                {{ __('labels.item_subtotal') }} <span id="sumItemCount">(0)</span>
                                            </span>
                                            <span class="fw-medium" id="sumItemSubtotal">{{ formatOrderMoney(0, $currency) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2" id="sumPromoRow" style="display:none !important;">
                                            <span class="text-secondary">{{ __('labels.promo_discount') ?? 'Promo discount' }}</span>
                                            <span class="fw-medium text-green" id="sumPromo">{{ formatOrderMoney(0, $currency) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-secondary">{{ __('labels.taxes_included') }}</span>
                                            <span class="fw-medium" id="sumTaxes">{{ formatOrderMoney(0, $currency) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2" id="sumShippingRow" style="display:none !important;">
                                            <span class="text-secondary">{{ __('labels.shipping') }}</span>
                                            <span class="fw-medium" id="sumShipping">{{ formatOrderMoney(0, $currency) }}</span>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between mb-3">
                                            <span class="fw-bold">{{ __('labels.refund_total') }}</span>
                                            <span class="fw-bold" id="sumRefundTotal">{{ formatOrderMoney(0, $currency) }}</span>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">{{ __('labels.refund_method') }}</label>
                                        <select class="form-select" disabled>
                                            <option>{{ __('labels.wallet') }}</option>
                                        </select>
                                        <small class="text-secondary">{{ __('labels.refund_gateway_coming_soon') }}</small>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label" for="refundAmountInput">{{ __('labels.refund_amount') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text">{{ $currencySym }}</span>
                                            <input type="number"
                                                   step="0.01"
                                                   min="0"
                                                   max="{{ $maxRefundable }}"
                                                   class="form-control text-end"
                                                   id="refundAmountInput"
                                                   name="refund_amount"
                                                   value="{{ number_format($amountRefundOwed > 0 ? $amountRefundOwed : 0, $currencyDec, '.', '') }}">
                                        </div>
                                        <small class="text-secondary d-block mt-1">
                                            {{ __('labels.available_for_refund', ['amount' => formatOrderMoney($maxRefundable, $currency)]) }}
                                        </small>
                                        @if($amountRefundOwed > 0)
                                            <div class="alert alert-warning py-2 small mt-2 mb-0">
                                                <i class="ti ti-alert-triangle me-1"></i>
                                                {!! __('labels.refund_owed_prefilled', [
                                                    'amount' => '<strong>' . formatOrderMoney($amountRefundOwed, $currency) . '</strong>',
                                                ]) !!}
                                            </div>
                                        @endif
                                    </div>

                                    <label class="form-check mt-3 mb-3">
                                        <input class="form-check-input" type="checkbox" name="notify_customer" value="1" checked>
                                        <span class="form-check-label">
                                            {{ __('labels.send_refund_notification') }}
                                        </span>
                                    </label>

                                    <button type="submit" class="btn btn-dark w-100" id="refundSubmitBtn">
                                        <i class="ti ti-receipt-refund me-1"></i>
                                        <span id="refundBtnLabel">{{ __('labels.refund') }} {{ formatOrderMoney($amountRefundOwed, $currency) }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal modal-blur fade" id="refundConfirmModal" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-status bg-dark"></div>
                                <div class="modal-header py-2">
                                    <h4 class="modal-title"><i class="ti ti-receipt-refund me-1"></i>{{ __('labels.confirm_refund') }}</h4>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-3">
                                    <div class="text-secondary small">{{ __('labels.confirm_refund_help') }}</div>
                                    <div id="refundConfirmLines" class="text-start small mt-3"></div>
                                    <div class="d-flex justify-content-between fw-bold border-top mt-2 pt-2">
                                        <span>{{ __('labels.refund_total') }}</span>
                                        <span id="refundConfirmTotal"></span>
                                    </div>
                                    <div class="text-secondary small mt-1">{{ __('labels.refund_method') }}: {{ __('labels.wallet') }}</div>
                                </div>
                                <div class="modal-footer py-2">
                                    <div class="w-100">
                                        <div class="row">
                                            <div class="col">
                                                <a href="#" class="btn btn-sm w-100" data-bs-dismiss="modal">{{ __('labels.cancel') }}</a>
                                            </div>
                                            <div class="col">
                                                <a href="#" class="btn btn-dark btn-sm w-100" id="refundConfirmBtn">{{ __('labels.confirm_refund') }}</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
    </div>

    <script>
        (function () {
            const sym = @json($currencySym);
            const dec = {{ $currencyDec }};
            const maxRefundable = {{ $maxRefundable }};
            const shippingTotal = {{ $shippingTotal }};
            const owedAmount = {{ $amountRefundOwed }};
            const fmt = (n) => sym + Number(n || 0).toFixed(dec);

            const qtyInputs = Array.from(document.querySelectorAll('.js-refund-qty'));
            document.querySelectorAll('.js-refund-return').forEach((select) => {
                select.addEventListener('change', () => {
                    const input = select.closest('.refund-item-controls').querySelector('.js-refund-qty');
                    const quantity = Number(select.selectedOptions[0].dataset.quantity);
                    input.max = quantity;
                    input.dataset.max = quantity;
                    input.value = quantity;
                    select.closest('.refund-item-controls').querySelector('.js-refund-max').textContent = quantity;
                    recompute();
                });
            });
            const lineTotals = Array.from(document.querySelectorAll('.js-line-total'));
            const itemCountEl = document.getElementById('sumItemCount');
            const itemSubtotalEl = document.getElementById('sumItemSubtotal');
            const promoRowEl = document.getElementById('sumPromoRow');
            const promoEl = document.getElementById('sumPromo');
            const taxesEl = document.getElementById('sumTaxes');
            const shippingRowEl = document.getElementById('sumShippingRow');
            const shippingEl = document.getElementById('sumShipping');
            const refundTotalEl = document.getElementById('sumRefundTotal');
            const itemsRow = document.getElementById('summaryItemsRow');
            const noItemsRow = document.getElementById('summaryNoItems');
            const amountInput = document.getElementById('refundAmountInput');
            const btnLabel = document.getElementById('refundBtnLabel');
            const submitBtn = document.getElementById('refundSubmitBtn');
            const sumItemLines = document.getElementById('sumItemLines');
            const reasonInput = document.getElementById('refundReason');
            const form = submitBtn.closest('form');

            let lastBreakdown = [];
            let lastAmt = 0;

            const shippingToggle = document.getElementById('refundShippingToggle');
            const shippingAmount = document.getElementById('refundShippingAmount');

            if (shippingToggle && shippingAmount) {
                shippingToggle.addEventListener('change', () => {
                    shippingAmount.disabled = !shippingToggle.checked;
                    if (shippingToggle.checked && Number(shippingAmount.value) === 0) {
                        shippingAmount.value = shippingTotal.toFixed(dec);
                    }
                    if (!shippingToggle.checked) {
                        shippingAmount.value = 0;
                    }
                    recompute();
                });
                shippingAmount.addEventListener('input', recompute);
            }

            let amountTouched = false;
            amountInput.addEventListener('input', () => { amountTouched = true; recompute(); });

            function recompute() {
                let itemCount = 0;
                let itemGross = 0;
                let itemPromo = 0;
                let itemTax = 0;
                const breakdown = [];
                qtyInputs.forEach((el, idx) => {
                    let q = parseInt(el.value || '0', 10);
                    const max = parseInt(el.dataset.max || '0', 10);
                    if (isNaN(q) || q < 0) q = 0;
                    if (q > max) { q = max; el.value = max; }
                    const perUnitGross = parseFloat(el.dataset.unitSubtotal || '0');
                    const perUnitPromo = parseFloat(el.dataset.unitPromo || '0');
                    const perUnitTax = parseFloat(el.dataset.unitTax || '0');
                    const lineGross = perUnitGross * q;
                    const lineProRataPromo = perUnitPromo * q;
                    const lineProRataTax = perUnitTax * q;
                    const lineNet = lineGross - lineProRataPromo + lineProRataTax;
                    itemGross += lineGross;
                    itemPromo += lineProRataPromo;
                    itemTax += lineProRataTax;
                    itemCount += q;
                    if (lineTotals[idx]) lineTotals[idx].textContent = fmt(lineNet);
                    if (q > 0) breakdown.push({ title: el.dataset.title || '', q: q, sub: lineNet });
                });
                const itemSubtotal = itemGross - itemPromo + itemTax;

                lastBreakdown = breakdown;
                if (sumItemLines) {
                    sumItemLines.innerHTML = breakdown.map((b) =>
                        '<div class="d-flex justify-content-between text-secondary">'
                        + '<span>' + (b.q + '× ') + escapeHtml(b.title) + '</span>'
                        + '<span>' + fmt(b.sub) + '</span></div>'
                    ).join('');
                }

                const shippingValue = shippingToggle && shippingToggle.checked ? Number(shippingAmount.value || 0) : 0;
                const refundTotal = itemSubtotal + shippingValue;

                if (itemCount > 0 || shippingValue > 0) {
                    itemsRow.classList.remove('d-none');
                    noItemsRow.classList.add('d-none');
                } else {
                    itemsRow.classList.add('d-none');
                    noItemsRow.classList.remove('d-none');
                }

                itemCountEl.textContent = '(' + itemCount + ')';
                itemSubtotalEl.textContent = fmt(itemGross);
                taxesEl.textContent = fmt(itemTax);
                refundTotalEl.textContent = fmt(refundTotal);

                if (promoRowEl && promoEl) {
                    if (itemPromo > 0.0001) {
                        promoRowEl.style.removeProperty('display');
                        promoEl.textContent = '−' + fmt(itemPromo);
                    } else {
                        promoRowEl.style.setProperty('display', 'none', 'important');
                    }
                }

                if (shippingValue > 0) {
                    shippingRowEl.style.display = '';
                    shippingEl.textContent = fmt(shippingValue);
                } else {
                    shippingRowEl.style.display = 'none';
                }

                if (!amountTouched) {
                    amountInput.value = Math.min(owedAmount + refundTotal, maxRefundable).toFixed(dec);
                }

                const amt = Math.min(Number(amountInput.value || 0), maxRefundable);
                lastAmt = amt;
                btnLabel.textContent = @json(__('labels.refund')) + ' ' + fmt(amt);
                submitBtn.disabled = amt <= 0;
            }

            qtyInputs.forEach((el) => el.addEventListener('input', recompute));

            function escapeHtml(s) {
                const d = document.createElement('div');
                d.textContent = s == null ? '' : String(s);
                return d.innerHTML;
            }

            const confirmBtn = document.getElementById('refundConfirmBtn');
            const confirmLines = document.getElementById('refundConfirmLines');
            const confirmTotal = document.getElementById('refundConfirmTotal');
            let confirmed = false;

            const showModal = () => { if (window.jQuery) { window.jQuery('#refundConfirmModal').modal('show'); return true; } return false; };
            const hideModal = () => { if (window.jQuery) window.jQuery('#refundConfirmModal').modal('hide'); };

            function proceed() {
                if (confirmBtn) {
                    if (confirmBtn.classList.contains('disabled')) return;
                    confirmBtn.classList.add('disabled');
                }
                hideModal();
                confirmed = true;
                if (form.requestSubmit) form.requestSubmit();
                else form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            }

            form.addEventListener('submit', (e) => {
                if (confirmed) { confirmed = false; return; }
                e.preventDefault();
                e.stopImmediatePropagation();
                if (lastAmt <= 0) return;

                if (confirmLines) {
                    confirmLines.innerHTML = lastBreakdown.length
                        ? lastBreakdown.map((b) =>
                            '<div class="d-flex justify-content-between text-secondary">'
                            + '<span>' + (b.q + '× ') + escapeHtml(b.title) + '</span>'
                            + '<span>' + fmt(b.sub) + '</span></div>').join('')
                        : '';
                }
                if (confirmTotal) confirmTotal.textContent = fmt(lastAmt);
                if (confirmBtn) confirmBtn.classList.remove('disabled');

                if (!showModal() && window.confirm(@json(__('labels.confirm_refund')))) {
                    proceed();
                }
            });

            if (confirmBtn) confirmBtn.addEventListener('click', (e) => { e.preventDefault(); proceed(); });

            recompute();
        })();
    </script>
@endsection
