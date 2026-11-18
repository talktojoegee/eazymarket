<?php

namespace App\Services;

use App\Enums\SpatieMediaCollectionName;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Events\Store\StoreCreated;
use App\Events\Store\StoreUpdated;
use App\Http\Requests\Store\StoreStoreRequest;
use App\Http\Requests\Store\UpdateStoreRequest;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Models\Country;
use App\Models\OrderItem;
use App\Models\OrderItemReturn;
use App\Models\Seller;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class StoreService
{
    public function listForSeller(Seller $seller, ?string $search, int $perPage = 15, $filters = []): LengthAwarePaginator
    {
        $query = Store::query()
            ->with(['markets:id,code,name', 'locations' => fn ($q) => $q->orderByDesc('is_default')->orderBy('name')])
            ->where('seller_id', $seller->id)
            ->orderByDesc('id');

        if (!empty($filters['visibility_status'])) {
            $query->where('visibility_status', $filters['visibility_status']);
        }
        if (!empty($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }

        if ($search !== null && trim($search) !== '') {
            $q = trim($search);
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%$q%")
                    ->orWhere('description', 'like', "%$q%")
                    ->orWhere('city', 'like', "%$q%")
                    ->orWhere('contact_number', 'like', "%$q%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a store for seller using validated request data.
     */
    public function createForSeller(StoreStoreRequest $request, Seller $seller): Store
    {
        return DB::transaction(function () use ($request, $seller) {
            $validated = $request->safe()->except('location', 'market_ids');
            $validated['seller_id'] = $seller->id;
            $validated['verification_status'] = StoreVerificationStatusEnum::NOT_APPROVED();
            $validated['visibility_status'] = StoreVisibilityStatusEnum::DRAFT();

            $store = Store::create($validated);

            $this->applyDefaultLocationAddress($store, (array) $request->input('location', []));
            $this->syncStoreReach($store, $request);

            if ($request->hasFile('store_logo')) {
                SpatieMediaService::upload($store, SpatieMediaCollectionName::STORE_LOGO());
            }
            if ($request->hasFile('store_banner')) {
                SpatieMediaService::upload($store, SpatieMediaCollectionName::STORE_BANNER());
            }

            event(new StoreCreated($store));

            return $store->load('markets:id,code,name');
        });
    }

    /**
     * Update an existing seller-owned store.
     */
    public function updateForSeller(UpdateStoreRequest $request, Store $store): Store
    {
        return DB::transaction(function () use ($request, $store) {
            $validated = collect($request->validated())
                ->except('location', 'market_ids')
                ->all();

            $store->update($validated);

            $this->syncStoreReach($store, $request);

            if ($request->hasFile('store_logo')) {
                SpatieMediaService::update($request, $store, SpatieMediaCollectionName::STORE_LOGO());
            }
            if ($request->hasFile('store_banner')) {
                SpatieMediaService::update($request, $store, SpatieMediaCollectionName::STORE_BANNER());
            }

            event(new StoreUpdated($store));

            return $store->load('markets:id,code,name');
        });
    }

    /**
     * Apply the submitted ship-from address onto the store's auto-seeded default
     * location. Mirrors StoreController::applyDefaultLocationAddress — the store
     * itself no longer carries an address.
     */
    protected function applyDefaultLocationAddress(Store $store, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $location = $store->locations()->where('is_default', true)->first();
        if (!$location) {
            return;
        }

        $countryName = $data['country_name'] ?? null;
        if (blank($countryName) && !empty($data['country_iso2'])) {
            $countryName = Country::where('iso2', $data['country_iso2'])->value('name');
        }

        $location->fill([
            'name'         => filled($data['name'] ?? null) ? $data['name'] : $location->name,
            'address_1'    => $data['address_1'] ?? null,
            'address_2'    => $data['address_2'] ?? null,
            'city'         => $data['city'] ?? null,
            'state'        => $data['state'] ?? null,
            'country_iso2' => $data['country_iso2'] ?? null,
            'country_name' => $countryName,
            'pin_code'     => $data['pin_code'] ?? null,
            'latitude'     => $data['latitude'] ?? null,
            'longitude'    => $data['longitude'] ?? null,
        ])->save();
    }

    /**
     * Sync the store's market reach from `market_ids[]`. Ships-to countries are
     * derived from shipping-zone coverage, never set here.
     */
    protected function syncStoreReach(Store $store, $request): void
    {
        if (!$request->has('market_ids')) {
            return;
        }

        $marketIds = collect((array) $request->input('market_ids', []))
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $store->markets()->sync($marketIds);
    }

    /**
     * Get any blockers preventing the store from being deleted.
     *
     * @return array<string> List of translated blocker descriptions
     */
    public function getDeleteBlockers(Store $store): array
    {
        $blockers = [];

        $openOrders = OrderItem::where(function ($query) use ($store) {
            $query->where('store_id', $store->id)
                ->orWhereHas('sellerOrderItem.sellerOrder.storeLocation', function ($sq) use ($store) {
                    $sq->where('store_id', $store->id);
                });
        })
            ->where('status', OrderItemStatusEnum::ACTIVE())
            ->exists();

        $openReturns = OrderItemReturn::where('store_id', $store->id)
            ->whereIn('return_status', [
                OrderItemReturnStatusEnum::REQUESTED(),
                OrderItemReturnStatusEnum::APPROVED(),
                OrderItemReturnStatusEnum::IN_TRANSIT(),
                OrderItemReturnStatusEnum::RECEIVED(),
            ])
            ->exists();

        if ($openOrders || $openReturns) {
            $blockers[] = __('labels.orders');
        }

        return $blockers;
    }

    public function deleteForSeller(Store $store): void
    {
        $blockers = $this->getDeleteBlockers($store);
        if (!empty($blockers)) {
            throw new \RuntimeException(__('labels.store_delete_blocked', ['items' => implode(', ', $blockers)]));
        }

        DB::transaction(function () use ($store) {
            $store->delete();
            $store->productVariants()->delete();
        });
    }


    /**
     * Ensure the given store belongs to the seller; throw if not found/owned.
     */
    public function findOwnedOrFail(Seller $seller, int $id): Store
    {
        $store = Store::findOrFail($id);
        if ((int)$store->seller_id !== (int)$seller->id) {
            throw new ModelNotFoundException('Store not found');
        }
        return $store;
    }

    /**
     * Get available stores for given zone IDs with optional search and pagination.
     *
     * @param array $zoneIds Array of zone IDs to filter available stores
     * @param string|null $search Optional search term to filter stores by name, description, address, or seller name
     * @param int $perPage Number of results per page
     * @return LengthAwarePaginator Paginated list of available stores
     */
    public function getAvailableStoresByZoneIds(array $zoneIds, ?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $zoneIds = array_values(array_unique(array_filter(array_map('intval', $zoneIds))));

        $query = $this->baseAvailabilityQuery($zoneIds);

        if (!empty($search)) {
            $this->applySearch($query, $search);
        }

        $query->orderBy('name');

        return $query->paginate($perPage);
    }

    /**
     * Build base store query constrained by verification/visibility status and zone availability.
     *
     * A store is considered available if it is approved and visible in the specified zones.
     */
    protected function baseAvailabilityQuery(array $zoneIds): Builder
    {
        $storeQuery = Store::query()
            ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
            ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE());
        if (!empty($zoneIds)) {
            $storeQuery->whereHas('zones', function ($q) use ($zoneIds) {
                $q->whereIn('delivery_zones.id', $zoneIds);
            });
        }
        return $storeQuery;
    }

    /**
     * Apply simple search on the store query.
     * Searches across store name, description, address, and seller name.
     */
    protected function applySearch(Builder $query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->orWhere('address', 'LIKE', "%{$search}%")
                ->orWhereHas('seller.user', function ($sq) use ($search) {
                    $sq->where('name', 'LIKE', "%{$search}%");
                });
        });
    }

    /**
     * Create an empty paginator.
     */
    protected function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}

