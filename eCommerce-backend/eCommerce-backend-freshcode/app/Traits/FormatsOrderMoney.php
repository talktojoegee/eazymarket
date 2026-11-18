<?php

namespace App\Traits;

use App\Services\CurrencyService;
use App\Support\PricePresenter;

/**
 * Renders an order amount in the currency the order settled in. Order money is
 * already stored in the market currency (the FX rate is snapshotted at
 * checkout), so this only formats — symbol, position, separators and grouping
 * come from the order's market. Falls back to the platform default when the
 * order has no market.
 */
trait FormatsOrderMoney
{
    /**
     * @param array{code:string,symbol:string,format:array<string,mixed>}|null $currency
     */
    protected function formatOrderMoney(float $amount, ?array $currency): string
    {
        if (!$currency) {
            return app(CurrencyService::class)->format($amount);
        }

        return PricePresenter::formatWith($amount, $currency['format'], $currency['symbol']);
    }
}
