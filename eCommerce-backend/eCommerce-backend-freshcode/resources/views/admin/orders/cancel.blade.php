@php use App\Enums\Order\OrderItemStatusEnum; @endphp
@extends('layouts.admin.app', ['page' => $menuAdmin['orders']['active'] ?? ''])

@section('title', __('labels.cancel_order'))

@section('header_data')
    @php
        $page_title = __('labels.cancel_order');
        $page_pretitle = __('labels.admin') . ' ' . __('labels.orders');
    @endphp
@endsection

@php
    $publicId = $order->order_number ?? $order->id;
    $currencySym = $currency['symbol'] ?? '';
    $currencyRules = $currency['format'] ?? [];
    $currencyDec = (int) ($currencyRules['decimal_places'] ?? 2);
    $originalTotal = (float) $order->final_total;
    $cancellable = collect($rows)->where('cancellable', true)->values();
    $charges = (float) ($summary['shipping_total'] ?? 0) + (float) ($summary['delivery_charge'] ?? 0);
    $promoDiscount = (float) ($summary['promo_discount'] ?? 0);
    $giftCardDiscount = (float) ($summary['gift_card_discount'] ?? 0);
    $taxTotal = (float) ($summary['tax_total'] ?? 0);
    $subtotal = (float) ($summary['subtotal'] ?? 0);
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.orders'), 'url' => route('admin.orders.index')],
        ['title' => __('labels.order') . ' #' . $publicId, 'url' => $backUrl],
        ['title' => __('labels.cancel_order'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <form class="form-submit"
          method="POST"
          action="{{ $submitUrl }}"
          data-redirect="{{ $backUrl }}"
          data-order-cancel-form
          data-symbol="{{ $currencySym }}"
          data-decimals="{{ $currencyDec }}"
          data-symbol-position="{{ $currencyRules['symbol_position'] ?? 'before' }}"
          data-space-symbol="{{ !empty($currencyRules['space_between_symbol']) ? '1' : '0' }}"
          data-paid="{{ $paid }}"
          data-already-refunded="{{ $alreadyRefunded ?? 0 }}"
          data-original-total="{{ $originalTotal }}"
          data-charges="{{ $charges }}"
          data-gift-card="{{ $giftCardDiscount }}"
          data-subtotal="{{ $subtotal }}"
          data-promo="{{ $promoDiscount }}"
          data-tax="{{ $taxTotal }}">
        @csrf
        <div class="row row-cards">
            <div class="col-12">
                <div class="card">
                    <div class="card-header py-2">
                        <div>
                            <h3 class="card-title">{{ __('labels.select_items_to_cancel') }}</h3>
                            <x-breadcrumb :items="$breadcrumbs"/>
                        </div>
                        <div class="card-actions">
                            <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-arrow-left me-1"></i>{{ __('labels.back') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body py-2">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <span class="text-secondary small">{{ __('labels.refund_processed_separately_note') }}</span>
                            <span class="badge bg-secondary-lt">{{ $cancellable->count() }} / {{ count($rows) }} {{ __('labels.items') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-8">
                <div class="card mb-3">
                    <div class="list-group list-group-flush">
                        @foreach($rows as $i => $row)
                            @php
                                $isTerminal = in_array($row['status'], [
                                    (string) OrderItemStatusEnum::CANCELLED(),
                                    (string) OrderItemStatusEnum::RETURNED(),
                                    (string) OrderItemStatusEnum::REFUNDED(),
                                ], true);
                                $statusBadge = match($row['status']) {
                                    (string) OrderItemStatusEnum::CANCELLED() => ['bg-red-lt text-red', 'ti-ban', __('labels.cancelled')],
                                    (string) OrderItemStatusEnum::RETURNED() => ['bg-orange-lt text-orange', 'ti-arrow-back-up', __('labels.returned')],
                                    (string) OrderItemStatusEnum::REFUNDED() => ['bg-azure-lt text-azure', 'ti-receipt-refund', __('labels.refunded')],
                                    default => null,
                                };
                                $unitSubtotal = $row['quantity'] > 0 ? $row['subtotal'] / $row['quantity'] : 0;
                                $unitPromo = $row['quantity'] > 0 ? $row['promo_discount'] / $row['quantity'] : 0;
                                $unitTax = $row['quantity'] > 0 ? $row['tax_amount'] / $row['quantity'] : 0;
                            @endphp
                            <div class="list-group-item px-3 py-3" data-cancel-item-row>
                                <div class="row g-3 align-items-center">
                                    <div class="col">
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="avatar avatar-lg flex-shrink-0 bg-secondary-lt overflow-hidden">
                                                @if(!empty($row['image']))
                                                    <img src="{{ $row['image'] }}" alt="{{ $row['title'] }}" class="avatar-img object-fit-cover">
                                                @else
                                                    <i class="ti ti-photo text-secondary fs-2"></i>
                                                @endif
                                            </span>
                                            <div class="flex-fill" style="min-width: 0;">
                                                <div class="fw-semibold text-truncate">{{ $row['title'] }}</div>
                                                @if($row['variant_title'])
                                                    <div class="text-secondary small text-truncate">{{ $row['variant_title'] }}</div>
                                                @endif
                                                <div class="small mt-1">
                                                    <span class="fw-medium">{{ formatOrderMoney($row['subtotal'], $currency) }}</span>
                                                    <span class="text-secondary">· {{ formatOrderMoney($row['price'], $currency) }} × {{ $row['quantity'] }}</span>
                                                </div>
                                                @if($statusBadge)
                                                    <span class="badge {{ $statusBadge[0] }} mt-1">
                                                        <i class="ti {{ $statusBadge[1] }} me-1"></i>{{ $statusBadge[2] }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-auto">
                                        <input type="hidden" name="lines[{{ $i }}][order_item_id]" value="{{ $row['id'] }}">
                                        @if($row['cancellable'])
                                            <div class="d-flex align-items-end flex-wrap gap-3">
                                                <div>
                                                    <label class="form-label small mb-1" for="cancelQty{{ $row['id'] }}">{{ __('labels.cancel_qty') }}</label>
                                                    <input type="number"
                                                           id="cancelQty{{ $row['id'] }}"
                                                           class="form-control form-control-sm text-center js-cancel-qty"
                                                           name="lines[{{ $i }}][cancel_qty]"
                                                           value="0"
                                                           min="0"
                                                           max="{{ $row['cancelable_quantity'] }}"
                                                           data-unit-subtotal="{{ $unitSubtotal }}"
                                                           data-unit-promo="{{ $unitPromo }}"
                                                           data-unit-tax="{{ $unitTax }}"
                                                           data-max="{{ $row['cancelable_quantity'] }}"
                                                           style="width: 6rem;">
                                                </div>
                                                <label class="form-check form-switch mb-1">
                                                    <input type="checkbox" class="form-check-input" name="lines[{{ $i }}][restock]" value="1">
                                                    <span class="form-check-label">{{ __('labels.restock') }}</span>
                                                </label>
                                            </div>
                                            @if(!$row['is_cancelable'])
                                                <div class="text-warning small mt-1" title="{{ __('labels.not_cancelable_policy_help') }}">
                                                    <i class="ti ti-alert-triangle me-1"></i>{{ __('labels.not_cancelable_policy') }}
                                                </div>
                                            @endif
                                        @else
                                            <input type="hidden" name="lines[{{ $i }}][cancel_qty]" value="0">
                                            @if($row['cancel_block_reason'] === 'delivered')
                                                <div class="alert alert-warning py-2 px-3 mb-0 small" data-cancel-blocked="delivered">
                                                    <i class="ti ti-truck-delivery me-1"></i>{{ __('labels.item_delivered_create_return_note') }}
                                                </div>
                                            @elseif($row['cancel_block_reason'] === 'shipped')
                                                <div class="alert alert-warning py-2 px-3 mb-0 small" data-cancel-blocked="shipped">
                                                    <i class="ti ti-package-export me-1"></i>{{ __('labels.cancel_blocked_item_delivered') }}
                                                </div>
                                            @elseif($row['cancel_block_reason'] === 'allocated')
                                                <div class="alert alert-warning py-2 px-3 mb-0 small">{{ __('labels.cancel_blocked_parcel_allocated') }}</div>
                                            @elseif($isTerminal)
                                                <div class="text-secondary small">{{ __('labels.order_item_cannot_be_cancelled_at_current_status') }}</div>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-3">
                        <label class="form-label" for="cancelReason">
                            {{ __('labels.reason_for_cancel') }} <span class="text-secondary">({{ __('labels.optional') }})</span>
                        </label>
                        <textarea id="cancelReason" name="reason" rows="3" maxlength="1000"
                                  class="form-control" placeholder="{{ __('labels.cancelled_by_admin') }}"></textarea>
                        <div class="invalid-feedback" data-field="reason"></div>
                        <div class="form-hint"><i class="ti ti-lock me-1"></i>{{ __('labels.only_visible_to_staff') }}</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card position-sticky" style="top: 1rem;">
                    <div class="card-header py-2">
                        <h3 class="card-title"><i class="ti ti-calculator me-1"></i>{{ __('labels.summary') }}</h3>
                        <div class="card-actions">
                            <span class="badge bg-secondary-lt"><span data-selected-count>0</span> {{ __('labels.selected') }}</span>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-secondary">{{ __('labels.subtotal') }}</span>
                            <span class="fw-medium" id="sumSubtotal">{{ formatOrderMoney($subtotal, $currency) }}</span>
                        </div>
                        @if($taxTotal > 0)
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-secondary">{{ __('labels.tax') }}</span>
                                <span class="fw-medium" id="sumTax">{{ formatOrderMoney($taxTotal, $currency) }}</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-secondary">{{ __('labels.shipping') }}</span>
                            <span class="fw-medium">{{ formatOrderMoney($charges, $currency) }}</span>
                        </div>
                        @if($promoDiscount > 0)
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-secondary">{{ __('labels.promo_discount') }}</span>
                                <span class="fw-medium text-success" id="sumPromo">−{{ formatOrderMoney($promoDiscount, $currency) }}</span>
                            </div>
                        @endif
                        @if($giftCardDiscount > 0)
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-secondary">{{ __('labels.gift_card') }}</span>
                                <span class="fw-medium text-success">−{{ formatOrderMoney($giftCardDiscount, $currency) }}</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between align-items-center border-top mt-2 pt-2">
                            <span class="fw-semibold">{{ __('labels.updated_total') }}</span>
                            <span class="h3 mb-0" id="updatedTotal">{{ formatOrderMoney($originalTotal, $currency) }}</span>
                        </div>

                        <div class="rounded bg-red-lt p-3 mt-3">
                            <div class="text-secondary small">{{ __('labels.refund_owed') }}</div>
                            <div class="h2 text-red mb-0" id="refundOwed">{{ formatOrderMoney(0, $currency) }}</div>
                            @if(($alreadyRefunded ?? 0) > 0)
                                <div class="small text-secondary mt-1">
                                    {{ __('labels.already_refunded') }}: {{ formatOrderMoney($alreadyRefunded, $currency) }}
                                </div>
                            @endif
                        </div>

                        <button type="submit" class="btn btn-danger w-100 mt-3" data-cancel-submit disabled>
                            <i class="ti ti-ban me-1"></i>{{ __('labels.cancel_order') }}
                        </button>
                        @if($cancellable->isEmpty())
                            <div class="text-secondary text-center small mt-2">{{ __('labels.nothing_to_cancel') }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/admin-order-cancel.js') }}" defer></script>
@endpush
