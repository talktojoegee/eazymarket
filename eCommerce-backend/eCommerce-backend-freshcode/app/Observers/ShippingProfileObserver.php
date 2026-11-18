<?php

namespace App\Observers;

use App\Models\ShippingProfile;

/**
 * Guards Shopify's default-profile invariants:
 *  - The default profile ("General profile") cannot be deleted.
 *  - is_default cannot be flipped off — only switched to another profile.
 *
 * Switching defaults is intentionally NOT supported via toggle. To move the
 * default, an admin must create the new profile, then use a dedicated service
 * call that flips both rows in a transaction. Catching it here as a hard stop
 * surfaces accidental writes that would otherwise corrupt the invariant.
 */
class ShippingProfileObserver
{
    public function deleting(ShippingProfile $profile): bool
    {
        if ($profile->is_default) {
            throw new \DomainException(
                "Cannot delete the store's default shipping profile ('General profile')."
            );
        }
        return true;
    }

    public function updating(ShippingProfile $profile): bool
    {
        if (
            $profile->isDirty('is_default')
            && $profile->getOriginal('is_default')
            && !$profile->is_default
        ) {
            throw new \DomainException(
                "Cannot unset the default flag — set another profile as default first."
            );
        }
        return true;
    }
}
