<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Models\User;
use App\Traits\ChecksPermissions;

class SplashScreenPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->getPanel() === 'admin';
    }

    public function update(User $user): bool
    {
        try {
            return $this->hasPermission(AdminPermissionEnum::SPLASH_SCREEN_EDIT());
        } catch (\Throwable) {
            return false;
        }
    }
}
