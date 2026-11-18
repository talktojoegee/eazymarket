<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Support\PricePresenter;

class ProductTaxBreakdownService
{
    public function forVariant(ProductVariant $variant): array
    {
        $product = $variant->relationLoaded('product')
            ? $variant->product
            : $variant->product()->first();

        if (! $product) {
            return [];
        }

        $product->loadMissing(['taxClasses.taxRates', 'taxClasses.market']);
        $inclusive = (bool) $product->is_inclusive_tax;
        $regularPrice = (float) ($variant->getRawOriginal('price') ?? 0);
        $specialRaw = $variant->getRawOriginal('special_price');
        $specialPrice = $specialRaw !== null && (float) $specialRaw > 0
            ? (float) $specialRaw
            : null;

        return $product->taxClasses
            ->filter(fn ($taxClass) => $taxClass->market)
            ->groupBy('market_id')
            ->map(function ($taxClasses) use ($inclusive, $regularPrice, $specialPrice) {
                $market = $taxClasses->first()->market;
                $totalRate = (float) $taxClasses->sum(
                    fn ($taxClass) => (float) $taxClass->taxRates->sum('rate')
                );
                $regular = $this->priceBreakdown($regularPrice, $inclusive, $totalRate);
                $special = $specialPrice !== null
                    ? $this->priceBreakdown($specialPrice, $inclusive, $totalRate)
                    : null;

                return [
                    'market' => [
                        'id' => (int) $market->id,
                        'code' => $market->code,
                        'name' => $market->name,
                        'currency_code' => $market->currency_code,
                    ],
                    'base_currency_code' => PricePresenter::baseCode(),
                    'is_inclusive' => $inclusive,
                    'total_rate' => $totalRate,
                    'regular' => $this->publicPriceBreakdown($regular),
                    'special' => $special ? $this->publicPriceBreakdown($special) : null,
                    'tax_classes' => $taxClasses->map(function ($taxClass) use ($regular, $special, $totalRate) {
                        $classRate = (float) $taxClass->taxRates->sum('rate');

                        return [
                            'id' => (int) $taxClass->id,
                            'title' => $taxClass->title,
                            'total_rate' => $classRate,
                            'regular_tax_amount' => $this->taxShare($regular['tax_amount'], $classRate, $totalRate),
                            'special_tax_amount' => $special
                                ? $this->taxShare($special['tax_amount'], $classRate, $totalRate)
                                : null,
                            'tax_rates' => $taxClass->taxRates->map(fn ($taxRate) => [
                                'id' => (int) $taxRate->id,
                                'title' => $taxRate->title,
                                'rate' => (float) $taxRate->rate,
                                'regular_tax_amount' => $this->taxShare(
                                    $regular['tax_amount'],
                                    (float) $taxRate->rate,
                                    $totalRate,
                                ),
                                'special_tax_amount' => $special
                                    ? $this->taxShare(
                                        $special['tax_amount'],
                                        (float) $taxRate->rate,
                                        $totalRate,
                                    )
                                    : null,
                            ])->values()->all(),
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function priceBreakdown(float $price, bool $inclusive, float $totalRate): array
    {
        if ($inclusive && $totalRate > 0) {
            $priceIncludingTax = $price;
            $taxAmount = $priceIncludingTax * ($totalRate / 100) / (1 + $totalRate / 100);
            $priceExcludingTax = $priceIncludingTax - $taxAmount;
        } else {
            $priceExcludingTax = $price;
            $taxAmount = $price * ($totalRate / 100);
            $priceIncludingTax = $price + $taxAmount;
        }

        return [
            'price_excluding_tax' => $priceExcludingTax,
            'tax_amount' => $taxAmount,
            'price_including_tax' => $priceIncludingTax,
        ];
    }

    private function publicPriceBreakdown(array $breakdown): array
    {
        return array_map(fn ($amount) => round((float) $amount, 6), $breakdown);
    }

    private function taxShare(float $taxAmount, float $rate, float $totalRate): float
    {
        if ($totalRate <= 0) {
            return 0.0;
        }

        return round($taxAmount * ($rate / $totalRate), 6);
    }
}
