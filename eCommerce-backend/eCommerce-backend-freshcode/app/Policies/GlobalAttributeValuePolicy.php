<?php

namespace App\Policies;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\GlobalProductAttributeValue;
use App\Models\User;
use App\Traits\ChecksPermissions;

class GlobalAttributeValuePolicy
{
    use ChecksPermissions;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole(DefaultSystemRolesEnum::SELLER())) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GlobalProductAttributeValue $attributeValue): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        try {
            if ($user->seller() == null) {
                return false;
            }

            if (
                $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                $this->hasPermission(SellerPermissionEnum::ATTRIBUTE_CREATE())
            ) {
                return true;
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GlobalProductAttributeValue $attributeValue): bool
    {
        try {
            if ($user->seller() == null) {
                return false;
            }

            if ($user->seller()->id == $attributeValue->attribute->seller_id) {
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::ATTRIBUTE_EDIT())
                ) {
                    return true;
                }
            }

            return false;

        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GlobalProductAttributeValue $attributeValue): bool
    {
        try {
            if ($user->seller() == null) {
                return false;
            }

            if ($user->seller()->id == $attributeValue->attribute->seller_id) {
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::ATTRIBUTE_DELETE())
                ) {
                    return true;
                }
            }

            return false;

        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, GlobalProductAttributeValue $attributeValue): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GlobalProductAttributeValue $attributeValue): bool
    {
        return false;
    }
}
