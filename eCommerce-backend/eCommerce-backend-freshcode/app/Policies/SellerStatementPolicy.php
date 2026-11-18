<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\User;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;

class SellerStatementPolicy
{
    use ChecksPermissions, PanelAware;

    /**
     * Determine whether the user can view any seller statements/commissions pages.
     */
    public function viewAny(User $user): bool
    {
        if ($this->getPanel() === 'admin') {
            return $this->hasPermission(AdminPermissionEnum::COMMISSION_VIEW());
        }

        if (
            $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
            $this->hasPermission(SellerPermissionEnum::EARNING_VIEW())
        ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view seller statements/commissions data.
     */
    public function view(User $user): bool
    {
        if ($this->getPanel() === 'admin') {
            return $this->hasPermission(AdminPermissionEnum::COMMISSION_VIEW());
        }

        if (
            $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
            $this->hasPermission(SellerPermissionEnum::EARNING_VIEW())
        ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can process a settlement action for a single entry.
     */
    public function processSettle(User $user): bool
    {
        return $this->hasPermission(AdminPermissionEnum::COMMISSION_SETTLE());
    }

    /**
     * Determine whether the user can process settlement action for all eligible entries.
     */
    public function processSettleAll(User $user): bool
    {
        return $this->hasPermission(AdminPermissionEnum::COMMISSION_SETTLE());
    }

    /**
     * Determine whether the user can view commission history.
     */
    public function viewHistory(User $user): bool
    {
        if ($this->getPanel() === 'admin') {
            return $this->hasPermission(AdminPermissionEnum::COMMISSION_VIEW());
        }

        if (
            $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
            $this->hasPermission(SellerPermissionEnum::EARNING_VIEW())
        ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view settled commissions list.
     */
    public function viewSettled(User $user): bool
    {
        if ($this->getPanel() === 'admin') {
            return $this->hasPermission(AdminPermissionEnum::COMMISSION_VIEW());
        }

        if (
            $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
            $this->hasPermission(SellerPermissionEnum::EARNING_VIEW())
        ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view unsettled debits list.
     */
    public function viewUnsettledDebits(User $user): bool
    {
        if ($this->getPanel() === 'admin') {
            return $this->hasPermission(AdminPermissionEnum::COMMISSION_VIEW());
        }

        if (
            $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
            $this->hasPermission(SellerPermissionEnum::EARNING_VIEW())
        ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can settle a debit entry.
     */
    public function settleDebit(User $user): bool
    {
        return $this->hasPermission(AdminPermissionEnum::COMMISSION_SETTLE());
    }

    /**
     * Determine whether the user can settle all debit entries.
     */
    public function settleAllDebits(User $user): bool
    {
        return $this->hasPermission(AdminPermissionEnum::COMMISSION_SETTLE());
    }
}
