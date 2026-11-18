<?php

namespace App\Support;

use App\Enums\DefaultSystemRolesEnum;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Throwable;

final class TourCatalog
{
    public function visibleFor(User $user, string $panel): array
    {
        if (! in_array($panel, ['admin', 'seller'], true)) {
            return ['groups' => [], 'tours' => []];
        }

        $groups = [];
        $tours = [];

        foreach (config("tours.{$panel}", []) as $group) {
            $visibleGuides = [];

            foreach ($group['guides'] ?? [] as $action => $guide) {
                $segments = $this->visibleSegments($user, $panel, $guide);

                if ($segments === []) {
                    continue;
                }

                $visibleGuides[$action] = [
                    'action' => $action,
                    'title' => $guide['title'],
                    'description' => $guide['description'],
                    'icon' => $guide['icon'],
                    'segment_count' => count($segments),
                ];

                $tours[$action] = [
                    'action' => $action,
                    'title' => $guide['title'],
                    'menu' => $guide['menu'],
                    'segments' => $segments,
                ];
            }

            if ($visibleGuides !== []) {
                $groups[] = [
                    'title' => $group['title'],
                    'guides' => $visibleGuides,
                ];
            }
        }

        return ['groups' => $groups, 'tours' => $tours];
    }

    private function visibleSegments(User $user, string $panel, array $guide): array
    {
        $segments = [];

        foreach ($guide['segments'] ?? [] as $segment) {
            if (! $this->canAccess($user, $panel, $segment['permission'] ?? null)) {
                continue;
            }

            if (! Route::has($segment['route'])) {
                continue;
            }

            $steps = array_map(function (array $step) use ($guide): array {
                $step['element'] = str_replace('{menu}', $guide['menu'], $step['element']);

                return $step;
            }, $segment['steps']);

            $segments[] = [
                'id' => $segment['id'],
                'url' => route($segment['route'], $segment['params'] ?? []),
                'steps' => $steps,
            ];
        }

        return $segments;
    }

    private function canAccess(User $user, string $panel, ?string $permission): bool
    {
        if ($permission === null) {
            return true;
        }

        if ($panel === 'seller' && session('impersonating_as_seller', false)) {
            return true;
        }

        $primaryRole = $panel === 'admin'
            ? DefaultSystemRolesEnum::SUPER_ADMIN()
            : DefaultSystemRolesEnum::SELLER();

        try {
            return $user->hasRole($primaryRole) || $user->hasPermissionTo($permission);
        } catch (Throwable) {
            return false;
        }
    }
}
