@php
    $isSeller = $isSeller ?? (($panel ?? 'admin') === 'seller');
    $rp = ($panel ?? 'admin') . '.orders.';
    $canRefund = !$isSeller && ($refundPermission ?? false);
    $canReview = $isSeller ? true : ($refundPermission ?? false);
    $canCreate = !$isSeller && ($refundPermission ?? false);
    $headOrderId = $order['order_number'] ?? $order['id'];
@endphp
<div class="card mb-3" data-order-return-list>
    <div class="card-header py-2 d-flex align-items-center justify-content-between gap-3">
        <div>
            <h3 class="card-title mb-0">
                <i class="ti ti-arrow-back-up me-1"></i>{{ __('labels.returns') ?? 'Returns' }}
                @if(count($returns) > 0)
                    <span class="badge bg-secondary-lt ms-1">{{ count($returns) }}</span>
                @endif
            </h3>
            <div class="text-secondary small">{{ __('labels.return_status') }}</div>
        </div>
        @if($canCreate && !empty($returnableItems))
            <a href="{{ route($rp.'returns.create_page', ['id' => $headOrderId]) }}" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-plus me-1"></i>{{ __('labels.create_return') ?? 'Create return' }}
            </a>
        @endif
    </div>
    @if(!empty($returns))
        <div class="list-group list-group-flush order-return-list">
        @foreach($returns as $r)
            @php
                $sb = match ($r['status']) {
                    'refunded'  => 'bg-green-lt text-green',
                    'declined'  => 'bg-red-lt text-red',
                    'approved'  => 'bg-blue-lt text-blue',
                    'requested' => 'bg-yellow-lt text-yellow',
                    default     => 'bg-secondary-lt',
                };
                $productUrl = !empty($r['product_id'])
                    ? route(($isSeller ? 'seller' : 'admin') . '.products.show', ['id' => $r['product_id']])
                    : null;
            @endphp
            <div class="list-group-item p-3" data-order-return-item>
                <div class="d-flex flex-column flex-sm-row align-items-sm-start justify-content-between gap-3">
                    <div class="d-flex align-items-start gap-3 order-overview-min-w-0">
                        <span class="avatar avatar-md bg-secondary-lt flex-shrink-0 overflow-hidden">
                            @if(!empty($r['image']))
                                <img src="{{ $r['image'] }}" alt="{{ $r['title'] }}" class="avatar-img object-fit-cover">
                            @else
                                <i class="ti ti-photo text-secondary"></i>
                            @endif
                        </span>
                        <div class="order-overview-min-w-0">
                            <div class="fw-semibold text-truncate">
                                @if($productUrl)
                                    <a href="{{ $productUrl }}" class="text-reset text-decoration-underline-hover">{{ $r['title'] }}</a>
                                @else
                                    {{ $r['title'] }}
                                @endif
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                                @if($r['variant'])
                                    <span class="text-secondary small">{{ $r['variant'] }}</span>
                                @endif
                                <span class="badge bg-secondary-lt">{{ __('labels.quantity') }}: {{ $r['quantity'] }}</span>
                                @if(!empty($r['source_shipment_id']))
                                    <span class="badge bg-azure-lt">{{ __('labels.shipment') }} #{{ $r['source_shipment_id'] }}@if(!empty($r['source_tracking'])) · {{ $r['source_tracking'] }}@endif</span>
                                @endif
                                <span class="badge {{ $sb }} text-uppercase">{{ $r['status_label'] }}</span>
                                @if($r['fault'] === 'SELLER_FAULT')
                                    <span class="badge bg-orange-lt text-orange">{{ __('labels.seller_fault') ?? 'Seller fault' }}</span>
                                @elseif($r['fault'] === 'CUSTOMER_FAULT')
                                    <span class="badge bg-azure-lt text-azure">{{ __('labels.customer_fault') ?? 'Customer fault' }}</span>
                                @endif
                            </div>
                            @if($r['reason'])
                                <div class="text-secondary small mt-2">
                                    <span class="fw-medium text-body">{{ __('labels.reason') }}:</span> {{ $r['reason'] }}
                                </div>
                            @endif
                            @if(!empty($r['pickup_tracking']))
                                <div class="text-secondary small mt-2">
                                    <i class="ti ti-truck-return me-1"></i>{{ __('labels.return_pickup') ?? 'Return pickup' }}:
                                    @if(!empty($r['pickup_tracking_url']) && \Illuminate\Support\Str::startsWith(strtolower($r['pickup_tracking_url']), ['http://', 'https://']))
                                        <a href="{{ $r['pickup_tracking_url'] }}" target="_blank" rel="noopener noreferrer">
                                            {{ $r['pickup_carrier'] ? $r['pickup_carrier'] . ' · ' : '' }}{{ $r['pickup_tracking'] }} <i class="ti ti-external-link"></i>
                                        </a>
                                    @else
                                        {{ $r['pickup_carrier'] ? $r['pickup_carrier'] . ' · ' : '' }}{{ $r['pickup_tracking'] }}
                                    @endif
                                </div>
                            @endif
                            @if(!empty($r['images']))
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    @foreach($r['images'] as $img)
                                        <a href="{{ $img }}" target="_blank" rel="noopener" data-fslightbox="return-{{ $r['id'] }}">
                                            <img src="{{ $img }}" alt="" class="rounded border"
                                                 style="width:40px;height:40px;object-fit:cover;">
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="d-flex flex-row flex-sm-column align-items-center align-items-sm-end justify-content-between gap-2 flex-shrink-0">
                        <div class="text-sm-end">
                            <div class="text-secondary small">{{ __('labels.refund_amount') }}</div>
                            <div class="fw-semibold">{{ formatOrderMoney($r['refund_amount'], $order['currency'] ?? null) }}</div>
                        </div>
                        @if($canReview && !$r['is_final'])
                            <div class="d-flex align-items-center gap-1">
                                @if(!empty($r['is_approved']))
                                    @if($canRefund && !empty($r['refund_url']))
                                        <a href="{{ $r['refund_url'] }}" class="btn btn-sm btn-primary">
                                            <i class="ti ti-receipt-refund me-1"></i>{{ __('labels.process_return_refund') ?? 'Process refund' }}
                                        </a>
                                    @endif
                                    <a href="{{ $r['review_url'] }}" class="btn btn-sm btn-ghost-secondary">
                                        {{ __('labels.edit') ?? 'Edit' }}
                                    </a>
                                @else
                                    <a href="{{ $r['review_url'] }}" class="btn btn-sm btn-primary">
                                        <i class="ti ti-eye me-1"></i>{{ __('labels.review') ?? 'Review' }}
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
        </div>
    @else
        <div class="card-body text-center py-4">
            <i class="ti ti-arrow-back-up fs-2 d-block mb-2 text-secondary"></i>
            <div class="fw-medium">{{ __('labels.no_returns_yet') ?? 'No returns for this order yet.' }}</div>
        </div>
    @endif
</div>
