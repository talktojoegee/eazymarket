<?php

namespace App\Services;

use App\Enums\Payment\PaymentTypeEnum;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Log;

class OrderInventoryService
{
    public const STATUS_RESERVED = 'reserved';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_RELEASED = 'released';

    public function __construct(private StockService $stockService) {}

    public function applyOnCreation(Order $order): array
    {
        return $this->isOnline($order)
            ? $this->reserve($order)
            : $this->deductImmediate($order);
    }

    public function commit(Order $order): void
    {
        if ($order->stock_status !== self::STATUS_RESERVED) {
            return;
        }

        $order->loadMissing(['items.sellerOrderItem.sellerOrder', 'items.addons']);

        foreach ($order->items as $orderItem) {
            $result = $this->adjustOrderItemStock($order, $orderItem, 'commit');
            if (($result['success'] ?? false) === false) {
                Log::critical('Order stock could not be committed after payment capture', [
                    'order_id' => $order->id,
                    'order_item_id' => $orderItem->id,
                    'message' => $result['message'] ?? null,
                ]);

                return;
            }

            if (! $this->decrementAddonStock($order, $orderItem)) {
                Log::critical('Addon stock could not be decremented after payment capture; order needs manual reconciliation', [
                    'order_id' => $order->id,
                    'order_item_id' => $orderItem->id,
                ]);
            }
        }

        $order->stock_status = self::STATUS_COMMITTED;
        $order->save();

        $this->clearUserCart($order);
    }

    public function release(Order $order): void
    {
        if ($order->stock_status !== self::STATUS_RESERVED) {
            return;
        }

        $order->loadMissing(['items.sellerOrderItem.sellerOrder']);

        foreach ($order->items as $orderItem) {
            $this->adjustOrderItemStock($order, $orderItem, 'release');
        }

        $order->stock_status = self::STATUS_RELEASED;
        $order->save();
    }

    public function restock(OrderItem $orderItem, int $quantity, string $reason): array
    {
        $orderItem->loadMissing('comboComponents');
        $locationId = $this->locationFor($orderItem);
        if ($orderItem->comboComponents->isEmpty()) {
            return $this->stockService->addStock((int) $orderItem->product_variant_id, $quantity, $reason, $locationId);
        }

        foreach ($orderItem->comboComponents as $component) {
            $inventory = \App\Models\StoreLocationInventory::query()
                ->where('store_location_id', $locationId)
                ->where('product_variant_id', $component->product_variant_id)
                ->first();
            if (! $inventory) {
                return ['success' => false, 'message' => __('messages.product_variant_not_available_in_store'), 'data' => []];
            }
            if (! $inventory->tracked) {
                continue;
            }
            $result = $this->stockService->addStock(
                (int) $component->product_variant_id,
                $quantity * (int) $component->quantity,
                $reason,
                $locationId,
            );
            if (($result['success'] ?? false) === false) {
                return $result;
            }
        }

        return ['success' => true, 'data' => []];
    }

    private function reserve(Order $order): array
    {
        $order->loadMissing(['items.sellerOrderItem.sellerOrder']);

        foreach ($order->items as $orderItem) {
            $result = $this->adjustOrderItemStock($order, $orderItem, 'reserve');

            if (($result['success'] ?? false) === false) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? __('labels.order_creation_failed'),
                    'data' => [],
                ];
            }
        }

        $order->stock_status = self::STATUS_RESERVED;
        $order->save();

        return ['success' => true];
    }

    private function deductImmediate(Order $order): array
    {
        $order->loadMissing(['items.sellerOrderItem.sellerOrder', 'items.addons']);

        foreach ($order->items as $orderItem) {
            $result = $this->adjustOrderItemStock($order, $orderItem, 'deduct');

            if (($result['success'] ?? false) === false) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? __('labels.order_creation_failed'),
                    'data' => [],
                ];
            }

            if (! $this->decrementAddonStock($order, $orderItem)) {
                return [
                    'success' => false,
                    'message' => __('messages.addon_out_of_stock'),
                    'data' => [],
                ];
            }
        }

        $order->stock_status = self::STATUS_COMMITTED;
        $order->save();

        $this->clearUserCart($order);

        return ['success' => true];
    }

    private function clearUserCart(Order $order): void
    {
        $cart = Cart::where('user_id', $order->user_id)->first();
        if ($cart) {
            $cart->items()->delete();
        }
    }

    private function decrementAddonStock(Order $order, OrderItem $orderItem): bool
    {
        $quantity = (int) $orderItem->quantity;
        if ($quantity <= 0) {
            return true;
        }

        $addons = $orderItem->relationLoaded('addons')
            ? $orderItem->getRelation('addons')
            : $orderItem->addons()->get();

        if (! $addons || $addons->isEmpty()) {
            return true;
        }

        $storeAddonItemService = app(StoreAddonItemService::class);

        foreach ($addons as $addon) {
            $inventory = $storeAddonItemService->findForCheckout(
                (int) $orderItem->store_id,
                (int) $addon->addon_item_id,
            );

            if (! $inventory) {
                continue;
            }

            $ok = $storeAddonItemService->decrementStock($inventory, $quantity);
            if (! $ok) {
                Log::error('Addon stock decrement failed', [
                    'order_id' => $order->id,
                    'order_item_id' => $orderItem->id,
                    'store_id' => $orderItem->store_id,
                    'addon_item_id' => $addon->addon_item_id,
                    'quantity' => $quantity,
                    'current_stock' => $inventory->fresh()?->stock,
                ]);

                return false;
            }
        }

        return true;
    }

    private function locationFor(OrderItem $orderItem): ?int
    {
        $locationId = $orderItem->sellerOrderItem?->sellerOrder?->store_location_id;

        return $locationId ? (int) $locationId : null;
    }

    private function adjustOrderItemStock(Order $order, OrderItem $orderItem, string $operation): array
    {
        $orderItem->loadMissing('comboComponents');
        $locationId = $this->locationFor($orderItem);
        $components = $orderItem->comboComponents;

        if ($components->isEmpty()) {
            return match ($operation) {
                'reserve' => $this->stockService->reserveStock((int) $orderItem->product_variant_id, (int) $orderItem->quantity, "Reserved {$orderItem->quantity} item(s) for Order #{$order->id}.", $locationId),
                'release' => $this->stockService->releaseReservation((int) $orderItem->product_variant_id, (int) $orderItem->quantity, "Released reservation for cancelled Order #{$order->id}.", $locationId),
                'commit' => $this->stockService->commitReservation((int) $orderItem->product_variant_id, (int) $orderItem->quantity, "Captured {$orderItem->quantity} item(s) for Order #{$order->id}.", $locationId),
                default => $this->stockService->removeStock((int) $orderItem->product_variant_id, (int) $orderItem->quantity, "Moved {$orderItem->quantity} item(s) from stock to Order #{$order->id}.", $locationId),
            };
        }

        foreach ($components as $component) {
            $inventory = \App\Models\StoreLocationInventory::query()
                ->where('store_location_id', $locationId)
                ->where('product_variant_id', $component->product_variant_id)
                ->first();

            if (! $inventory) {
                return ['success' => false, 'message' => __('messages.product_variant_not_available_in_store'), 'data' => []];
            }
            if (! $inventory->tracked) {
                continue;
            }

            $quantity = (int) $orderItem->quantity * (int) $component->quantity;
            $reason = "{$operation} component stock for Order #{$order->id}.";
            $result = match ($operation) {
                'reserve' => $this->stockService->reserveStock((int) $component->product_variant_id, $quantity, $reason, $locationId),
                'release' => $this->stockService->releaseReservation((int) $component->product_variant_id, $quantity, $reason, $locationId),
                'commit' => $this->stockService->commitReservation((int) $component->product_variant_id, $quantity, $reason, $locationId),
                default => $this->stockService->removeStock((int) $component->product_variant_id, $quantity, $reason, $locationId),
            };

            if (($result['success'] ?? false) === false) {
                return $result;
            }
        }

        return ['success' => true, 'data' => []];
    }

    private function isOnline(Order $order): bool
    {
        return in_array($order->payment_method, [
            PaymentTypeEnum::RAZORPAY(),
            PaymentTypeEnum::STRIPE(),
            PaymentTypeEnum::PAYSTACK(),
            PaymentTypeEnum::FLUTTERWAVE(),
            PaymentTypeEnum::XENDIT(),
            PaymentTypeEnum::MERCADO_PAGO(),
        ], true);
    }
}
