<?php

namespace App\Services;

use App\Enums\Order\FulfillmentStatusEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\Shipping\ShipmentStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemReturn;
use Illuminate\Support\Collection;

/**
 * Customer-facing status presenter (read-only).
 *
 * Translates the operational axes (payment / fulfillment rollup / per-parcel
 * shipment status / item bucket) into shopper-friendly language for the customer
 * app. It NEVER writes anything and never changes the source-of-truth statuses —
 * the raw values keep flowing in the API alongside these derived ones.
 *
 * Three outputs:
 *   - forOrder()    : one headline for the whole order
 *   - parcelStatus(): friendly label/stage for a single shipment
 *   - itemStatus()  : friendly label for a single line item
 *
 * Carrier-only parcel states (in_transit / out_for_delivery / RTO / lost) are
 * mapped too, so the moment a carrier integration starts feeding them the UI
 * lights up — but in today's manual flow only Packed / Shipped / Delivered /
 * Cancelled actually occur.
 */
class OrderCustomerStatusPresenter
{
    public function quantitiesForItem(OrderItem $item): array
    {
        $lineQuantity = max(0, (int) $item->quantity);
        $snapshot = $item->relationLoaded('sellerOrderItem') ? $item->sellerOrderItem : null;
        $ordered = $snapshot && (int) $snapshot->quantity >= $lineQuantity
            ? (int) $snapshot->quantity : null;
        $cancelled = (string) $item->status === (string) OrderItemStatusEnum::CANCELLED();
        $current = $cancelled ? 0 : $lineQuantity;
        $returns = $item->relationLoaded('returns') ? $item->returns : collect();
        $validReturns = $returns->reject(fn ($return) => in_array((string) $return->return_status, [
            (string) OrderItemReturnStatusEnum::CANCELLED(),
            (string) OrderItemReturnStatusEnum::DECLINED(),
        ], true));
        $received = $validReturns->filter(fn ($return) => $return->received_at !== null);
        $refunded = (int) $validReturns->filter(fn ($return) => (string) $return->return_status === (string) OrderItemReturnStatusEnum::REFUNDED()
            || $return->refund_processed_at !== null)->sum('quantity');

        return [
            'ordered' => $ordered,
            'current' => $current,
            'cancelled' => $ordered !== null ? max(0, $ordered - $current - $refunded) : ($cancelled ? $lineQuantity : null),
            'return_requested' => min($current, (int) $validReturns->sum('quantity')),
            'return_received' => min($current, (int) $received->sum('quantity')),
        ];
    }

    private function deliveryProgressForItem(OrderItem $item, array $facts): array
    {
        $quantities = $this->quantitiesForItem($item);
        $lines = $item->shipmentItems->filter(fn ($line) => $line->shipment
            && ! $line->shipment->is_return
            && (string) $line->shipment->status !== (string) ShipmentStatusEnum::CANCELLED());
        $quantity = max(
            (int) ($quantities['ordered'] ?? 0),
            (int) $facts['current'],
            (int) $lines->sum('quantity'),
        );
        $target = max(0, $quantity - (int) ($quantities['cancelled'] ?? 0));
        if ($item->shipmentItems->isEmpty()) {
            return ['quantity' => $quantity, 'target' => $target, 'shipped' => $facts['shipped'], 'delivered' => $facts['delivered']];
        }

        $hasStatus = fn ($shipment, array $statuses) => in_array((string) $shipment->status, $statuses, true)
            || $shipment->statusEvents->contains(fn ($event) => in_array((string) $event->status, $statuses, true));
        $shipped = $lines->filter(fn ($line) => $line->shipment->picked_up_at
            || $line->shipment->delivered_at
            || $hasStatus($line->shipment, ShipmentStatusEnum::dispatched()));
        $delivered = $lines->filter(fn ($line) => $line->shipment->delivered_at
            || $hasStatus($line->shipment, [(string) ShipmentStatusEnum::DELIVERED()]));

        return [
            'quantity' => $quantity,
            'target' => $target,
            'shipped' => min($quantity, (int) $shipped->sum('quantity')),
            'delivered' => min($quantity, (int) $delivered->sum('quantity')),
        ];
    }

    /** Headline / item / parcel codes are kept as plain strings → labels.* keys. */

    public function activityForItem(OrderItem $item): array
    {
        $order = $item->order;
        if (!$order) {
            return [];
        }
        $events = [];
        $add = function (string $code, string $label, $at, array $meta = [], bool $done = true, bool $exception = false) use (&$events) {
            $events[] = [
                'code' => $code, 'label' => $label, 'done' => $done,
                'at' => $at?->toIso8601String(), 'is_exception' => $exception, 'meta' => $meta,
            ];
        };
        $add('order_placed', __('labels.ev_order_placed'), $order->created_at);
        if ($order->relationLoaded('paymentTransactions')) {
            $payment = $order->paymentTransactions->first(fn ($transaction) => in_array((string) $transaction->payment_status, ['completed', 'partially_paid', 'paid'], true));
            if ($payment) {
                $add('payment_received', __('labels.ev_payment_received'), $payment->created_at);
            }
        }
        foreach ($item->shipmentItems as $line) {
            $shipment = $line->shipment;
            if (!$shipment || $shipment->is_return) {
                continue;
            }
            $meta = ['quantity' => (int) $line->quantity, 'shipment_id' => (int) $shipment->id, 'courier' => $shipment->courier_name ?: $shipment->manual_carrier_name, 'tracking_id' => $shipment->tracking_number, 'tracking_url' => $shipment->tracking_url];
            $recorded = $shipment->relationLoaded('statusEvents') ? $shipment->statusEvents : collect();
            foreach ($recorded as $event) {
                $status = $this->parcelStatus((string) $event->status);
                $label = $status['code'] === 'cancelled' ? __('labels.tracking_parcel_cancelled') : $status['label'];
                $add('shipment_'.$shipment->id.'_'.$event->id, $label, $event->occurred_at, $meta + ['status' => $status['code']], true, in_array($status['code'], ['failed', 'lost', 'attempted', 'rto'], true));
            }
            foreach (['confirmed' => $shipment->picked_up_at, 'delivered' => $shipment->delivered_at] as $status => $at) {
                if ($at && !$recorded->contains(fn ($event) => (string) $event->status === $status)) {
                    $add('shipment_'.$shipment->id.'_'.$status, $this->parcelStatus($status)['label'], $at, $meta + ['status' => $this->parcelStatus($status)['code']]);
                }
            }
        }
        $quantities = $this->quantitiesForItem($item);
        if ($quantities['cancelled'] > 0) {
            $add('cancelled', __('labels.ev_cancelled'), null, ['quantity' => $quantities['cancelled']]);
        }
        foreach ($item->returns as $return) {
            $meta = ['quantity' => (int) $return->quantity, 'return_id' => (int) $return->id];
            if ($return->source_shipment_id) {
                $meta['shipment_id'] = (int) $return->source_shipment_id;
            }
            foreach ([
                'requested' => ['ev_return_requested', $return->created_at],
                'approved' => ['return_approved', $return->seller_approved_at],
                'picked_up' => ['rstep_picked_up', $return->picked_up_at],
                'received' => ['ev_return_received', $return->received_at],
            ] as $key => [$label, $at]) {
                if ($at) {
                    $add('return_'.$return->id.'_'.$key, __('labels.'.$label), $at, $meta);
                }
            }
            if (in_array((string) $return->return_status, ['declined', 'cancelled'], true)) {
                $add('return_'.$return->id.'_'.(string) $return->return_status, __('labels.return_'.(string) $return->return_status), $return->updated_at, $meta);
            }
        }
        $refunds = $order->refunds->keyBy('id');
        foreach ($refunds as $refund) {
            $allocation = $refund->items->firstWhere('id', $item->id);
            if (!$allocation) {
                continue;
            }
            $settlement = $refund->settled_by_refund_id ? $refunds->get($refund->settled_by_refund_id) : null;
            $payment = $settlement ?? $refund;
            $issued = $payment->status->value === 'issued';
            $failed = $payment->status->value === 'failed';
            $meta = ['quantity' => (int) $allocation->pivot->quantity, 'amount' => (float) $allocation->pivot->amount, 'refund_method' => $payment->method->value];
            $label = $issued ? 'ev_refund_issued' : ($failed ? 'customer_refund_failed' : 'customer_refund_pending');
            $add('refund_'.$refund->id, __('labels.'.$label), $issued ? $payment->issued_at : $refund->created_at, $meta, $issued, $failed);
        }

        $ordered = collect($events)->where('code', '!=', 'cancelled')
            ->sortBy(fn ($event) => $event['at'] ?? '9999')->values()->all();
        $cancellation = collect($events)->firstWhere('code', 'cancelled');
        if ($cancellation) {
            $position = collect($ordered)->search(fn ($event) => str_starts_with($event['code'], 'refund_'));
            array_splice($ordered, $position === false ? count($ordered) : $position, 0, [$cancellation]);
        }
        return $ordered;
    }

    public function forOrder(Order $order): array
    {
        $payment = (string) $order->payment_status;
        $paid = in_array($payment, [
            (string) PaymentStatusEnum::COMPLETED(),
            (string) PaymentStatusEnum::PARTIALLY_PAID(),
            (string) PaymentStatusEnum::AUTHORIZED(),
            (string) PaymentStatusEnum::REFUNDED(),
            (string) PaymentStatusEnum::PARTIALLY_REFUNDED(),
        ], true);

        $items     = $order->relationLoaded('items') ? $order->items : collect();
        $shipments = $this->collectShipments($order);

        $activeIds = $items
            ->filter(fn ($i) => (string) $i->status === (string) OrderItemStatusEnum::ACTIVE())
            ->pluck('id');

        $cancelledLike = [(string) OrderItemStatusEnum::CANCELLED()];
        $allCancelled = $items->isNotEmpty()
            && $items->every(fn ($i) => in_array((string) $i->status, $cancelledLike, true));
        $allRefunded = $items->isNotEmpty()
            && $items->every(fn ($i) => (string) $i->status === (string) OrderItemStatusEnum::REFUNDED());
        $anyActiveReturn = $this->anyActiveReturn($items);

        $shippedStates = $this->shippedEvidenceStates();
        $shippedItemIds   = collect();
        $deliveredItemIds = collect();
        $anyOutForDelivery = $anyPacked = $anyShipped = false;

        foreach ($shipments as $s) {
            $st = (string) $s->status;
            if ($st === (string) ShipmentStatusEnum::CANCELLED()) {
                continue;
            }
            $itemIds = $s->relationLoaded('items') ? $s->items->pluck('order_item_id') : collect();

            if (in_array($st, $shippedStates, true)) {
                $anyShipped = true;
                $shippedItemIds = $shippedItemIds->merge($itemIds);
            }
            if ($st === (string) ShipmentStatusEnum::DELIVERED()) {
                $deliveredItemIds = $deliveredItemIds->merge($itemIds);
                $shippedItemIds   = $shippedItemIds->merge($itemIds);
            }
            if ($st === (string) ShipmentStatusEnum::OUT_FOR_DELIVERY()) {
                $anyOutForDelivery = true;
            }
            if ($st === (string) ShipmentStatusEnum::LABEL_PRINTED()
                || $st === (string) ShipmentStatusEnum::PENDING()) {
                $anyPacked = true;
            }
        }
        $shippedItemIds   = $shippedItemIds->unique();
        $deliveredItemIds = $deliveredItemIds->unique();

        $allDelivered = $activeIds->isNotEmpty() && $activeIds->diff($deliveredItemIds)->isEmpty();
        $allShipped   = $activeIds->isNotEmpty() && $activeIds->diff($shippedItemIds)->isEmpty();

        $ff = (string) $order->fulfillment_status;
        $unpaid = in_array($payment, [
            (string) PaymentStatusEnum::PENDING(),
            (string) PaymentStatusEnum::FAILED(),
            (string) PaymentStatusEnum::VOIDED(),
        ], true);

        [$code, $stage] = match (true) {
            $allCancelled                                                        => ['cancelled', 0],
            ($allRefunded || $payment === (string) PaymentStatusEnum::REFUNDED()) => ['refunded', 0],
            $anyActiveReturn                                                     => ['return_in_progress', 0],
            $unpaid                                                              => ['awaiting_payment', 1],
            ($allDelivered || $ff === (string) FulfillmentStatusEnum::FULFILLED()) => ['delivered', 5],
            $anyOutForDelivery                                                   => ['out_for_delivery', 4],
            $anyShipped                                                          => [$allShipped ? 'on_the_way' : 'partly_shipped', 4],
            ($anyPacked || $ff === (string) FulfillmentStatusEnum::IN_PROGRESS()) => ['being_prepared', 3],
            $ff === (string) FulfillmentStatusEnum::ON_HOLD()                    => ['on_hold', 3],
            $paid                                                                => ['confirmed', 2],
            default                                                              => ['placed', 1],
        };

        return [
            'code'         => $code,
            'label'        => (string) __('labels.cs_' . $code),
            'description'  => (string) __('labels.cs_' . $code . '_desc'),
            'stage'        => $stage,
            'is_exception' => $stage === 0,
        ];
    }

    /**
     * Per-item timeline: the same tracker scoped to ONE order item — its own
     * parcel(s), returns and refund — while placed/confirmed stay order-level.
     * Returns the timeline steps (same shape as forOrder()['timeline']).
     */
    public function deliveryForItem(OrderItem $item, ?array $tracking = null): array
    {
        $tracking ??= $this->trackingForItem($item);
        if (!$tracking['milestones']) {
            return [];
        }
        if (($tracking['milestones'][0]['key'] ?? null) === 'placed') {
            return $tracking['milestones'];
        }
        $placed = collect($tracking['history'])->firstWhere('code', 'order_placed');
        return array_merge([[
            'key' => 'placed', 'label' => __('labels.step_placed'), 'done' => true,
            'at' => $placed['at'] ?? null, 'events' => $placed ? [$placed] : [],
        ]], $tracking['milestones']);
    }

    public function trackingForItem(OrderItem $item): array
    {
        $item->loadMissing(['order.paymentTransactions', 'order.refunds.items', 'sellerOrderItem', 'returns', 'shipmentItems.shipment.statusEvents']);
        $lifecycle = app(FulfillmentRollupService::class);
        $facts = $lifecycle->itemFacts($item);
        $displayProgress = $this->deliveryProgressForItem($item, $facts);
        $activity = collect($this->activityForItem($item));
        $history = $activity->values();
        $quantity = $facts['current'];
        $payment = $activity->firstWhere('code', 'payment_received');
        $confirmed = $payment !== null || $facts['preparing'] || in_array((string) $item->order?->payment_method, ['cod', 'cash_on_delivery'], true)
            || in_array((string) $item->order?->payment_status, ['completed', 'partially_paid', 'partially_refunded', 'refunded'], true);
        $allShipped = $quantity > 0 && $displayProgress['target'] > 0 && $displayProgress['shipped'] >= $displayProgress['target'];
        $allDelivered = $quantity > 0 && $displayProgress['target'] > 0 && $displayProgress['delivered'] >= $displayProgress['target'];
        $deliveryLines = $item->shipmentItems->filter(fn ($line) => $line->shipment && !$line->shipment->is_return && (string) $line->shipment->status !== 'cancelled');
        $milestones = [];
        $definitions = [
            'confirmed' => [$confirmed, !$confirmed, $payment['at'] ?? null, $payment === null && $confirmed, null],
            'preparing' => [$facts['shipped'] > 0, $confirmed && $facts['shipped'] === 0, null, $facts['shipped'] > 0, null],
            'shipped' => [$allShipped, $facts['shipped'] > 0 && !$allShipped, null, false, $displayProgress['shipped']],
            'delivered' => [$allDelivered, $allShipped && !$allDelivered, null, false, $displayProgress['delivered']],
        ];
        if ($quantity > 0) {
            foreach ($definitions as $key => [$done, $current, $at, $inferred, $completedQuantity]) {
                $matching = $history->filter(fn ($event) => ($event['meta']['status'] ?? null) === $key);
                if ($done && in_array($key, ['shipped', 'delivered'], true)) {
                    $parcelTimes = $deliveryLines->map(fn ($line) => $matching
                        ->filter(fn ($event) => ($event['meta']['shipment_id'] ?? null) === (int) $line->shipment->id)
                        ->pluck('at')->filter()->min());
                    $at = $parcelTimes->isNotEmpty() && $parcelTimes->every(fn ($time) => $time !== null) ? $parcelTimes->max() : null;
                    $inferred = $at === null;
                }
                $milestones[] = [
                    'key' => $key, 'status_code' => $key, 'label' => __('labels.step_'.$key), 'done' => $done,
                    'current' => $current && !$facts['on_hold'],
                    'marker' => $done ? 'done' : ($current && !$facts['on_hold'] ? 'current' : 'pending'),
                    'at' => $at, 'inferred' => $inferred, 'quantity' => $displayProgress['quantity'],
                    'completed_quantity' => $completedQuantity, 'events' => [],
                ];
            }
        } else {
            $placed = $history->firstWhere('code', 'order_placed');
            $cancelled = $history->firstWhere('code', 'cancelled');
            $refundEvents = $history->filter(fn ($event) => str_starts_with($event['code'], 'refund_'))->values();
            if ($placed) {
                $milestones[] = [
                    'key' => 'placed', 'label' => __('labels.step_placed'), 'done' => true, 'current' => false,
                    'marker' => 'done', 'at' => $placed['at'], 'inferred' => false, 'events' => [],
                ];
            }
            if ($confirmed) {
                $milestones[] = [
                    'key' => 'confirmed', 'label' => __('labels.step_confirmed'), 'done' => true, 'current' => false,
                    'marker' => 'done', 'at' => $payment['at'] ?? null, 'inferred' => $payment === null, 'events' => [],
                ];
            }
            if ($cancelled) {
                $milestones[] = [
                    'key' => 'cancelled', 'label' => __('labels.step_cancelled'), 'done' => true, 'current' => false,
                    'marker' => 'done', 'at' => $cancelled['at'], 'inferred' => $cancelled['at'] === null, 'events' => [$cancelled],
                ];
            }
            if ($refundEvents->isNotEmpty()) {
                $refundDone = $refundEvents->every(fn ($event) => $event['done']);
                $refundFailed = $refundEvents->contains(fn ($event) => $event['is_exception']);
                $methods = $refundEvents->pluck('meta.refund_method')->filter()->unique()->values();
                $meta = [
                    'quantity' => (int) $refundEvents->sum('meta.quantity'),
                    'amount' => round((float) $refundEvents->sum('meta.amount'), 2),
                ];
                if ($methods->count() === 1) {
                    $meta['refund_method'] = $methods->first();
                }
                $label = $refundDone ? __('labels.ev_refund_issued') : ($refundEvents->firstWhere('done', false)['label'] ?? __('labels.customer_refund_pending'));
                $at = $refundDone ? $refundEvents->pluck('at')->filter()->max() : null;
                $event = ['code' => 'refunded', 'label' => $label, 'done' => $refundDone, 'at' => $at, 'is_exception' => $refundFailed, 'meta' => $meta];
                $milestones[] = [
                    'key' => 'refunded', 'label' => $label, 'done' => $refundDone, 'current' => !$refundDone && !$refundFailed,
                    'marker' => $refundDone ? 'done' : (!$refundFailed ? 'current' : 'pending'),
                    'at' => $at, 'inferred' => false, 'events' => [$event],
                ];
            }
        }
        $exceptions = [];
        if ($facts['on_hold']) {
            $exceptions[] = ['code' => 'on_hold', 'label' => __('labels.ev_on_hold'), 'at' => null, 'tone' => 'warning'];
        }
        foreach ($deliveryLines as $line) {
            $parcel = $this->parcelStatus((string) $line->shipment->status);
            if (in_array($parcel['code'], ['failed', 'lost', 'attempted', 'rto', 'returned'], true)) {
                $exceptions[] = ['code' => 'shipment_'.$line->shipment->id, 'label' => $parcel['label'], 'at' => $line->shipment->last_event_at?->toIso8601String(), 'tone' => 'warning'];
            }
        }
        if (in_array((string) $item->order?->payment_status, ['failed', 'voided'], true)) {
            $exceptions[] = ['code' => 'payment_failed', 'label' => __('labels.ev_payment_failed'), 'at' => null, 'tone' => 'danger'];
        }
        $headline = match (true) {
            $quantity === 0 => 'cancelled',
            $facts['on_hold'] => 'on_hold',
            $allDelivered => 'delivered',
            $facts['delivered'] > 0 => 'partially_delivered',
            $facts['shipped'] > 0 && !$allShipped => 'partially_shipped',
            $allShipped => 'shipped',
            $facts['preparing'] => 'preparing',
            $confirmed => 'confirmed',
            default => 'placed',
        };

        $historyList = collect($history);

        if ($quantity > 0) {
            foreach ($milestones as $milestone) {
                if (!$milestone['done']) {
                    $key = $milestone['key'];
                    $exists = $historyList->contains(fn ($ev) => ($ev['code'] ?? null) === $key || ($ev['status_code'] ?? null) === $key || ($ev['meta']['status'] ?? null) === $key);
                    if (!$exists) {
                        $historyList->push([
                            'code' => $key,
                            'status_code' => $key,
                            'label' => $milestone['label'] ?? __('labels.step_'.$key),
                            'done' => false,
                            'at' => null,
                            'is_exception' => false,
                            'meta' => [],
                        ]);
                    }
                }
            }
        }

        foreach ($item->returns as $return) {
            if (in_array((string) $return->return_status, ['declined', 'cancelled'], true)) {
                continue;
            }
            $returnId = $return->id;
            $hasApproved = $historyList->contains(fn ($ev) => $ev['code'] === "return_{$returnId}_approved");
            $hasReceived = $historyList->contains(fn ($ev) => in_array($ev['code'], ["return_{$returnId}_received", "return_{$returnId}_picked_up"], true));
            $hasRefund = $historyList->contains(fn ($ev) => str_starts_with($ev['code'], 'refund_'));

            if (!$hasApproved) {
                $historyList->push([
                    'code' => "return_{$returnId}_approved",
                    'status_code' => 'return_approved',
                    'label' => __('labels.return_approved'),
                    'done' => false,
                    'at' => null,
                    'is_exception' => false,
                    'meta' => ['return_id' => (int) $returnId],
                ]);
            }
            if (!$hasReceived) {
                $historyList->push([
                    'code' => "return_{$returnId}_received",
                    'status_code' => 'return_received',
                    'label' => __('labels.ev_return_received'),
                    'done' => false,
                    'at' => null,
                    'is_exception' => false,
                    'meta' => ['return_id' => (int) $returnId],
                ]);
            }
            if (!$hasRefund) {
                $historyList->push([
                    'code' => "refund_pending_{$returnId}",
                    'status_code' => 'refunded',
                    'label' => __('labels.customer_refund_pending'),
                    'done' => false,
                    'at' => null,
                    'is_exception' => false,
                    'meta' => ['return_id' => (int) $returnId],
                ]);
            }
        }

        if ($quantity === 0) {
            $hasRefund = $historyList->contains(fn ($ev) => str_starts_with($ev['code'], 'refund_'));
            if (!$hasRefund) {
                $historyList->push([
                    'code' => 'refund_pending',
                    'status_code' => 'refunded',
                    'label' => __('labels.customer_refund_pending'),
                    'done' => false,
                    'at' => null,
                    'is_exception' => false,
                    'meta' => [],
                ]);
            }
        }

        $firstPending = $historyList->firstWhere('done', false);
        $lastDone = $historyList->where('done', true)->last();
        $currentHistoryCode = $firstPending ? $firstPending['code'] : ($lastDone ? $lastDone['code'] : $headline);

        return [
            'status' => ['code' => $headline, 'label' => __('labels.tracking_'.$headline), 'status_code' => $currentHistoryCode],
            'status_code' => $currentHistoryCode,
            'quantities' => array_merge($this->quantitiesForItem($item), $facts),
            'milestones' => $milestones,
            'history' => $historyList->all(),
            'exceptions' => $exceptions,
            'cancellation' => $lifecycle->cancellationForItem($item, true, $facts),
        ];
    }

    public function forOrderItem(OrderItem $item): array
    {
        $item->loadMissing(['order.paymentTransactions', 'order.refunds.items', 'sellerOrderItem', 'shipmentItems.shipment.statusEvents', 'returns']);
        $order = $item->relationLoaded('order') ? $item->order : $item->order()->first();
        if (! $order) {
            return [];
        }

        $shipments = $item->relationLoaded('shipmentItems')
            ? $item->shipmentItems->map(fn ($si) => $si->shipment)->filter(fn ($shipment) => $shipment && !$shipment->is_return)->unique('id')->values()
            : collect();

        $payment = (string) $order->payment_status;
        $paid = in_array($payment, [
            (string) PaymentStatusEnum::COMPLETED(),
            (string) PaymentStatusEnum::PARTIALLY_PAID(),
            (string) PaymentStatusEnum::AUTHORIZED(),
            (string) PaymentStatusEnum::REFUNDED(),
            (string) PaymentStatusEnum::PARTIALLY_REFUNDED(),
        ], true);

        $shippedStates = $this->shippedEvidenceStates();
        $everDelivered = $shipments->contains(fn ($s) => (string) $s->status === (string) ShipmentStatusEnum::DELIVERED());
        $everShipped   = $everDelivered || $shipments->contains(fn ($s) => in_array((string) $s->status, $shippedStates, true));
        $anyPacked     = $shipments->contains(fn ($s) => in_array((string) $s->status, [
            (string) ShipmentStatusEnum::LABEL_PRINTED(),
            (string) ShipmentStatusEnum::PENDING(),
        ], true));

        return $this->timeline($order, $shipments, collect([$item]), $paid, $anyPacked, $everShipped, $everDelivered, false);
    }

    /** Friendly status for a single parcel (shipment). */
    public function parcelStatus(?string $shipmentStatus): array
    {
        $s = (string) $shipmentStatus;

        [$code, $stage] = match ($s) {
            (string) ShipmentStatusEnum::PENDING()            => ['packed', 2],
            (string) ShipmentStatusEnum::LABEL_PRINTED()      => ['packed', 2],
            (string) ShipmentStatusEnum::CONFIRMED()          => ['shipped', 3],
            (string) ShipmentStatusEnum::IN_TRANSIT()         => ['in_transit', 3],
            (string) ShipmentStatusEnum::OUT_FOR_DELIVERY()   => ['out_for_delivery', 4],
            (string) ShipmentStatusEnum::DELIVERED()          => ['delivered', 5],
            (string) ShipmentStatusEnum::ATTEMPTED_DELIVERY() => ['attempted', 4],
            (string) ShipmentStatusEnum::FAILURE()            => ['failed', 4],
            (string) ShipmentStatusEnum::RETURNED()           => ['returned', 0],
            (string) ShipmentStatusEnum::CANCELLED()          => ['cancelled', 0],
            (string) ShipmentStatusEnum::RTO_INITIATED()      => ['rto', 0],
            (string) ShipmentStatusEnum::RTO_DELIVERED()      => ['rto', 0],
            (string) ShipmentStatusEnum::LOST()               => ['lost', 0],
            default                                           => ['packed', 2],
        };

        return [
            'code'  => $code,
            'label' => (string) __('labels.parcel_' . $code),
            'stage' => $stage,
        ];
    }

    /** Friendly status for a single line item. */
    public function itemStatus(OrderItem $item): array
    {
        $status = (string) $item->status;
        $ff     = (string) $item->fulfillment_status;

        $code = match (true) {
            $status === (string) OrderItemStatusEnum::CANCELLED() => 'cancelled',
            $status === (string) OrderItemStatusEnum::REFUNDED() => 'refunded',
            $status === (string) OrderItemStatusEnum::RETURNED() => 'returned',
            ($status === (string) OrderItemStatusEnum::ACTIVE() && $this->itemHasActiveReturn($item)) => 'return_requested',
            $ff === (string) FulfillmentStatusEnum::FULFILLED() => 'delivered',
            $ff === (string) FulfillmentStatusEnum::ON_HOLD()   => 'on_hold',
            default                                             => $this->liveParcelItemCode($item, $ff),
        };

        return [
            'code'  => $code,
            'label' => (string) __('labels.item_' . $code),
            // When the item reached this status (best available timestamp).
            'at'    => $this->itemStatusAt($item, $code),
        ];
    }

    /**
     * Most-advanced live parcel state for the item, translated to shopper
     * vocabulary: out_for_delivery > attempted > in_transit > shipped > packed.
     */
    private function liveParcelItemCode(OrderItem $item, string $ff): string
    {
        $statuses = ($item->relationLoaded('shipmentItems')
            ? $item->shipmentItems->map(fn ($si) => $si->shipment)->filter()
            : collect())
            ->reject(fn ($s) => $s->is_return || (string) $s->status === (string) ShipmentStatusEnum::CANCELLED())
            ->map(fn ($s) => (string) $s->status);

        return match (true) {
            $statuses->contains((string) ShipmentStatusEnum::OUT_FOR_DELIVERY())   => 'out_for_delivery',
            $statuses->contains((string) ShipmentStatusEnum::ATTEMPTED_DELIVERY()) => 'attempted',
            $statuses->contains((string) ShipmentStatusEnum::IN_TRANSIT())         => 'in_transit',
            $statuses->contains((string) ShipmentStatusEnum::CONFIRMED())          => 'shipped',
            $ff === (string) FulfillmentStatusEnum::PARTIALLY_FULFILLED()          => 'shipped',
            $statuses->contains((string) ShipmentStatusEnum::PENDING())
                || $statuses->contains((string) ShipmentStatusEnum::LABEL_PRINTED()) => 'packed',
            default                                                                => 'processing',
        };
    }

    /**
     * Best-available "changed at" timestamp for an item's current status code,
     * using the same real fields the timeline reads (shipment / return times),
     * falling back to the item's own updated_at.
     */
    private function itemStatusAt(OrderItem $item, string $code): ?string
    {
        $fmt = fn ($dt) => $dt?->format('Y-m-d H:i:s');

        $shipments = $item->relationLoaded('shipmentItems')
            ? $item->shipmentItems->map(fn ($si) => $si->shipment)->filter()
            : collect();
        $returns = $item->relationLoaded('returns') ? $item->returns : collect();

        return match ($code) {
            'delivered' => $fmt($shipments->map(fn ($s) => $s->delivered_at)->filter()->max()),
            'shipped'   => $fmt($shipments->map(fn ($s) => $s->picked_up_at ?? $s->created_at)->filter()->min()),
            'out_for_delivery', 'attempted', 'in_transit' => $fmt($shipments->map(fn ($s) => $s->last_event_at)->filter()->max()),
            'packed'    => $fmt($shipments->map(fn ($s) => $s->created_at)->filter()->min()),
            'return_requested' => $fmt($returns->map(fn ($r) => $r->created_at)->filter()->max()),
            'returned'  => $fmt(
                $returns->map(fn ($r) => $r->received_at)->filter()->max()
                    ?? $returns->map(fn ($r) => $r->created_at)->filter()->max()
            ),
            'refunded'  => $fmt(
                $returns->map(fn ($r) => $r->refund_processed_at)->filter()->max()
                    ?? $item->updated_at
            ),
            default     => $fmt($item->updated_at), // cancelled / on_hold / processing
        };
    }

    /** Friendly status + tracker for a single return. */
    public function returnStatus(OrderItemReturn $return): array
    {
        $s = (string) $return->return_status;

        [$code, $stage] = match ($s) {
            (string) OrderItemReturnStatusEnum::REQUESTED()  => ['requested', 1],
            (string) OrderItemReturnStatusEnum::APPROVED()   => ['approved', 2],
            (string) OrderItemReturnStatusEnum::IN_TRANSIT() => ['in_transit', 3],
            (string) OrderItemReturnStatusEnum::RECEIVED()   => ['received', 4],
            (string) OrderItemReturnStatusEnum::REFUNDED()   => ['refunded', 5],
            (string) OrderItemReturnStatusEnum::CLOSED()     => ['closed', 5],
            (string) OrderItemReturnStatusEnum::DECLINED()   => ['declined', 0],
            (string) OrderItemReturnStatusEnum::CANCELLED()  => ['cancelled', 0],
            default                                          => ['requested', 1],
        };

        return [
            'code'         => $code,
            'label'        => (string) __('labels.rs_' . $code),
            'description'  => (string) __('labels.rs_' . $code . '_desc'),
            'stage'        => $stage,
            'is_exception' => $stage === 0,
        ];
    }


    /**
     * Parcel statuses that prove the parcel left the seller at some point —
     * including post-pickup exception states (failed attempt, RTO, lost,
     * returned), so a parcel that shipped and then bounced still counts as
     * having shipped.
     */
    private function shippedEvidenceStates(): array
    {
        return ShipmentStatusEnum::dispatched();
    }

    /** Flatten every shipment across the order's seller orders. */
    private function collectShipments(Order $order): Collection
    {
        if (! $order->relationLoaded('sellerOrders')) {
            return collect();
        }

        return $order->sellerOrders->flatMap(function ($so) {
            return $so->relationLoaded('shipments') ? $so->shipments : collect();
        })->values();
    }

    private function anyActiveReturn(Collection $items): bool
    {
        return $items->contains(fn ($item) => $this->itemHasActiveReturn($item));
    }

    private function itemHasActiveReturn(OrderItem $item): bool
    {
        if (! $item->relationLoaded('returns')) {
            return false;
        }
        $active = [
            (string) OrderItemReturnStatusEnum::REQUESTED(),
            (string) OrderItemReturnStatusEnum::APPROVED(),
            (string) OrderItemReturnStatusEnum::IN_TRANSIT(),
            (string) OrderItemReturnStatusEnum::RECEIVED(),
        ];

        return $item->returns->contains(fn ($r) => in_array((string) $r->return_status, $active, true));
    }

    /**
     * Canonical tracker. The five base steps are MONOTONIC — a milestone that has
     * happened stays done even after a later return/refund flips the live state
     * (payment → refunded, item → returned). When the order has a return/refund,
     * two more steps (Returned → Refunded) are appended after Delivered.
     */
    private function timeline(Order $order, Collection $shipments, Collection $items, bool $paid, bool $anyPacked, bool $everShipped, bool $everDelivered, bool $orderScope = true): array
    {
        $active = $shipments->reject(fn ($s) => (string) $s->status === (string) ShipmentStatusEnum::CANCELLED());
        $facts = !$orderScope && $items->count() === 1
            ? app(FulfillmentRollupService::class)->itemFacts($items->first()) : null;
        $displayProgress = $facts !== null ? $this->deliveryProgressForItem($items->first(), $facts) : null;

        $deliveredAt = $active
            ->filter(fn ($s) => (string) $s->status === (string) ShipmentStatusEnum::DELIVERED())
            ->map(fn ($s) => $s->delivered_at)
            ->filter()
            ->max();

        $anyInTransit = $anyOutForDelivery = $anyAttempted = $anyFailure = $anyRto = $anyLost = false;
        $primary = null;
        foreach ($active as $s) {
            $st = (string) $s->status;
            if ($st === (string) ShipmentStatusEnum::IN_TRANSIT())         { $anyInTransit = true; }
            if ($st === (string) ShipmentStatusEnum::OUT_FOR_DELIVERY())   { $anyOutForDelivery = true; }
            if ($st === (string) ShipmentStatusEnum::ATTEMPTED_DELIVERY()) { $anyAttempted = true; }
            if ($st === (string) ShipmentStatusEnum::FAILURE())            { $anyFailure = true; }
            if ($st === (string) ShipmentStatusEnum::RTO_INITIATED()
                || $st === (string) ShipmentStatusEnum::RTO_DELIVERED())   { $anyRto = true; }
            if ($st === (string) ShipmentStatusEnum::LOST())               { $anyLost = true; }
            if ($primary === null && ($s->tracking_number || $s->courier_name || $s->manual_carrier_name)) {
                $primary = $s;
            }
        }
        $primary = $primary ?? $active->first();

        $delivered = $everDelivered || $deliveredAt !== null;
        $shipped   = $everShipped || $delivered;

        $hasReturnRecord = $items->contains(fn ($i) =>
            ($i->relationLoaded('returns') && $i->returns->isNotEmpty())
            || (string) $i->status === (string) OrderItemStatusEnum::RETURNED()
        );
        if ($hasReturnRecord) {
            $shipped = true;
            $delivered = true;
        }
        if ($facts !== null) {
            $shipped = $facts['current'] > 0 && $displayProgress['target'] > 0 && $displayProgress['shipped'] >= $displayProgress['target'];
            $delivered = $facts['current'] > 0 && $displayProgress['target'] > 0 && $displayProgress['delivered'] >= $displayProgress['target'];
            $anyPacked = $facts['preparing'] || $facts['shipped'] > 0;
        }

        $terminalNoFulfil = $items->isNotEmpty()
            && $items->every(fn ($i) => in_array((string) $i->status, [
                (string) OrderItemStatusEnum::CANCELLED(),
                (string) OrderItemStatusEnum::REFUNDED(),
            ], true));
        $cancelled = $terminalNoFulfil && ! $shipped && ! $delivered;

        $ff = (string) ($orderScope ? $order->fulfillment_status : $items->first()?->fulfillment_status);
        $onHold = $ff === (string) FulfillmentStatusEnum::ON_HOLD();
        $paymentFailed = in_array((string) $order->payment_status, [
            (string) PaymentStatusEnum::FAILED(),
            (string) PaymentStatusEnum::VOIDED(),
        ], true);

        $reached = 1;
        $cashOnDelivery = in_array((string) $order->payment_method, ['cod', 'cash_on_delivery'], true);
        if ($paid || $shipped || $anyPacked || $cashOnDelivery) $reached = max($reached, 2);
        if ($anyPacked || $shipped) $reached = max($reached, 3);
        if ($shipped)               $reached = max($reached, 4);
        if ($delivered)             $reached = 5;

        $fmt = fn ($dt) => $dt?->format('Y-m-d H:i:s');

        $ev = function (string $code, bool $done, $at = null, bool $isException = false, ?array $meta = null): array {
            $e = [
                'code'         => $code,
                'label'        => (string) __('labels.ev_' . $code),
                'done'         => $done,
                'at'           => $at,
                'is_exception' => $isException,
            ];
            if ($meta !== null) {
                $e['meta'] = $meta;
            }
            return $e;
        };

        $courier = $primary ? ($primary->courier_name ?: $primary->manual_carrier_name) : null;
        $shipMeta = ($primary && ($courier || $primary->tracking_number)) ? array_filter([
            'courier'      => $courier,
            'tracking_id'  => $primary->tracking_number,
            'tracking_url' => $primary->tracking_url,
        ], fn ($v) => $v !== null && $v !== '') : null;
        if ($facts !== null && $facts['shipped'] > 0) {
            $shipMeta = ($shipMeta ?? []) + ['quantity' => $facts['shipped']];
        }

        $eventTimes = [];
        foreach ($active as $s) {
            if (! $s->relationLoaded('statusEvents')) {
                continue;
            }
            foreach ($s->statusEvents as $e) {
                $st = (string) $e->status;
                $t  = $e->occurred_at;
                if ($t && (! isset($eventTimes[$st]) || $t->lt($eventTimes[$st]))) {
                    $eventTimes[$st] = $t;
                }
            }
        }
        $tAt = fn (string $status) => isset($eventTimes[$status]) ? $eventTimes[$status]->format('Y-m-d H:i:s') : null;

        $paidAt = null;
        if ($order->relationLoaded('paymentTransactions')) {
            $paidStates = [
                (string) PaymentStatusEnum::COMPLETED(),
                (string) PaymentStatusEnum::PARTIALLY_PAID(),
                (string) PaymentStatusEnum::AUTHORIZED(),
                'paid',
            ];
            $paidAt = $order->paymentTransactions
                ->filter(fn ($t) => in_array(strtolower((string) $t->payment_status), $paidStates, true))
                ->map(fn ($t) => $t->created_at)->filter()->min();
        }
        $paidAtStr = $fmt($paidAt);

        $preparingAt    = $active->map(fn ($s) => $s->created_at)->filter()->min();
        $preparingAtStr = $tAt((string) ShipmentStatusEnum::LABEL_PRINTED())
            ?? $tAt((string) ShipmentStatusEnum::PENDING())
            ?? $fmt($preparingAt);

        $shippedAtStr = $tAt((string) ShipmentStatusEnum::CONFIRMED())
            ?? $fmt($active->map(fn ($s) => $s->picked_up_at)->filter()->min())
            ?? $tAt((string) ShipmentStatusEnum::IN_TRANSIT());
        if ($facts !== null && $shipped) {
            $dispatchTimes = $active->map(fn ($shipment) => $shipment->statusEvents
                ->filter(fn ($event) => in_array((string) $event->status, ['confirmed', 'in_transit', 'out_for_delivery'], true))
                ->pluck('occurred_at')->filter()->min() ?? $shipment->picked_up_at);
            $shippedAtStr = $dispatchTimes->isNotEmpty() && $dispatchTimes->every(fn ($at) => $at !== null)
                ? $fmt($dispatchTimes->max()) : null;
        }
        $shippedEventAt = $shippedAtStr;
        $deliveredEventAt = $fmt($deliveredAt);
        if (!$shipped) {
            $shippedAtStr = null;
        }
        if (!$delivered) {
            $deliveredAt = null;
        }

        $steps = [];

        $steps[] = [
            'key' => 'placed', 'done' => true, 'at' => $fmt($order->created_at),
            'events' => [ $ev('order_placed', true, $fmt($order->created_at)) ],
        ];

        $confirmedEvents = $paymentFailed
            ? [ $ev('payment_failed', true, $paidAtStr, true) ]
            : [ $ev('payment_received', $paid, $paidAtStr) ];
        if (!$paid && !$paymentFailed && $reached >= 2) {
            $confirmedEvents = [[
                'code' => 'confirmed', 'label' => __('labels.step_confirmed'),
                'done' => true, 'at' => null, 'is_exception' => false,
            ]];
        }
        $steps[] = [
            'key' => 'confirmed', 'done' => $reached >= 2, 'at' => $paidAtStr,
            'events' => $confirmedEvents,
        ];

        if ($cancelled) {
            $cancelledAt = null;
            $steps[] = [
                'key' => 'cancelled', 'done' => true, 'at' => $cancelledAt,
                'events' => [ $ev('cancelled', true, $cancelledAt, true) ],
            ];
        } else {
            $preparingEvents = [ $ev('preparing', $reached >= 3, $preparingAtStr) ];
            if ($onHold) { $preparingEvents[] = $ev('on_hold', true, null, true); }
            $steps[] = [
                'key' => 'preparing', 'done' => $reached >= 3, 'at' => $preparingAtStr,
                'events' => $preparingEvents,
            ];

            $shippedEvents = [ $ev('shipped', $reached >= 4 || ($facts['shipped'] ?? 0) > 0, $shippedEventAt, false, $shipMeta) ];
            if ($facts !== null && $active->isNotEmpty()) {
                $parcelEvents = $active->sortBy('id')->map(function ($shipment) use ($items, $ev, $fmt) {
                    $dispatchEvents = $shipment->statusEvents->filter(fn ($event) => in_array((string) $event->status, ['confirmed', 'in_transit', 'out_for_delivery'], true));
                    $dispatched = $shipment->picked_up_at || $shipment->delivered_at
                        || in_array((string) $shipment->status, ShipmentStatusEnum::dispatched(), true)
                        || $shipment->statusEvents->contains(fn ($event) => in_array((string) $event->status, ShipmentStatusEnum::dispatched(), true));
                    $at = $dispatched ? $fmt($dispatchEvents->pluck('occurred_at')->filter()->min() ?? $shipment->picked_up_at) : null;
                    $quantity = (int) $items->first()->shipmentItems->where('shipment_id', $shipment->id)->sum('quantity');
                    return $ev('shipped', (bool) $dispatched, $at, false, array_filter([
                        'quantity' => $quantity,
                        'courier' => $shipment->courier_name ?: $shipment->manual_carrier_name,
                        'tracking_id' => $shipment->tracking_number,
                        'tracking_url' => $shipment->tracking_url,
                    ], fn ($value) => $value !== null && $value !== ''));
                })->values()->all();
                $shippedEvents = $parcelEvents;
            }
            if ($anyInTransit) { $shippedEvents[] = $ev('in_transit', true, $tAt((string) ShipmentStatusEnum::IN_TRANSIT())); }
            $shippedEvents[] = $ev('out_for_delivery', $anyOutForDelivery || $delivered || ($facts['delivered'] ?? 0) > 0, $tAt((string) ShipmentStatusEnum::OUT_FOR_DELIVERY()));
            if ($anyAttempted) { $shippedEvents[] = $ev('attempted_delivery', true, $tAt((string) ShipmentStatusEnum::ATTEMPTED_DELIVERY()), true); }
            if ($anyFailure)   { $shippedEvents[] = $ev('delivery_failed', true, $tAt((string) ShipmentStatusEnum::FAILURE()), true); }
            if ($anyRto)       { $shippedEvents[] = $ev('rto', true, $tAt((string) ShipmentStatusEnum::RTO_INITIATED()), true); }
            if ($anyLost)      { $shippedEvents[] = $ev('lost', true, $tAt((string) ShipmentStatusEnum::LOST()), true); }
            $steps[] = [
                'key' => 'shipped', 'done' => $reached >= 4, 'at' => $shippedAtStr,
                'events' => $shippedEvents,
            ];

            $steps[] = [
                'key' => 'delivered', 'done' => $reached >= 5, 'at' => $fmt($deliveredAt),
                'events' => [ $ev('delivered', $delivered || ($facts['delivered'] ?? 0) > 0, $deliveredEventAt, false, ($facts['delivered'] ?? 0) > 0 ? ['quantity' => $facts['delivered']] : null) ],
            ];
        }

        $returns = $items->flatMap(fn ($i) => $i->relationLoaded('returns') ? $i->returns : collect());
        $abandonedReturns = $returns->filter(fn ($return) => in_array((string) $return->return_status, ['cancelled', 'declined'], true));
        $returns = $returns->reject(fn ($return) => in_array((string) $return->return_status, ['cancelled', 'declined'], true));
        $itemStatuses = $items->map(fn ($i) => (string) $i->status);
        $anyReturnedItem = $itemStatuses->contains((string) OrderItemStatusEnum::RETURNED());
        $anyRefundedItem = $itemStatuses->contains((string) OrderItemStatusEnum::REFUNDED());
        $paymentRefunded = in_array((string) $order->payment_status, [
            (string) PaymentStatusEnum::REFUNDED(),
            (string) PaymentStatusEnum::PARTIALLY_REFUNDED(),
        ], true);

        $returnRequestedAt = $returns->map(fn ($r) => $r->created_at)->filter()->min();
        $returnReceivedAt  = $returns->map(fn ($r) => $r->received_at)->filter()->max();
        $refundProcessedAt = $returns->map(fn ($r) => $r->refund_processed_at)->filter()->max();

        $isRefunded = $anyRefundedItem || ($orderScope && $paymentRefunded) || $refundProcessedAt !== null;
        $hasReturn  = $returns->isNotEmpty() || $anyReturnedItem;

        if (!$orderScope && $returns->count() > 1) {
            foreach ($returns as $return) {
                $status = (string) $return->return_status;
                $received = $return->received_at !== null || in_array($status, ['received', 'refunded', 'closed'], true);
                $approved = $return->seller_approved_at !== null || in_array($status, ['approved', 'in_transit', 'received', 'refunded', 'closed'], true);
                $pickedUp = $return->picked_up_at !== null || in_array($status, ['in_transit', 'received', 'refunded', 'closed'], true);
                $meta = ['quantity' => (int) $return->quantity, 'return_id' => (int) $return->id];
                if ($return->source_shipment_id) {
                    $meta['shipment_id'] = (int) $return->source_shipment_id;
                }
                $returnEvents = [
                    array_merge($ev('return_'.$return->id.'_requested', true, $fmt($return->created_at), false, $meta), ['label' => __('labels.ev_return_requested')]),
                    array_merge($ev('return_'.$return->id.'_approved', $approved, $fmt($return->seller_approved_at), false, $meta), ['label' => __('labels.return_approved')]),
                ];
                if (!$return->no_shipping_required) {
                    $returnEvents[] = array_merge($ev('return_'.$return->id.'_picked_up', $pickedUp, $fmt($return->picked_up_at), false, $meta), ['label' => __('labels.rstep_picked_up')]);
                }
                $returnEvents[] = array_merge($ev('return_'.$return->id.'_received', $received, $fmt($return->received_at), false, $meta), ['label' => __('labels.ev_return_received')]);
                $steps[] = ['key' => 'returned_'.$return->id, 'label' => __('labels.step_returned'), 'done' => $received, 'at' => $fmt($return->received_at), 'events' => $returnEvents];
            }
        } elseif ($hasReturn) {
            $returnArrivedBack = $returns->isNotEmpty()
                ? $returns->every(fn ($return) => $return->received_at !== null || in_array((string) $return->return_status, [
                    (string) OrderItemReturnStatusEnum::RECEIVED(),
                    (string) OrderItemReturnStatusEnum::REFUNDED(),
                    (string) OrderItemReturnStatusEnum::CLOSED(),
                ], true)) : $anyReturnedItem;

            $reason = $returns->map(fn ($r) => $r->reason)->filter()->first();

            $receivedStr = $returnArrivedBack ? $fmt($returnReceivedAt) : null;
            $returnQuantity = (int) $returns->sum('quantity');
            $returnApproved = $returnArrivedBack || ($returns->isNotEmpty() && $returns->every(fn ($return) => $return->seller_approved_at !== null || in_array((string) $return->return_status, ['approved', 'in_transit', 'received', 'refunded', 'closed'], true)));
            $returnPickedUp = $returnArrivedBack || ($returns->isNotEmpty() && $returns->every(fn ($return) => $return->picked_up_at !== null || in_array((string) $return->return_status, ['in_transit', 'received', 'refunded', 'closed'], true)));
            $returnEvents = [
                $ev('return_requested', true, $fmt($returnRequestedAt), false, array_filter(['reason' => $reason, 'quantity' => $returnQuantity])),
                array_merge($ev('return_approved', $returnApproved, $returnApproved ? $fmt($returns->pluck('seller_approved_at')->filter()->max()) : null, false, ['quantity' => $returnQuantity]), ['label' => __('labels.return_approved')]),
            ];
            if (!$returns->every(fn ($return) => $return->no_shipping_required)) {
                $returnEvents[] = array_merge($ev('return_picked_up', $returnPickedUp, $returnPickedUp ? $fmt($returns->pluck('picked_up_at')->filter()->max()) : null, false, ['quantity' => $returnQuantity]), ['label' => __('labels.rstep_picked_up')]);
            }
            $returnEvents[] = $ev('return_received', $returnArrivedBack, $receivedStr, false, $returnQuantity > 0 ? ['quantity' => $returnQuantity] : null);

            $steps[] = [
                'key' => 'returned', 'done' => $returnArrivedBack, 'at' => $receivedStr,
                'events' => $returnEvents,
            ];
        }

        if ($abandonedReturns->isNotEmpty() && !$hasReturn) {
            $returnEvents = $abandonedReturns->flatMap(fn ($return) => [
                $ev('return_requested', true, $fmt($return->created_at), false, ['quantity' => (int) $return->quantity]),
                [
                    'code' => 'return_'.$return->id.'_'.(string) $return->return_status,
                    'label' => __('labels.return_'.(string) $return->return_status),
                    'done' => true, 'at' => null, 'is_exception' => false,
                    'meta' => ['quantity' => (int) $return->quantity],
                ],
            ])->all();
            $steps[] = ['key' => 'returned', 'done' => false, 'at' => null, 'events' => $returnEvents];
        }

        $refundEvents = !$orderScope && ($cancelled || $hasReturn || $anyRefundedItem)
            ? collect($this->activityForItem($items->first()))->filter(fn ($event) => str_starts_with($event['code'], 'refund_'))->values()
            : collect();
        if ($refundEvents->isNotEmpty()) {
            $refundDone = $refundEvents->every(fn ($event) => $event['done']);
            $steps[] = [
                'key' => 'refunded', 'done' => $refundDone,
                'label' => $refundDone ? __('labels.step_refunded') : ($refundEvents->firstWhere('done', false)['label'] ?? __('labels.customer_refund_pending')),
                'at' => $refundDone ? $refundEvents->pluck('at')->filter()->max() : null,
                'events' => $refundEvents->all(),
            ];
        } elseif (($hasReturn && $paid) || $isRefunded) {
            $steps[] = [
                'key' => 'refunded', 'done' => $isRefunded, 'at' => $fmt($refundProcessedAt),
                'events' => [ $ev('refund_issued', $isRefunded, $fmt($refundProcessedAt), false, $hasReturn ? ['quantity' => (int) $returns->sum('quantity')] : null) ],
            ];
        }

        $lastDone = -1;
        foreach ($steps as $i => $s) {
            if ($s['done']) {
                $lastDone = $i;
            }
        }

        return array_map(function ($s, $i) use ($lastDone, $displayProgress) {
            $current = $i === $lastDone;
            $step = [
                'key'     => $s['key'],
                'label'   => $s['label'] ?? (string) __('labels.step_' . $s['key']),
                'done'    => $s['done'],
                'current' => $current,
                'marker'  => $current ? 'current' : ($s['done'] ? 'done' : 'pending'),
                'at'      => $s['at'],
                'events'  => $s['events'],
            ];
            if ($displayProgress !== null && in_array($s['key'], ['shipped', 'delivered'], true)) {
                $step['quantity'] = $displayProgress['quantity'];
                $step['completed_quantity'] = $displayProgress[$s['key']];
            }
            return $step;
        }, $steps, array_keys($steps));
    }

}
