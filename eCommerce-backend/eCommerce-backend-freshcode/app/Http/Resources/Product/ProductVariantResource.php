<?php

namespace App\Http\Resources\Product;

use App\Models\Market;
use App\Models\Product;
use App\Models\StoreAddonItem;
use App\Support\PricePresenter;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProductVariantResource extends JsonResource
{
    public function toArray($request): array
    {
        $attributes = [];

        if ($this->relationLoaded('attributes')) {
            foreach ($this->attributes as $attribute) {
                if ($attribute->attribute && $attribute->attributeValue) {
                    $attributeSlug = $attribute->attribute->slug;
                    $attributeValue = $attribute->attributeValue->title;
                    $attributes[$attributeSlug] = $attributeValue;
                }
            }
        } else {
            $this->load(['attributes.attribute', 'attributes.attributeValue']);

            foreach ($this->attributes as $attribute) {
                if ($attribute->attribute && $attribute->attributeValue) {
                    $attributeSlug = $attribute->attribute->slug;
                    $attributeValue = $attribute->attributeValue->title;
                    $attributes[$attributeSlug] = $attributeValue;
                }
            }
        }

        $cartItem = $this->isInUserCart();

        $firstMapping = $this->pickMarketScopedMapping($request);
        $storeId = $firstMapping?->store_id;
        $stock = $this->stockForStore($storeId);

        $fulfillmentLocation = $this->fulfillmentLocationForStore($storeId);

        $market = $request?->attributes->get('market');
        $price        = $market instanceof Market
            ? PricePresenter::forMarket((float) $this->price, $market)
            : PricePresenter::forCurrent((float) $this->price);
        $specialPrice = $market instanceof Market
            ? PricePresenter::forMarket((float) $this->special_price, $market)
            : PricePresenter::forCurrent((float) $this->special_price);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'image' => $this->image ?? '',
            'weight' => (float)$this->weight ?? 0,
            'height' => (float)$this->height ?? 0,
            'breadth' => (float)$this->breadth ?? 0,
            'length' => (float)$this->length ?? 0,
            'availability' => $this->availability,
            'cart_item' => $cartItem,
            'barcode' => $this->barcode,
            'is_default' => $this->is_default,
            'price' => $price['price'],
            'price_base' => $price['price_base'],
            'special_price' => $specialPrice['price'],
            'special_price_base' => $specialPrice['price_base'],
            'currency_code' => $price['currency_code'],
            'currency_symbol' => $price['currency_symbol'],
            'fx_rate' => $price['fx_rate'],
            'fx_fallback' => (bool) ($price['fx_fallback'] ?? false),
            'formatted' => $price['formatted'] ?? null,
            'formatted_special_price' => $specialPrice['formatted'] ?? null,
            'format' => $price['format'] ?? null,
            'store_id' => $storeId,
            'store_slug' => $firstMapping?->store->slug ?? null,
            'store_name' => $firstMapping?->store->name ?? null,
            'store_location_id' => $fulfillmentLocation?->id,
            'store_location_name' => $fulfillmentLocation?->name,
            'stock' => $stock,
            'is_in_stock' => $stock === null || $stock > 0,
            'sku' => $this->sku,
            'attributes' => $attributes,
            'addon_groups' => $this->formatAddonGroups(),
        ];
    }

    /**
     * Group the eager-loaded storeVariantAddons rows into addon groups with their items.
     */
    protected function formatAddonGroups(): array
    {
        if (! $this->relationLoaded('storeVariantAddons') || $this->storeVariantAddons->isEmpty()) {
            return [];
        }
        $storeId = $this->pickMarketScopedMapping(request())?->store_id;
        if (empty($storeId)) {
            return [];
        }

        $rows = $this->storeVariantAddons
            ->where('store_id', $storeId)
            ->filter(fn ($row) => $row->addonGroup && $row->addonItem);

        if ($rows->isEmpty()) {
            return [];
        }

        $inventory = $this->lookupStoreAddonItems(
            (int) $storeId,
            $rows->pluck('addon_item_id')->filter()->unique()->values()->all()
        );

        return $rows
            ->filter(function ($row) use ($inventory) {
                $inv = $inventory->get((int) $row->addon_item_id);

                if ($inv) {
                    return (bool) $inv->is_available;
                }

                $item = $row->addonItem;

                return ($item->is_available ?? true);
            })
            ->groupBy('addon_group_id')
            ->map(function ($groupRows) use ($inventory) {
                $group = $groupRows->first()->addonGroup;

                $market = request()?->attributes->get('market');

                return [
                    'id'             => $group->id,
                    'uuid'           => $group->uuid,
                    'title'          => $group->title,
                    'slug'           => $group->slug,
                    'selection_type' => $group->selection_type?->value ?? (is_string($group->selection_type) ? $group->selection_type : null),
                    'is_required'    => (bool) $group->is_required,
                    'sort_order'     => (int) $group->sort_order,
                    'items' => $groupRows
                        ->sortBy(fn ($row) => (int) ($row->addonItem->sort_order ?? 0))
                        ->values()
                        ->map(function ($row) use ($inventory, $market) {
                            $item = $row->addonItem;
                            $inv  = $inventory->get((int) $row->addon_item_id);

                            $basePrice = $inv && $inv->price !== null
                                ? (float) $inv->price
                                : (float) ($item->price ?? 0);

                            $shape = $market instanceof Market
                                ? PricePresenter::forMarket($basePrice, $market)
                                : PricePresenter::forCurrent($basePrice);

                            return [
                                'id'           => $item->id,
                                'uuid'         => $item->uuid,
                                'title'        => $item->title,
                                'slug'         => $item->slug,
                                'indicator'    => $item->indicator?->value ?? (is_string($item->indicator) ? $item->indicator : null),
                                'price'        => (float) $shape['price'],
                                'price_base'   => $basePrice,
                                'stock'        => $inv ? (int) $inv->stock : 0,
                                'is_available' => $inv
                                    ? (bool) $inv->is_available
                                    : ($item->is_available ?? true),
                            ];
                        })
                        ->toArray(),
                ];
            })
            ->sortBy('sort_order')
            ->values()
            ->toArray();
    }

    /**
     * Batch-fetch StoreAddonItem rows for the (store, addon_item) pairs we need, cached per request.
     *
     * Avoids an N+1 when a variant exposes many addon items, and keeps `StoreProductVariantAddon`
     * free of a compound-key relation that Laravel's `belongsTo` cannot model safely.
     *
     * @param array<int,int> $addonItemIds
     * @return Collection<int, StoreAddonItem>  Keyed by addon_item_id.
     */
    protected function lookupStoreAddonItems(int $storeId, array $addonItemIds): Collection
    {
        static $cache = [];

        if (empty($addonItemIds)) {
            return collect();
        }

        $missingIds = [];
        foreach ($addonItemIds as $id) {
            if (! array_key_exists($storeId.':'.(int) $id, $cache)) {
                $missingIds[] = (int) $id;
            }
        }

        if ($missingIds) {
            $rows = StoreAddonItem::query()
                ->where('store_id', $storeId)
                ->whereIn('addon_item_id', $missingIds)
                ->get();

            foreach ($rows as $row) {
                $cache[$storeId.':'.(int) $row->addon_item_id] = $row;
            }

            foreach ($missingIds as $id) {
                $key = $storeId.':'.$id;
                $cache[$key] ??= null;
            }
        }

        return collect($addonItemIds)
            ->mapWithKeys(function ($id) use ($cache, $storeId) {
                $row = $cache[$storeId.':'.(int) $id] ?? null;
                return $row ? [(int) $id => $row] : [];
            });
    }

    /**
     * Pick the storeProductVariants mapping row to render off — re-filtered by
     * the request's market so a variant always surfaces a store enabled in the
     * caller's market.
     *
     * - If the upstream eager-load already scoped storeProductVariants to that
     *   market's store ids, this is a no-op (every row passes).
     * - If the upstream eager-load forgot to constrain (e.g. an ad-hoc
     *   ->with(['variants', ...]) elsewhere), this still keeps cross-market
     *   stores out of `store_id` / `stock` / addon-group rendering.
     *
     * The market storeIds list is cached on the request attribute bag so a
     * paginated response with many variants doesn't re-issue the store query.
     */
    protected function pickMarketScopedMapping(?Request $request)
    {
        $market = $request?->attributes->get('market');

        if (! $market instanceof Market) {
            return $this->storeProductVariants->first();
        }

        $cacheKey = 'market_store_ids.' . $market->id;
        $storeIds = $request->attributes->get($cacheKey);
        if ($storeIds === null) {
            $storeIds = Product::getStoreIdsForMarket($market);
            $request->attributes->set($cacheKey, $storeIds);
        }

        if (empty($storeIds)) {
            return null;
        }

        return $this->storeProductVariants
            ->whereIn('store_id', $storeIds)
            ->first();
    }
}
