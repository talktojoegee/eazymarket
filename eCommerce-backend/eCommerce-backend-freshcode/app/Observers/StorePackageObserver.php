<?php

namespace App\Observers;

use App\Models\StorePackage;
use Illuminate\Support\Facades\DB;

/**
 * Guards the "every store has exactly one default package" invariant.
 *
 *  - Block deleting the row that's currently is_default — promote another
 *    first (mirrors StoreLocationObserver / ShippingProfileObserver pattern).
 *  - Block unsetting is_default directly. Promotion to a new default goes
 *    through StorePackageService::promoteToDefault() which atomically flips
 *    the old + new in one transaction.
 *  - On save, mirror the current default's dims into the legacy
 *    `stores.default_*_cm` columns so older readers (AWB form pre-fill code
 *    that hasn't been updated) keep working.
 */
class StorePackageObserver
{
    public function deleting(StorePackage $package): bool
    {
        if ($package->is_default) {
            throw new \DomainException(
                "Cannot delete the store's default package — promote another package to default first."
            );
        }
        return true;
    }

    public function updating(StorePackage $package): bool
    {
        if (
            $package->isDirty('is_default')
            && $package->getOriginal('is_default')
            && !$package->is_default
        ) {
            throw new \DomainException(
                "Cannot unset the default flag — set another package as default first."
            );
        }
        return true;
    }

    public function saved(StorePackage $package): void
    {
        if (!$package->is_default) {
            return;
        }

        DB::table('stores')->where('id', $package->store_id)->update([
            'default_weight_kg'  => $package->empty_weight_kg,
            'default_length_cm'  => $package->length_cm,
            'default_breadth_cm' => $package->breadth_cm,
            'default_height_cm'  => $package->height_cm,
        ]);
    }
}
