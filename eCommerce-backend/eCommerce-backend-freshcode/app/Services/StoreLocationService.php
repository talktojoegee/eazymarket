<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreLocation;
use Illuminate\Support\Facades\DB;

/**
 * StoreLocation CRUD + invariants.
 *
 * Two invariants this service owns:
 *   1. Every Store has at least one location (StoreObserver seeds the first
 *      "Shop location"; this service can only delete non-default rows so the
 *      count never drops below 1).
 *   2. Exactly one location per store has is_default=true (`promoteToDefault`
 *      flips both rows in a single transaction; the StoreLocationObserver
 *      blocks accidental unsetting).
 */
class StoreLocationService
{
    /**
     * Create a new location under the given store. Booleans are normalised so
     * the form's "unset checkbox = absent key" semantics don't accidentally
     * flip an existing toggle on.
     */
    public function create(Store $store, array $data): StoreLocation
    {
        $payload = $this->normalisePayload($data);
        $payload['is_default'] = false;

        return $store->locations()->create($payload);
    }

    public function update(StoreLocation $location, array $data): StoreLocation
    {
        $payload = $this->normalisePayload($data);
        unset($payload['is_default']);

        $location->fill($payload)->save();
        return $location->refresh();
    }

    /**
     * Promote the given location to the store's default. Demotes whatever is
     * currently default in the same transaction so the invariant holds at
     * every commit point. The Observer's "can't unset is_default" guard is
     * bypassed safely here because we set a new default in the same query.
     */
    public function promoteToDefault(StoreLocation $location): StoreLocation
    {
        if ($location->is_default) {
            return $location;
        }

        DB::transaction(function () use ($location) {
            StoreLocation::where('store_id', $location->store_id)
                ->where('id', '!=', $location->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            StoreLocation::where('id', $location->id)
                ->update(['is_default' => true]);
        });

        return $location->refresh();
    }

    /**
     * Delete a non-default location. The StoreLocationObserver throws when
     * is_default=true so the rule is enforced even if a caller bypasses this
     * service.
     */
    public function delete(StoreLocation $location): void
    {
        $location->delete();
    }

    /**
     * Map request input to model fillable, coercing checkbox values.
     * "Unsubmitted" checkboxes (browser drops them entirely) default to false.
     */
    protected function normalisePayload(array $data): array
    {
        $bools = [
            'is_physical_storefront',
            'fulfills_online_orders',
            'offers_local_delivery',
            'offers_local_pickup',
            'offers_pos',
            'is_active',
        ];

        foreach ($bools as $key) {
            $data[$key] = array_key_exists($key, $data)
                ? (bool) $data[$key]
                : false;
        }

        $data['status'] = $data['is_active'] ? 'active' : 'inactive';

        return $data;
    }
}
