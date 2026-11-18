<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MarketResource;
use App\Models\Country;
use App\Models\Market;
use App\Services\MarketService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/geo-detect
 *
 * Quick "what's my market?" probe used by the customer apps on first launch.
 *
 * Detection order (least invasive first):
 *   1. CF-IPCountry / X-Country-Code / X-AppEngine-Country headers
 *      (set by Cloudflare, Vercel, AWS CloudFront, GCP App Engine)
 *   2. ?country=US query param override (useful for QA)
 *   3. Setting fallback (system default market)
 *
 * Returns the detected country code (when available) and the suggested
 * market. Client apps typically use this to seed their MarketContext on
 * first launch when no user-saved preference exists yet.
 */
#[Group('Marketplace API')]
class GeoDetectApiController extends Controller
{
    public function __invoke(Request $request, MarketService $markets): JsonResponse
    {
        $iso2 = $this->resolveCountryIso2($request);

        $market = $iso2 ? $markets->byCountry($iso2) : null;
        $market ??= $markets->default();
        $market->loadMissing(['currency', 'countries:id,iso2,name']);

        $country = $iso2
            ? Country::query()->where('iso2', $iso2)->first(['iso2', 'iso3', 'name', 'emoji', 'phonecode'])
            : null;

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.geo_detect_resolved',
            data: [
                'detected_country' => $country ? [
                    'iso2'      => $country->iso2,
                    'iso3'      => $country->iso3,
                    'name'      => $country->name,
                    'emoji'     => $country->emoji,
                    'phonecode' => $country->phonecode,
                ] : null,
                'suggested_market' => (new MarketResource($market))->resolve($request),
                'source'           => $this->detectionSource($request),
            ],
        );
    }

    protected function resolveCountryIso2(Request $request): ?string
    {
        foreach (['CF-IPCountry', 'X-Country-Code', 'X-AppEngine-Country'] as $header) {
            $iso = trim((string) $request->header($header));
            if ($iso !== '' && strtoupper($iso) !== 'XX') {
                return strtoupper($iso);
            }
        }

        $override = $request->query('country');
        if ($override) {
            return strtoupper(substr((string) $override, 0, 2));
        }

        return null;
    }

    protected function detectionSource(Request $request): string
    {
        if ($request->query('country')) {
            return 'query_override';
        }
        foreach (['CF-IPCountry', 'X-Country-Code', 'X-AppEngine-Country'] as $header) {
            if (trim((string) $request->header($header)) !== '') {
                return 'edge_header';
            }
        }
        return 'fallback_default';
    }
}
