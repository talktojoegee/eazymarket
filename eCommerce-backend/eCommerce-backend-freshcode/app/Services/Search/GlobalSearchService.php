<?php

namespace App\Services\Search;

use App\Enums\DefaultSystemRolesEnum;
use App\Models\User;
use App\Services\Search\Contracts\SearchProvider;
use App\Services\Search\Providers\CategorySearchProvider;
use App\Services\Search\Providers\CustomerSearchProvider;
use App\Services\Search\Providers\MenuSearchProvider;
use App\Services\Search\Providers\OrderSearchProvider;
use App\Services\Search\Providers\ProductSearchProvider;
use App\Services\Search\Providers\ReturnSearchProvider;
use App\Services\Search\Providers\StoreSearchProvider;

class GlobalSearchService
{
    /** @var array<int, class-string<SearchProvider>> */
    private array $providers = [
        MenuSearchProvider::class,
        CategorySearchProvider::class,
        ProductSearchProvider::class,
        OrderSearchProvider::class,
        ReturnSearchProvider::class,
        CustomerSearchProvider::class,
        StoreSearchProvider::class,
    ];

    /** @var array<class-string<SearchProvider>, string|array{admin: string, seller: string}> */
    private array $permissions = [
        CategorySearchProvider::class => 'category.view',
        ProductSearchProvider::class => 'product.view',
        OrderSearchProvider::class => ['admin' => 'orders.view', 'seller' => 'order.view'],
        ReturnSearchProvider::class => ['admin' => 'orders.view', 'seller' => 'return.view'],
        CustomerSearchProvider::class => 'customer.view',
        StoreSearchProvider::class => 'store.view',
    ];

    /** @return array{query: string, total: int, results: array<string, array<int, array<string, string>>>} */
    public function search(string $query, string $panel, User $user, int $limitPerProvider = 5): array
    {
        $groups = [];
        $total = 0;

        foreach ($this->providers as $providerClass) {
            if (! $this->canSearch($providerClass, $panel, $user)) {
                continue;
            }

            $results = app($providerClass)->search($query, $panel, $user, $limitPerProvider);
            if ($results === []) {
                continue;
            }

            $groups[$results[0]['category']] = $results;
            $total += count($results);
        }

        return ['query' => $query, 'total' => $total, 'results' => $groups];
    }

    /** @param class-string<SearchProvider> $providerClass */
    private function canSearch(string $providerClass, string $panel, User $user): bool
    {
        $permission = $this->permissions[$providerClass] ?? null;
        if (is_array($permission)) {
            $permission = $permission[$panel] ?? null;
        }
        if ($permission === null) {
            return true;
        }

        if (($panel === 'admin' && $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN()))
            || ($panel === 'seller' && (session('impersonating_as_seller', false)
                || $user->hasRole(DefaultSystemRolesEnum::SELLER())))) {
            return true;
        }

        try {
            return $user->hasPermissionTo($permission);
        } catch (\Throwable) {
            return false;
        }
    }
}
