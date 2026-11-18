<?php

namespace App\Services\Search\Providers;

use App\Enums\DefaultSystemRolesEnum;
use App\Models\User;
use App\Services\Search\Contracts\SearchProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class MenuSearchProvider implements SearchProvider
{
    public function search(string $query, string $panel, User $user, int $limit = 5): array
    {
        $results = [];
        $query = Str::lower(trim($query));
        $singular = Str::lower(Str::singular($query));

        foreach (config("menu.{$panel}", []) as $key => $item) {
            if (($item['type'] ?? null) === 'section') {
                continue;
            }

            $children = is_array($item['route'] ?? null) ? $item['route'] : [$item];
            foreach ($children as $childKey => $child) {
                if (! is_array($child) || ! $this->canSee($user, $panel, $child['permission'] ?? $item['permission'] ?? null)) {
                    continue;
                }

                $routeName = $child['sub_route'] ?? $child['route'] ?? null;
                if (! is_string($routeName) || ! Route::has($routeName)) {
                    continue;
                }

                $titleKey = $child['sub_title'] ?? $child['title'] ?? $item['title'] ?? '';
                $title = __($titleKey);
                $parent = __($item['title'] ?? '');
                $searchable = implode(' ', [$title, $parent, $titleKey, $key, is_string($childKey) ? $childKey : '']);
                if (! $this->matches($searchable, $query, $singular)) {
                    continue;
                }

                try {
                    $url = route($routeName, $child['route_param'] ?? $child['params'] ?? []);
                } catch (\Throwable) {
                    continue;
                }

                $rawIcon = $item['icon'] ?? 'ti-compass';
                $results[] = [
                    'type' => 'navigation', 'category' => 'Navigation', 'badge' => 'MENU',
                    'badge_class' => 'bg-indigo-lt',
                    'icon' => str_starts_with($rawIcon, 'ti-') ? "ti {$rawIcon}" : $rawIcon,
                    'title' => $title, 'subtitle' => $parent === $title ? ucfirst($panel).' Panel' : $parent,
                    'url' => $url,
                ];

                if (count($results) >= $limit) {
                    return $results;
                }
            }
        }

        return $results;
    }

    private function matches(string $text, string $query, string $singular): bool
    {
        $text = Str::lower(str_replace(['labels.', '_', '-'], ' ', $text));

        return str_contains($text, $query) || str_contains($text, $singular);
    }

    private function canSee(User $user, string $panel, ?string $permission): bool
    {
        if ($permission === null || $permission === '') {
            return true;
        }

        if (($panel === 'admin' && $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN()))
            || ($panel === 'seller' && $user->hasRole(DefaultSystemRolesEnum::SELLER()))) {
            return true;
        }

        try {
            return $user->hasPermissionTo($permission);
        } catch (\Throwable) {
            return false;
        }
    }
}
