<?php

namespace App\Policies;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\User;
use App\Models\WatchBuyStatus;
use App\Traits\ChecksPermissions;

class WatchBuyStatusPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->permitted($user, SellerPermissionEnum::WATCH_BUY_STATUS_VIEW());
    }

    public function create(User $user): bool
    {
        return $this->permitted($user, SellerPermissionEnum::WATCH_BUY_STATUS_CREATE());
    }

    public function delete(User $user, WatchBuyStatus $status): bool
    {
        return $this->ownedBySeller($user, $status)
            && $this->permitted($user, SellerPermissionEnum::WATCH_BUY_STATUS_DELETE());
    }

    private function permitted(User $user, string $permission): bool
    {
        return $user->seller() !== null
            && ($user->hasRole(DefaultSystemRolesEnum::SELLER()) || $this->hasPermission($permission));
    }

    private function ownedBySeller(User $user, WatchBuyStatus $status): bool
    {
        return $user->seller() !== null && (int) $status->profile?->seller_id === (int) $user->seller()->id;
    }
}
