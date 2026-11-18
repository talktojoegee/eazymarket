<?php

namespace App\Services\Search\Providers;

use App\Models\Product;
use App\Models\User;
use App\Services\MarketService;
use App\Services\Search\Contracts\SearchProvider;
use App\Services\SellerStoreService;
use Illuminate\Support\Str;

class ProductSearchProvider implements SearchProvider
{
    public function search(string $query, string $panel, User $user, int $limit = 5): array
    {
        $term = addcslashes($query, '%_\\');
        $singular = addcslashes(Str::singular($query), '%_\\');
        $products = Product::query()->where(fn ($builder) => $builder
            ->where('title', 'like', "%{$term}%")
            ->orWhere('title', 'like', "%{$singular}%")
            ->orWhere('slug', 'like', "%{$term}%")
            ->orWhere('short_description', 'like', "%{$term}%")
            ->orWhereHas('variants', fn ($variant) => $variant->where('sku', 'like', "%{$term}%"))
            ->orWhereHas('categories', fn ($category) => $category->where('title', 'like', "%{$term}%")));

        if ($panel === 'seller') {
            $sellerId = $user->seller()?->id;
            $storeId = app(SellerStoreService::class)->selectedId();
            if (! $sellerId || ! $storeId) {
                return [];
            }
            $products->where('seller_id', $sellerId)
                ->whereHas('variants.storeProductVariants', fn ($variant) => $variant->where('store_id', $storeId));
        } else {
            $marketId = app(MarketService::class)->adminSelectedId();
            $products->whereHas('variants.storeProductVariants.store.markets', fn ($market) => $market->whereKey($marketId));
        }

        return $products->limit($limit)->get()->map(fn (Product $product) => [
            'type' => 'product', 'category' => 'Products', 'badge' => 'PRODUCT',
            'badge_class' => 'bg-blue-lt', 'icon' => 'ti ti-package',
            'title' => $product->title,
            'subtitle' => 'Slug: '.($product->slug ?: 'N/A').' • Type: '.ucfirst($product->type ?? 'simple'),
            'url' => route("{$panel}.products.show", $product->id),
        ])->all();
    }
}
