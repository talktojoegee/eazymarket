<?php

namespace App\Policies;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\User;
use App\Models\WatchBuyReel;
use App\Traits\ChecksPermissions;

class WatchBuyReelPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->permitted($user, SellerPermissionEnum::WATCH_BUY_REEL_VIEW());
    }

    public function view(User $user, WatchBuyReel $reel): bool
    {
        return $this->ownedBySeller($user, $reel)
            && $this->permitted($user, SellerPermissionEnum::WATCH_BUY_REEL_VIEW());
    }

    public function create(User $user): bool
    {
        return $this->permitted($user, SellerPermissionEnum::WATCH_BUY_REEL_CREATE());
    }

    public function update(User $user, WatchBuyReel $reel): bool
    {
        return $this->ownedBySeller($user, $reel)
            && $this->permitted($user, SellerPermissionEnum::WATCH_BUY_REEL_EDIT());
    }

    public function delete(User $user, WatchBuyReel $reel): bool
    {
        return $this->ownedBySeller($user, $reel)
            && $this->permitted($user, SellerPermissionEnum::WATCH_BUY_REEL_DELETE());
    }

    public function publish(User $user, WatchBuyReel $reel): bool
    {
        return $this->ownedBySeller($user, $reel)
            && $this->permitted($user, SellerPermissionEnum::WATCH_BUY_REEL_PUBLISH());
    }

    public function viewEligibleProducts(User $user): bool
    {
        return $user->seller() !== null && (
            $user->hasRole(DefaultSystemRolesEnum::SELLER())
            || $this->hasAnyPermission([
                SellerPermissionEnum::WATCH_BUY_REEL_VIEW(),
                SellerPermissionEnum::WATCH_BUY_STATUS_VIEW(),
                SellerPermissionEnum::WATCH_BUY_STATUS_CREATE(),
            ])
        );
    }

    private function permitted(User $user, string $permission): bool
    {
        return $user->seller() !== null
            && ($user->hasRole(DefaultSystemRolesEnum::SELLER()) || $this->hasPermission($permission));
    }

    private function ownedBySeller(User $user, WatchBuyReel $reel): bool
    {
        return $user->seller() !== null && (int) $reel->profile?->seller_id === (int) $user->seller()->id;
    }
}
