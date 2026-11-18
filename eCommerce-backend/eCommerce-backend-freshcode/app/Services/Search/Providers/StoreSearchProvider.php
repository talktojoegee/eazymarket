<?php

namespace App\Services\Search\Providers;

use App\Models\Store;
use App\Models\User;
use App\Services\MarketService;
use App\Services\Search\Contracts\SearchProvider;

class StoreSearchProvider implements SearchProvider
{
    public function search(string $query, string $panel, User $user, int $limit = 5): array
    {
        if ($panel !== 'admin') {
            return [];
        }

        $term = addcslashes($query, '%_\\');
        $marketId = app(MarketService::class)->adminSelectedId();

        return Store::query()
            ->whereHas('markets', fn ($market) => $market->whereKey($marketId))
            ->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%")
                ->orWhere('contact_email', 'like', "%{$term}%")
                ->orWhere('contact_number', 'like', "%{$term}%"))
            ->limit($limit)->get()
            ->map(fn (Store $store) => [
                'type' => 'store', 'category' => 'Sellers / Stores', 'badge' => 'STORE',
                'badge_class' => 'bg-teal-lt', 'icon' => 'ti ti-building-store',
                'title' => $store->name,
                'subtitle' => 'Seller ID: '.($store->seller_id ?: 'N/A').' • '.$store->contact_email,
                'url' => route('admin.sellers.store.show', $store->id),
            ])->all();
    }
}
