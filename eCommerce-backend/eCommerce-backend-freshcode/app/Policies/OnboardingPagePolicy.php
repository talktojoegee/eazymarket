<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Models\OnboardingPage;
use App\Models\User;
use App\Traits\ChecksPermissions;

class OnboardingPagePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->getPanel() === 'admin';
    }

    public function view(User $user, OnboardingPage $onboardingPage): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        try {
            return $this->hasPermission(AdminPermissionEnum::ONBOARDING_CREATE());
        } catch (\Throwable) {
            return false;
        }
    }

    public function updateAny(User $user): bool
    {
        try {
            return $this->hasPermission(AdminPermissionEnum::ONBOARDING_EDIT());
        } catch (\Throwable) {
            return false;
        }
    }

    public function update(User $user, OnboardingPage $onboardingPage): bool
    {
        return $this->updateAny($user);
    }

    public function deleteAny(User $user): bool
    {
        try {
            return $this->hasPermission(AdminPermissionEnum::ONBOARDING_DELETE());
        } catch (\Throwable) {
            return false;
        }
    }

    public function delete(User $user, OnboardingPage $onboardingPage): bool
    {
        return $this->deleteAny($user);
    }
}
