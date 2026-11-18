<?php

namespace App\Http\Resources;

use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Http\Resources\User\PromoLineResource;
use App\Support\PricePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isSellerOrder = get_class($this->resource) === 'App\Models\SellerOrder';

        if ($isSellerOrder) {
            return [
                'id' => $this->id,
                'user_id' => $this->user_id,
                'order_id' => $this->order->id ?? $this->id,
                'order_number' => $this->order->order_number ?? null,
                'uuid' => $this->order->uuid,
                'email' => $this->order->email,
                'status' => $this->order->status,
                'status_label' => formatOrderStatusLabel($this->order->status),
                'fulfillment_status' => $this->fulfillment_status ?? 'unfulfilled',
                'fulfillment_status_label' => formatOrderStatusLabel($this->fulfillment_status ?? 'unfulfilled'),
                'payment_method' => $this->order->payment_method,
                'payment_status' => $this->order->payment_status,
                'currency' => PricePresenter::payload($this->order->market ?? null),
                'total_price' => $this->total_price,

                'billing_name' => $this->order->billing_name,
                'billing_phone' => $this->order->billing_phone,

                'shipping_name' => $this->order->shipping_name,
                'shipping_address_1' => $this->order->shipping_address_1,
                'shipping_address_2' => $this->order->shipping_address_2,
                'shipping_landmark' => $this->order->shipping_landmark,
                'shipping_city' => $this->order->shipping_city,
                'shipping_state' => $this->order->shipping_state,
                'shipping_zip' => $this->order->shipping_zip,
                'shipping_country' => $this->order->shipping_country,
                'shipping_phone' => $this->order->shipping_phone,
                'order_note' => $this->order->order_note,
                'estimated_delivery_time' => $this->order->estimated_delivery_time ?? null,
                'promo_line' => new PromoLineResource($this->whenLoaded('promoLine')),
                'items' => $this->whenLoaded('items', function () {
                    return $this->items->map(function ($item) {
                        $attachments = [];
                        try {
                            $mediaItems = $item->orderItem->getMedia(SpatieMediaCollectionName::ORDER_ITEM_ATTACHMENTS());
                            foreach ($mediaItems as $media) {
                                $attachments[] = $media->getUrl();
                            }
                        } catch (\Throwable $e) {
                        }
                        return [
                            'id' => $item->id,
                            'attachments' => $attachments,
                            'orderItem' => [
                                'id' => $item->orderItem->id,
                                'status' => $item->orderItem->status,
                                'status_formatted' => Str::ucfirst(Str::replace("_", " ", $item->orderItem->status)),
                                'status_label' => formatOrderStatusLabel($item->orderItem->status),
                                'cancellation_reason' => $item->orderItem->cancellation_reason,
                                'delivery_fail_reason' => $item->orderItem->delivery_fail_reason,
                            ],
                            'title' => $item->orderItem?->title ?? $item->product?->title,
                            'variant_title' => $item->orderItem?->variant_title ?? $item->variant?->title,
                            'sku' => $item->orderItem?->sku,
                            'product' => $item->product ? [
                                'id' => $item->product->id,
                                'title' => $item->orderItem?->title ?? $item->product->title,
                                'image' => $item->product->main_image,
                            ] : null,
                            'variant' => $item->variant ? [
                                'id' => $item->variant->id,
                                'title' => $item->orderItem?->variant_title ?? $item->variant->title,
                                'image' => $item->variant->image,
                            ] : null,
                            'store' => $item->store ? [
                                'id' => $item->store->id,
                                'name' => $item->store->name,
                            ] : null,
                            'price' => $item->price,
                            'tax_amount' => $item->orderItem?->tax_amount,
                            'sub_total' => $item->orderItem?->subtotal,
                            'quantity' => $item->quantity,
                            'subtotal' => $item->price * $item->quantity,
                            'addons' => $this->formatOrderItemAddons($item->orderItem),
                            'addons_total' => $this->sumOrderItemAddonsLineTotal($item->orderItem),
                        ];
                    });
                }),

                'shipments' => $this->whenLoaded('shipments', function () {
                    return $this->shipments->map(fn ($s) => [
                        'id' => $s->id,
                        'status' => $s->status,
                        'status_label' => formatOrderStatusLabel((string) $s->status),
                        'provider_code' => $s->provider_code,
                        'carrier_name' => $s->manual_carrier_name ?? $s->carrier_name ?? null,
                        'awb_number' => $s->awb_number,
                        'tracking_number' => $s->tracking_number,
                        'tracking_url' => $s->tracking_url,
                        'label_url' => $s->label_url,
                        'manifest_url' => $s->manifest_url,
                        'created_at' => $s->created_at?->format('M d, Y h:i A'),
                    ])->values();
                }),
                'created_at' => $this->created_at?->format('M d, Y h:i A'),
            ];
        }

        $terminalStatuses = [
            OrderItemStatusEnum::CANCELLED(),
        ];

        $items = $this->relationLoaded('items') ? $this->items : collect();
        $cancelledRejectedAmount = (float) $items
            ->whereIn('status', $terminalStatuses)
            ->sum('subtotal');
        $activeItemsCount = $items
            ->whereNotIn('status', $terminalStatuses)
            ->count();
        $allItemsTerminal = $items->isNotEmpty() && $activeItemsCount === 0;

        $displayFinalTotal = (float) $this->final_total + $cancelledRejectedAmount;
        $displaySubtotal = (float) $this->subtotal + $cancelledRejectedAmount;
        $displayTotalPayable = $allItemsTerminal ? 0 : (float) $this->total_payable;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'user_id' => $this->user_id,
            'uuid' => $this->uuid,
            'email' => $this->email,
            'status' => $this->status,
            'status_label' => formatOrderStatusLabel($this->status),
            'fulfillment_status' => $this->fulfillment_status ?? 'unfulfilled',
            'fulfillment_status_label' => formatOrderStatusLabel($this->fulfillment_status ?? 'unfulfilled'),
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'currency' => PricePresenter::payload($this->market ?? null),
            'promo_code' => $this->promo_code,
            'promo_discount' => $this->promo_discount,
            'wallet_balance' => $this->wallet_balance,
            'subtotal' => $displaySubtotal,
            'delivery_charge' => $this->delivery_charge,
            'platform_fee' => $this->platform_fee,
            'cod_fee' => $this->cod_fee,
            'total_payable' => $displayTotalPayable,
            'final_total' => $displayFinalTotal,

            'billing_name' => $this->billing_name,
            'billing_phone' => $this->billing_phone,

            'shipping_name' => $this->shipping_name,
            'shipping_address_1' => $this->shipping_address_1,
            'shipping_address_2' => $this->shipping_address_2,
            'shipping_landmark' => $this->shipping_landmark,
            'shipping_city' => $this->shipping_city,
            'shipping_state' => $this->shipping_state,
            'shipping_zip' => $this->shipping_zip,
            'shipping_country' => $this->shipping_country,
            'shipping_phone' => $this->shipping_phone,
            'order_note' => $this['order_note'],
            'estimated_delivery_time' => $this->estimated_delivery_time ?? null,
            'is_flagged' => (bool) ($this->is_flagged ?? false),
            'escalation_reasons' => $this->escalation_reasons ?? null,
            'escalated_at' => $this->escalated_at ?? null,
            'promo_line' => new PromoLineResource($this->whenLoaded('promoLine')),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    $attachments = [];
                    try {
                        $mediaItems = $item->getMedia(SpatieMediaCollectionName::ORDER_ITEM_ATTACHMENTS());
                        foreach ($mediaItems as $media) {
                            $attachments[] = $media->getUrl();
                        }
                    } catch (\Throwable $e) {
                    }
                    return [
                        'id' => $item->id,
                        'attachments' => $attachments,
                        'orderItem' => [
                            'id' => $item->id,
                            'status' => $item->status,
                            'status_formatted' => Str::ucfirst(Str::replace("_", " ", $item->status)),
                            'status_label' => formatOrderStatusLabel($item->status),
                            'cancellation_reason' => $item->cancellation_reason,
                            'delivery_fail_reason' => $item->delivery_fail_reason,
                        ],
                        'title' => $item->title ?? $item->product?->title,
                        'variant_title' => $item->variant_title ?? $item->variant?->title,
                        'sku' => $item->sku,
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'title' => $item->title ?? $item->product->title,
                            'image' => $item->product->main_image,
                        ] : null,
                        'variant' => $item->variant ? [
                            'id' => $item->variant->id,
                            'title' => $item->variant_title ?? $item->variant->title,
                            'image' => $item->variant->image,
                        ] : null,
                        'store' => $item->store ? [
                            'id' => $item->store->id,
                            'name' => $item->store->name,
                            'city' => $item->store->city ?? null,
                            'state' => $item->store->state ?? null,
                            'address' => $item->store->address ?? null,
                            'contact_email' => $item->store->contact_email ?? null,
                            'contact_number' => $item->store->contact_number ?? null,
                            'seller' => $item->store->seller ? [
                                'id' => $item->store->seller->id,
                                'name' => $item->store->seller->user?->name,
                                'email' => $item->store->seller->user?->email,
                                'mobile' => $item->store->seller->user?->mobile,
                            ] : null,
                        ] : null,
                        'price' => $item->price,
                        'tax_amount' => $item->tax_amount,
                        'sub_total' => $item->subtotal,
                        'quantity' => $item->quantity,
                        'subtotal' => $item->price * $item->quantity,
                        'eta_min' => $item->eta_min,
                        'eta_max' => $item->eta_max,
                        'eta_unit' => $item->eta_unit,
                        'delivery_estimate' => $this->orderItemDeliveryEstimate($item),
                        'addons' => $this->formatOrderItemAddons($item),
                        'addons_total' => $this->sumOrderItemAddonsLineTotal($item),
                    ];
                });
            }),

            'created_at' => $this->created_at?->format('M d, Y h:i A'),
        ];
    }

    /**
     * Flatten an OrderItem's addons into a scalar-friendly list. Mirrors the
     * user-side `User/OrderItemResource::formatAddons()` shape so admin and
     * seller panels render the same breakdown.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * Upcoming delivery date window for an order item, counted from the order
     * (item) placed date + the snapshot ETA. Null when no ETA was captured.
     *
     * @return array{from: string, to: string, unit: string}|null
     */
    protected function orderItemDeliveryEstimate($item): ?array
    {
        $min = $item->eta_min;
        $max = $item->eta_max;

        if ($min === null && $max === null) {
            return null;
        }

        $anchor = $item->created_at ? $item->created_at->copy() : now();
        $unit   = $item->eta_unit === 'hours' ? 'hours' : 'days';
        $min    = $min ?? $max;
        $max    = $max ?? $min;

        $add = fn (int $n) => $unit === 'hours'
            ? $anchor->copy()->addHours($n)
            : $anchor->copy()->addDays($n);

        $format = $unit === 'hours' ? 'M d, Y H:i' : 'M d, Y';

        return [
            'from' => $add((int) $min)->format($format),
            'to'   => $add((int) $max)->format($format),
            'unit' => $unit,
        ];
    }

    protected function formatOrderItemAddons($orderItem): array
    {
        if (!$orderItem) {
            return [];
        }

        $addons = $orderItem->relationLoaded('addons')
            ? $orderItem->getRelation('addons')
            : $orderItem->addons()->with(['addonGroup', 'addonItem'])->get();

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
     * Line-total addon contribution: `quantity × sum(addon.price)`.
     */
    protected function sumOrderItemAddonsLineTotal($orderItem): float
    {
        if (!$orderItem) {
            return 0.0;
        }

        $addons = $orderItem->relationLoaded('addons')
            ? $orderItem->getRelation('addons')
            : $orderItem->addons()->get();

        if (!$addons || $addons->isEmpty()) {
            return 0.0;
        }

        return round(((int) $orderItem->quantity) * (float) $addons->sum(fn ($a) => (float) $a->price), 2);
    }

    /**
     * Resolve the rider's earnings on the active DELIVERY assignment for this
     * order. Falls back to the live calculation off the zone + route when no
     * persisted row exists (e.g. admin viewing a brand-new order, or a row
     * that hasn't yet been written because the rider hasn't accepted).
     *
     * @return array<string, mixed>|null
     */
    protected function resolveActiveDeliveryAssignment(): ?array
    {
        if (get_class($this->resource) !== \App\Models\Order::class) {
            return null;
        }

        $assignment = null;
        if ($this->relationLoaded('deliveryBoyAssignments')) {
            $assignment = $this->deliveryBoyAssignments
                ->where('assignment_type', \App\Enums\DeliveryBoy\DeliveryBoyAssignmentTypeEnum::DELIVERY())
                ->whereNotIn('status', [
                    \App\Enums\DeliveryBoy\DeliveryBoyAssignmentStatusEnum::CANCELED(),
                    \App\Enums\DeliveryBoy\DeliveryBoyAssignmentStatusEnum::DROPPED(),
                ])
                ->sortByDesc('assigned_at')
                ->first();
        }

        $live = $this->delivery_boy_earnings ?? null;

        $fmt = static function ($value): ?string {
            if (empty($value)) {
                return null;
            }
            try {
                return $value instanceof \DateTimeInterface
                    ? $value->format('M d, Y h:i A')
                    : \Illuminate\Support\Carbon::parse($value)->format('M d, Y h:i A');
            } catch (\Throwable) {
                return null;
            }
        };

        $payload = [
            'id'                => $assignment?->id,
            'has_persisted_row' => (bool) $assignment,
            'status'            => $assignment?->status,
            'payment_status'    => $assignment?->payment_status,
            'paid_at'           => $fmt($assignment?->paid_at),
            'assigned_at'       => $fmt($assignment?->assigned_at),
            'base_fee'          => (float) ($assignment?->base_fee ?? $live['breakdown']['base_fee'] ?? 0),
            'per_store_pickup_fee' => (float) ($assignment?->per_store_pickup_fee ?? $live['breakdown']['per_store_pickup_fee'] ?? 0),
            'distance_based_fee' => (float) ($assignment?->distance_based_fee ?? $live['breakdown']['distance_based_fee'] ?? 0),
            'per_order_incentive' => (float) ($assignment?->per_order_incentive ?? $live['breakdown']['per_order_incentive'] ?? 0),
            'total_earnings'    => (float) ($assignment?->total_earnings ?? $live['total'] ?? 0),
            'awaiting_settle'   => $assignment
                && $assignment->status === \App\Enums\DeliveryBoy\DeliveryBoyAssignmentStatusEnum::CANCELLED_BY_ADMIN()
                && $assignment->payment_status === \App\Enums\DeliveryBoy\EarningPaymentStatusEnum::PENDING(),
        ];

        if (!$assignment && empty($live['total'])) {
            return null;
        }

        return $payload;
    }

    /**
     * Snapshot the delivery_route attribute (set by the controller before
     * resource construction) into a shape the admin Delivery card can render.
     *
     * @return array<string, mixed>|null
     */
    protected function resolveDeliveryRouteSnapshot(): ?array
    {
        if (get_class($this->resource) !== \App\Models\Order::class) {
            return null;
        }

        $route = $this->delivery_route ?? null;
        if (empty($route) || empty($route['total_distance'])) {
            return null;
        }

        $stops = is_array($route['route'] ?? null) ? count($route['route']) : 0;

        return [
            'total_distance' => (float) ($route['total_distance'] ?? 0),
            'stops_count'    => $stops,
        ];
    }
}
