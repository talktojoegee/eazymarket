@php
    use App\Services\OrderService;use Illuminate\Support\Str;$panel = $panel ?? 'admin';
@endphp
@extends($panel === 'seller' ? 'layouts.seller.app' : 'layouts.admin.app', ['page' => 'returns'])

@section('title', ($createMode ?? false) ? (__('labels.create_return') ?? 'Create return') : (__('labels.review_return_request') ?? 'Review return request'))

@section('header_data')
    @php
        $page_title = ($createMode ?? false) ? (__('labels.create_return') ?? 'Create return') : (__('labels.review_return_request') ?? 'Review return request');
        $page_pretitle = __('labels.order') . ' #' . ($order->order_number ?? ($order->id ?? '—'));
    @endphp
@endsection

@push('styles')
    <style>
        .return-action-page .card-header {
            min-height: auto;
        }

        .return-create-item-row {
            grid-template-columns: 3rem minmax(0, 1fr) auto auto auto;
            align-items: center;
        }

        .return-item-info {
            min-width: 0;
        }

        .return-item-total {
            min-width: 5.5rem;
            text-align: right;
        }

        .return-action-summary {
            top: 1rem;
        }

        @media (max-width: 767.98px) {
            .return-create-item-row {
                grid-template-columns: 2.5rem minmax(0, 1fr) auto;
                align-items: start;
            }

            .return-create-item-row .avatar {
                width: 2.5rem;
                height: 2.5rem;
            }

            .return-item-info {
                grid-column: 2 / -1;
            }

            .return-item-price {
                grid-column: 2;
            }

            .return-item-total {
                grid-column: 3;
                min-width: 0;
            }

            .return-item-quantity {
                grid-column: 1 / -1;
                justify-self: end;
            }
        }

        @media (max-width: 991.98px) {
            .return-action-summary {
                position: static !important;
            }
        }
    </style>
@endpush

@section($panel === 'seller' ? 'seller-content' : 'admin-content')
    @php
        $createMode    = $createMode ?? false;
        $returnGroups  = $returnGroups ?? [];
        $reasonOptions = $reasonOptions ?? [];
        $currencySym   = $currency['symbol'] ?? '';
        $currencyDec   = (int) ($currency['decimals'] ?? 2);
        $publicId      = $order->order_number ?? ($order->id ?? '—');
        $cancelUrl     = $cancelUrl ?? null;
        $refundUrl     = $refundUrl ?? null;
        $pageActionTitle = $createMode ? (__('labels.create_return') ?? 'Create return') : (__('labels.review_return_request') ?? 'Review return request');
        $breadcrumbs = [
            ['title' => __('labels.home'), 'url' => route($panel . '.dashboard')],
            ['title' => __('labels.orders'), 'url' => route($panel . '.orders.index')],
            ['title' => __('labels.order') . ' #' . $publicId, 'url' => $backUrl],
            ['title' => $pageActionTitle, 'url' => null],
        ];

        if (!$createMode) {
            $base        = max(0, (float) $return->refund_amount);
            $returnedQty = app(OrderService::class)->maximumReturnQuantity($return);
            $curQty      = max(1, (int) ($return->quantity ?: $returnedQty));
            $shipCharge  = (float) $return->return_shipping_charge;
            $reasonLabel = $return->reason_code?->label() ?? ($return->reason ?: '—');
            $faultValue  = $return->reason_type?->value;
            $returnRef   = '#' . ($order->id ?? '') . '-R' . $return->id;
            $title       = $orderItem->title ?? ($orderItem->product->title ?? '—');
            $variant     = $orderItem->variant_title ?? ($orderItem->variant->title ?? null);
            $statusVal   = (string) $return->return_status;
            $labelFile   = $return->labelFileUrl();
            $prefCarrier = $return->return_carrier;
            $prefTrackNo = $return->return_tracking_number;
            $prefTrackUrl= $return->return_tracking_url;
            $prefLabelUrl= $return->return_label_url;
            $prefNoShip  = (bool) $return->no_shipping_required;
        } else {
            $base = 0; $returnedQty = 1; $curQty = 1; $shipCharge = 0;
            $reasonLabel = null; $faultValue = null; $returnRef = null; $title = null; $variant = null;
            $statusVal = 'requested'; $labelFile = null;
            $prefCarrier = $prefTrackNo = $prefTrackUrl = $prefLabelUrl = null; $prefNoShip = false;
        }

        $statusBadge = match ($statusVal) {
            'refunded'  => 'bg-green-lt text-green',
            'declined'  => 'bg-red-lt text-red',
            'approved'  => 'bg-blue-lt text-blue',
            'requested' => 'bg-yellow-lt text-yellow',
            default     => 'bg-secondary-lt',
        };
        $isFinal     = in_array($statusVal, ['refunded', 'declined', 'cancelled', 'closed'], true);
        $isApproved  = !$createMode && $statusVal === 'approved';
        $isRequested = $createMode || $statusVal === 'requested';
    @endphp

    <div class="return-action-page">
        <div class="card mb-3" data-order-action-header>
            <div class="card-header py-2">
                <div>
                    <h3 class="card-title"><i class="ti ti-arrow-back-up me-1"></i>{{ $pageActionTitle }}</h3>
                    <x-breadcrumb :items="$breadcrumbs"/>
                </div>
                <div class="card-actions">
                    <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ti ti-arrow-left me-1"></i>{{ __('labels.back') }}
                    </a>
                </div>
            </div>
            <div class="card-body py-2">
                @if($createMode)
                    <span
                        class="text-secondary small">{{ __('labels.select_qty_to_return') ?? 'Select quantity to return' }}</span>
                @else
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <span class="text-secondary small">{{ $returnRef }}</span>
                        <span
                            class="badge {{ $statusBadge }} text-uppercase">{{ str_replace('_', ' ', $statusVal) }}</span>
                    </div>
                @endif
            </div>
        </div>
        <form id="returnForm"
              data-url="{{ $createMode ? $createUrl : ($approveUrl ?? '') }}"
              data-create="{{ $createMode ? 1 : 0 }}" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-lg-8">
                    @if($createMode)
                        <div class="card mb-3">
                            <div class="card-header py-2"><h3 class="card-title"><i
                                        class="ti ti-package me-1"></i>{{ __('labels.select_qty_to_return') ?? 'Select quantity to return' }}
                                </h3></div>
                            <div class="card-body p-3">
                                @forelse($returnGroups as $g)
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center small mb-2">
                                            <span class="fw-medium">{{ $g['ref'] }}</span>
                                            @if($g['location'])
                                                <span
                                                    class="text-secondary">{{ __('labels.shipped_from') ?? 'Shipped from' }} {{ $g['location'] }}</span>
                                            @endif
                                        </div>
                                        @foreach($g['items'] as $it)
                                            <div class="border rounded p-3 mb-2" data-return-create-item>
                                                <div class="return-create-item-row d-grid gap-3">
                                                            <span class="avatar">
                                                                @if(!empty($it['image']))
                                                                    <img src="{{ $it['image'] }}" alt=""
                                                                         class="avatar-img">
                                                                @else
                                                                    <i class="ti ti-shirt text-secondary"></i>
                                                                @endif
                                                            </span>
                                                    <div class="return-item-info">
                                                        <div class="fw-semibold text-truncate">{{ $it['title'] }}</div>
                                                        @if($it['variant'])
                                                            <div class="text-secondary small">{{ $it['variant'] }}</div>
                                                        @endif
                                                    </div>
                                                    <div
                                                        class="return-item-price text-secondary text-nowrap">{{ formatOrderMoney($it['price'], $currency) }}</div>
                                                    <div class="return-item-quantity d-flex align-items-center gap-1">
                                                        <input type="number" min="0" max="{{ $it['quantity'] }}"
                                                               step="1" value="{{ $it['quantity'] }}"
                                                               class="form-control text-center js-create-qty"
                                                               style="width: 70px;"
                                                               data-item-id="{{ $it['id'] }}"
                                                               data-shipment-id="{{ $it['shipment_id'] }}"
                                                               data-line-key="{{ $it['id'] }}-{{ $it['shipment_id'] ?? 'legacy' }}"
                                                               data-net="{{ $it['net'] }}"
                                                               data-qty="{{ $it['quantity'] }}">
                                                        <span
                                                            class="text-secondary small">/ {{ $it['quantity'] }}</span>
                                                    </div>
                                                    <div
                                                        class="return-item-total fw-semibold text-nowrap js-create-line"
                                                        data-line-for="{{ $it['id'] }}-{{ $it['shipment_id'] ?? 'legacy' }}">
                                                        {{ formatOrderMoney($it['net'], $currency) }}
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <label
                                                        class="form-label required small mb-1">{{ __('labels.return_reason') ?? 'Return reason' }}</label>
                                                    <select class="form-select form-select-sm js-create-reason text-capitalize"
                                                            data-reason-for="{{ $it['id'] }}-{{ $it['shipment_id'] ?? 'legacy' }}"
                                                            required>
                                                        <option
                                                            value="">{{ __('labels.select_reason') ?? 'Select a reason' }}</option>
                                                        @foreach($reasonOptions as $val => $label)
                                                            <option
                                                                value="{{ $val }}">{{ Str::replace("_"," ",$label) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @empty
                                    <div
                                        class="text-secondary small">{{ __('labels.no_returnable_items') ?? 'No delivered items available to return.' }}</div>
                                @endforelse
                            </div>
                        </div>
                    @else
                        <div class="card mb-3">
                            <div class="card-header py-2 d-flex align-items-center justify-content-between">
                                        <span class="badge {{ $statusBadge }} text-uppercase">
                                            <i class="ti ti-arrow-back-up me-1"></i>{{ ucwords(str_replace('_', ' ', $statusVal)) }}
                                        </span>
                                <span class="text-secondary">{{ $returnRef }}</span>
                            </div>
                            <div class="card-body p-3">
                                <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3"
                                     data-return-review-item>
                                            <span class="avatar">
                                                @if(!empty($orderItem->product?->main_image))
                                                    <img src="{{ $orderItem->product->main_image }}" alt=""
                                                         class="avatar-img">
                                                @else
                                                    <i class="ti ti-shirt text-secondary"></i>
                                                @endif
                                            </span>
                                    <div class="flex-fill">
                                        <div class="fw-medium">{{ $title }}</div>
                                        @if($variant)
                                            <div class="text-secondary small">{{ $variant }}</div>
                                        @endif
                                        @if($return->source_shipment_id)
                                            <div class="text-secondary small">{{ __('labels.shipment') }}
                                                #{{ $return->source_shipment_id }}@if($return->sourceShipment?->tracking_number)
                                                    · {{ $return->sourceShipment->tracking_number }}
                                                @endif</div>
                                        @endif
                                    </div>
                                    <input type="number" min="1" max="{{ $returnedQty }}" step="1"
                                           class="form-control text-center" id="qty" style="width: 80px;"
                                           value="{{ $curQty }}" {{ $isFinal ? 'disabled' : '' }}>
                                    <div class="fw-medium text-nowrap" id="lineTotal"
                                         style="min-width: 100px; text-align:right;">
                                        {{ formatOrderMoney($base, $currency) }}
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <label
                                        class="form-label">{{ __('labels.return_reason') ?? 'Return reason' }}</label>
                                    <div class="form-control text-capitalize">{{ Str::replace("_"," ",$reasonLabel) }}</div>
                                    @if(!empty($return->reason) && $return->reason_code)
                                        <div class="text-secondary small mt-1"><i
                                                class="ti ti-message me-1"></i>{{ $return->reason }}</div>
                                    @endif
                                    @if($faultValue === 'SELLER_FAULT')
                                        <span
                                            class="badge bg-orange-lt text-orange mt-2">{{ __('labels.seller_fault') ?? 'Seller fault' }}</span>
                                    @elseif($faultValue === 'CUSTOMER_FAULT')
                                        <span
                                            class="badge bg-azure-lt text-azure mt-2">{{ __('labels.customer_fault') ?? 'Customer fault' }}</span>
                                    @endif
                                </div>

                                @php $returnImages = $return?->images ?? collect(); @endphp
                                @if($returnImages->isNotEmpty())
                                    <div class="mt-3">
                                        <label class="form-label">
                                            <i class="ti ti-photo me-1"></i>{{ __('labels.customer_attachments') ?? 'Customer photos' }}
                                        </label>
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach($returnImages as $img)
                                                <a href="{{ $img }}" target="_blank" rel="noopener"
                                                   data-fslightbox="return-evidence" class="d-block">
                                                    <img src="{{ $img }}"
                                                         alt="{{ __('labels.customer_attachments') ?? 'Customer photo' }}"
                                                         class="rounded border"
                                                         style="width:84px;height:84px;object-fit:cover;">
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="card mb-3">
                        <div class="card-header py-2">
                            <div>
                                <h3 class="card-title"><i
                                        class="ti ti-truck-return me-1"></i>{{ __('labels.return_shipping_options') ?? 'Return shipping options' }}
                                </h3>
                                <div
                                    class="text-secondary small">{{ __('labels.fulfill_tracking_optional_hint') ?? 'Tracking is optional — you can add it now or later.' }}</div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <label class="form-check mb-3">
                                <input type="checkbox" class="form-check-input"
                                       id="noShipping" {{ $prefNoShip ? 'checked' : '' }}>
                                <span
                                    class="form-check-label">{{ __('labels.no_shipping_required') ?? 'No shipping required' }}</span>
                            </label>
                            <div id="shippingFieldsWrap">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label
                                            class="form-label">{{ __('labels.tracking_number') ?? 'Tracking number' }}</label>
                                        <input type="text" class="form-control" id="trackingNo" maxlength="120"
                                               value="{{ $prefTrackNo }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label
                                            class="form-label">{{ __('labels.shipping_carrier') ?? 'Shipping carrier' }}</label>
                                        <input type="text" class="form-control" id="carrier" maxlength="120"
                                               value="{{ $prefCarrier }}">
                                    </div>
                                    <div class="col-12">
                                        <label
                                            class="form-label">{{ __('labels.tracking_url') ?? 'Tracking URL' }}</label>
                                        <input type="url" class="form-control" id="trackingUrl" maxlength="512"
                                               placeholder="https://" value="{{ $prefTrackUrl }}">
                                    </div>
                                    <div class="col-12">
                                        <label
                                            class="form-label">{{ __('labels.return_label_file') ?? 'Upload return label' }}</label>
                                        <input type="file" class="form-control" id="labelFile" name="return_label"
                                               accept=".pdf,.png,.jpg,.jpeg">
                                        @if($labelFile)
                                            <a href="{{ $labelFile }}" target="_blank" class="small d-block mt-1"><i
                                                    class="ti ti-file me-1"></i>{{ __('labels.view_current_label') ?? 'View current label' }}
                                            </a>
                                        @endif
                                    </div>
                                    <div class="col-12">
                                        <label
                                            class="form-label">{{ __('labels.return_label_url') ?? 'Return label URL' }}</label>
                                        <input type="url" class="form-control" id="labelUrl" maxlength="512"
                                               placeholder="https://" value="{{ $prefLabelUrl }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card position-sticky return-action-summary">
                        <div class="card-header py-2"><h3 class="card-title"><i
                                    class="ti ti-calculator me-1"></i>{{ __('labels.summary') ?? 'Summary' }}</h3></div>
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-secondary">{{ __('labels.return_item') ?? 'Return item' }}</span>
                                <span class="fw-medium" id="sumItem">{{ formatOrderMoney($base, $currency) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span
                                    class="text-secondary">{{ __('labels.return_shipping') ?? 'Return shipping' }}</span>
                                <div class="input-group input-group-sm" style="max-width: 130px;">
                                    <span class="input-group-text">{{ $currencySym }}</span>
                                    <input type="number" step="0.01" min="0" class="form-control text-end"
                                           id="returnShipping"
                                           value="{{ number_format($shipCharge, $currencyDec, '.', '') }}" {{ $isFinal ? 'disabled' : '' }}>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-bold">{{ __('labels.expected_refund') ?? 'Expected refund' }}</span>
                                <span class="h3 mb-0"
                                      id="sumExpected">{{ formatOrderMoney(max(0, $base - $shipCharge), $currency) }}</span>
                            </div>
                            <div class="alert alert-secondary py-2 small mb-3">
                                <i class="ti ti-info-circle me-1"></i>{{ __('labels.no_refund_at_this_time') ?? 'No refund will be issued at this time — refund from the order refund page.' }}
                            </div>

                            @if($createMode)
                                <button type="button" class="btn btn-dark w-100" id="approveBtn">
                                    <i class="ti ti-plus me-1"></i>{{ __('labels.create_return') ?? 'Create return' }}
                                </button>
                            @elseif($isFinal)
                                <div class="alert alert-secondary mb-0">
                                    <i class="ti ti-check me-1"></i>{{ ucwords(str_replace('_', ' ', $statusVal)) }}
                                </div>
                            @elseif($isApproved)
                                <button type="button" class="btn btn-primary w-100 mb-2" id="approveBtn">
                                    <i class="ti ti-device-floppy me-1"></i>{{ __('labels.save_changes') ?? 'Save changes' }}
                                </button>
                                @if($refundUrl)
                                    <a href="{{ $refundUrl }}" class="btn btn-dark w-100 mb-2">
                                        <i class="ti ti-receipt-refund me-1"></i>{{ __('labels.process_return_refund') ?? 'Process refund' }}
                                    </a>
                                @endif
                                @if($cancelUrl)
                                    <button type="button" class="btn btn-outline-danger w-100" id="cancelBtn"
                                            data-url="{{ $cancelUrl }}">
                                        {{ __('labels.cancel_return') ?? 'Cancel return' }}
                                    </button>
                                @endif
                            @else
                                <button type="button" class="btn btn-dark w-100 mb-2" id="approveBtn">
                                    {{ __('labels.approve') ?? 'Approve' }}
                                </button>
                                <button type="button" class="btn btn-outline-danger w-100" id="declineBtn"
                                        data-url="{{ $declineUrl }}">
                                    {{ __('labels.decline') ?? 'Decline' }}
                                </button>
                            @endif
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
            const createMode = {{ $createMode ? 'true' : 'false' }};
            const perUnitBase = {{ $curQty > 0 ? $base / $curQty : 0 }};
            const returnedQty = {{ $returnedQty }};
            const fmt = (n) => sym + Number(Math.max(0, n) || 0).toFixed(dec);
            const backUrl = @json($backUrl);

            const form = document.getElementById('returnForm');
            const qtyEl = document.getElementById('qty');
            const shipEl = document.getElementById('returnShipping');
            const sumItem = document.getElementById('sumItem');
            const lineTotal = document.getElementById('lineTotal');
            const sumExpected = document.getElementById('sumExpected');
            const approveBtn = document.getElementById('approveBtn');
            const declineBtn = document.getElementById('declineBtn');
            const cancelBtn = document.getElementById('cancelBtn');

            const createQtyInputs = Array.from(document.querySelectorAll('.js-create-qty'));

            let labelPond = null;
            const labelInput = document.getElementById('labelFile');

            function initLabelPond() {
                if (labelInput && window.FilePond && !labelPond) {
                    labelPond = FilePond.create(labelInput, {
                        instantUpload: false, storeAsFile: true, credits: false,
                        acceptedFileTypes: ['application/pdf', 'image/png', 'image/jpeg'],
                    });
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initLabelPond);
            } else {
                initLabelPond();
            }

            function pickedLabel() {
                if (labelPond && labelPond.getFiles().length) return labelPond.getFiles()[0].file;
                if (labelInput && labelInput.files && labelInput.files[0]) return labelInput.files[0];
                return null;
            }

            const noShip = document.getElementById('noShipping');
            const shipWrap = document.getElementById('shippingFieldsWrap');

            function toggleShip() {
                if (shipWrap) shipWrap.style.display = noShip.checked ? 'none' : '';
            }

            if (noShip) {
                noShip.addEventListener('change', toggleShip);
                toggleShip();
            }

            function currentQty() {
                if (!qtyEl) return returnedQty;
                let q = parseInt(qtyEl.value || '1', 10);
                if (isNaN(q) || q < 1) q = 1;
                if (q > returnedQty) {
                    q = returnedQty;
                    qtyEl.value = returnedQty;
                }
                return q;
            }

            function lineQty(el) {
                const max = parseInt(el.getAttribute('data-qty'), 10) || 0;
                let q = parseInt(el.value || '0', 10);
                if (isNaN(q) || q < 0) q = 0;
                if (q > max) {
                    q = max;
                    el.value = max;
                }
                return q;
            }

            function currentBase() {
                if (createMode) {
                    let total = 0;
                    createQtyInputs.forEach(function (el) {
                        const net = parseFloat(el.getAttribute('data-net')) || 0;
                        const oq = parseInt(el.getAttribute('data-qty'), 10) || 1;
                        const perUnit = oq > 0 ? net / oq : 0;
                        const q = lineQty(el);
                        const lineBase = Math.round(perUnit * q * 100) / 100;
                        total += lineBase;
                        const lineEl = document.querySelector('.js-create-line[data-line-for="' + el.getAttribute('data-line-key') + '"]');
                        if (lineEl) lineEl.textContent = fmt(lineBase);
                    });
                    return Math.round(total * 100) / 100;
                }
                return Math.round(perUnitBase * currentQty() * 100) / 100;
            }

            function recompute() {
                const base = currentBase();
                const ship = parseFloat((shipEl || {}).value || '0') || 0;
                if (sumItem) sumItem.textContent = fmt(base);
                if (lineTotal) lineTotal.textContent = fmt(base);
                if (sumExpected) sumExpected.textContent = fmt(Math.max(0, base - ship));
            }

            if (qtyEl) qtyEl.addEventListener('input', recompute);
            if (shipEl) shipEl.addEventListener('input', recompute);
            createQtyInputs.forEach(function (el) {
                el.addEventListener('input', recompute);
            });
            recompute();

            function done(res, btn) {
                const data = (res && res.data) || {};
                if (data.success) {
                    if (typeof Toast !== 'undefined') Toast.fire({icon: 'success', title: data.message || 'Done'});
                    const url = (data.data && data.data.redirect) || null;
                    setTimeout(function () {
                        url ? (window.location.href = url) : window.location.reload();
                    }, 600);
                } else {
                    if (typeof Toast !== 'undefined') Toast.fire({icon: 'error', title: data.message || 'Error'});
                    if (btn) btn.disabled = false;
                }
            }

            function fail(err, btn) {
                const msg = (err && err.response && err.response.data && err.response.data.message) || null;
                if (typeof Toast !== 'undefined') Toast.fire({icon: 'error', title: msg || 'Something went wrong'});
                if (btn) btn.disabled = false;
            }

            function appendShipping(fd) {
                fd.append('return_shipping_charge', parseFloat((shipEl || {}).value || '0') || 0);
                fd.append('return_carrier', (document.getElementById('carrier') || {}).value || '');
                fd.append('return_tracking_number', (document.getElementById('trackingNo') || {}).value || '');
                fd.append('return_tracking_url', (document.getElementById('trackingUrl') || {}).value || '');
                fd.append('return_label_url', (document.getElementById('labelUrl') || {}).value || '');
                fd.append('no_shipping_required', noShip && noShip.checked ? 1 : 0);
                const f = pickedLabel();
                if (f) fd.append('return_label', f);
            }

            if (approveBtn) {
                approveBtn.addEventListener('click', function () {
                    const fd = new FormData();
                    if (createMode) {
                        let n = 0;
                        let missingReason = false;
                        createQtyInputs.forEach(function (el, idx) {
                            const q = lineQty(el);
                            if (q <= 0) return;
                            const id = el.getAttribute('data-item-id');
                            const reasonEl = document.querySelector('.js-create-reason[data-reason-for="' + el.getAttribute('data-line-key') + '"]');
                            const reason = reasonEl ? (reasonEl.value || '') : '';
                            if (!reason) {
                                missingReason = true;
                                if (reasonEl) reasonEl.classList.add('is-invalid');
                                return;
                            }
                            reasonEl.classList.remove('is-invalid');
                            fd.append('items[' + n + '][order_item_id]', id);
                            if (el.getAttribute('data-shipment-id')) fd.append('items[' + n + '][shipment_id]', el.getAttribute('data-shipment-id'));
                            fd.append('items[' + n + '][quantity]', q);
                            fd.append('items[' + n + '][reason_code]', reason);
                            n++;
                        });
                        if (missingReason) {
                            if (typeof Toast !== 'undefined') Toast.fire({
                                icon: 'error',
                                title: @json(__('labels.select_reason') ?? 'Select a reason') });
                            return;
                        }
                        if (n === 0) {
                            if (typeof Toast !== 'undefined') Toast.fire({
                                icon: 'error',
                                title: @json(__('labels.select_at_least_one_item') ?? 'Select a quantity for at least one item.') });
                            return;
                        }
                    } else {
                        fd.append('quantity', currentQty());
                    }
                    appendShipping(fd);
                    approveBtn.disabled = true;
                    window.axios.post(form.getAttribute('data-url'), fd)
                        .then(function (r) {
                            done(r, approveBtn);
                        }).catch(function (e) {
                        fail(e, approveBtn);
                    });
                });
            }
            if (declineBtn) {
                declineBtn.addEventListener('click', function () {
                    declineBtn.disabled = true;
                    window.axios.post(declineBtn.getAttribute('data-url'), {})
                        .then(function (r) {
                            done(r, declineBtn);
                        }).catch(function (e) {
                        fail(e, declineBtn);
                    });
                });
            }
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function () {
                    cancelBtn.disabled = true;
                    window.axios.post(cancelBtn.getAttribute('data-url'), {})
                        .then(function (r) {
                            done(r, cancelBtn);
                        }).catch(function (e) {
                        fail(e, cancelBtn);
                    });
                });
            }
        })();
    </script>
@endsection
