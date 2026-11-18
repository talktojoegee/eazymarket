<?php

namespace App\Observers;

use App\Jobs\SyncShiprocketPickupLocation;
use App\Models\StoreLocation;
use App\Services\Shipping\ShiprocketClient;
use Illuminate\Support\Facades\DB;

/**
 * Guards Shopify's default-location invariants on store_locations:
 *  - The default location ("Shop location") cannot be deleted.
 *  - is_default cannot be flipped off — a different location has to be
 *    promoted to default first.
 *
 * Promotion of a new default is done explicitly via a service call that
 * flips both rows in a transaction; the observer catches accidental writes
 * that would otherwise leave a store with zero default locations.
 *
 * Also drives automatic Shiprocket pickup registration: a new location, or an
 * address change on an existing one, re-registers with Shiprocket (a new pickup,
 * since Shiprocket has no update-pickup API). Runs after commit and only reacts
 * to address changes, so the sync job's own writes never re-trigger it.
 */
class StoreLocationObserver
{
    private const ADDRESS_FIELDS = ['address_1', 'address_2', 'city', 'state', 'pin_code', 'country_iso2', 'country_name'];

    public function created(StoreLocation $location): void
    {
        $this->maybeSyncShiprocket($location);
    }

    public function updated(StoreLocation $location): void
    {
        if ($location->wasChanged(self::ADDRESS_FIELDS)) {
            $this->maybeSyncShiprocket($location);
        }
    }

    private function maybeSyncShiprocket(StoreLocation $location): void
    {
        if (!$location->hasCompleteShiprocketAddress() || !$location->needsShiprocketSync()) {
            return;
        }
        if (!app(ShiprocketClient::class)->isEnabled()) {
            return;
        }

        DB::afterCommit(function () use ($location) {
            try {
                SyncShiprocketPickupLocation::dispatchSync($location->id);
            } catch (\Throwable $e) {
            }
        });
    }

    public function deleting(StoreLocation $location): bool
    {
        if ($location->is_default) {
            throw new \DomainException(
                "Cannot delete the store's default location."
            );
        }
        return true;
    }

    public function updating(StoreLocation $location): bool
    {
        if (
            $location->isDirty('is_default')
            && $location->getOriginal('is_default')
            && !$location->is_default
        ) {
            throw new \DomainException(
                "Cannot unset the default flag — promote another location to default first."
            );
        }
        return true;
    }
}
