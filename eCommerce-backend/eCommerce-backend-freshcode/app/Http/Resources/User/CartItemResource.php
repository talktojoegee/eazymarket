<?php

namespace App\Http\Resources\User;

use App\Enums\Product\ProductAttachmentModeEnum;
use App\Models\Market;
use App\Models\Review;
use App\Models\StoreProductVariantAddon;
use App\Services\DeliveryZoneService;
use App\Support\PricePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variant = $this->variant;
        $basePrice        = (float) ($variant?->price ?? 0);
        $baseSpecialPrice = (float) ($variant?->special_price ?? 0);

        $market = $request?->attributes->get('market');
        $priceShape        = $market instanceof Market
            ? PricePresenter::forMarket($basePrice, $market)
            : PricePresenter::forCurrent($basePrice);
        $specialPriceShape = $market instanceof Market
            ? PricePresenter::forMarket($baseSpecialPrice, $market)
            : PricePresenter::forCurrent($baseSpecialPrice);

        $price        = (float) $priceShape['price'];
        $specialPrice = (float) $specialPriceShape['price'];

        $stock = $variant?->stockForStore((int) $this->store_id);
        $reviews = Review::scopeProductRatingStats($this->product->id);
        if (isset($request->latitude) && isset($request->longitude)) {
            $this->product->user_latitude = $request->latitude;
            $this->product->user_longitude = $request->longitude;
        }

        $addonsTotalBase = $this->resolveAddonsTotal();
        $addonsTotalShape = $market instanceof Market
            ? PricePresenter::forMarket($addonsTotalBase, $market)
            : PricePresenter::forCurrent($addonsTotalBase);
        $addonsTotal = (float) $addonsTotalShape['price'];
        $quantity = max(1, (int)$this->quantity);
        $effectiveUnitPrice = $specialPrice > 0 ? $specialPrice : $price;

        return [
            'id' => $this->id,
            'cart_id' => $this->cart_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'store_id' => $this->store_id,
            'quantity' => $this->quantity,
            'save_for_later' => $this->save_for_later,
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->title,
                'slug' => $this->product->slug,
                'category' => $this->product?->category?->title ?? null,
                'brand' => $this->product?->brand?->title ?? null,
                'minimum_order_quantity' => $this->product->minimum_order_quantity,
                'quantity_step_size' => $this->product->quantity_step_size,
                'total_allowed_quantity' => $this->product->total_allowed_quantity,
                'is_attachment_required' => $this->product->is_attachment_required == "1" ? true : false,
                'allow_cod' => (string)($this->product->allow_cod ?? '1') === '1',
                'attachment_mode' => $this->product->attachment_mode ?? ProductAttachmentModeEnum::REQUIRED(),
                'image' => $this->product->main_image ?? null,
                'estimated_delivery_time' => $this->product->estimated_delivery_time,
                'image_fit' => $this->product->image_fit,
                'ratings' => $reviews['average_rating'] ?? 0,
                'rating_count' => $reviews['total_reviews'] ?? 0,
            ],
            'variant' => [
                'id' => $this->variant->id,
                'title' => $this->variant->title,
                'slug' => $this->variant->slug,
                'image' => $this->variant->image ?? null,
//                'variant' => $this->variant?->attributes
//                        ->mapWithKeys(function ($item) {
//                            return [
//                                $item->attribute->title => $item->attributeValue->title,
//                            ];
//                        }) ?? [],
                'variant' => $this->variant->attributes
                        ->pluck('attributeValue.title') ?? [],
                'price' => $price,
                'price_base' => $basePrice,
                'special_price' => $specialPrice,
                'special_price_base' => $baseSpecialPrice,
                'currency_code' => $priceShape['currency_code'],
                'currency_symbol' => $priceShape['currency_symbol'],
                'fx_rate' => $priceShape['fx_rate'],
                'fx_fallback' => (bool) ($priceShape['fx_fallback'] ?? false),
                'formatted' => $priceShape['formatted'] ?? null,
                'formatted_special_price' => $specialPriceShape['formatted'] ?? null,
                'format' => $priceShape['format'] ?? null,
                'stock' => $stock,
                'is_in_stock' => $stock === null || $stock > 0,
                'sku' => $this->variant->sku,
                'is_addons' => StoreProductVariantAddon::where('store_id', $this->store_id)
                    ->where('product_variant_id', $this->product_variant_id)
                    ->exists(),
            ],
            'store' => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
                'total_products' => $this->store->product_count ?? 0,
            ],
            'delivery_eta' => isset($this->resource->eta_min) || isset($this->resource->eta_max)
                ? [
                    'min'  => $this->resource->eta_min,
                    'max'  => $this->resource->eta_max,
                    'unit' => $this->resource->eta_unit ?? 'days',
                ]
                : null,
            'addons' => $this->formatAddons(),
            'addons_total' => round($addonsTotal, 2),
            'addons_total_base' => round($addonsTotalBase, 2),
            'total' => round(($effectiveUnitPrice * $quantity) + $addonsTotal, 2),
            'total_item_price' => round($price + ($addonsTotal / $quantity), 2),
            'total_item_special_price' => round($specialPrice + ($addonsTotal / $quantity), 2),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Flatten the CartItemAddon rows into a scalar-friendly list.
     *
     * Shape mirrors the catalog/order-side addon payload so clients can render
     * the "as-configured" summary (group title + item title + price) without
     * additional lookups. Uses the `addons` relation which `CartService`
     * eager-loads via `items.addons.addonGroup` / `items.addons.addonItem` —
     * falling back to a lazy load in the unlikely case it wasn't loaded.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function formatAddons(): array
    {
        $addons = $this->resource->relationLoaded('addons')
            ? $this->resource->getRelation('addons')
            : $this->resource->addons()->with(['addonGroup', 'addonItem'])->get();

        if (!$addons || $addons->isEmpty()) {
            return [];
        }

        $market = request()?->attributes->get('market');

        return $addons->map(function ($addon) use ($market) {
            $group = $addon->addonGroup;
            $item = $addon->addonItem;

            $basePrice = (float) $addon->price;
            $shape     = $market instanceof Market
                ? PricePresenter::forMarket($basePrice, $market)
                : PricePresenter::forCurrent($basePrice);

            return [
                'id' => $addon->id,
                'uuid' => $addon->uuid,
                'addon_group_id' => $addon->addon_group_id,
                'addon_item_id' => $addon->addon_item_id,
                'price' => (float) $shape['price'],
                'price_base' => $basePrice,
                'group' => $group ? [
                    'id' => $group->id,
                    'title' => $group->title,
                    'selection_type' => $group->selection_type?->value,
                    'is_required' => (bool)$group->is_required,
                ] : null,
                'item' => $item ? [
                    'id' => $item->id,
                    'title' => $item->title,
                    'indicator' => $item->indicator?->value,
                ] : null,
            ];
        })->values()->all();
    }

    /**
     * Resolve the line-total addon amount for this cart item.
     *
     * `CartService::calculateCartTotals()` writes `addons_total` onto each
     * item when it runs (every time the cart is rendered through `getCart`).
     * If the resource is consumed outside that flow, compute it on the fly
     * from the eager-loaded `addons` relation so the field is always present.
     */
    protected function resolveAddonsTotal(): float
    {
        if (isset($this->resource->addons_total)) {
            return (float)$this->resource->addons_total;
        }

        $addons = $this->resource->relationLoaded('addons')
            ? $this->resource->getRelation('addons')
            : $this->resource->addons()->get();

        if (!$addons || $addons->isEmpty()) {
            return 0.0;
        }

        return (float)((int)$this->quantity) * (float)$addons->sum(fn($a) => (float)$a->price);
    }
}
