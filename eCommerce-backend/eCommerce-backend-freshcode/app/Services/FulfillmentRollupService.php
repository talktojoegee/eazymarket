<?php

namespace App\Services;

use App\Enums\Order\FulfillmentStatusEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\Shipping\ShipmentStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Models\Shipment;

/**
 * Derives the coarse `fulfillment_status` axis UP from shipments — the only
 * place fulfillment progress is set is `shipments.status` (+ the per-line
 * `shipment_items.quantity`); this rolls it up:
 *
 *   shipment.status + qty  →  order_item.fulfillment_status (per item, by qty)
 *                          →  seller_order.fulfillment_status (aggregate)
 *                          →  order.fulfillment_status (aggregate)
 *
 * Fulfilment is delivery-based and quantity-aware: a line is FULFILLED only when
 * ALL its units have been delivered, PARTIALLY_FULFILLED when some have,
 * IN_PROGRESS when units sit in a live (non-cancelled) shipment that hasn't
 * delivered yet, and UNFULFILLED otherwise — so partial-quantity shipments
 * (ship 1 of 2 now, 1 later) roll up correctly.
 */
class FulfillmentRollupService
{
    public function itemFacts(OrderItem $item): array
    {
        $item->loadMissing('shipmentItems.shipment.statusEvents');
        $quantity = (string) $item->status === 'cancelled' ? 0 : max(0, (int) $item->quantity);
        $lines = $item->shipmentItems->filter(fn ($line) => $line->shipment && !$line->shipment->is_return
            && (string) $line->shipment->status !== 'cancelled');
        $shippedStates = ShipmentStatusEnum::dispatched();
        $hasEvidence = fn ($shipment, $states) => in_array((string) $shipment->status, $states, true)
            || $shipment->statusEvents->contains(fn ($event) => in_array((string) $event->status, $states, true));
        $shipped = $lines->filter(fn ($line) => $line->shipment->picked_up_at || $line->shipment->delivered_at || $hasEvidence($line->shipment, $shippedStates));
        $delivered = $lines->filter(fn ($line) => $line->shipment->delivered_at || $hasEvidence($line->shipment, ['delivered']));
        $committed = $lines->filter(fn ($line) => (string) $line->shipment->status !== 'pending'
            || $line->shipment->picked_up_at || $line->shipment->delivered_at || $hasEvidence($line->shipment, $shippedStates));
        $legacyDelivered = $item->shipmentItems->isEmpty() && (string) $item->fulfillment_status === 'fulfilled';

        return [
            'current' => $quantity,
            'allocated' => min($quantity, (int) $lines->sum('quantity')),
            'committed' => $legacyDelivered ? $quantity : min($quantity, (int) $committed->sum('quantity')),
            'shipped' => $legacyDelivered ? $quantity : min($quantity, (int) $shipped->sum('quantity')),
            'delivered' => $legacyDelivered ? $quantity : min($quantity, (int) $delivered->sum('quantity')),
            'unshipped' => $legacyDelivered ? 0 : max(0, $quantity - (int) $committed->sum('quantity')),
            'on_hold' => $quantity > 0 && $lines->isEmpty() && (string) $item->fulfillment_status === 'on_hold',
            'preparing' => $quantity > 0 && ($legacyDelivered || $lines->isNotEmpty() || (string) $item->fulfillment_status === 'in_progress'),
        ];
    }

    public function cancellationForItem(OrderItem $item, bool $customer = false, ?array $facts = null): array
    {
        $facts ??= $this->itemFacts($item);
        $policyState = $facts['allocated'] > 0 && $facts['committed'] === 0 ? 'unfulfilled' : (string) $item->fulfillment_status;
        $reason = match (true) {
            (string) $item->status !== 'active' => 'inactive',
            $customer && !$item->is_cancelable => 'policy',
            $facts['unshipped'] === 0 && $facts['delivered'] > 0 => 'delivered',
            $facts['unshipped'] === 0 && $facts['shipped'] > 0 => 'shipped',
            $facts['unshipped'] === 0 => 'allocated',
            $customer && $facts['committed'] === 0 && !in_array($policyState,
                FulfillmentStatusEnum::cancellableWhileIn($item->cancelable_till), true) => 'policy',
            default => null,
        };

        return ['allowed' => $reason === null, 'quantity' => $reason === null ? $facts['unshipped'] : 0, 'reason' => $reason];
    }

    /**
     * Entry point: a shipment changed — recompute its seller order, that seller
     * order's items, and the parent order.
     */
    public function syncFromShipment(Shipment $shipment): void
    {
        $sellerOrder = $shipment->sellerOrder;
        if ($sellerOrder) {
            $this->recomputeSellerOrder($sellerOrder);
        }
    }

    public function recomputeSellerOrder(SellerOrder $sellerOrder): void
    {
        $orderItemIds = $sellerOrder->items()->pluck('order_item_id')->filter()->all();
        $orderItems = OrderItem::whereIn('id', $orderItemIds)->with('shipmentItems.shipment.statusEvents')->get();

        $activeStatuses = [];
        foreach ($orderItems as $orderItem) {
            if (in_array((string) $orderItem->status, $this->terminalItemStatuses(), true)) {
                continue;
            }
            $status = $this->deriveItemStatus($orderItem);
            $orderItem->update(['fulfillment_status' => $status]);
            $activeStatuses[] = $status;
        }

        $sellerOrder->update(['fulfillment_status' => $this->aggregate($activeStatuses)]);

        $order = $sellerOrder->order;
        if ($order) {
            $this->recomputeOrder($order);
        }
    }

    public function recomputeOrder(Order $order): void
    {
        $statuses = $order->sellerOrders()->pluck('fulfillment_status')->all();

        $order->update(['fulfillment_status' => $this->aggregate($statuses)]);

        $this->recomputeOrderStatus($order);
    }

    /**
     * Derive the order-level lifecycle bucket (orders.status) from the item axes:
     *   - CANCELLED — every line is cancelled (the order died pre-fulfilment).
     *   - OPEN      — still has unresolved work (a live, not-yet-delivered line, or an
     *                 open return). Also pulls a stale ARCHIVED order back to OPEN.
     *   - ARCHIVED  — left ALONE here. Archiving is an explicit action (admin "Archive"
     *                 or the completion/settlement job), only valid once the order is
     *                 fully resolved (see isFullyResolved). This method preserves an
     *                 existing ARCHIVED while the order is still resolved, but never
     *                 auto-archives, so the settlement trigger stays under control.
     */
    public function recomputeOrderStatus(Order $order): void
    {
        $items = $order->items()->with('returns')->get();
        if ($items->isEmpty()) {
            return;
        }

        $cancelledLike = [
            (string) OrderItemStatusEnum::CANCELLED(),
        ];

        $allCancelled = $items->every(fn ($i) => in_array((string) $i->status, $cancelledLike, true));
        $resolved = $this->isFullyResolved($items);

        $current = (string) $order->status;
        $archived = (string) OrderStatusEnum::ARCHIVED();

        $new = match (true) {
            $allCancelled                          => (string) OrderStatusEnum::CANCELLED(),
            $resolved && $current === $archived    => $archived,
            $resolved                              => (string) OrderStatusEnum::OPEN(),
            default                                => (string) OrderStatusEnum::OPEN(),
        };

        $updates = [];
        if ($current !== $new) {
            $updates['status'] = $new;
        }

        if (! $allCancelled) {
            if ($resolved && $order->resolved_at === null) {
                $updates['resolved_at'] = now();
            } elseif (! $resolved && $order->resolved_at !== null) {
                $updates['resolved_at'] = null;
            }
        }

        if (! empty($updates)) {
            $order->update($updates);
        }
    }

    /**
     * Is every line of the order finished — i.e. terminal (cancelled/returned/
     * refunded), or delivered with no open return? This is the gate an
     * order must pass before it can be archived (and, later, settled).
     *
     * @param \Illuminate\Support\Collection $items items with their `returns` loaded
     */
    public function isFullyResolved($items): bool
    {
        $terminalLike = [
            (string) OrderItemStatusEnum::CANCELLED(),
            (string) OrderItemStatusEnum::RETURNED(),
            (string) OrderItemStatusEnum::REFUNDED(),
        ];
        $openReturnStatuses = [
            (string) OrderItemReturnStatusEnum::REQUESTED(),
            (string) OrderItemReturnStatusEnum::APPROVED(),
            (string) OrderItemReturnStatusEnum::IN_TRANSIT(),
            (string) OrderItemReturnStatusEnum::RECEIVED(),
        ];
        $fulfilled = (string) FulfillmentStatusEnum::FULFILLED();

        return $items->isNotEmpty() && $items->every(function ($i) use ($terminalLike, $fulfilled, $openReturnStatuses) {
            if (in_array((string) $i->status, $terminalLike, true)) {
                return true;
            }
            $delivered = (string) $i->fulfillment_status === $fulfilled;
            $hasOpenReturn = $i->relationLoaded('returns')
                && $i->returns->contains(fn ($r) => in_array((string) $r->return_status, $openReturnStatuses, true));

            return $delivered && ! $hasOpenReturn;
        });
    }

    /**
     * Days to wait after an order is resolved before auto-archiving (and, later,
     * settling). Reads the `orderAutoArchiveDays` system setting (default 10).
     *
     * DEV SHORTCUT: if a marker file exists at storage/dev, the grace is dropped to
     * 0 so auto-archive can be exercised without waiting. (The per-item return
     * window still applies — only the grace buffer is cut.)
     */
    public function graceDays(): int
    {
        if (is_file(storage_path('dev'))) {
            return 0;
        }

        $values = app(SettingService::class)->getSettingValues(SettingTypeEnum::SYSTEM());

        return max(0, (int) ($values['orderAutoArchiveDays'] ?? 10));
    }

    /**
     * When this order is expected to auto-archive (and seller settlement to fire):
     *   max( latest return_deadline across returnable items,  resolved_at + grace ).
     * Returns null while the order isn't fully resolved yet (no resolved_at).
     */
    public function estimatedArchiveDate(Order $order): ?\Illuminate\Support\Carbon
    {
        if ($order->resolved_at === null) {
            return null;
        }

        $graceDate = $order->resolved_at->copy()->addDays($this->graceDays());

        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();
        $latestReturnClose = $items
            ->filter(fn ($i) => $i->return_eligible && $i->return_deadline)
            ->map(fn ($i) => $i->return_deadline->copy()->endOfDay())
            ->max();

        return ($latestReturnClose && $latestReturnClose->greaterThan($graceDate))
            ? $latestReturnClose
            : $graceDate;
    }

    /**
     * Set the seller order's fulfillment status BY HAND (Shopify's "Mark as in
     * progress / on hold / unfulfilled"). Only permitted while the seller order
     * has no live shipment — once a shipment exists the status is derived from
     * shipments and this manual value would be immediately overwritten.
     *
     * @throws \RuntimeException on an illegal value or when a live shipment exists
     */
    public function setManualStatus(SellerOrder $sellerOrder, string $status): void
    {
        $allowed = array_map(fn ($s) => (string) $s, FulfillmentStatusEnum::manuallySettable());
        if (!in_array($status, $allowed, true)) {
            throw new \RuntimeException(__('labels.invalid_fulfillment_status'));
        }

        $cancelled = (string) ShipmentStatusEnum::CANCELLED();
        $hasLiveShipment = $sellerOrder->shipments()
            ->where('status', '!=', $cancelled)
            ->exists();
        if ($hasLiveShipment) {
            throw new \RuntimeException(__('labels.fulfillment_status_locked_by_shipment'));
        }

        $sellerOrder->update(['fulfillment_status' => $status]);

        $orderItemIds = $sellerOrder->items()->pluck('order_item_id')->filter()->all();
        if (!empty($orderItemIds)) {
            OrderItem::whereIn('id', $orderItemIds)
                ->whereNotIn('status', $this->terminalItemStatuses())
                ->update(['fulfillment_status' => $status]);
        }

        if ($order = $sellerOrder->order) {
            $this->recomputeOrder($order);
        }
    }

    /**
     * A single order line's fulfillment status from how many of its units have
     * been delivered. Units in a cancelled shipment don't count (they freed up).
     */
    private function deriveItemStatus(OrderItem $orderItem): string
    {
        $facts = $this->itemFacts($orderItem);
        $ordered = $facts['current'];
        if ($ordered <= 0) {
            return (string) FulfillmentStatusEnum::UNFULFILLED();
        }

        $deliveredQty = $facts['delivered'];

        if ($deliveredQty >= $ordered) {
            return (string) FulfillmentStatusEnum::FULFILLED();
        }
        if ($deliveredQty > 0) {
            return (string) FulfillmentStatusEnum::PARTIALLY_FULFILLED();
        }

        $shippedQty = $facts['allocated'];

        if ($shippedQty > 0) {
            return (string) FulfillmentStatusEnum::IN_PROGRESS();
        }

        return (string) FulfillmentStatusEnum::UNFULFILLED();
    }

    /**
     * Aggregate a set of child fulfillment statuses: all fulfilled → fulfilled;
     * any progress (fulfilled or partial) → partially_fulfilled. If nothing has
     * shipped yet, a manual hold/in-progress flag on any child surfaces upward
     * (on_hold wins over in_progress); otherwise unfulfilled.
     *
     * @param array<int,string> $statuses
     */
    private function aggregate(array $statuses): string
    {
        if (empty($statuses)) {
            return (string) FulfillmentStatusEnum::UNFULFILLED();
        }

        $fulfilled  = (string) FulfillmentStatusEnum::FULFILLED();
        $partial    = (string) FulfillmentStatusEnum::PARTIALLY_FULFILLED();
        $onHold     = (string) FulfillmentStatusEnum::ON_HOLD();
        $inProgress = (string) FulfillmentStatusEnum::IN_PROGRESS();

        $values = collect($statuses)->map(fn ($s) => (string) $s);

        if ($values->every(fn ($s) => $s === $fulfilled)) {
            return $fulfilled;
        }

        if ($values->contains(fn ($s) => in_array($s, [$fulfilled, $partial], true))) {
            return $partial;
        }

        if ($values->contains($onHold)) {
            return $onHold;
        }
        if ($values->contains($inProgress)) {
            return $inProgress;
        }

        return (string) FulfillmentStatusEnum::UNFULFILLED();
    }

    /** Order-item statuses that take a line out of the fulfilment picture. */
    private function terminalItemStatuses(): array
    {
        return [
            (string) OrderItemStatusEnum::CANCELLED(),
            (string) OrderItemStatusEnum::REFUNDED(),
            (string) OrderItemStatusEnum::RETURNED(),
        ];
    }
}
