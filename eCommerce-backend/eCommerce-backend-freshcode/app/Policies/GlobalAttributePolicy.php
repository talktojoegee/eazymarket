<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\GlobalProductAttribute;
use App\Models\User;
use App\Traits\ChecksPermissions;

class GlobalAttributePolicy
{
    use ChecksPermissions;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        try {
            if ($this->getPanel() === 'admin') {
                return $this->hasPermission(AdminPermissionEnum::PRODUCT_VIEW());
            }

            if ($user->seller() == null) {
                return false;
            }

            if (
                $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                $this->hasPermission(SellerPermissionEnum::ATTRIBUTE_VIEW())
            ) {
                return true;
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GlobalProductAttribute $attribute): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        try {
            if ($this->getPanel() === 'admin') {
                return $this->hasPermission(AdminPermissionEnum::PRODUCT_CREATE());
            }

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
    public function update(User $user, GlobalProductAttribute $attribute): bool
    {
        try {
            if ($this->getPanel() === 'admin') {
                return $this->hasPermission(AdminPermissionEnum::PRODUCT_EDIT());
            }

            if ($user->seller() == null) {
                return false;
            }

            if ($user->seller()->id == $attribute->seller_id) {
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
    public function delete(User $user, GlobalProductAttribute $attribute): bool
    {
        try {
            if ($this->getPanel() === 'admin') {
                return $this->hasPermission(AdminPermissionEnum::PRODUCT_DELETE());
            }

            if ($user->seller() == null) {
                return false;
            }

            if ($user->seller()->id == $attribute->seller_id) {
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
    public function restore(User $user, GlobalProductAttribute $attribute): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GlobalProductAttribute $attribute): bool
    {
        return false;
    }
}
