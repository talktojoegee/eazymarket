@php $canRefund = $refundPermission ?? false; @endphp
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title"><i class="ti ti-arrow-back-up me-1"></i>{{ __('labels.returns') ?? 'Returns' }}</h3>
        @if($canRefund && !empty($returnableItems))
            <a href="{{ route('admin.orders.returns.create_page', ['id' => $order['id']]) }}" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-plus me-1"></i>{{ __('labels.create_return') ?? 'Create return' }}
            </a>
        @endif
    </div>
    <div class="card-body">
        @forelse($returns as $r)
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 @if(!$loop->last) border-bottom pb-2 mb-2 @endif">
                <div>
                    <div class="fw-medium">
                        {{ $r['title'] }}
                        @if($r['variant'])<span class="text-muted small">· {{ $r['variant'] }}</span>@endif
                        <span class="text-muted small">× {{ $r['quantity'] }}</span>
                    </div>
                    <div class="small text-secondary mt-1">
                        @if($r['reason']){{ $r['reason'] }} · @endif
                        @php
                            $sb = match ($r['status']) {
                                'refunded'  => 'bg-green-lt text-green',
                                'declined'  => 'bg-red-lt text-red',
                                'approved'  => 'bg-blue-lt text-blue',
                                'requested' => 'bg-yellow-lt text-yellow',
                                default     => 'bg-secondary-lt',
                            };
                        @endphp
                        <span class="badge {{ $sb }} text-uppercase">{{ $r['status_label'] }}</span>
                        @if($r['fault'] === 'SELLER_FAULT')
                            <span class="badge bg-orange-lt text-orange ms-1">{{ __('labels.seller_fault') ?? 'Seller fault' }}</span>
                        @elseif($r['fault'] === 'CUSTOMER_FAULT')
                            <span class="badge bg-azure-lt text-azure ms-1">{{ __('labels.customer_fault') ?? 'Customer fault' }}</span>
                        @endif
                    </div>
                    @if(!empty($r['pickup_tracking']))
                        <div class="text-secondary small mt-1">
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
                </div>
                <div class="text-end">
                    <div class="fw-medium">{{ formatOrderMoney($r['refund_amount'], $order['currency'] ?? null) }}</div>
                    @if($canRefund && !$r['is_final'])
                        @if(!empty($r['is_approved']))
                            <a href="{{ $r['refund_url'] }}" class="btn btn-sm btn-primary mt-1">
                                <i class="ti ti-receipt-refund me-1"></i>{{ __('labels.process_return_refund') ?? 'Process refund' }}
                            </a>
                            <a href="{{ $r['review_url'] }}" class="btn btn-sm mt-1">{{ __('labels.edit') ?? 'Edit' }}</a>
                        @else
                            <a href="{{ $r['review_url'] }}" class="btn btn-sm btn-primary mt-1">
                                <i class="ti ti-eye me-1"></i>{{ __('labels.review') ?? 'Review' }}
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        @empty
            <div class="text-secondary small">{{ __('labels.no_returns_yet') ?? 'No returns for this order yet.' }}</div>
        @endforelse
    </div>
</div>

