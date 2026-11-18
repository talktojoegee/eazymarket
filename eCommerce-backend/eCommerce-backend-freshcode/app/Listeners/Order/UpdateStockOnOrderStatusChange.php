<?php

namespace App\Listeners\Order;

use App\Enums\Order\OrderItemStatusEnum;
use App\Events\Order\OrderStatusUpdated;
use App\Models\OrderItem;
use App\Services\OrderService;
use App\Services\OrderInventoryService;
use App\Services\StoreAddonItemService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateStockOnOrderStatusChange
{
    protected OrderInventoryService $orderInventoryService;
    protected OrderService $orderService;
    protected StoreAddonItemService $storeAddonItemService;

    /**
     * Create the event listener.
     */
    public function __construct(
        OrderInventoryService $orderInventoryService,
        OrderService $orderService,
        StoreAddonItemService $storeAddonItemService
    ) {
        $this->orderInventoryService = $orderInventoryService;
        $this->orderService = $orderService;
        $this->storeAddonItemService = $storeAddonItemService;
    }

    /**
     * Handle the event.
     */
    public function handle(OrderStatusUpdated $event): void
    {
        if (in_array($event->newStatus, [
            OrderItemStatusEnum::CANCELLED(),
            OrderItemStatusEnum::RETURNED(),
            OrderItemStatusEnum::REFUNDED(),
        ], true)) {
            if ($event->oldStatus === $event->newStatus) {
                return;
            }

            if ($event->orderItem->is_restocked) {
                return;
            }

            try {
                DB::beginTransaction();

                $orderItem = $event->orderItem;
                $orderItem->loadMissing(['sellerOrderItem.sellerOrder', 'comboComponents']);
                $quantity = (int) $orderItem->quantity;

                $stockResult = $this->orderInventoryService->restock(
                    $orderItem,
                    $quantity,
                    "Returned {$quantity} item(s) to stock due to {$event->newStatus} of Order Item #{$orderItem->id}."
                );

                if (!$stockResult['success']) {
                    throw new \Exception($stockResult['message']);
                }

                $orderItem->is_restocked = true;
                $orderItem->saveQuietly();

                app(\App\Services\AddonStockService::class)->restoreForOrderItem($orderItem, null, (string) $event->newStatus);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error updating stock after order item status change', [
                    'order_item_id' => $event->orderItem->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

}
