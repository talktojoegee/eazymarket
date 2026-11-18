<?php

namespace App\Http\Controllers\Api;

use App\Enums\Market\MarketStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\MarketResource;
use App\Models\Market;
use App\Services\MarketService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Customer-facing market endpoints. Consumed by:
 *   - customer Flutter app (lib/services/market/)
 *   - seller Flutter app (when listing enabled markets on store edit)
 *   - customer web (Next.js — MarketContext + <MarketSwitcher />)
 *
 * Auth: most routes are public. /switch is public too (lets anonymous customers
 * set their market) but also updates user_market when sanctum-authenticated.
 */
#[Group('Marketplace API')]
class MarketApiController extends Controller
{
    public function __construct(protected MarketService $markets)
    {
    }

    /**
     * GET /api/markets
     *
     * List of active markets the storefront supports. Ordered by priority DESC,
     * name ASC. Countries pivot is included so the UI can render a flag list.
     */
    public function index(): JsonResponse
    {
        $markets = Market::query()
            ->with(['currency', 'countries:id,iso2,name'])
            ->where('status', MarketStatusEnum::ACTIVE())
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.markets_fetched',
            data: MarketResource::collection($markets)->resolve(),
        );
    }

    /**
     * GET /api/markets/current
     *
     * The market this request resolves to — same logic the DetectMarket
     * middleware applies (header → query → user pivot → cookie → country-header → default).
     * Returns full Market with currency + countries so clients can hydrate
     * their local context in one round-trip.
     */
    public function current(Request $request): JsonResponse
    {
        $market = $this->markets->resolveForRequest($request);
        $market->loadMissing(['currency', 'countries:id,iso2,name']);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.current_market_fetched',
            data: (new MarketResource($market))->resolve($request),
        );
    }

    /**
     * POST /api/markets/switch
     *
     * Body: { code: "IN" } — change the customer's market preference.
     *   - Always sets a 1-year cookie ('market', code).
     *   - When authenticated, also writes the user_market pivot.
     */
    public function switch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:8', 'alpha_num'],
        ]);

        $code   = strtoupper($data['code']);
        $market = Market::query()
            ->where('code', $code)
            ->where('status', MarketStatusEnum::ACTIVE())
            ->first();

        if (! $market) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('messages.market_not_found'),
                data: [],
            );
        }

        if ($user = $request->user()) {
            $this->markets->setForUser($user->id, $market);
        }

        $cookieDays = 365;
        $cookie     = Cookie::make(MarketService::COOKIE_NAME, $market->code, $cookieDays * 24 * 60);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('messages.market_switched'),
            data: ['market' => new MarketResource($market->loadMissing(['currency', 'countries:id,iso2,name']))->resolve($request)],
        )->withCookie($cookie);
    }
}
