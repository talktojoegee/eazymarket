<?php

namespace App\Policies;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\User;
use App\Models\WatchBuyProfile;
use App\Traits\ChecksPermissions;

class WatchBuyProfilePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->permitted($user, SellerPermissionEnum::WATCH_BUY_PROFILE_VIEW());
    }

    public function view(User $user, WatchBuyProfile $profile): bool
    {
        return $this->ownedBySeller($user, $profile)
            && $this->permitted($user, SellerPermissionEnum::WATCH_BUY_PROFILE_VIEW());
    }

    public function create(User $user): bool
    {
        return $this->permitted($user, SellerPermissionEnum::WATCH_BUY_PROFILE_EDIT());
    }

    public function update(User $user, WatchBuyProfile $profile): bool
    {
        return $this->ownedBySeller($user, $profile)
            && $this->permitted($user, SellerPermissionEnum::WATCH_BUY_PROFILE_EDIT());
    }

    private function permitted(User $user, string $permission): bool
    {
        return $user->seller() !== null
            && ($user->hasRole(DefaultSystemRolesEnum::SELLER()) || $this->hasPermission($permission));
    }

    private function ownedBySeller(User $user, WatchBuyProfile $profile): bool
    {
        return $user->seller() !== null && (int) $profile->seller_id === (int) $user->seller()->id;
    }
}
