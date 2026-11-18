<?php

namespace App\Services;

use App\Models\StoreProductVariant;
class KitStoreAvailabilityService
{
    public function storesForVariant(int $variantId, int $sellerId, array $allowedStoreIds = []): array
    {
        return StoreProductVariant::query()
            ->where('product_variant_id', $variantId)
            ->whereHas('store', fn ($query) => $query->where('seller_id', $sellerId))
            ->when($allowedStoreIds !== [], fn ($query) => $query->whereIn('store_id', $allowedStoreIds))
            ->pluck('store_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function eligibleStoreIds(array $variantIds, int $sellerId, array $allowedStoreIds = []): array
    {
        $variantIds = collect($variantIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($variantIds->isEmpty()) {
            return [];
        }

        return StoreProductVariant::query()
            ->select('store_id')
            ->whereIn('product_variant_id', $variantIds)
            ->whereHas('store', fn ($query) => $query->where('seller_id', $sellerId))
            ->when($allowedStoreIds !== [], fn ($query) => $query->whereIn('store_id', $allowedStoreIds))
            ->groupBy('store_id')
            ->havingRaw('COUNT(DISTINCT product_variant_id) = ?', [$variantIds->count()])
            ->pluck('store_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
