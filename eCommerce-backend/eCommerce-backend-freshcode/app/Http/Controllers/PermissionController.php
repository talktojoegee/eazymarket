<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\SellerPermissionEnum;
use App\Exceptions\SellerNotFoundException;
use App\Http\Requests\Permission\StorePermissionRequest;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    use ChecksPermissions, PanelAware, AuthorizesRequests;

    protected bool $editPermission = false;

    public function __construct()
    {
        $enum = $this->getPanel() === 'seller' ? SellerPermissionEnum::class : AdminPermissionEnum::class;
        $user = auth()->user();
        $this->editPermission = $this->hasPermission($enum::ROLE_PERMISSIONS_EDIT()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
    }

    public function index($role): View
    {
        try {
            if ($this->getPanel() === 'seller') {
                $user = auth()->user();
                $seller = $user->seller();
                if (!empty($seller)) {
                    $role = Role::where('name', $role)
                        ->where('guard_name', GuardNameEnum::SELLER())
                        ->where('team_id', $seller->id)
                        ->firstOrFail();
                }
            } else {
                $role = Role::where('name', $role)->where('guard_name', GuardNameEnum::ADMIN())->firstOrFail();
            }
            $this->authorize('viewPermission', $role);
            if ($this->getPanel() === 'seller') {
                $permissionModule = SellerPermissionEnum::groupedPermissions();
            } else {
                $permissionModule = AdminPermissionEnum::groupedPermissions();
            }
            $rolePermissions = $role->permissions->pluck('name')->toArray();

            $editPermission = $this->editPermission;

            $user = auth()->user();
            $isOwnRole = $user?->hasRole($role->name, $role->guard_name) ?? false;
            $isSuperAdmin = $user?->hasRole(\App\Enums\DefaultSystemRolesEnum::SUPER_ADMIN()) ?? false;
            $canEditThisRole = $editPermission && (!$isOwnRole || $isSuperAdmin);

            return view($this->panelView('permissions.index'), compact('role', 'permissionModule', 'rolePermissions', 'editPermission', 'canEditThisRole'));
        } catch (AuthorizationException) {
            abort(403, "Unauthorized action.");
        } catch (Exception $e) {
            abort(404, "Seller not found.");
        }
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        try {
            $request->validated();
            if ($this->getPanel() === 'seller') {
                $user = auth()->user();
                $seller = $user->seller();
                if (!empty($seller)) {
                    $role = Role::where('name', $request->role)
                        ->where('guard_name', GuardNameEnum::SELLER())
                        ->where('team_id', $seller->id)
                        ->firstOrFail();
                }
            } else {
                $role = Role::where('name', $request->role)->where('guard_name', GuardNameEnum::ADMIN())->firstOrFail();
            }
            $this->authorize('storePermission', $role);

            if (in_array(strtolower((string) $role->name), ['super admin', 'customer', 'seller'], true)) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    __('labels.cannot_modify_role'),
                    null,
                    422
                );
            }
            $role->syncPermissions($this->assignablePermissions($request->permissions ?? []));

            return ApiResponseType::sendJsonResponse(
                true,
                "Permissions updated successfully.",
                $role->permissions
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.unauthorized_access') ?? "Unauthorized.",
                null,
                403
            );
        } catch (Exception $e) {
            Log::error('Error updating permissions: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.something_went_wrong'),
                null,
                500
            );
        }
    }


    private function assignablePermissions(array $requested): array
    {
        $user = auth()->user();

        if ($this->isAdminSuperAdmin($user) || $this->isSellerOwner($user)) {
            return $requested;
        }

        $held = $user?->getAllPermissions()->pluck('name')->all() ?? [];

        return array_values(array_intersect($requested, $held));
    }
}
