<?php

namespace App\Services\Search\Providers;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\MarketService;
use App\Services\Search\Contracts\SearchProvider;
use App\Services\SellerStoreService;

class OrderSearchProvider implements SearchProvider
{
    public function search(string $query, string $panel, User $user, int $limit = 5): array
    {
        $term = addcslashes($query, '%_\\');

        if ($panel === 'seller') {
            return $this->sellerResults($term, $user, $limit);
        }

        $marketId = app(MarketService::class)->adminSelectedId();

        return Order::query()
            ->where('market_id', $marketId)
            ->where(fn ($builder) => $builder
                ->where('id', 'like', "%{$term}%")
                ->orWhere('uuid', 'like', "%{$term}%")
                ->orWhere('billing_name', 'like', "%{$term}%")
                ->orWhere('shipping_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhereHas('items', fn ($item) => $item
                    ->where('title', 'like', "%{$term}%")
                    ->orWhere('variant_title', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")))
            ->with('items:id,order_id,title')
            ->limit($limit)
            ->get()
            ->map(fn (Order $order) => $this->format($order, 'admin', $order->status, $order->items->first()?->title))
            ->all();
    }

    /** @return array<int, array<string, string>> */
    private function sellerResults(string $term, User $user, int $limit): array
    {
        $sellerId = $user->seller()?->id;
        $storeId = app(SellerStoreService::class)->selectedId();
        if (! $sellerId || ! $storeId) {
            return [];
        }

        return SellerOrder::query()
            ->where('seller_id', $sellerId)
            ->whereHas('items.orderItem', fn ($item) => $item->where('store_id', $storeId))
            ->where(fn ($builder) => $builder
                ->where('order_id', 'like', "%{$term}%")
                ->orWhereHas('order', fn ($order) => $order
                    ->where('uuid', 'like', "%{$term}%")
                    ->orWhere('billing_name', 'like', "%{$term}%")
                    ->orWhere('shipping_name', 'like', "%{$term}%"))
                ->orWhereHas('items.orderItem', fn ($item) => $item
                    ->where('store_id', $storeId)
                    ->where(fn ($match) => $match
                        ->where('title', 'like', "%{$term}%")
                        ->orWhere('variant_title', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%"))))
            ->with(['order', 'items.orderItem'])
            ->limit($limit)
            ->get()
            ->filter(fn (SellerOrder $sellerOrder) => $sellerOrder->order !== null)
            ->map(fn (SellerOrder $sellerOrder) => $this->format(
                $sellerOrder->order,
                'seller',
                $sellerOrder->status,
                $sellerOrder->items->first()?->orderItem?->title,
                $sellerOrder->id,
            ))->values()->all();
    }

    /** @return array<string, string> */
    private function format(Order $order, string $panel, mixed $status, ?string $item, ?int $routeId = null): array
    {
        $status = is_object($status) && property_exists($status, 'value') ? $status->value : (string) $status;

        return [
            'type' => 'order', 'category' => 'Orders', 'badge' => 'ORDER',
            'badge_class' => 'bg-green-lt', 'icon' => 'ti ti-shopping-cart',
            'title' => 'Order #'.$order->id,
            'subtitle' => 'Status: '.ucfirst(str_replace('_', ' ', $status ?: 'pending')).($item ? ' • Item: '.$item : ''),
            'url' => route("{$panel}.orders.show", $routeId ?? $order->id),
        ];
    }
}
