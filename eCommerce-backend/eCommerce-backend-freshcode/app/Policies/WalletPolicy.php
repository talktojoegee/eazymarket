<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\User;
use App\Models\Wallet;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;

class WalletPolicy
{
    use ChecksPermissions, PanelAware;

    /**
     * Determine whether the user can view the wallet pages/listing.
     */
    public function viewAny(User $user): bool
    {
        try {
            if ($this->getPanel() === 'admin') {
                return $this->hasPermission(AdminPermissionEnum::CUSTOMER_WALLET_VIEW());
            }

            if ($user->seller() == null) {
                return false;
            }

            if (
                $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                $this->hasPermission(SellerPermissionEnum::WALLET_VIEW())
            ) {
                return true;
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }
    public function processDeposit(User $user): bool
    {
        return $this->getPanel() === 'admin'
            && $this->hasPermission(AdminPermissionEnum::CUSTOMER_WALLET_PROCESS());
    }

    public function viewWithdrawal(User $user): bool
    {
        try {
            if ($user->seller() == null) {
                return false;
            }

            if (
                $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                $this->hasPermission(SellerPermissionEnum::WITHDRAWAL_VIEW())
            ) {
                return true;
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    public function requestWithdrawal(User $user): bool
    {
        try {
            if ($user->seller() == null) {
                return false;
            }

            if (
                $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                $this->hasPermission(SellerPermissionEnum::WITHDRAWAL_REQUEST())
            ) {
                return true;
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }


    /**
     * Determine whether the user can view a specific wallet model.
     */
    public function view(User $user, Wallet $wallet): bool
    {
        try {
            if ($user->seller() == null) {
                return false;
            }

            if (
                $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                $this->hasPermission(SellerPermissionEnum::WALLET_VIEW())
            ) {
                return true;
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }
}
