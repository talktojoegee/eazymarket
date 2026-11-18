<?php

namespace App\Services;

use App\Models\OrderItem;
use Illuminate\Support\Facades\Log;

class AddonStockService
{
    public function __construct(private StoreAddonItemService $storeAddonItemService)
    {
    }

    public function restoreForOrderItem(OrderItem $orderItem, ?int $quantity = null, string $context = 'restock'): void
    {
        $quantity ??= (int) $orderItem->quantity;

        if ($quantity <= 0) {
            return;
        }

        $addons = $orderItem->relationLoaded('addons')
            ? $orderItem->getRelation('addons')
            : $orderItem->addons()->get();

        if (!$addons || $addons->isEmpty()) {
            return;
        }

        foreach ($addons as $addon) {
            $inventory = $this->storeAddonItemService->findForCheckout(
                (int) $orderItem->store_id,
                (int) $addon->addon_item_id,
            );

            if (!$inventory) {
                continue;
            }

            if (!$this->storeAddonItemService->incrementStock($inventory, $quantity)) {
                Log::warning('Addon stock restore returned false', [
                    'order_item_id' => $orderItem->id,
                    'store_id'      => $orderItem->store_id,
                    'addon_item_id' => $addon->addon_item_id,
                    'quantity'      => $quantity,
                    'context'       => $context,
                ]);
            }
        }
    }
}
