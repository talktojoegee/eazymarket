<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\User;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;

class OrderReturnPolicy
{
    use ChecksPermissions, PanelAware;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($this->getPanel() === 'admin') {
            return $this->hasPermission(AdminPermissionEnum::RETURN_VIEW());
        }

        if (
            $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
            $this->hasPermission(SellerPermissionEnum::RETURN_VIEW())
        ) {
            return true;
        }

        return false;
    }

    public function decide(User $user, $order): bool
    {
        try {
            if ($user->seller() == null) {
                return false;
            }

            if ($user->seller()->id == $order->seller_id) {
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::RETURN_DECIDE())
                ) {
                    return true;
                }
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }
}
