<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\TaxRate;
use App\Models\User;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;

class TaxRatePolicy
{
    use ChecksPermissions, PanelAware;

    public function viewAny(User $user): bool
    {
        try {
            if (method_exists($this, 'getPanel') ? $this->getPanel() == 'admin' : true) {
                return $this->hasPermission(AdminPermissionEnum::TAX_RATE_VIEW());
            }
            return $user->hasRole(DefaultSystemRolesEnum::SELLER())
                || $this->hasPermission(SellerPermissionEnum::TAX_RATE_VIEW());
        } catch (\Exception) {
            return false;
        }
    }

    public function view(User $user, TaxRate $taxRate): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->allows(
            AdminPermissionEnum::TAX_RATE_CREATE(),
            '',
            $user
        );
    }

    public function update(User $user, TaxRate $taxRate): bool
    {
        return $this->allows(
            AdminPermissionEnum::TAX_RATE_EDIT(),
            '',
            $user
        );
    }

    public function delete(User $user, TaxRate $taxRate): bool
    {
        return $this->allows(
            AdminPermissionEnum::TAX_RATE_DELETE(),
           '',
            $user
        );
    }

    private function allows(string $adminPermission, string $sellerPermission, User $user): bool
    {
        try {
            if ($this->getPanel() === 'admin') {
                return $this->hasPermission($adminPermission);
            }

            return $user->hasRole(DefaultSystemRolesEnum::SELLER())
                || $this->hasPermission($sellerPermission);
        } catch (\Exception) {
            return false;
        }
    }
}
