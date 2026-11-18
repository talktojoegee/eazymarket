@php use App\Services\OrderEarningsCalculator; @endphp
@php
    $isSellerEarnings = (bool) ($earnings['is_seller_view'] ?? false);
    $currency = $order['currency'] ?? null;
@endphp

@if(!empty($earnings) && !empty($earnings['seller_orders']))
    <div class="card mb-3" data-earnings-summary>
        <div class="card-header py-2">
            <div>
                <h3 class="card-title mb-0">
                    <i class="ti ti-coins me-1"></i>{{ __('labels.earnings_settlement') }}
                </h3>
                <div class="text-secondary small mt-1">
                    {{ $earnings['is_cod'] ? __('labels.cod') : __('labels.prepaid') }} · {{ __('labels.earnings_display_only') }}
                </div>
            </div>
        </div>

        <div class="card-body p-3">
            <div class="d-flex flex-column gap-3">
                @foreach($earnings['seller_orders'] as $g)
                    @php($creditSeller = $g['direction'] === OrderEarningsCalculator::DIR_PLATFORM_OWES_SELLER)
                    <div class="border rounded overflow-hidden" data-seller-settlement="{{ $g['seller_order_id'] }}">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 px-3 py-2 border-bottom">
                            <div>
                                <div class="fw-semibold">
                                    <i class="ti ti-building-store text-secondary me-1"></i>{{ $g['seller_name'] }}
                                </div>
                                <div class="text-secondary small mt-1">
                                    {{ Str::headline($g['fulfillment_method']) }}
                                    @if(!empty($g['fulfillment_status']))
                                        · {{ Str::headline($g['fulfillment_status']) }}
                                    @endif
                                </div>
                            </div>
                            <span class="badge {{ $earnings['is_cod'] ? 'bg-orange-lt text-orange' : 'bg-blue-lt text-blue' }}">
                                {{ $earnings['is_cod'] ? __('labels.cod') : __('labels.prepaid') }}
                            </span>
                        </div>

                        <div class="list-group list-group-flush">
                            @foreach($g['items'] as $it)
                                <div @class(['list-group-item px-3 py-2', 'bg-secondary-lt' => ! $it['is_counted']])>
                                    <div class="d-flex align-items-center justify-content-between gap-3">
                                        <div class="min-w-0">
                                            <div @class(['fw-medium text-truncate', 'text-decoration-line-through text-secondary' => ! $it['is_counted']])>
                                                {{ $it['title'] }}
                                            </div>
                                            <div class="text-secondary small">
                                                @if(!empty($it['variant'])){{ $it['variant'] }} · @endif
                                                {{ __('labels.qty') }} {{ $it['quantity'] }}
                                                @unless($it['is_counted'])
                                                    · {{ Str::headline($it['status']) }} · {{ __('labels.not_counted') }}
                                                @endunless
                                            </div>
                                        </div>
                                        @if($it['is_counted'])
                                            <div class="text-end text-nowrap" data-product-earning="{{ $it['line_earning'] }}">
                                                <div class="text-secondary small">{{ __('labels.seller_earns') }}</div>
                                                <div class="fw-semibold text-success">{{ formatOrderMoney($it['line_earning'], $currency) }}</div>
                                            </div>
                                        @else
                                            <span class="text-secondary">—</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <details class="border-top">
                            <summary class="px-3 py-2 text-primary small fw-medium" style="cursor: pointer;">
                                {{ __('labels.how_amount_calculated') }}
                            </summary>
                            <div class="px-3 pb-2 small">
                                <div class="d-flex justify-content-between gap-3 py-1">
                                    <span class="text-secondary">{{ __('labels.items_total') }}</span>
                                    <span>{{ formatOrderMoney($g['items_subtotal'], $currency) }}</span>
                                </div>
                                <div class="d-flex justify-content-between gap-3 py-1">
                                    <span class="text-secondary">{{ __('labels.marketplace_fee_deducted') }}</span>
                                    <span class="text-danger">{{ formatOrderMoney($g['commission_total'], $currency) }}</span>
                                </div>
                                @if($g['shipping_to_seller'] > 0)
                                    <div class="d-flex justify-content-between gap-3 py-1">
                                        <span class="text-secondary">{{ __('labels.shipping_seller_keeps') }}</span>
                                        <span class="text-success">{{ formatOrderMoney($g['shipping_to_seller'], $currency) }}</span>
                                    </div>
                                @endif
                                <div class="d-flex justify-content-between gap-3 py-1 border-top mt-1 pt-2 fw-semibold">
                                    <span>{{ __('labels.seller_earns') }}</span>
                                    <span>{{ formatOrderMoney($g['seller_net'], $currency) }}</span>
                                </div>
                            </div>
                        </details>

                        <div class="d-flex align-items-center justify-content-between gap-3 px-3 py-3 {{ $creditSeller ? 'bg-success-lt' : 'bg-warning-lt' }}">
                            <div>
                                <div class="fw-semibold {{ $creditSeller ? 'text-success' : 'text-warning' }}">
                                    <i class="ti {{ $creditSeller ? 'ti-arrow-down-right' : 'ti-arrow-up-right' }} me-1"></i>
                                    {{ $creditSeller ? __('labels.pay_seller') : __('labels.collect_from_seller') }}
                                </div>
                                <div class="text-secondary small">{{ __('labels.settlement_amount') }}</div>
                            </div>
                            <div class="h2 mb-0 {{ $creditSeller ? 'text-success' : 'text-warning' }}">
                                {{ formatOrderMoney($g['settlement_amount'], $currency) }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if(!empty($earnings['archive']))
                @php($arch = $earnings['archive'])
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 rounded bg-secondary-lt px-3 py-2 mt-3">
                    <span class="text-secondary small">
                        <i class="ti ti-calendar-check me-1"></i>{{ __('labels.expected_settlement_date') }}
                    </span>
                    <span class="fw-medium">
                        @if($arch['is_archived'])
                            <span class="badge bg-green-lt text-green">{{ __('labels.archived') }}</span>
                        @elseif(!empty($arch['estimated_date']))
                            {{ $arch['estimated_date'] }}
                            @if($arch['eligible_now'])
                                <span class="badge bg-yellow-lt text-yellow ms-1">{{ __('labels.archive_eligible_now') }}</span>
                            @endif
                        @else
                            {{ __('labels.archive_after_completion') }}
                        @endif
                    </span>
                </div>
            @endif
        </div>
    </div>
@endif
