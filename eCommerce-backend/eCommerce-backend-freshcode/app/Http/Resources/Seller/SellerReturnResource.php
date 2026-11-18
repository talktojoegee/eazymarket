<?php

namespace App\Http\Resources\Seller;

use App\Traits\FormatsOrderMoney;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A return row for the seller returns screens.
 *
 * Every field is listed explicitly so the payload is a contract rather than
 * whatever columns happen to exist on the table. Money is echoed raw and again
 * formatted in the order's own currency, and `order_item.addons` is shaped like
 * the order listing instead of raw addon rows.
 *
 * The caller supplies the currency descriptor (`PricePresenter::payload()`) and
 * the seller order id, so a list resolves both once for the page rather than
 * per row.
 */
class SellerReturnResource extends JsonResource
{
    use FormatsOrderMoney;

    /**
     * @param array{code:string,symbol:string,format:array<string,mixed>}|null $currency
     */
    public function __construct(
        $resource,
        private readonly ?array $currency = null,
        private readonly ?int $sellerOrderId = null,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller_order_id' => $this->sellerOrderId,
            'order_item_id' => $this->order_item_id,
            'order_id' => $this->order_id,
            'user_id' => $this->user_id,
            'seller_id' => $this->seller_id,
            'store_id' => $this->store_id,
            'quantity' => $this->quantity,

            'reason' => $this->reason,
            'reason_type' => $this->reason_type?->value ?? $this->reason_type,
            'reason_code' => $this->reason_code?->value ?? $this->reason_code,
            'shipping_cost_payer' => $this->shipping_cost_payer,
            'admin_adjudication_status' => $this->admin_adjudication_status?->value ?? $this->admin_adjudication_status,
            'admin_notes' => $this->admin_notes,
            'seller_comment' => $this->seller_comment,
            'return_status' => $this->return_status,

            'refund_amount' => $this->refund_amount,
            'refund_amount_formatted' => $this->formatOrderMoney((float) $this->refund_amount, $this->currency),
            'return_shipping_charge' => $this->return_shipping_charge,
            'return_shipping_charge_formatted' => $this->formatOrderMoney((float) $this->return_shipping_charge, $this->currency),
            'reimbursement_amount' => $this->reimbursement_amount,
            'reimbursement_amount_formatted' => $this->reimbursement_amount === null
                ? null
                : $this->formatOrderMoney((float) $this->reimbursement_amount, $this->currency),
            'currency' => $this->currency,

            'return_shipment_id' => $this->return_shipment_id,
            'return_carrier' => $this->return_carrier,
            'return_tracking_number' => $this->return_tracking_number,
            'return_tracking_url' => $this->return_tracking_url,
            'return_label_url' => $this->return_label_url,
            'no_shipping_required' => $this->no_shipping_required,

            'seller_approved_at' => $this->seller_approved_at,
            'picked_up_at' => $this->picked_up_at,
            'received_at' => $this->received_at,
            'refund_processed_at' => $this->refund_processed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'images' => $this->images,
            'media' => $this->whenLoaded('media'),
            'order' => $this->whenLoaded('order'),
            'store' => $this->whenLoaded('store'),
            'user' => $this->whenLoaded('user'),
            'order_item' => $this->orderItemPayload($request),
        ];
    }

    private function orderItemPayload(Request $request): ?array
    {
        $orderItem = $this->orderItem;
        if (!$orderItem) {
            return null;
        }

        $addons = $orderItem->relationLoaded('addons')
            ? $orderItem->getRelation('addons')
            : $orderItem->addons()->with(['addonGroup', 'addonItem'])->get();

        return array_merge($orderItem->toArray(), [
            'addons' => OrderItemAddonResource::listFor($addons, $this->currency, $request),
        ]);
    }
}
