<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Market;
use App\Models\Product;
use App\Models\SharedList;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Support\PricePresenter;
use Illuminate\Support\Str;

class SharedListService
{
    public function __construct(private readonly CartService $cartService)
    {
    }

    public function createCartShare(User $user): ?SharedList
    {
        $cart = Cart::where('user_id', $user->id)
            ->with(['items' => fn ($query) => $query->where('save_for_later', '0')->with('addons')])
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return null;
        }

        $items = $cart->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'store_id' => $item->store_id,
            'quantity' => $item->quantity,
            'addons' => $item->addons->map(fn ($addon) => [
                'addon_group_id' => $addon->addon_group_id,
                'addon_item_id' => $addon->addon_item_id,
            ])->values()->all(),
        ])->values()->all();

        return $this->create($user, $items);
    }

    public function preview(SharedList $sharedList): array
    {
        $ids = collect($sharedList->items)->pluck('product_id')->filter()->unique();
        $products = Product::whereIn('id', $ids)->with('variants')->get()->keyBy('id');
        $market = request()->attributes->get('market');

        $items = collect($sharedList->items)->map(function (array $item) use ($products, $market) {
            $product = $products->get($item['product_id']);
            $variant = $product?->variants->firstWhere('id', $item['product_variant_id']);
            $store = empty($item['product_variant_id'])
                ? Store::find($item['store_id'])
                : StoreProductVariant::with('store')
                    ->where('product_variant_id', $item['product_variant_id'])
                    ->where('store_id', $item['store_id'])
                    ->first()?->store;

            if (! $product || (! empty($item['product_variant_id']) && ! $variant) || ! $store) {
                return null;
            }

            $price = $variant
                ? ($market instanceof Market
                    ? PricePresenter::forMarket((float) $variant->price, $market)
                    : PricePresenter::forCurrent((float) $variant->price))
                : null;

            return [
                'product_id' => $product->id,
                'title' => $product->title,
                'slug' => $product->slug,
                'image' => $product->main_image,
                'variant' => $variant ? [
                    'id' => $variant->id,
                    'title' => $variant->title,
                    'image' => $variant->image,
                    'price' => $price['price'] ?? null,
                    'price_base' => $price['price_base'] ?? null,
                    'special_price' => $variant ? ($market instanceof Market
                        ? PricePresenter::forMarket((float) $variant->special_price, $market)['price']
                        : PricePresenter::forCurrent((float) $variant->special_price)['price']) : null,
                    'currency_code' => $price['currency_code'] ?? null,
                    'currency_symbol' => $price['currency_symbol'] ?? null,
                ] : null,
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                ],
                'quantity' => $item['quantity'] ?? 1,
            ];
        })->filter()->values();

        return [
            'items' => $items,
            'items_count' => $items->count(),
            'created_at' => $sharedList->created_at?->toIso8601String(),
        ];
    }

    public function import(User $user, SharedList $sharedList): array
    {
        return $this->importCart($user, $sharedList);
    }

    private function importCart(User $user, SharedList $sharedList): array
    {
        $imported = 0;
        $failed = [];

        foreach ($sharedList->items as $item) {
            $result = $this->cartService->addToCart($user, [
                'product_variant_id' => $item['product_variant_id'],
                'store_id' => $item['store_id'],
                'quantity' => $item['quantity'] ?? 1,
                'addons' => $item['addons'] ?? [],
            ]);

            if ($result['success']) {
                $imported++;
            } else {
                $failed[] = [
                    'product_id' => $item['product_id'],
                    'message' => $result['message'],
                ];
            }
        }

        return [
            'success' => $imported > 0 || $failed === [],
            'message' => $imported > 0
                ? 'labels.shared_cart_added_successfully'
                : 'labels.shared_cart_import_failed',
            'imported_count' => $imported,
            'failed_items' => $failed,
        ];
    }

    private function create(User $user, array $items): SharedList
    {
        return SharedList::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'items' => $items,
            'expired_at' => now()->addDays(7),
        ]);
    }
}
