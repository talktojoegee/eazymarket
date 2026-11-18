<?php

namespace App\Http\Resources\User;

use App\Http\Resources\OrderSellerFeedbackResource;
use App\Http\Resources\User\PromoLineResource;
use App\Services\DeliveryBoyService;
use App\Services\MarketService;
use App\Services\OrderCustomerStatusPresenter;
use App\Support\PricePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isDeliveryFeedbackGiven = false;
        $deliveryFeedback = null;

        $market = $request?->attributes->get('market');

        $statusPresenter = new OrderCustomerStatusPresenter();
        $customerStatus = $statusPresenter->forOrder($this->resource);
        if ($this->relationLoaded('items')) {
            foreach ($this->items as $item) {
                $item->setRelation('order', $this->resource);
            }
        }

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'user_id' => $this->user_id,
            'email' => $this->email,
            'currency_code' => $this->currency_code,
            'currency_rate' => $this->currency_rate,
            'currency_symbol' => $this->order_summary['fx_settings']['currency_symbol'] ?? ($market ? PricePresenter::symbolFor($market->currency_code) : PricePresenter::symbolFor(PricePresenter::baseCode())),
            'format' => $this->order_summary['fx_settings']['format_rules'] ?? ($market
                ? PricePresenter::formatRules($market, $market->resolveDecimals())
                : PricePresenter::formatRules(app(MarketService::class)->current(), 2)),
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'status' => $this->status,
            'fulfillment_status' => $this->fulfillment_status ?? 'unfulfilled',
            'fulfillment_status_label' => ucwords(strtolower(str_replace('_', ' ', (string) ($this->fulfillment_status ?? 'unfulfilled')))),
            'customer_status' => [
                'code'         => $customerStatus['code'],
                'label'        => $customerStatus['label'],
                'description'  => $customerStatus['description'],
                'stage'        => $customerStatus['stage'],
                'is_exception' => $customerStatus['is_exception'],
            ],
            'invoice' => url('order-invoice?id=' . $this->uuid) ?? "",
            'is_rush_order' => null,
            'estimated_delivery_time' => null,
            'delivery_time_slot_id' => null,
            'delivery_boy_id' => null,
            'delivery_boy_name' => "",
            'delivery_boy_phone' => 0.0,
            'delivery_boy_profile' => "",
            'is_delivery_feedback_given' => $isDeliveryFeedbackGiven,
            'delivery_feedback' => $deliveryFeedback,
            'wallet_balance' => $this->wallet_balance,
            'promo_code' => $this->promo_code,
            'promo_discount' => $this->promo_discount,
            'gift_card' => $this->gift_card,
            'gift_card_discount' => $this->gift_card_discount,
            'delivery_charge' => $this->delivery_charge,
            'platform_fee' => $this->platform_fee,
            'cod_fee' => $this->cod_fee,
            'per_store_drop_off_fee' => null,
            'subtotal' => (string) $this->subtotal,
            'total_payable' => (string) $this->total_payable,
            'final_total' => (string) $this->final_total,
            'order_summary' => $this->order_summary,
            'refunds' => OrderRefundResource::collection($this->whenLoaded('refunds')),


            'shipping_name' => $this->shipping_name,
            'shipping_address_1' => $this->shipping_address_1,
            'shipping_address_2' => $this->shipping_address_2,
            'shipping_landmark' => $this->shipping_landmark,
            'shipping_zip' => $this->shipping_zip,
            'shipping_phone' => $this->shipping_phone,
            'shipping_address_type' => $this->shipping_address_type,
            'shipping_latitude' => $this->shipping_latitude,
            'shipping_longitude' => $this->shipping_longitude,
            'shipping_city' => $this->shipping_city,
            'shipping_state' => $this->shipping_state,
            'shipping_country' => $this->shipping_country,
            'shipping_country_code' => $this->shipping_country_code,
            'order_note' => $this->order_note ?? '',

            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'seller_feedbacks' => OrderSellerFeedbackResource::collection($this->whenLoaded('sellerOrders')),
            'promo_line' => new PromoLineResource($this->whenLoaded('promoLine')),
            'user' => $this->when($this->relationLoaded('user'), function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'payment_response' => $this->payment_response ?? null,
        ];
    }

}
