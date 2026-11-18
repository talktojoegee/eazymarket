@php
    $fulfillment = $fulfillment ?? 'unfulfilled';
    $lifecycle = $lifecycle ?? null;
    $lifecycleVal = strtolower((string) $lifecycle);
    $lifecycleBadge = match ($lifecycleVal) {
        'cancelled' => ['bg-red-lt text-red', 'ti-ban', __('labels.cancelled')],
        'archived'  => ['bg-secondary-lt text-secondary', 'ti-archive', __('labels.archived')],
        default     => null,
    };
@endphp
<div class="d-flex flex-column gap-1">
    <span class="badge {{ fulfillmentStatusBadgeClass($fulfillment) }} text-uppercase">
        {{ formatOrderStatusLabel($fulfillment) }}
    </span>
    @if($lifecycleBadge)
        <span class="badge {{ $lifecycleBadge[0] }} text-uppercase">
            <i class="ti {{ $lifecycleBadge[1] }} me-1"></i>{{ $lifecycleBadge[2] }}
        </span>
    @endif
</div>
