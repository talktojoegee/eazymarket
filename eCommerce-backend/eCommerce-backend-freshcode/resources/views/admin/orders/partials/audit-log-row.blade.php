@php
    use Illuminate\Support\Str;
    /**
     * Renders a single order_audit_logs entry. Database stores old_value and
     * new_value as JSON; this partial unpacks them into human-readable lines
     * per action so the UI never shows raw braces / quotes to admins.
     *
     * @var \App\Models\OrderAuditLog $log
     */

    $action = (string) $log->action;
    $old = is_array($log->old_value) ? $log->old_value : (array) ($log->old_value ?? []);
    $new = is_array($log->new_value) ? $log->new_value : (array) ($log->new_value ?? []);

    $badgeClass = match ($action) {
        'force_cancel'         => 'bg-red-lt text-red',
        'force_status'         => 'bg-orange-lt text-orange',
        'force_refund'         => 'bg-green-lt text-green',
        'add_note'             => 'bg-purple-lt text-purple',
        'escalation_flagged'   => 'bg-red-lt text-red',
        default                => 'bg-secondary-lt text-secondary',
    };
    $iconClass = match ($action) {
        'force_cancel'         => 'ti-ban',
        'force_status'         => 'ti-arrows-exchange',
        'force_refund'         => 'ti-receipt-refund',
        'add_note'             => 'ti-note',
        'escalation_flagged'   => 'ti-alert-triangle',
        default                => 'ti-history',
    };
    $actionLabel = Str::title(str_replace('_', ' ', $action));

    $details = [];

    if ($action === 'force_status') {
        $oldStatus = $old['status'] ?? null;
        $newStatus = $new['status'] ?? null;
        if ($oldStatus || $newStatus) {
            $details[] = [
                'label' => __('labels.status'),
                'value' => Str::title(str_replace('_', ' ', (string) $oldStatus))
                    . ' → ' . Str::title(str_replace('_', ' ', (string) $newStatus)),
            ];
        }
    } elseif ($action === 'force_cancel') {
        if (!empty($old['status'])) {
            $details[] = [
                'label' => __('labels.previous_status'),
                'value' => Str::title(str_replace('_', ' ', (string) $old['status'])),
            ];
        }
    } elseif ($action === 'force_refund') {
        if (isset($new['amount'])) {
            $details[] = [
                'label' => __('labels.amount'),
                'value' => formatOrderMoney((float) $new['amount'], $order['currency'] ?? null),
            ];
        }
    } elseif ($action === 'escalation_flagged') {
        $reasons = $new['new_reasons'] ?? ($new['all_reasons'] ?? []);
        if (!empty($reasons)) {
            $reasonText = collect((array) $reasons)
                ->map(fn ($r) => __('labels.' . $r) !== 'labels.' . $r ? __('labels.' . $r) : Str::title(str_replace('_', ' ', $r)))
                ->join(', ');
            $details[] = [
                'label' => __('labels.reason'),
                'value' => $reasonText,
            ];
        }
        if (!empty($new['store_ids']) && is_array($new['store_ids'])) {
            $details[] = [
                'label' => __('labels.stores'),
                'value' => '#' . implode(', #', $new['store_ids']),
            ];
        }
    }
@endphp

<div class="list-group-item p-3" data-order-activity-item>
    <div class="d-flex align-items-start gap-3">
        <span class="avatar avatar-sm {{ $badgeClass }} flex-shrink-0">
            <i class="ti {{ $iconClass }}"></i>
        </span>
        <div class="flex-fill order-overview-min-w-0">
            <div class="d-flex flex-column flex-sm-row align-items-sm-start justify-content-between gap-1 gap-sm-3">
                <div>
                    <div class="fw-semibold">{{ $actionLabel }}</div>
                    <div class="text-secondary small">
                        {{ optional($log->admin)->name ?? __('labels.system') }}
                    </div>
                </div>
                <time class="text-secondary small text-sm-end text-nowrap"
                      datetime="{{ $log->created_at?->toIso8601String() }}"
                      title="{{ $log->created_at?->toDateTimeString() }}">
                    {{ $log->created_at?->diffForHumans() }}
                    <span class="d-none d-md-inline">· {{ $log->created_at?->format('M j, Y H:i') }}</span>
                </time>
            </div>

            @if(!empty($log->reason))
                <div @class([
                    'small mt-2 rounded px-2 py-1',
                    'bg-purple-lt text-purple' => $action === 'add_note',
                    'bg-secondary-lt text-body' => $action !== 'add_note',
                ])>{{ $log->reason }}</div>
            @endif

            @if(!empty($details))
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @foreach($details as $detail)
                        <span class="badge bg-secondary-lt text-body fw-normal text-wrap text-start">
                            {{ $detail['label'] }}: <span class="fw-semibold">{{ $detail['value'] }}</span>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
