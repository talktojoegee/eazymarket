<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\GeoCity;
use App\Models\GeoPincode;
use App\Models\GeoState;
use App\Services\GeoImportService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer-facing geo lookups for the address form. Backed entirely by the
 * local geo tables (no external calls) and scoped to countries the platform
 * actually sells to (market countries).
 *
 * Cascading pickers: countries -> states(country) -> cities(country[,state]).
 * Every city/state item embeds its parents so the app can reverse-fill: pick a
 * city and its state + country come with it; pick a state and its country does.
 * `resolve` covers the same reverse lookup for a typed city/state value.
 */
#[Group('Geography')]
class GeoApiController extends Controller
{
    public function __construct(protected GeoImportService $geo)
    {
    }

    /**
     * Countries the platform sells to (union of all market countries).
     *
     * Each item
     *  carries `type`: `zipcode` = postal-code driven (pincode required), `city`
     *  = no postal codes (city/state driven, pincode optional) — so the address
     *  form knows whether to show/require the pincode field.
     */
    public function countries(): JsonResponse
    {
        $iso2s = $this->geo->marketCountryIso2s();
        $noPostcode = array_map('strtoupper', (array) config('geo.no_postcode_countries', []));

        $items = Country::whereIn('iso2', $iso2s)
            ->orderBy('name')
            ->get(['iso2', 'name'])
            ->map(fn ($c) => [
                'iso2' => $c->iso2,
                'name' => $c->name,
                'type' => in_array(strtoupper($c->iso2), $noPostcode, true) ? 'city' : 'zipcode',
            ])
            ->all();

        return ApiResponseType::sendJsonResponse(true, 'ok', ['items' => $items]);
    }

    /** States of a market country. */
    public function states(Request $request): JsonResponse
    {
        $country = $this->marketCountry($request->query('country'));
        if (!$country) {
            return ApiResponseType::sendJsonResponse(true, 'ok', ['items' => []]);
        }

        $items = GeoState::where('country_iso2', $country)
            ->orderBy('name')
            ->get(['state_code', 'name'])
            ->map(fn ($s) => [
                'state_code'   => $s->state_code,
                'name'         => $s->name,
                'country_iso2' => $country,
                'country_name' => $this->countryName($country),
            ])
            ->all();

        return ApiResponseType::sendJsonResponse(true, 'ok', ['items' => $items]);
    }

    /**
     * Cities of a market country, optionally filtered by state and a name
     * prefix search. Each item embeds its state + country for reverse-fill.
     */
    #[QueryParameter('search', description: 'Search you city', type: 'string', example: 'Bhuj')]
    #[QueryParameter('state', description: 'state filter', type: 'string', example: 'gujarat')]
    #[QueryParameter('iso2', description: 'iso2 filter', type: 'string', example: 'AE,IN,US')]
    #[QueryParameter('per_page', description: 'items per page', type: 'integer', example: 25)]
    #[QueryParameter('page', description: 'page number', type: 'integer', example: 1)]
    public function cities(Request $request): JsonResponse
    {
        $country = $this->marketCountry($request->query('iso2'));
        if (!$country) {
            return ApiResponseType::sendJsonResponse(true, 'ok', [
                'items' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $this->resolvePerPage($request),
                'total' => 0,
            ]);
        }

        $stateCode = trim((string) $request->query('state', ''));
        $search    = trim((string) $request->query('search', ''));
        $perPage   = $this->resolvePerPage($request);

        $query = GeoCity::where('country_iso2', $country);
        if ($stateCode !== '') {
            $query->where('state_code', $stateCode);
        }
        if ($search !== '') {
            $query->where('name', 'like', $this->escapeLike($search) . '%');
        }

        $cities = $query->orderBy('name')->paginate($perPage, ['name', 'state_code']);
        $stateNames = $this->stateNameMap($country, $cities->pluck('state_code')->unique()->all());
        $countryName = $this->countryName($country);

        $items = $cities->getCollection()->map(fn ($c) => [
            'name'         => $c->name,
            'state_code'   => $c->state_code,
            'state_name'   => $stateNames[$c->state_code] ?? null,
            'country_iso2' => $country,
            'country_name' => $countryName,
        ])->all();

        return ApiResponseType::sendJsonResponse(true, 'ok', [
            'items'        => $items,
            'current_page' => $cities->currentPage(),
            'last_page'    => $cities->lastPage(),
            'per_page'     => $cities->perPage(),
            'total'        => $cities->total(),
        ]);
    }

    private function resolvePerPage(Request $request, int $default = 25, int $max = 100): int
    {
        $perPage = (int) $request->query('per_page', $default);

        return max(1, min($perPage, $max));
    }

    /**
     * Reverse lookup: given a city,
     *
     * return every matching (country, state, city) candidate. A bare
     *  city name can appear in several states/countries, so this returns a list.
     */
    #[QueryParameter('city', description: 'city.', type: 'string')]
    public function resolve(Request $request): JsonResponse
    {
        $city  = trim((string) $request->query('city', ''));
        $marketCountries = $this->geo->marketCountryIso2s();

        $items = [];

        if ($city !== '') {
            $q = GeoCity::whereIn('country_iso2', $marketCountries)->where('name', $city);
            foreach ($q->limit(25)->get(['name', 'state_code', 'country_iso2']) as $row) {
                $items[] = [
                    'country_iso2' => $row->country_iso2,
                    'country_name' => $this->countryName($row->country_iso2),
                    'state_code'   => $row->state_code,
                    'state_name'   => $this->singleStateName($row->country_iso2, $row->state_code),
                    'city'         => $row->name,
                ];
            }
        }

        return ApiResponseType::sendJsonResponse(true, 'ok', ['items' => $items]);
    }

    /**
     * Reverse-fill from a pincode alone: given just a pincode
     *
     * Returns `matches` grouped by (country, state) — almost always a single
     * entry, so the client fills country/state/city from matches[0]. Multiple
     * entries mean the pincode is ambiguous across markets and the user picks.
     */
    #[QueryParameter('pincode', description: 'pincode.', type: 'string')]
    public function resolvePincode(Request $request): JsonResponse
    {
        $pincode = trim((string) $request->query('pincode', ''));
        if ($pincode === '') {
            return ApiResponseType::sendJsonResponse(true, 'ok', ['matches' => []]);
        }

        $query = GeoPincode::whereIn('country_iso2', $this->geo->marketCountryIso2s())
            ->where('postcode', $pincode);

        $rows = $query->orderBy('country_iso2')->orderBy('city')->get(['country_iso2', 'city', 'state_code', 'state_name']);

        $matches = $rows
            ->groupBy(fn ($r) => $r->country_iso2 . '|' . $r->state_code . '|' . $r->state_name)
            ->map(fn ($group) => [
                'country_iso2' => $group->first()->country_iso2,
                'country_name' => $this->countryName($group->first()->country_iso2),
                'state_code'   => $group->first()->state_code,
                'state_name'   => $group->first()->state_name,
                'cities'       => $group->pluck('city')->unique()->values()->all(),
            ])
            ->values()
            ->all();

        return ApiResponseType::sendJsonResponse(true, 'ok', ['matches' => $matches]);
    }

    /** Normalize + gate a country param to a market country iso2, else null. */
    protected function marketCountry($raw): ?string
    {
        $iso2 = strtoupper(trim((string) $raw));
        if (strlen($iso2) !== 2) {
            return null;
        }

        return in_array($iso2, $this->geo->marketCountryIso2s(), true) ? $iso2 : null;
    }

    /** @param array<int, string> $stateCodes @return array<string, string> */
    protected function stateNameMap(string $country, array $stateCodes): array
    {
        $codes = array_filter($stateCodes, fn ($c) => $c !== null && $c !== '');
        if (empty($codes)) {
            return [];
        }

        return GeoState::where('country_iso2', $country)
            ->whereIn('state_code', $codes)
            ->pluck('name', 'state_code')
            ->all();
    }

    protected function singleStateName(string $country, ?string $stateCode): ?string
    {
        if ($stateCode === null || $stateCode === '') {
            return null;
        }

        return GeoState::where('country_iso2', $country)
            ->where('state_code', $stateCode)
            ->value('name');
    }

    protected function countryName(string $iso2): ?string
    {
        return Country::where('iso2', $iso2)->value('name');
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
