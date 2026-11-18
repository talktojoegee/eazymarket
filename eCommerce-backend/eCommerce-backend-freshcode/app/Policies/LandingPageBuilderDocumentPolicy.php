<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Models\LandingPageBuilderDocument;
use App\Models\User;
use App\Traits\ChecksPermissions;

class LandingPageBuilderDocumentPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission(AdminPermissionEnum::LANDING_PAGE_BUILDER_VIEW());
    }

    public function updateAny(User $user): bool
    {
        return $this->hasPermission(AdminPermissionEnum::LANDING_PAGE_BUILDER_EDIT());
    }

    public function create(User $user): bool
    {
        return $this->updateAny($user);
    }

    public function update(User $user, LandingPageBuilderDocument $document): bool
    {
        return $this->updateAny($user);
    }

    public function delete(User $user, LandingPageBuilderDocument $document): bool
    {
        return $this->updateAny($user);
    }
}
