<?php

namespace App\Listeners\Order;

use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Events\Order\OrderStatusUpdated;
use App\Notifications\OrderStatusUpdated as OrderStatusUpdatedNotificationClass;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class OrderStatusUpdatedNotification implements ShouldQueue
{
    /**
     * Fan the event out to every user who should hear about it.
     * All message copy lives in OrderStatusUpdatedNotificationClass — do not build payloads here.
     */
    public function handle(OrderStatusUpdated $event): void
    {
        try {
            $customer = $event->orderItem->order->user ?? null;
            if ($customer) {
                $customer->notify(new OrderStatusUpdatedNotificationClass($event, NotificationRoleTypeEnum::CUSTOMER));
            }

            $sellerUser = $event->orderItem->store->seller->user ?? null;
            if ($sellerUser) {
                $sellerUser->notify(new OrderStatusUpdatedNotificationClass($event, NotificationRoleTypeEnum::SELLER));
            }
        } catch (\Throwable $e) {
            Log::error('OrderStatusUpdatedNotification listener failed: '.$e->getMessage(), [
                'order_item_id' => $event->orderItem->id ?? null,
                'new_status' => $event->newStatus ?? null,
            ]);
        }
    }
}
