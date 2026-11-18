<?php

namespace App\Traits;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Types\Api\ApiResponseType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

trait ChecksPermissions
{
    use PanelAware;
    /**
     * Check if the authenticated user has the given permission.
     *
     * @param string $permission
     * @return bool
     */
    protected function hasPermission(string $permission): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        if ($this->isAdminSuperAdmin($user) || $this->isSellerOwner($user)) {
            return true;
        }
        try {
            return $user && $user->hasPermissionTo($permission);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if the authenticated user has any of the given permissions.
     *
     * @param array $permissions
     * @return bool
     */
    protected function hasAnyPermission(array $permissions): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        if ($this->isAdminSuperAdmin($user) || $this->isSellerOwner($user)) {
            return true;
        }
        return $user->hasAnyPermission($permissions);
    }

    /**
     * Check if the authenticated user has all of the given permissions.
     *
     * @param array $permissions
     * @return bool
     */
    protected function hasAllPermissions(array $permissions): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        if ($this->isAdminSuperAdmin($user) || $this->isSellerOwner($user)) {
            return true;
        }
        return $user->hasAllPermissions($permissions);
    }

    protected function isAdminSuperAdmin($user): bool
    {
        if (!$user || $this->getPanel() !== 'admin') {
            return false;
        }

        return $user->hasRole(
            DefaultSystemRolesEnum::SUPER_ADMIN(),
            GuardNameEnum::ADMIN->value
        );
    }

    protected function isSellerOwner($user): bool
    {
        if (!$user || $this->getPanel() !== 'seller') {
            return false;
        }

        try {
            return (bool) (session('impersonating_as_seller', false)
                || $user->hasRole(DefaultSystemRolesEnum::SELLER(), GuardNameEnum::SELLER->value)
                || $user->hasRole(DefaultSystemRolesEnum::SELLER())
                || \App\Models\Seller::where('user_id', $user->id)->exists());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Return a JSON response for unauthorized access.
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'You do not have permission to perform this action.'): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(success: false, message: $message, data: [], status: 403);
    }
}
