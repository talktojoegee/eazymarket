<?php

namespace App\Http\Resources\User;

use App\Enums\Order\RefundStatusEnum;
use App\Enums\Shipping\ShipmentStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Services\OrderCustomerStatusPresenter;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isReviewExist = OrderService::checkUserReviewExistByOrderItemId($this->id);
        if ($isReviewExist) {
            $userReview = OrderService::getUserReviewByOrderItemId($this->id);
        }
        $sellerId = $this->store?->seller?->id;
        $sellerName = $this->store?->seller?->user?->name;

        $presenter = app(OrderCustomerStatusPresenter::class);
        $trackingData = $presenter->trackingForItem($this->resource);
        $canCancel = $trackingData['cancellation']['allowed'];
        $cancelableQty = $trackingData['cancellation']['quantity'];
        $tracking = [
            'status_code' => $trackingData['status_code'],
            'milestones' => $trackingData['milestones'],
            'history' => $trackingData['history'],
            'exceptions' => $trackingData['exceptions'],
        ];

        $returnableShipments = app(OrderService::class)->returnableShipments($this->resource);
        $canReturn = $returnableShipments !== [];

        $attachments = [];
        try {
            $mediaItems = $this->getMedia(SpatieMediaCollectionName::ORDER_ITEM_ATTACHMENTS());
            foreach ($mediaItems as $media) {
                $attachments[] = $media->getUrl();
            }
        } catch (\Throwable $e) {
        }
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'store_id' => $this->store_id,
            'seller_id' => $sellerId,
            'seller_name' => $sellerName,
            'title' => $this->title,
            'variant_title' => $this->variant_title,
            'gift_card_discount' => $this->gift_card_discount,
            'admin_commission_amount' => $this->admin_commission_amount,
            'seller_commission_amount' => $this->seller_commission_amount,
            'commission_settled' => $this->commission_settled,
            'discounted_price' => $this->discounted_price,
            'promo_discount' => $this->promo_discount,
            'discount' => $this->discount,
            'tax_amount' => $this->tax_amount,
            'tax_percent' => $this->tax_percent,
            'sku' => $this->sku,
            'return_eligible' => $this->return_eligible,
            'can_return' => $canReturn,
            'returnable_shipments' => $returnableShipments,
            'return_deadline' => $this->return_deadline,
            'quantity' => $this->quantity,
            'quantity_summary' => $presenter->quantitiesForItem($this->resource),
            'price' => $this->price,
            'subtotal' => $this->subtotal,
            'eta_min' => $this->eta_min,
            'eta_max' => $this->eta_max,
            'eta_unit' => $this->eta_unit,
            'delivery_estimate' => $this->deliveryEstimate(),
            'status' => $this->status,
            'customer_status' => $presenter->itemStatus($this->resource),
            'tracking' => $tracking,
            'shipments' => $this->shipments($presenter),
            'can_cancel' => $canCancel,
            'cancelable_quantity' => $cancelableQty,
            'cancellation_reason' => $this->cancellation_reason,
            'delivery_fail_reason' => $this->delivery_fail_reason,
            'otp' => $this->otp ?? null,
            'otp_verified' => $this->otp_verified ?? false,
            'is_user_review_given' => $isReviewExist,
            'user_review' => $userReview ?? null,
            'product' => $this->when($this->product, [
                'id' => $this->product->id ?? "N/A",
                'name' => $this->product->title ?? "N/A",
                'slug' => $this->product->slug ?? "N/A",
                'is_returnable' => (bool)$this->product?->is_returnable ?? false,
                'returnable_days' => (int)$this->product?->returnable_days ?? 0,
                'is_cancelable' => (bool) $this->is_cancelable,
                'cancelable_till' => $this->cancelable_till,
                'image' => $this->product->main_image ?? null,
                'requires_otp' => $this->product->requires_otp ?? false,
                'category' => $this->product?->category?->title ?? "",
                'brand' => $this->product?->brand?->title ?? "",
            ]),
            'variant' => $this->when($this->variant, [
                'id' => $this->variant->id ?? "N/A",
                'title' => $this->variant->title ?? "N/A",
                'slug' => $this->variant->slug ?? "N/A",
                'image' => $this->variant->image ?? null,
            ]),
            'store' => $this->when($this->store, [
                'id' => $this->store->id ?? "N/A",
                'name' => $this->store->name ?? "N/A",
                'slug' => $this->store->slug ?? "N/A",
            ]),
            'returns' => OrderItemReturnResource::collection($this->customerReturns()),
            'attachments' => $attachments,
            'addons'        => $this->formatAddons(),
            'addons_total'  => $this->resolveAddonsTotal(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s')
        ];
    }

    protected function customerReturns()
    {
        if (! $this->resource->relationLoaded('returns') || $this->hasIssuedRefund()) {
            return collect();
        }

        return $this->returns
            ->sortByDesc('id')
            ->take(1)
            ->values();
    }

    protected function hasIssuedRefund(): bool
    {
        if (! $this->resource->relationLoaded('order') || ! $this->order?->relationLoaded('refunds')) {
            return false;
        }

        $refunds = $this->order->refunds;

        return $refunds->contains(function ($refund) use ($refunds) {
            if (! $refund->relationLoaded('items') || ! $refund->items->contains('id', $this->id)) {
                return false;
            }

            $resolved = $refund->settled_by_refund_id
                ? $refunds->firstWhere('id', $refund->settled_by_refund_id)
                : $refund;
            $status = $resolved?->status;
            $value = $status instanceof RefundStatusEnum ? $status->value : (string) $status;

            return $value === RefundStatusEnum::ISSUED();
        });
    }

    /**
     * Flatten `order_item_addons` rows into a scalar-friendly list for the
     * API. Uses the eager-loaded `addons` relation when available and falls
     * back to a lazy load (rare — `OrderService::finalizeOrderCreation()`
     * eager-loads `items.addons`).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function formatAddons(): array
    {
        $addons = $this->resource->relationLoaded('addons')
            ? $this->resource->getRelation('addons')
            : $this->resource->addons()->with(['addonGroup', 'addonItem'])->get();

        if (!$addons || $addons->isEmpty()) {
            return [];
        }

        return $addons->map(function ($addon) {
            $group = $addon->addonGroup;
            $item  = $addon->addonItem;

            return [
                'id'              => $addon->id,
                'uuid'            => $addon->uuid,
                'addon_group_id'  => $addon->addon_group_id,
                'addon_item_id'   => $addon->addon_item_id,
                'price'           => (float) $addon->price,
                'group' => $group ? [
                    'id'             => $group->id,
                    'title'          => $group->title,
                    'selection_type' => $group->selection_type?->value,
                    'is_required'    => (bool) $group->is_required,
                ] : null,
                'item' => $item ? [
                    'id'        => $item->id,
                    'title'     => $item->title,
                    'indicator' => $item->indicator?->value,
                ] : null,
            ];
        })->values()->all();
    }

    /**
     * Line-total contribution of the selected addons for this order item:
     * `quantity × sum(addon.price)`. 0 when no addons are attached.
     */
    protected function resolveAddonsTotal(): float
    {
        $addons = $this->resource->relationLoaded('addons')
            ? $this->resource->getRelation('addons')
            : $this->resource->addons()->get();

        if (!$addons || $addons->isEmpty()) {
            return 0.0;
        }

        return round(((int) $this->quantity) * (float) $addons->sum(fn ($a) => (float) $a->price), 2);
    }

    protected function shipments(OrderCustomerStatusPresenter $presenter): array
    {
        if (! $this->resource->relationLoaded('shipmentItems')) {
            return [];
        }

        return $this->shipmentItems
            ->filter(fn ($line) => $line->shipment
                && ! $line->shipment->is_return
                && (string) $line->shipment->status !== (string) ShipmentStatusEnum::CANCELLED())
            ->map(function ($line) use ($presenter) {
                $shipment = $line->shipment;
                $status = (string) $shipment->status;
                $customerStatus = $presenter->parcelStatus($status);

                return [
                    'id' => (int) $shipment->id,
                    'status' => $status,
                    'customer_status' => $customerStatus['code'],
                    'customer_status_label' => $customerStatus['label'],
                    'carrier_name' => $shipment->manual_carrier_name ?: $shipment->courier_name,
                    'tracking_number' => $shipment->tracking_number,
                    'tracking_url' => $shipment->tracking_url,
                    'quantity' => (int) $line->quantity,
                    'picked_up_at' => $shipment->picked_up_at?->format('Y-m-d H:i:s'),
                    'delivered_at' => $shipment->delivered_at?->format('Y-m-d H:i:s'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Turn the snapshot ETA into an upcoming delivery date window, counted from
     * the order-placed date (this item's created_at). Returns null when no ETA
     * was captured (e.g. no zone rate carried one at checkout).
     *
     * @return array{from: string, to: string, unit: string}|null
     */
    protected function deliveryEstimate(): ?array
    {
        $min = $this->eta_min;
        $max = $this->eta_max;

        if ($min === null && $max === null) {
            return null;
        }

        $anchor = $this->created_at ? $this->created_at->copy() : now();
        $unit   = $this->eta_unit === 'hours' ? 'hours' : 'days';

        $min = $min ?? $max;
        $max = $max ?? $min;

        $add = fn (int $n) => $unit === 'hours'
            ? $anchor->copy()->addHours($n)
            : $anchor->copy()->addDays($n);

        $format = $unit === 'hours' ? 'Y-m-d H:i:s' : 'Y-m-d';

        return [
            'from' => $add((int) $min)->format($format),
            'to'   => $add((int) $max)->format($format),
            'unit' => $unit,
        ];
    }
}
