<?php

namespace App\Http\Controllers;

use App\Enums\Market\CurrencyStatusEnum;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Search endpoint for the reusable <x-tom-select-currency> component.
 *
 * Reads from the `currencies` table (seeded by CurrenciesSeeder from countries data).
 * NOTE: this is distinct from CountryController::getCurrency() which actually returns
 * countries-pivoted-by-their-currency — that legacy method is kept for back-compat
 * with screens that select a currency by browsing countries.
 */
class CurrencyController extends \Illuminate\Routing\Controller
{
    public function search(Request $request): JsonResponse
    {
        $query      = (string) $request->input('search', '');
        $onlyActive = (bool) $request->input('only_active', true);

        $base = Currency::query()
            ->when($onlyActive, fn ($q) => $q->where('status', CurrencyStatusEnum::ACTIVE()))
            ->orderBy('code');

        if (!empty($request->input('find'))) {
            $rows = (clone $base)->where('code', strtoupper($request->input('find')))->get();
        } elseif (!empty($request->input('find_in')) && is_array($request->input('find_in'))) {
            $rows = (clone $base)
                ->whereIn('code', array_map('strtoupper', $request->input('find_in')))
                ->get();
        } else {
            $rows = (clone $base)
                ->when($query !== '', function ($q) use ($query) {
                    $q->where(function ($sub) use ($query) {
                        $sub->where('code', 'like', "%{$query}%")
                            ->orWhere('name', 'like', "%{$query}%")
                            ->orWhere('symbol', 'like', "%{$query}%");
                    });
                })
                ->limit(20)
                ->get();
        }

        return response()->json($rows->map(fn (Currency $c) => [
            'value'           => $c->code,
            'text'            => $c->code . ' — ' . $c->symbol . ' (' . $c->name . ')',
            'code'            => $c->code,
            'name'            => $c->name,
            'symbol'          => $c->symbol,
            'decimal_places'  => $c->decimal_places,
        ]));
    }
}
