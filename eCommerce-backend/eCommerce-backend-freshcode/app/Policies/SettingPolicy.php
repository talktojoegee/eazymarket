<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Enums\SettingTypeEnum;
use App\Models\Setting;
use App\Models\User;
use App\Traits\ChecksPermissions;
use Illuminate\Auth\Access\Response;

class SettingPolicy
{
    use ChecksPermissions;
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $viewPerms = array_map(
            fn(string $type) => $this->permissionFor($type, 'view'),
            SettingTypeEnum::values()
        );
        return $this->hasAnyPermission($viewPerms);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Setting $setting): bool
    {
        $type = is_string($setting->variable) ? $setting->variable : (string)$setting->variable;
        return $this->hasPermission($this->permissionFor($type, 'view'));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user): bool
    {
        return false;
    }

    /**
     * Module-wise view authorization using the type string.
     */
    public function viewSetting(User $user, string $type): bool
    {
        return $this->hasPermission($this->permissionFor($type, 'view'));
    }

    /**
     * Module-wise update authorization using the type string.
     */
    public function updateSetting(User $user, string $type): bool
    {
        return $this->hasPermission($this->permissionFor($type, 'edit'));
    }

    /**
     * Build the permission name for a setting type and action.
     */
    private function permissionFor(string $type, string $action): string
    {
        if (!in_array($type, SettingTypeEnum::values(), true)) {
            return '__forbidden__';
        }
        $action = strtolower($action) == 'edit' ? 'edit' : 'view';
        return "setting.$type.$action";
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Setting $setting): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Setting $setting): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Setting $setting): bool
    {
        return false;
    }
}
