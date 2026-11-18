<?php

namespace App\Services;

use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Seller\SellerSettlementTypeEnum;
use App\Enums\Shipping\FulfillmentMethodEnum;
use App\Enums\Shipping\ShipmentStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Models\OrderItem;
use App\Models\OrderItemReturn;
use App\Models\SellerOrder;
use App\Models\SellerStatement;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\Shipping\Providers\ShiprocketProvider;
use App\Services\Shipping\ShipmentMoney;
use App\Services\Shipping\ShipmentProviderResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates and advances shipments for a seller order, and keeps the fulfillment
 * rollup in sync. This is the Shopify-aligned fulfillment engine: there is no
 * seller "acceptance" gate — a shipment simply contains the seller order's live
 * (active) lines; cancelled lines never ship.
 *
 * Phase scope: only `manual` and `local_pickup` are live. `shiprocket` and
 * `local_delivery` are rejected until their provider / coverage config exists.
 */
class ShipmentService
{
    private ?int $manualDispatchSlaDays = null;

    public function __construct(
        protected FulfillmentRollupService $rollup,
        protected SellerStatementService $sellerStatementService,
        protected ShipmentProviderResolver $providers,
    ) {
    }

    /**
     * Create the shipment for a seller order and roll the fulfillment status up.
     *
     * $data (optional): carrier_name, tracking_number, tracking_url, weight_kg.
     *
     * @throws \RuntimeException on unsupported method / missing location / nothing to ship
     */
    public function createForSellerOrder(SellerOrder $sellerOrder, array $data = []): Shipment
    {
        $method = $this->methodValue($sellerOrder);
        $provider = $this->providers->resolveOrFail($method);

        if (!$sellerOrder->store_location_id) {
            throw new RuntimeException(__('labels.shipment_requires_store_location'));
        }

        $shipment = DB::transaction(function () use ($sellerOrder, $method, $data, $provider) {

            SellerOrder::whereKey($sellerOrder->id)
                ->lockForUpdate()
                ->first();

            $activeItems = $sellerOrder->items()
                ->whereHas('orderItem', fn ($q) => $q->where('status', OrderItemStatusEnum::ACTIVE()))
                ->with('orderItem')
                ->get();

            if ($activeItems->isEmpty()) {
                throw new RuntimeException(__('labels.no_items_to_fulfill'));
            }

            $remaining = $this->remainingToShipMap($activeItems);

            $linesToShip = $this->resolveShipmentLines($remaining, $data);

            $order = $sellerOrder->order;
            $isCod = $order && stripos((string) $order->payment_method, 'cod') !== false;

            $money = ShipmentMoney::linesFor($sellerOrder, $linesToShip);
            $cancelled = (string) ShipmentStatusEnum::CANCELLED();
            $carriesShippingShare = $sellerOrder->shipments()
                ->where('status', '!=', $cancelled)
                ->where('shipping_cost', '>', 0)
                ->exists();
            $shippingShare = $carriesShippingShare ? 0.0 : (float) $sellerOrder->shipping_charge_applied;

            $baseAttributes = [
                'uuid'              => (string) Str::uuid(),
                'seller_order_id'   => $sellerOrder->id,
                'store_location_id' => $sellerOrder->store_location_id,
                'provider_code'     => $method,
                'shipping_zone_id'  => $sellerOrder->shipping_zone_id,
                'weight_kg'         => (float) ($data['weight_kg'] ?? 0),
                'shipping_cost'     => $shippingShare,
                'shipping_currency' => $order->currency_code ?? 'USD',
                'payment_method'    => $isCod ? 'cod' : 'prepaid',
                'cod_amount'        => $isCod
                    ? round($money['gross'] - $money['discount'] + $shippingShare, 2)
                    : null,
                'last_event_at'     => now(),
            ];

            $shipment = Shipment::create(array_merge(
                $baseAttributes,
                $provider->buildShipmentAttributes($sellerOrder, $data),
            ));

            foreach ($linesToShip as $orderItemId => $qty) {
                ShipmentItem::create([
                    'shipment_id'   => $shipment->id,
                    'order_item_id' => $orderItemId,
                    'quantity'      => $qty,
                ]);
            }

            $shipment->recordStatusEvent();

            $this->rollup->syncFromShipment($shipment);
            return $shipment;
        });

        try {
            $this->bookRemote($shipment);
        } catch (\Throwable $e) {
            Log::warning('Shipment booking failed after creation', [
                'shipment_id' => $shipment->id,
                'error'       => $e->getMessage(),
            ]);
        }

        return $shipment;
    }

    public function bookRemote(Shipment $shipment): Shipment
    {
        $provider = $this->providers->resolve($this->methodValue($shipment->sellerOrder));
        if (!$provider) {
            return $shipment;
        }

        $before = (string) $shipment->status;
        $provider->bookShipment($shipment);

        if ((string) $shipment->status !== $before) {
            $shipment->recordStatusEvent();
            $this->rollup->syncFromShipment($shipment);
        }

        return $shipment;
    }

    public function retryBooking(Shipment $shipment): Shipment
    {
        $meta = (array) ($shipment->metadata ?? []);
        $isPending = (string) $shipment->status === (string) ShipmentStatusEnum::PENDING();
        $terminal = array_map(fn ($s) => (string) $s, ShipmentStatusEnum::terminal());

        if (in_array((string) $shipment->status, $terminal, true)
            || (!$isPending && empty($meta['booking_warning']))) {
            throw new RuntimeException(__('labels.shipment_not_pending'));
        }

        return $this->bookRemote($shipment);
    }

    public function updatePendingShipmentLines(Shipment $shipment, array $data): Shipment
    {
        if ((string) $shipment->status !== (string) ShipmentStatusEnum::PENDING()) {
            throw new RuntimeException(__('labels.shipment_not_pending'));
        }

        $sellerOrder = $shipment->sellerOrder;
        $provider = $this->providers->resolveOrFail($this->methodValue($sellerOrder));

        $lines = DB::transaction(fn () => $this->resolveEditableLines($sellerOrder, $shipment, $data));

        foreach (['weight_kg', 'length_cm', 'breadth_cm', 'height_cm'] as $field) {
            if (isset($data[$field]) && is_numeric($data[$field])) {
                $shipment->{$field} = $field === 'weight_kg' ? (float) $data[$field] : (int) round((float) $data[$field]);
            }
        }

        $provider->updateRemoteLines($shipment, $lines);

        return DB::transaction(function () use ($shipment, $sellerOrder, $data) {
            $fresh = Shipment::whereKey($shipment->id)->lockForUpdate()->first();
            if (!$fresh
                || (string) $fresh->status !== (string) ShipmentStatusEnum::PENDING()
                || !empty($fresh->awb_number)) {
                throw new RuntimeException(__('labels.shipment_not_pending'));
            }

            $lines = $this->resolveEditableLines($sellerOrder, $shipment, $data);

            $shipment->items()->delete();
            foreach ($lines as $orderItemId => $qty) {
                ShipmentItem::create([
                    'shipment_id'   => $shipment->id,
                    'order_item_id' => $orderItemId,
                    'quantity'      => $qty,
                ]);
            }

            if ((string) $shipment->payment_method === 'cod') {
                $money = ShipmentMoney::linesFor($sellerOrder, $lines);
                $shipment->cod_amount = round($money['gross'] - $money['discount'] + (float) $shipment->shipping_cost, 2);
            }

            $shipment->last_event_at = now();
            $shipment->save();

            $this->rollup->syncFromShipment($shipment);

            return $shipment;
        });
    }

    public function ndrAction(Shipment $shipment, string $action, string $comments): Shipment
    {
        $attempted = (string) ShipmentStatusEnum::ATTEMPTED_DELIVERY();
        $provider = $this->providers->resolve($this->methodValue($shipment->sellerOrder));

        if ((string) $shipment->status !== $attempted
            || !$provider instanceof ShiprocketProvider
            || !$shipment->awb_number) {
            throw new RuntimeException(__('labels.ndr_not_available'));
        }

        $provider->ndrAction($shipment, $action, $comments);

        if ($action === 'return') {
            $this->applyCarrierStatus($shipment, (string) ShipmentStatusEnum::RTO_INITIATED(), __('labels.ndr_return_note'));
        } else {
            $shipment->recordStatusEvent(null, __('labels.ndr_reattempt_note') . ' ' . $comments);
        }

        return $shipment;
    }

    private function resolveEditableLines(SellerOrder $sellerOrder, Shipment $shipment, array $data): array
    {
        SellerOrder::whereKey($sellerOrder->id)
            ->lockForUpdate()
            ->first();

        $activeItems = $sellerOrder->items()
            ->whereHas('orderItem', fn ($q) => $q->where('status', OrderItemStatusEnum::ACTIVE()))
            ->with('orderItem')
            ->get();

        $remaining = $this->remainingToShipMap($activeItems);
        foreach ($shipment->items()->get() as $shipmentItem) {
            $itemId = (int) $shipmentItem->order_item_id;
            $remaining[$itemId] = (int) ($remaining[$itemId] ?? 0) + (int) $shipmentItem->quantity;
        }

        return $this->resolveShipmentLines($remaining, $data);
    }

    private function resolveShipmentLines(array $remaining, array $data): array
    {
        $itemsProvided = array_key_exists('items', $data) && is_array($data['items']);

        $requested = collect($data['items'] ?? [])
            ->filter(fn ($line) => (int) ($line['quantity'] ?? 0) > 0);

        if ($itemsProvided && $requested->isEmpty()) {
            throw new RuntimeException(__('labels.select_at_least_one_item'));
        }

        // If nothing was requested, use all remaining quantities.
        if ($requested->isEmpty()) {
            $requested = collect($remaining)->map(
                fn ($qty, $itemId) => [
                    'order_item_id' => $itemId,
                    'quantity'      => $qty,
                ]
            );
        }

        $lines = [];

        foreach ($requested as $line) {
            $itemId = (int) $line['order_item_id'];
            $qty = (int) $line['quantity'];
            $available = (int) ($remaining[$itemId] ?? 0);

            if ($available <= 0) {
                continue;
            }

            if ($qty > $available) {
                throw new RuntimeException(__('labels.fulfill_qty_exceeds_remaining'));
            }

            $lines[$itemId] = $qty;
        }

        if (empty($lines)) {
            throw new RuntimeException(__('labels.no_items_available_to_ship'));
        }

        return $lines;
    }

    /**
     * Per-active-line fulfilment summary for a seller order, for the fulfil UI:
     * ordered / shipped / delivered / remaining quantities. Only ACTIVE lines
     * are returned (cancelled/refunded/returned lines aren't fulfillable).
     *
     * @return array<int, array{order_item_id:int,title:string,variant:?string,ordered:int,shipped:int,delivered:int,remaining:int}>
     */
    public function lineFulfillmentSummary(SellerOrder $sellerOrder): array
    {
        $activeItems = $sellerOrder->items()
            ->whereHas('orderItem', fn ($q) => $q->where('status', OrderItemStatusEnum::ACTIVE()))
            ->with(['orderItem.product.media', 'orderItem.variant.media'])
            ->get();

        if ($activeItems->isEmpty()) {
            return [];
        }

        $orderItemIds = $activeItems->pluck('order_item_id')->map(fn ($i) => (int) $i)->all();
        $cancelled = (string) ShipmentStatusEnum::CANCELLED();
        $delivered = (string) ShipmentStatusEnum::DELIVERED();

        $shippedByItem = ShipmentItem::query()
            ->whereIn('order_item_id', $orderItemIds)
            ->whereHas('shipment', fn ($q) => $q->where('status', '!=', $cancelled)->where('is_return', false))
            ->selectRaw('order_item_id, SUM(quantity) as qty')->groupBy('order_item_id')
            ->pluck('qty', 'order_item_id');
        $deliveredByItem = ShipmentItem::query()
            ->whereIn('order_item_id', $orderItemIds)
            ->whereHas('shipment', fn ($q) => $q->where('status', $delivered)->where('is_return', false))
            ->selectRaw('order_item_id, SUM(quantity) as qty')->groupBy('order_item_id')
            ->pluck('qty', 'order_item_id');

        $out = [];
        foreach ($activeItems as $soi) {
            $oi = $soi->orderItem;
            $comboComponents = $oi
                ? ($oi->relationLoaded('comboComponents') ? $oi->getRelation('comboComponents') : $oi->comboComponents()->get())
                : collect();
            $itemId  = (int) $soi->order_item_id;
            $ordered = (int) ($oi->quantity ?? $soi->quantity);
            $shipped = (int) ($shippedByItem[$itemId] ?? 0);
            $out[] = [
                'order_item_id' => $itemId,
                'product_id'    => (int) ($oi->product_id ?? 0),
                'title'         => $oi->title ?? '—',
                'variant'       => $oi->variant_title ?? null,
                'combo_components' => $comboComponents->map(fn ($component) => [
                    'title' => $component->product_title,
                    'variant' => $component->variant_title,
                    'quantity' => (int) $component->quantity,
                ])->all() ?? [],
                'image'         => $oi->variant?->image ?: $oi->product?->main_image,
                'unit_price'    => (float) ($oi->price ?? 0),
                'line_total'    => (float) ($oi->subtotal ?? 0),
                'ordered'       => $ordered,
                'shipped'       => $shipped,
                'delivered'     => (int) ($deliveredByItem[$itemId] ?? 0),
                'remaining'     => max(0, $ordered - $shipped),
                'weight_kg'     => round((float) ($oi->variant->weight ?? 0), 3),
                'delivery_estimate' => $oi ? $this->deliveryEstimateText($oi) : null,
                'attachments'   => $oi ? $this->orderItemAttachments($oi) : [],
                'addons'        => $oi ? $this->orderItemAddons($oi) : [],
            ];
        }

        return $out;
    }

    /**
     * Addons snapshotted on an order item — label plus the price charged, which
     * is stored in the order's market currency.
     *
     * @return array<int, array{label:string, price:float}>
     */
    private function orderItemAddons($orderItem): array
    {
        return $orderItem->addons()
            ->with(['addonGroup', 'addonItem'])
            ->get()
            ->map(function ($addon) {
                $group = $addon->addonGroup?->title;
                $item  = $addon->addonItem?->title;
                $label = $group ? trim($group . ' — ' . $item) : $item;

                return $label ? ['label' => $label, 'price' => (float) $addon->price] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Customer-uploaded attachment URLs for an order item (prescriptions and
     * similar), for the fulfilment UI.
     *
     * @return array<int, string>
     */
    private function orderItemAttachments($orderItem): array
    {
        try {
            return $orderItem
                ->getMedia(SpatieMediaCollectionName::ORDER_ITEM_ATTACHMENTS())
                ->map(fn ($media) => $media->getUrl())
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Build the "arrives between" date window text for an order item from its
     * snapshot ETA, counted from the order-placed date. Null when no ETA was
     * captured at checkout.
     */
    private function deliveryEstimateText($orderItem): ?string
    {
        $min = $orderItem->eta_min;
        $max = $orderItem->eta_max;

        if ($min === null && $max === null) {
            return null;
        }

        $anchor = $orderItem->created_at ? $orderItem->created_at->copy() : now();
        $unit   = $orderItem->eta_unit === 'hours' ? 'hours' : 'days';
        $min    = $min ?? $max;
        $max    = $max ?? $min;

        $add = fn (int $n) => $unit === 'hours'
            ? $anchor->copy()->addHours($n)
            : $anchor->copy()->addDays($n);

        $format = $unit === 'hours' ? 'M d, H:i' : 'M d';

        return $add((int) $min)->format($format) . ' – ' . $add((int) $max)->format($format);
    }

    /**
     * Remaining-to-ship quantity per active line: the ordered quantity minus the
     * units already sitting in a non-cancelled shipment. A cancelled shipment
     * frees its units, so a re-ship after a cancellation works.
     *
     * @param  \Illuminate\Support\Collection  $activeSellerOrderItems  SellerOrderItem rows with orderItem loaded
     * @return array<int,int>  order_item_id => remaining qty
     */
    private function remainingToShipMap($activeSellerOrderItems): array
    {
        $orderItemIds = $activeSellerOrderItems
            ->pluck('order_item_id')->map(fn ($i) => (int) $i)->all();
        if (empty($orderItemIds)) {
            return [];
        }

        $cancelled = (string) ShipmentStatusEnum::CANCELLED();
        $shippedByItem = ShipmentItem::query()
            ->whereIn('order_item_id', $orderItemIds)
            ->whereHas('shipment', fn ($q) => $q->where('status', '!=', $cancelled)->where('is_return', false))
            ->selectRaw('order_item_id, SUM(quantity) as qty')
            ->groupBy('order_item_id')
            ->pluck('qty', 'order_item_id');

        $map = [];
        foreach ($activeSellerOrderItems as $soi) {
            $itemId  = (int) $soi->order_item_id;
            $ordered = (int) ($soi->orderItem->quantity ?? $soi->quantity);
            $shipped = (int) ($shippedByItem[$itemId] ?? 0);
            $map[$itemId] = max(0, $ordered - $shipped);
        }

        return $map;
    }

    /**
     * Advance a shipment to a new status, enforcing the per-method state machine,
     * stamping timestamps, and rolling the fulfillment status up.
     *
     * @throws \RuntimeException on illegal / terminal transition
     */
    public function transition(Shipment $shipment, string $to, array $data = []): Shipment
    {
        $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->first() ?? $shipment;
        $from = (string) $shipment->status;
        $to   = (string) $to;
        $shippedStatuses = [
            (string) ShipmentStatusEnum::IN_TRANSIT(),
            (string) ShipmentStatusEnum::OUT_FOR_DELIVERY(),
            (string) ShipmentStatusEnum::DELIVERED(),
        ];
        $wasShipped = in_array($from, $shippedStatuses, true)
            || $shipment->statusEvents()->whereIn('status', $shippedStatuses)->exists();

        $delivered = (string) ShipmentStatusEnum::DELIVERED();
        $cancelled = (string) ShipmentStatusEnum::CANCELLED();

        $isRevertDelivery = $from === $delivered && $to === $cancelled;

        $terminal = array_map(fn ($s) => (string) $s, ShipmentStatusEnum::terminal());
        if (in_array($from, $terminal, true) && !$isRevertDelivery) {
            throw new \RuntimeException(__('labels.shipment_already_terminal'));
        }

        if ($isRevertDelivery) {
            $this->assertNoActiveReturnOrRefund($shipment);
        }

        $allowed = $this->allowedTransitions($this->methodValue($shipment->sellerOrder));
        if (!isset($allowed[$from]) || !in_array($to, $allowed[$from], true)) {
            throw new \RuntimeException(__('labels.invalid_shipment_transition'));
        }

        if ($to === $cancelled && !$isRevertDelivery) {
            $provider = $this->providers->resolve($this->methodValue($shipment->sellerOrder));
            $provider?->cancelRemote($shipment);
        }

        $shipment = DB::transaction(function () use ($shipment, $to, $data, $delivered, $cancelled) {
            $shipment->status = $to;
            $shipment->last_event_at = now();

            if ($to === $delivered) {
                $shipment->delivered_at = now();
            }
            if ($to === $cancelled) {
                $shipment->delivered_at = null;
            }
            if (!empty($data['tracking_number'])) {
                $shipment->tracking_number = $data['tracking_number'];
            }
            if (!empty($data['carrier_name'])) {
                $shipment->manual_carrier_name = $data['carrier_name'];
            }

            $shipment->save();

            $shipment->recordStatusEvent($to);

            if ($shipment->is_return) {
                $this->syncReturnFromShipment($shipment, $to);
            } else {
                $this->rollup->syncFromShipment($shipment);
            }

            return $shipment;
        });

        if ($to === $delivered && !$shipment->is_return) {
            DB::afterCommit(fn () => event(new \App\Events\Order\ShipmentDelivered($shipment->id)));
        }
        if (! $wasShipped && in_array($to, $shippedStatuses, true) && ! $shipment->is_return) {
            DB::afterCommit(fn () => event(new \App\Events\Order\ShipmentShipped($shipment->id)));
        }

        return $shipment;
    }

    public function releaseItemFromPendingParcels(int $orderItemId): void
    {
        $pending = (string) ShipmentStatusEnum::PENDING();
        $shipments = Shipment::query()
            ->where('status', $pending)
            ->where('is_return', false)
            ->whereNull('picked_up_at')
            ->whereNull('delivered_at')
            ->whereDoesntHave('statusEvents', fn ($q) => $q->whereIn('status', ShipmentStatusEnum::dispatched()))
            ->whereHas('items', fn ($q) => $q->where('order_item_id', $orderItemId))
            ->get();

        foreach ($shipments as $shipment) {
            try {
                $otherLines = $shipment->items()
                    ->where('order_item_id', '!=', $orderItemId)
                    ->get();

                if ($otherLines->isEmpty()) {
                    $this->transition($shipment, (string) ShipmentStatusEnum::CANCELLED());
                    continue;
                }

                $this->updatePendingShipmentLines($shipment, [
                    'items' => $otherLines->map(fn ($si) => [
                        'order_item_id' => (int) $si->order_item_id,
                        'quantity'      => (int) $si->quantity,
                    ])->values()->all(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Could not release item from pending parcel', [
                    'order_item_id' => $orderItemId,
                    'shipment_id'   => $shipment->id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }
    }

    public function syncShipmentFromRemote(Shipment $shipment): Shipment
    {
        $provider = $this->providers->resolve($this->methodValue($shipment->sellerOrder));
        if (!$provider instanceof ShiprocketProvider) {
            throw new RuntimeException(__('labels.shiprocket_sync_not_available'));
        }

        if (!$shipment->is_return) {
            $this->refreshCarrierAssignment($shipment);
            $shipment->refresh();
        }

        $status = $provider->remoteTrackedStatus($shipment);
        if ($status) {
            $this->applyCarrierStatus($shipment, $status, __('labels.shiprocket_sync_note'));
        }

        return $shipment;
    }

    public function refreshCarrierAssignment(Shipment $shipment): bool
    {
        if ($shipment->is_return) {
            return false;
        }

        $provider = $this->providers->resolve($this->methodValue($shipment->sellerOrder));
        if (!$provider instanceof ShiprocketProvider) {
            return false;
        }

        $changed = $provider->syncRemoteAssignment($shipment);
        if ($changed) {
            $this->rollup->syncFromShipment($shipment);
        }

        return $changed;
    }

    public function requestCarrierReturnPickup(OrderItemReturn $return): ?Shipment
    {
        if ($return->no_shipping_required || !empty($return->return_tracking_number)) {
            return null;
        }

        if ($return->return_shipment_id) {
            return $this->retryReturnPickupBooking($return);
        }

        $sellerOrder = $return->orderItem?->sellerOrderItem?->sellerOrder;
        $provider = $this->providers->resolve($this->methodValue($sellerOrder));
        if (!$provider) {
            return null;
        }

        try {
            return $provider->requestReturnPickup($return);
        } catch (\Throwable $e) {
            Log::warning('Carrier return pickup failed', [
                'return_id' => $return->id,
                'error'     => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function retryReturnPickupBooking(OrderItemReturn $return): ?Shipment
    {
        $shipment = Shipment::find($return->return_shipment_id);
        if (!$shipment
            || (string) $shipment->status !== (string) ShipmentStatusEnum::PENDING()
            || !empty($shipment->awb_number)) {
            return $shipment;
        }

        try {
            $this->bookRemote($shipment);
        } catch (\Throwable $e) {
            Log::warning('Carrier return pickup retry failed', [
                'return_id'   => $return->id,
                'shipment_id' => $shipment->id,
                'error'       => $e->getMessage(),
            ]);
        }

        if ($shipment->awb_number) {
            $return->forceFill([
                'return_carrier'         => $shipment->courier_name,
                'return_tracking_number' => $shipment->awb_number,
                'return_tracking_url'    => $shipment->tracking_url,
            ])->save();
        }

        return $shipment;
    }

    /**
     * Apply a carrier-reported status (webhook / poll). The carrier is
     * authoritative, so this does NOT enforce the per-method transition graph —
     * it may jump steps. Idempotent (same status = no-op) and terminal-guarded
     * (won't override delivered/cancelled/returned/lost). Returns true if it
     * changed anything.
     */
    public function applyCarrierStatus(Shipment $shipment, string $status, ?string $note = null): bool
    {
        $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->first() ?? $shipment;
        $status  = (string) $status;
        $current = (string) $shipment->status;
        $shippedStatuses = [
            (string) ShipmentStatusEnum::IN_TRANSIT(),
            (string) ShipmentStatusEnum::OUT_FOR_DELIVERY(),
            (string) ShipmentStatusEnum::DELIVERED(),
        ];
        $wasShipped = in_array($current, $shippedStatuses, true)
            || $shipment->statusEvents()->whereIn('status', $shippedStatuses)->exists();

        if ($current === $status) {
            return false;
        }

        $terminal = array_map(fn ($s) => (string) $s, ShipmentStatusEnum::terminal());
        if (in_array($current, $terminal, true)) {
            return false;
        }

        $progressRank = [
            (string) ShipmentStatusEnum::PENDING()          => 0,
            (string) ShipmentStatusEnum::LABEL_PRINTED()    => 1,
            (string) ShipmentStatusEnum::CONFIRMED()        => 2,
            (string) ShipmentStatusEnum::IN_TRANSIT()       => 3,
            (string) ShipmentStatusEnum::OUT_FOR_DELIVERY() => 4,
            (string) ShipmentStatusEnum::DELIVERED()        => 5,
        ];
        if (isset($progressRank[$status], $progressRank[$current]) && $progressRank[$status] < $progressRank[$current]) {
            return false;
        }

        $delivered = (string) ShipmentStatusEnum::DELIVERED();
        $cancelled = (string) ShipmentStatusEnum::CANCELLED();

        if ($current === $delivered && $status === $cancelled) {
            $this->assertNoActiveReturnOrRefund($shipment);
        }

        $updated = DB::transaction(function () use ($shipment, $status, $note, $delivered, $cancelled) {
            $shipment->status = $status;
            $shipment->last_event_at = now();
            if ($status === $delivered) {
                $shipment->delivered_at = now();
            }
            if ($status === $cancelled) {
                $shipment->delivered_at = null;
            }
            $shipment->save();

            $shipment->recordStatusEvent($status, $note);

            if ($shipment->is_return) {
                $this->syncReturnFromShipment($shipment, $status);
            } else {
                $this->rollup->syncFromShipment($shipment);
            }

            return true;
        });

        if ($updated && $status === $delivered && !$shipment->is_return) {
            DB::afterCommit(fn () => event(new \App\Events\Order\ShipmentDelivered($shipment->id)));
        }
        if ($updated && ! $wasShipped && in_array($status, $shippedStatuses, true) && ! $shipment->is_return) {
            DB::afterCommit(fn () => event(new \App\Events\Order\ShipmentShipped($shipment->id)));
        }

        return $updated;
    }

    private function syncReturnFromShipment(Shipment $shipment, string $status): void
    {
        $return = OrderItemReturn::where('return_shipment_id', $shipment->id)->first();
        if (!$return) {
            return;
        }

        $current = (string) $return->return_status;
        $moving = [
            (string) ShipmentStatusEnum::IN_TRANSIT(),
            (string) ShipmentStatusEnum::OUT_FOR_DELIVERY(),
        ];

        if (in_array($status, $moving, true)
            && $current === (string) OrderItemReturnStatusEnum::APPROVED()) {
            $return->forceFill([
                'return_status' => (string) OrderItemReturnStatusEnum::IN_TRANSIT(),
                'picked_up_at'  => $return->picked_up_at ?? now(),
            ])->save();
            return;
        }

        $received = [
            (string) ShipmentStatusEnum::DELIVERED(),
            (string) ShipmentStatusEnum::RETURNED(),
        ];
        $advanceable = [
            (string) OrderItemReturnStatusEnum::APPROVED(),
            (string) OrderItemReturnStatusEnum::IN_TRANSIT(),
        ];

        if (in_array($status, $received, true) && in_array($current, $advanceable, true)) {
            $return->forceFill([
                'return_status' => (string) OrderItemReturnStatusEnum::RECEIVED(),
                'received_at'   => $return->received_at ?? now(),
            ])->save();
        }
    }

    /**
     * Guard a delivered→cancelled revert: refuse it when any line in the shipment
     * is already returned/refunded or has an in-flight return (any return whose
     * status is not declined/cancelled). Reverting the delivery in those cases
     * would leave the return/refund records pointing at a cancelled shipment.
     *
     * @throws \RuntimeException when a return/refund is attached to a line
     */
    private function assertNoActiveReturnOrRefund(Shipment $shipment): void
    {
        $orderItemIds = $shipment->items()->pluck('order_item_id')->filter()->all();
        if (empty($orderItemIds)) {
            return;
        }

        $blockedItemStatuses = [
            (string) OrderItemStatusEnum::RETURNED(),
            (string) OrderItemStatusEnum::REFUNDED(),
        ];
        $hasReturnedOrRefundedLine = OrderItem::whereIn('id', $orderItemIds)
            ->whereIn('status', $blockedItemStatuses)
            ->whereDoesntHave('returns', fn ($query) => $query->whereNotNull('source_shipment_id'))
            ->exists();

        $hasActiveReturn = OrderItemReturn::whereIn('order_item_id', $orderItemIds)
            ->whereNotIn('return_status', [
                (string) OrderItemReturnStatusEnum::CANCELLED(),
                (string) OrderItemReturnStatusEnum::DECLINED(),
            ])
            ->where(fn ($query) => $query->where('source_shipment_id', $shipment->id)
                ->orWhereNull('source_shipment_id'))
            ->exists();

        if ($hasReturnedOrRefundedLine || $hasActiveReturn) {
            throw new \RuntimeException(__('labels.cannot_revert_delivery_with_return'));
        }
    }

    /**
     * Edit a created fulfillment's tracking details (carrier, tracking number,
     * tracking URL) without changing its status. Allowed while the shipment is
     * not terminal (Shopify's "Edit tracking" on a fulfillment).
     *
     * $data: carrier_name, tracking_number, tracking_url (all optional).
     *
     * @throws \RuntimeException if the shipment is already terminal
     */
    public function editShipment(Shipment $shipment, array $data = []): Shipment
    {
        $terminal = array_map(fn ($s) => (string) $s, ShipmentStatusEnum::terminal());
        if (in_array((string) $shipment->status, $terminal, true)) {
            throw new \RuntimeException(__('labels.shipment_already_terminal'));
        }

        if (array_key_exists('carrier_name', $data)) {
            $shipment->manual_carrier_name = $data['carrier_name'] ?: null;
        }
        if (array_key_exists('tracking_number', $data)) {
            $shipment->tracking_number = $data['tracking_number'] ?: null;
        }
        if (array_key_exists('tracking_url', $data)) {
            $shipment->tracking_url = $data['tracking_url'] ?: null;
        }

        $labelPrinted = (string) ShipmentStatusEnum::LABEL_PRINTED();
        $confirmed    = (string) ShipmentStatusEnum::CONFIRMED();
        $current      = (string) $shipment->status;
        $hasTracking  = !empty($shipment->tracking_number);

        if ($current === $labelPrinted && $hasTracking) {
            $shipment->status = $confirmed;
        } elseif ($current === $confirmed && !$hasTracking) {
            $shipment->status = $labelPrinted;
        }

        $shipment->last_event_at = now();
        $shipment->save();

        if ((string) $shipment->status !== $current) {
            $shipment->recordStatusEvent();
        }

        return $shipment;
    }

    /**
     * Create the seller settlement credit for every order item in a delivered
     * shipment. Idempotent via a per-order-item dedupe key so it never double-pays.
     *
     * PUBLIC API, intentionally not auto-wired: the trigger was removed from the
     * delivery transition while the settlement point is being repositioned. Call
     * this from the new settlement trigger once decided
     * (e.g. app(ShipmentService::class)->creditSellerForShipment($shipment)).
     * Until then, delivery has no financial effect.
     */
    public function creditSellerForShipment(Shipment $shipment): void
    {
        $shipmentItems = $shipment->items()->with(['orderItem.store', 'orderItem.order'])->get();

        foreach ($shipmentItems as $shipmentItem) {
            $orderItem = $shipmentItem->orderItem;
            if (!$orderItem || !$orderItem->store) {
                continue;
            }

            $orderedQty = (int) $orderItem->quantity;
            $shippedQty = (int) $shipmentItem->quantity;
            if ($orderedQty <= 0 || $shippedQty <= 0) {
                continue;
            }

            try {
                $exists = SellerStatement::where('order_item_id', $orderItem->id)
                    ->where('entry_type', SellerSettlementTypeEnum::CREDIT())
                    ->where('reference_type', 'order_item_delivery')
                    ->where('reference_id', $shipment->id)
                    ->exists();
                if ($exists) {
                    continue;
                }

                $lineEarning = (float) $orderItem->subtotal - (float) $orderItem->admin_commission_amount;
                $amount = round(($lineEarning / $orderedQty) * $shippedQty, 2);

                $this->sellerStatementService->addEntry(data: [
                    'seller_id'      => $orderItem->store->seller_id,
                    'entry_type'     => SellerSettlementTypeEnum::CREDIT(),
                    'amount'         => $amount,
                    'currency_code'  => $orderItem->order->currency_code ?? null,
                    'order_id'       => $orderItem->order_id,
                    'order_item_id'  => $orderItem->id,
                    'reference_type' => 'order_item_delivery',
                    'reference_id'   => $shipment->id,
                    'description'    => "Seller earning for {$shippedQty} unit(s) of Order Item #{$orderItem->id} (shipment #{$shipment->id})",
                    'meta'           => [
                        'product_id'  => $orderItem->product_id,
                        'quantity'    => $shippedQty,
                        'shipment_id' => $shipment->id,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to create seller settlement credit on shipment success', [
                    'shipment_id'   => $shipment->id,
                    'order_item_id' => $orderItem->id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Per-method allowed transitions, delegated to the provider's state machine.
     * Kept as a method here so existing callers (transition(), the fulfilment UI)
     * need no change. Unsupported methods return [] (no transitions).
     */
    public function allowedTransitions(string $method): array
    {
        $provider = $this->providers->resolve($method);

        return $provider ? $provider->allowedTransitions() : [];
    }

    /**
     * The date a manual seller order must ship by (order date + the admin's
     * dispatch SLA). Null when the method isn't manual, the SLA is off, or the
     * order has no creation date.
     */
    public function manualShipByDate(SellerOrder $sellerOrder): ?\Carbon\CarbonInterface
    {
        if ($this->methodValue($sellerOrder) !== (string) FulfillmentMethodEnum::MANUAL()) {
            return null;
        }

        $slaDays = $this->manualDispatchSlaDays ??= (int) (app(SettingService::class)->shiprocketConfig()['manualDispatchSlaDays'] ?? 0);
        if ($slaDays <= 0 || !$sellerOrder->created_at) {
            return null;
        }

        return $sellerOrder->created_at->copy()->addDays($slaDays)->endOfDay();
    }

    /** Normalise a seller order's fulfillment_method to its string value. */
    private function methodValue(?SellerOrder $sellerOrder): string
    {
        if (!$sellerOrder) {
            return (string) FulfillmentMethodEnum::MANUAL();
        }
        $m = $sellerOrder->fulfillment_method;
        return $m instanceof \BackedEnum ? $m->value : (string) $m;
    }
}
