<?php

namespace App\Http\Resources\User;

use App\Enums\Order\FulfillmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Shipping\ShipmentStatusEnum;
use App\Services\MarketService;
use App\Services\OrderCustomerStatusPresenter;
use App\Services\OrderService;
use App\Support\PricePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flat customer "My Orders" entry — ONE order item plus its order context.
 * Used by the item-list endpoint. The per-item timeline lives on the order
 * detail response, not here (this list is intentionally lean).
 */
class OrderItemListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->order;

        $orderedQty = (int) $this->quantity;
        $committedQty = 0;
        if ($this->resource->relationLoaded('shipmentItems')) {
            $committedQty = (int) $this->shipmentItems
                ->filter(fn ($si) => $si->shipment
                    && (string) $si->shipment->status !== (string) ShipmentStatusEnum::CANCELLED())
                ->sum('quantity');
        }
        $unshippedQty = max(0, $orderedQty - $committedQty);
        $isActive = (string) $this->status === (string) OrderItemStatusEnum::ACTIVE();

        $canCancel = false;
        if ((bool) $this->is_cancelable && $isActive && $unshippedQty > 0) {
            $canCancel = $committedQty > 0 || in_array((string)$this->fulfillment_status, FulfillmentStatusEnum::cancellableWhileIn($this->cancelable_till), true);
        }

        $isReviewExist = OrderService::checkUserReviewExistByOrderItemId($this->id);
        if ($isReviewExist) {
            $userReview = OrderService::getUserReviewByOrderItemId($this->id);
        }
        return [
            'id'              => $this->id,
            'order_id'        => $this->order_id,
            'title'           => $this->title,
            'variant_title'   => $this->variant_title,
            'slug' => $order?->slug,
            'sku'             => $this->sku,
            'quantity'        => $this->quantity,
            'price'           => $this->price,
            'subtotal'        => $this->subtotal,
            'status'          => $this->status,
            'customer_status' => new OrderCustomerStatusPresenter()->itemStatus($this->resource),
            'return_eligible' => $this->return_eligible,
            'return_deadline' => $this->return_deadline,
            'can_cancel'          => $canCancel,
            'cancelable_quantity' => $canCancel ? $unshippedQty : 0,
            'seller_id'       => $this->store?->seller?->id,
            'seller_name'     => $this->store?->seller?->user?->name,
            'is_user_review_given' => $isReviewExist,
            'user_review' => $userReview ?? null,
            'refund' => $this->refundSummary($order),
            'product' => $this->when($this->product, fn () => [
                'id'    => $this->product->id ?? null,
                'name'  => $this->product->title ?? null,
                'slug'  => $this->product->slug ?? null,
                'image' => $this->product->main_image ?? null,
                'category' => $this->product?->category?->title ?? "",
                'brand' => $this->product?->brand?->title ?? "",
            ]),
            'variant' => $this->when($this->variant, fn () => [
                'id'    => $this->variant->id ?? null,
                'title' => $this->variant->title ?? null,
                'image' => $this->variant->image ?? null,
            ]),
            'store' => $this->when($this->store, fn () => [
                'id'   => $this->store->id ?? null,
                'name' => $this->store->name ?? null,
                'slug' => $this->store->slug ?? null,
            ]),
            'order' => $order ? [
                'id'             => $order->id,
                'order_number'   => $order->order_number,
                'uuid'           => $order->uuid,
                'slug'           => $order->slug,
                'order_date'     => $order->created_at?->format('Y-m-d H:i:s'),
                'status'         => $order->status,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'currency'       => $order->currency_code,
                'currency_symbol' => $order->order_summary['fx_settings']['currency_symbol']
                    ?? PricePresenter::symbolFor($order->currency_code),
                'format'          => $order->order_summary['fx_settings']['format_rules']
                    ?? ($order->market
                        ? PricePresenter::formatRules($order->market, $order->market->resolveDecimals())
                        : PricePresenter::formatRules(app(MarketService::class)->current(), 2)),
                'final_total'    => (float) $order->final_total,
                'total_payable'  => (float) $order->total_payable,
            ] : null,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function refundSummary($order): ?array
    {
        if (! $order?->relationLoaded('refunds')) {
            return null;
        }

        $refunds = $order->refunds;
        $rows = $refunds->filter(fn ($refund) => $refund->relationLoaded('items')
            && $refund->items->contains('id', $this->id))
            ->map(function ($refund) use ($refunds) {
                $settlement = $refund->settled_by_refund_id
                    ? $refunds->firstWhere('id', $refund->settled_by_refund_id)
                    : null;
                $payment = $settlement ?? $refund;

                return [
                    'amount' => (float) $refund->items->firstWhere('id', $this->id)->pivot->amount,
                    'status' => $payment->status->value,
                    'method' => $payment->method->value,
                    'created_at' => $refund->created_at?->toIso8601String(),
                    'issued_at' => $payment->issued_at?->toIso8601String(),
                ];
            })->values();

        if ($rows->isEmpty()) {
            return null;
        }

        $status = $rows->contains('status', RefundStatusEnum::FAILED())
            ? RefundStatusEnum::FAILED()
            : ($rows->contains('status', RefundStatusEnum::OWED())
                ? RefundStatusEnum::OWED()
                : RefundStatusEnum::ISSUED());

        return [
            'amount' => (float) $rows->sum('amount'),
            'status' => $status,
            'method' => $rows->pluck('method')->unique()->count() === 1 ? $rows->first()['method'] : null,
            'created_at' => $rows->max('created_at'),
            'issued_at' => $rows->max('issued_at'),
        ];
    }
}
