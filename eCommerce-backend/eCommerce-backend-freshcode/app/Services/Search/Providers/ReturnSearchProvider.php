<?php

namespace App\Services\Search\Providers;

use App\Models\OrderItemReturn;
use App\Models\User;
use App\Services\MarketService;
use App\Services\Search\Contracts\SearchProvider;
use App\Services\SellerStoreService;

class ReturnSearchProvider implements SearchProvider
{
    public function search(string $query, string $panel, User $user, int $limit = 5): array
    {
        $term = addcslashes($query, '%_\\');
        $returns = OrderItemReturn::query()
            ->where(fn ($builder) => $builder
                ->where('id', 'like', "%{$term}%")
                ->orWhere('reason', 'like', "%{$term}%")
                ->orWhereHas('orderItem', fn ($item) => $item
                    ->where('title', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%"))
                ->orWhereHas('order', fn ($order) => $order
                    ->where('id', 'like', "%{$term}%")
                    ->orWhere('uuid', 'like', "%{$term}%")));

        if ($panel === 'seller') {
            $sellerId = $user->seller()?->id;
            $storeId = app(SellerStoreService::class)->selectedId();
            if (! $sellerId || ! $storeId) {
                return [];
            }
            $returns->where('seller_id', $sellerId)->where('store_id', $storeId);
        } else {
            $marketId = app(MarketService::class)->adminSelectedId();
            $returns->whereHas('order', fn ($order) => $order->where('market_id', $marketId));
        }

        return $returns->with(['order', 'orderItem'])->limit($limit)->get()
            ->map(function (OrderItemReturn $return) use ($panel) {
                $status = $return->return_status;
                $status = is_object($status) && property_exists($status, 'value') ? $status->value : (string) $status;
                $url = $panel === 'seller'
                    ? route('seller.orders.return_review', $return->id)
                    : route('admin.orders.show', $return->order_id);

                return [
                    'type' => 'return', 'category' => 'Return Requests', 'badge' => 'RETURN',
                    'badge_class' => 'bg-orange-lt', 'icon' => 'ti ti-truck-return',
                    'title' => 'Return #'.$return->id.' (Order #'.$return->order_id.')',
                    'subtitle' => 'Status: '.ucfirst(str_replace('_', ' ', $status ?: 'pending')).' • '.($return->orderItem?->title ?? 'Returned item'),
                    'url' => $url,
                ];
            })->all();
    }
}
