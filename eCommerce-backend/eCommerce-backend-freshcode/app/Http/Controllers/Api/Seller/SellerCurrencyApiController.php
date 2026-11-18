<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Market;
use App\Support\PricePresenter;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Seller Currency')]
class SellerCurrencyApiController extends Controller
{
    #[QueryParameter('amount', description: 'Amount in the platform base currency', type: 'number', default: 1)]
    #[QueryParameter('market_ids', description: 'Optional seller market IDs to include', type: 'array')]
    public function conversion(Request $request): JsonResponse
    {
        $seller = auth()->user()?->seller();
        if (! $seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
        }

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'market_ids' => 'nullable|array',
            'market_ids.*' => 'integer|exists:markets,id',
        ]);
        $amount = (float) ($validated['amount'] ?? 1);
        $marketIds = $validated['market_ids'] ?? [];
        $markets = Market::query()
            ->whereHas('stores', fn ($query) => $query->where('seller_id', $seller->id))
            ->when($marketIds !== [], fn ($query) => $query->whereIn('id', $marketIds))
            ->orderBy('name')
            ->get();
        $baseCurrency = Currency::query()->where('is_base', true)->first();

        return ApiResponseType::sendJsonResponse(true, __('labels.currency_conversion_fetched_successfully'), [
            'base_currency' => $baseCurrency ? [
                'code' => $baseCurrency->code,
                'symbol' => $baseCurrency->symbol,
                'decimal_places' => (int) $baseCurrency->decimal_places,
            ] : null,
            'amount_base' => $amount,
            'markets' => $markets->map(function (Market $market) use ($amount) {
                $conversion = PricePresenter::forMarket($amount, $market);

                return [
                    'id' => (int) $market->id,
                    'code' => $market->code,
                    'name' => $market->name,
                    'target_currency_code' => $market->currency_code,
                    'currency_code' => $conversion['currency_code'],
                    'currency_symbol' => $conversion['currency_symbol'],
                    'conversion_rate' => $conversion['fx_rate'],
                    'fx_fallback' => (bool) ($conversion['fx_fallback'] ?? false),
                    'converted_amount' => (float) $conversion['price'],
                    'formatted_amount' => $conversion['formatted'],
                    'format' => $conversion['format'],
                ];
            })->values()->all(),
        ]);
    }
}
