<?php

namespace App\Services;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Route;

final class PanelNavigationService
{
    public function visibleMenu(User $user, string $panel, ?bool $isImpersonating = null): array
    {
        $isImpersonating ??= (bool) session('impersonating_as_seller', false);
        $visible = [];
        $pendingSection = null;

        foreach (config("menu.{$panel}", []) as $key => $item) {
            if (($item['type'] ?? null) === 'section') {
                $pendingSection = [$key, $item];

                continue;
            }

            if ($panel === 'seller' && $key === 'subscriptions' && ! Setting::isSubscriptionEnabled()) {
                continue;
            }

            $filteredItem = $this->visibleItem($item, $user, $panel, $isImpersonating);
            if ($filteredItem === null) {
                continue;
            }

            if ($pendingSection !== null) {
                [$sectionKey, $section] = $pendingSection;
                $visible[$sectionKey] = $section;
                $pendingSection = null;
            }

            $visible[$key] = $filteredItem;
        }

        return $visible;
    }

    public function landingRouteName(User $user, string $panel, ?bool $isImpersonating = null): string
    {
        return $this->landingDestination($user, $panel, $isImpersonating)['route'];
    }

    public function landingUrl(User $user, string $panel, ?bool $isImpersonating = null): string
    {
        $destination = $this->landingDestination($user, $panel, $isImpersonating);

        return route($destination['route'], $destination['parameters']);
    }

    private function landingDestination(User $user, string $panel, ?bool $isImpersonating): array
    {
        foreach ($this->visibleMenu($user, $panel, $isImpersonating) as $item) {
            if (($item['type'] ?? null) === 'section') {
                continue;
            }

            if (is_string($item['route'] ?? null)) {
                $routeName = $item['route'];
                if (! str_ends_with($routeName, '.logout') && Route::has($routeName)) {
                    return [
                        'route' => $routeName,
                        'parameters' => $item['route_param'] ?? [],
                    ];
                }

                continue;
            }

            foreach ($item['route'] ?? [] as $child) {
                $routeName = $child['sub_route'] ?? null;
                if (is_string($routeName) && Route::has($routeName)) {
                    return [
                        'route' => $routeName,
                        'parameters' => $child['route_param'] ?? [],
                    ];
                }
            }
        }

        $fallback = "{$panel}.no-permissions";

        return [
            'route' => Route::has($fallback) ? $fallback : "{$panel}.dashboard",
            'parameters' => [],
        ];
    }

    private function visibleItem(array $item, User $user, string $panel, bool $isImpersonating): ?array
    {
        if (is_string($item['route'] ?? null)) {
            return $this->canSee($user, $panel, $item['permission'] ?? null, $isImpersonating)
                ? $item
                : null;
        }

        if (! is_array($item['route'] ?? null)) {
            return null;
        }

        $item['route'] = array_filter(
            $item['route'],
            fn ($child) => is_array($child)
                && $this->canSee($user, $panel, $child['permission'] ?? null, $isImpersonating)
        );

        return $item['route'] === [] ? null : $item;
    }

    private function canSee(User $user, string $panel, ?string $permission, bool $isImpersonating): bool
    {
        if ($permission === null || $permission === '') {
            return true;
        }

        $definedPermissions = match ($panel) {
            'admin' => AdminPermissionEnum::values(),
            'seller' => SellerPermissionEnum::values(),
            default => [],
        };

        if (! in_array($permission, $definedPermissions, true)) {
            return false;
        }

        if ($panel === 'admin' && $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())) {
            return true;
        }

        if ($panel === 'seller' && ($isImpersonating || $user->hasRole(DefaultSystemRolesEnum::SELLER()) || \App\Models\Seller::where('user_id', $user->id)->exists())) {
            return true;
        }

        try {
            if ($user->hasPermissionTo($permission)) {
                return true;
            }

            if (str_starts_with($permission, 'report.') && $user->hasPermissionTo('report.view')) {
                return true;
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }
}
