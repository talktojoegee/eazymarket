<?php

namespace App\Http\Controllers;

use App\Models\GeoCity;
use App\Models\GeoState;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only geo lookups for the seller UI (serviceability city/state pickers).
 * Backed entirely by the local geo_states / geo_cities tables — no external
 * calls. States load in full (few per country); cities are prefix-searched
 * (a country can have thousands).
 */
class GeoController extends Controller
{
    public function states(Request $request): JsonResponse
    {
        $country = strtoupper((string) $request->query('country', ''));
        if (strlen($country) !== 2) {
            return ApiResponseType::sendJsonResponse(true, 'ok', ['items' => []]);
        }

        $items = GeoState::where('country_iso2', $country)
            ->orderBy('name')
            ->get(['state_code', 'name'])
            ->map(fn ($s) => ['state_code' => $s->state_code, 'name' => $s->name])
            ->all();

        return ApiResponseType::sendJsonResponse(true, 'ok', ['items' => $items]);
    }

    public function cities(Request $request): JsonResponse
    {
        $country = strtoupper((string) $request->query('country', ''));
        $search  = trim((string) $request->query('search', ''));
        if (strlen($country) !== 2) {
            return ApiResponseType::sendJsonResponse(true, 'ok', ['items' => []]);
        }

        $query = GeoCity::where('country_iso2', $country);
        if ($search !== '') {
            $query->where('name', 'like', $this->escapeLike($search) . '%');
        }

        $items = $query->orderBy('name')
            ->limit(50)
            ->pluck('name')
            ->map(fn ($n) => ['name' => $n])
            ->all();

        return ApiResponseType::sendJsonResponse(true, 'ok', ['items' => $items]);
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
