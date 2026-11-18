<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\Store;
use App\Models\User;
use App\Traits\ChecksPermissions;

class StorePolicy
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
        if ($this->hasPermission(AdminPermissionEnum::STORE_VIEW())) {
            return true;
        }
        return $this->hasPermission(SellerPermissionEnum::STORE_VIEW());
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Store $store): bool
    {
        try {
            if ($this->hasPermission(AdminPermissionEnum::STORE_VIEW())) {
                return true;
            }

            if ($user->seller() !== null && $user->seller()->id == $store->seller_id) {
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::STORE_VIEW())
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
                $this->hasPermission(SellerPermissionEnum::STORE_CREATE())
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
    public function update(User $user, Store $store): bool
    {
        try {
            if ($user->seller() == null) {
                return false;
            }

            if ($user->seller()->id == $store->seller_id) {
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::STORE_EDIT())
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
    public function delete(User $user, Store $store): bool
    {
        try {
            if ($user->seller() == null) {
                return false;
            }

            if ($user->seller()->id == $store->seller_id) {
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::STORE_DELETE())
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
    public function restore(User $user, Store $store): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Store $store): bool
    {
        return false;
    }

    public function verifyStore(User $user): bool
    {
        return $this->hasPermission(AdminPermissionEnum::STORE_VERIFY());
    }
}
