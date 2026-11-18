<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Models\User;
use App\Traits\ChecksPermissions;

class LandingPagePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission(AdminPermissionEnum::SETTING_SELLER_LANDING_VIEW());
    }

    public function updateAny(User $user): bool
    {
        return $this->hasPermission(AdminPermissionEnum::SETTING_SELLER_LANDING_EDIT());
    }
}
