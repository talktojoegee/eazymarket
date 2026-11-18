<?php

namespace App\Services\Search\Providers;

use App\Models\User;
use App\Services\MarketService;
use App\Services\Search\Contracts\SearchProvider;

class CustomerSearchProvider implements SearchProvider
{
    public function search(string $query, string $panel, User $user, int $limit = 5): array
    {
        if ($panel !== 'admin') {
            return [];
        }

        $term = addcslashes($query, '%_\\');
        $marketId = app(MarketService::class)->adminSelectedId();

        return User::query()
            ->whereHas('markets', fn ($market) => $market->whereKey($marketId))
            ->where(fn ($builder) => $builder->whereNull('access_panel')->orWhere('access_panel', 'web'))
            ->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%"))
            ->limit($limit)->get()
            ->map(fn (User $customer) => [
                'type' => 'customer', 'category' => 'Customers', 'badge' => 'CUSTOMER',
                'badge_class' => 'bg-purple-lt', 'icon' => 'ti ti-user',
                'title' => $customer->name ?: 'Customer #'.$customer->id,
                'subtitle' => 'Email: '.($customer->email ?: 'N/A').' • Phone: '.($customer->mobile ?: 'N/A'),
                'url' => route('admin.customers.show', $customer->id),
            ])->all();
    }
}
