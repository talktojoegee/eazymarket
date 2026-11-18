<?php

namespace App\Http\Controllers\Api;

use App\Enums\Market\CurrencyStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer-facing currency list. Used by client apps that need to render
 * currency symbols / decimal_places without a per-market round-trip.
 */
#[Group('Currencies')]
class CurrencyApiController extends Controller
{
    /**
     * GET /api/currencies
     *
     * Optional query params:
     *   - search=USD       — substring match on code/name/symbol
     *   - active_only=1    — default; pass `0` to include inactive currencies
     */
    public function index(Request $request): JsonResponse
    {
        $search     = (string) $request->input('search', '');
        $activeOnly = (bool) $request->input('active_only', true);

        $rows = Currency::query()
            ->when($activeOnly, fn ($q) => $q->where('status', CurrencyStatusEnum::ACTIVE()))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('symbol', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->get();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.currencies_fetched',
            data: CurrencyResource::collection($rows)->resolve(),
        );
    }
}
