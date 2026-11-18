<?php

namespace App\Http\Resources\User;

use App\Enums\SettingTypeEnum;
use App\Models\Market;
use App\Services\MarketService;
use App\Services\SettingService;
use App\Support\PricePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $market = $request?->attributes->get('market');

        return [
            'items_total'           => $this->convert($this['items_total'] ?? 0, $market),
            'total_saving'          => $this->convert($this['total_saving'] ?? 0, $market),
            'cod_available'         => (bool) ($this['cod_available'] ?? false),
            'products_allow_cod'    => (bool) ($this['products_allow_cod'] ?? true),
            'platform_fee'          => $this->convert($this['platform_fee'] ?? 0, $market),
            'cod_fee'               => $this->convert($this['cod_fee'] ?? 0, $market),
            'additional_charges_total' => $this->convert($this['additional_charges_total'] ?? 0, $market),
            'delivery_charges'      => $this->convert($this['delivery_charges'] ?? 0, $market),
            'use_wallet'            => $this['use_wallet'] ?? false,
            'promo_code'            => $this['promo_code'] ?? '',
            'promo_discount'        => $this->convert((float)($this['promo_discount'] ?? 0), $market),
            'promo_applied'         => $this['promo_applied'] ?? [],
            'promo_error'           => $this['promo_error'] ?? null,
            'wallet_balance'        => $this->convert($this['wallet_balance'] ?? 0, $market),
            'wallet_amount_used'    => $this->convert($this['wallet_amount_used'] ?? 0, $market),
            'payable_amount'        => $this->convert($this['payable_amount'] ?? 0, $market),
            'order_total'           => $this->convert($this['order_total'] ?? 0, $market),
            'seller_shipping_costs' => collect($this['seller_shipping_costs'] ?? [])->map(function ($cost) use ($market) {
                return [
                    'store_id'      => $cost['store_id'],
                    'store_name'    => $cost['store_name'],
                    'shipping_cost' => $cost['shipping_cost'] !== null ? $this->convert($cost['shipping_cost'], $market) : null,
                    'is_fulfillable'=> $cost['is_fulfillable'],
                    'items'         => collect($cost['items'] ?? [])->map(function ($item) use ($market) {
                        return [
                            'cart_item_id'       => $item['cart_item_id'],
                            'product_name'       => $item['product_name'],
                            'is_fulfillable'     => $item['is_fulfillable'],
                            'unfulfillable_reason' => $item['unfulfillable_reason'],
                            'shipping_rate'      => $item['shipping_rate'] !== null ? $this->convert($item['shipping_rate'], $market) : null,
                            'location_id'        => $item['location_id'],
                            'location_name'      => $item['location_name'],
                            'shipping_zone_id'   => $item['shipping_zone_id'],
                        ];
                    })->all(),
                ];
            })->all(),
            'pending_charges' => collect($this['pending_charges'] ?? [])->map(function ($charge) use ($market) {
                return [
                    'id'              => $charge['id'],
                    'amount'          => $this->convert($charge['amount'] ?? 0, $market),
                    'reason'          => $charge['reason'],
                    'reason_note'     => $charge['reason_note'],
                    'seller_order_id' => $charge['seller_order_id'],
                ];
            })->all(),
            'pending_charges_total' => $this->convert($this['pending_charges_total'] ?? 0, $market),
            'tax_breakdown' => collect($this['tax_breakdown'] ?? [])->map(function ($tax) use ($market) {
                return [
                    'tax_rate_id' => $tax['tax_rate_id'],
                    'title'       => $tax['title'],
                    'rate'        => $tax['rate'],
                    'amount'      => $this->convert($tax['amount'] ?? 0, $market),
                ];
            })->all(),
            'tax_total'       => $this->convert($this['tax_total'] ?? 0, $market),
            'minimum_cart_amount' => $this->convert($this->minimumCartAmountBase(), $market),
            'currency_code'   => $this->currencyShape($market)['currency_code'],
            'currency_symbol' => $this->currencyShape($market)['currency_symbol'],
            'format'          => $market
                ? PricePresenter::formatRules($market, $market->resolveDecimals())
                : PricePresenter::formatRules(app(MarketService::class)->current(), 2),
        ];
    }

    /**
     * Read the configured minimum cart amount (base currency) from the
     * cached system settings. Mirrors OrderService::processOrderCreation,
     * which checks payable_amount against this same value server-side.
     */
    protected function minimumCartAmountBase(): float
    {
        $values = app(SettingService::class)->getSettingValues(SettingTypeEnum::SYSTEM());
        return (float) ($values['minimumCartAmount'] ?? 0);
    }

    protected function currencyShape(?Market $market): array
    {
        return $market
            ? PricePresenter::forMarket(1.0, $market)
            : PricePresenter::forCurrent(1.0);
    }

    /**
     * Helper to convert an amount in base currency to market currency.
     */
    protected function convert(float|int|string $amount, ?Market $market): float
    {
        $baseAmount = (float) $amount;
        $shape = $market
            ? PricePresenter::forMarket($baseAmount, $market)
            : PricePresenter::forCurrent($baseAmount);
        return (float) ($shape['price'] ?? 0.0);
    }
}
