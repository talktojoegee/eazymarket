<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\TaxClass;
use App\Models\User;
use App\Traits\ChecksPermissions;

class TaxClassPolicy
{
    use ChecksPermissions;

    /**
     * Determine whether the user can view any tax classes.
     */
    public function viewAny(User $user): bool
    {
         try {
            if (method_exists($this, 'getPanel') ? $this->getPanel() == 'admin' : true) {
                return $this->hasPermission(AdminPermissionEnum::TAX_CLASS_VIEW());
            }
            return $user->hasRole(DefaultSystemRolesEnum::SELLER())
                || $this->hasPermission(SellerPermissionEnum::TAX_RATE_VIEW());
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can view the tax class.
     */
    public function view(User $user, TaxClass $taxClass): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create tax classes.
     */
    public function create(User $user): bool
    {
        try {
            return $this->hasPermission(AdminPermissionEnum::TAX_CLASS_CREATE());
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can update the tax class.
     */
    public function update(User $user, TaxClass $taxClass): bool
    {
        try {
            return $this->hasPermission(AdminPermissionEnum::TAX_CLASS_EDIT());
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can delete the tax class.
     */
    public function delete(User $user, TaxClass $taxClass): bool
    {
        try {
            return $this->hasPermission(AdminPermissionEnum::TAX_CLASS_DELETE());
        } catch (\Exception) {
            return false;
        }
    }
}
