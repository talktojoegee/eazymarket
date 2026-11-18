<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\Shipping\EtaUnitEnum;
use App\Enums\Shipping\FulfillmentMethodEnum;
use App\Enums\Shipping\RateTypeEnum;
use App\Enums\Shipping\ShippingCostPayerEnum;
use App\Models\Product;
use App\Models\Seller;
use App\Models\ShippingProfile;
use App\Models\StorePackage;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read side of the seller's shipping setup: profiles, their zones and rates,
 * and parcel presets. Writes go through the panel controllers (same validation,
 * same services), so this class only covers the screens the panel renders as
 * Blade rather than JSON.
 */
#[Group('Seller Shipping')]
class SellerShippingApiController
{
    /**
     * Shipping profiles for the scoped store, with zone and product counts.
     */
    #[QueryParameter('all', description: 'Return profiles across all of the seller\'s stores', type: 'bool', default: false)]
    public function profiles(Request $request): JsonResponse
    {
        try {
            $seller = $this->requireSeller();
            $storeIds = $seller->stores()->pluck('id');
            $scopedId = $request->boolean('all') ? null : $request->attributes->get('seller_store')?->id;

            $profiles = ShippingProfile::query()
                ->with(['store:id,name,slug', 'locations:id', 'products:id', 'zones:id,shipping_profile_id'])
                ->whereIn('store_id', $storeIds)
                ->when($scopedId, fn ($q) => $q->where('store_id', $scopedId))
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();

            $counts = $this->effectiveProductCounts($profiles, $seller);

            $data = $profiles->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'store' => $p->store?->only(['id', 'name', 'slug']),
                'is_default' => (bool) $p->is_default,
                'location_count' => $p->locations->count(),
                'zone_count' => $p->zones->count(),
                'product_count' => $counts[$p->id] ?? 0,
            ]);

            return ApiResponseType::sendJsonResponse(true, 'labels.shipping_profiles_fetched_successfully', $data);
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', [], 404);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * One profile with its ship-from locations, assigned products and zones
     * (countries, serviceability rules and rates), plus the countries the store
     * is allowed to ship to.
     */
    public function profile(int $id): JsonResponse
    {
        try {
            $seller = $this->requireSeller();

            $profile = ShippingProfile::whereIn('store_id', $seller->stores()->pluck('id'))
                ->with([
                    'products:id,title',
                    'zones' => fn ($q) => $q->orderBy('priority')->orderBy('name'),
                    'zones.countries:id,iso2,name',
                    'zones.serviceabilityRules',
                    'zones.rates',
                ])
                ->findOrFail($id);

            $store = $profile->store;

            return ApiResponseType::sendJsonResponse(true, 'labels.shipping_profiles_fetched_successfully', [
                'profile' => $profile->only(['id', 'name', 'store_id', 'is_default']),
                'store' => $store?->only(['id', 'name', 'slug']),
                'locations' => $store
                    ? $store->locations()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default', 'is_active'])
                    : [],
                'ranked_locations' => $profile->locations()
                    ->orderByPivot('priority')
                    ->orderBy('store_locations.name')
                    ->get(['store_locations.id', 'store_locations.name', 'store_locations.is_default']),
                'products' => $profile->products,
                'zones' => $profile->zones,
                'allowed_countries' => $store
                    ? $store->allowedShippingCountries()->map(fn ($c) => ['iso2' => $c->iso2, 'name' => $c->name])->values()
                    : [],
            ]);
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.not_found', [], 404);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * Parcel presets for the scoped store.
     */
    #[QueryParameter('all', description: 'Return packages across all of the seller\'s stores', type: 'bool', default: false)]
    public function packages(Request $request): JsonResponse
    {
        try {
            $seller = $this->requireSeller();
            $scopedId = $request->boolean('all') ? null : $request->attributes->get('seller_store')?->id;

            $packages = StorePackage::query()
                ->with('store:id,name,slug')
                ->whereIn('store_id', $seller->stores()->pluck('id'))
                ->when($scopedId, fn ($q) => $q->where('store_id', $scopedId))
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();

            return ApiResponseType::sendJsonResponse(true, 'labels.packages_fetched_successfully', $packages);
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', [], 404);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * A single parcel preset owned by the seller.
     */
    public function package(int $id): JsonResponse
    {
        try {
            $package = StorePackage::whereIn('store_id', $this->requireSeller()->stores()->pluck('id'))
                ->findOrFail($id);

            return ApiResponseType::sendJsonResponse(true, 'labels.packages_fetched_successfully', $package);
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.not_found', [], 404);
        }
    }

    /**
     * Enum options the shipping forms need: rate types, fulfilment methods,
     * who bears the cost, ETA units and package types.
     */
    public function enums(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(true, 'labels.fetched_successfully', [
            'rate_types' => RateTypeEnum::values(),
            'fulfillment_methods' => FulfillmentMethodEnum::values(),
            'cost_payers' => ShippingCostPayerEnum::values(),
            'eta_units' => EtaUnitEnum::values(),
            'package_types' => [StorePackage::TYPE_BOX, StorePackage::TYPE_ENVELOPE, StorePackage::TYPE_SOFT_PACK],
        ]);
    }

    /**
     * Profiles are counted against seller-owned products: every store's default
     * profile absorbs whatever is not explicitly assigned elsewhere.
     */
    protected function effectiveProductCounts($profiles, Seller $seller): array
    {
        $sellerTotal = Product::where('seller_id', $seller->id)->count();

        $assigned = 0;
        foreach ($profiles as $p) {
            if (!$p->is_default) {
                $assigned += $p->products->count();
            }
        }

        $defaultFallback = max(0, $sellerTotal - $assigned);

        $counts = [];
        foreach ($profiles as $p) {
            $counts[$p->id] = $p->is_default ? $defaultFallback : $p->products->count();
        }

        return $counts;
    }

    protected function requireSeller(): Seller
    {
        $seller = auth()->user()?->seller();
        if (!$seller) {
            throw new ModelNotFoundException('Seller not found');
        }

        return $seller;
    }
}
