<?php

namespace App\Listeners\Order;

use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Models\Shipment;
use App\Notifications\ShipmentDelivered as ShipmentDeliveredNotificationClass;
use App\Services\WhatsAppEventNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ShipmentDeliveredNotification implements ShouldQueue
{
    public function handle(\App\Events\Order\ShipmentDelivered $event): void
    {
        $shipment = Shipment::with([
            'sellerOrder.order.user',
            'sellerOrder.seller.user',
            'items.orderItem.product',
        ])->find($event->shipmentId);

        if (!$shipment || $shipment->is_return) {
            return;
        }

        $order = $shipment->sellerOrder?->order;
        $customer = $order?->user;
        $sellerUser = $shipment->sellerOrder?->seller?->user;

        if ($customer && $order) {
            app(WhatsAppEventNotifier::class)->send($customer, 'customer_order_delivered_invoice', [
                'customer_name' => (string) ($customer->name ?? ''),
                'order_number' => (string) ($order->order_number ?: $order->id),
                'order_date' => optional($order->created_at)->format('Y-m-d') ?? now()->toDateString(),
            ], $shipment->items->first()?->orderItem?->product?->main_image);
        }

        foreach ([
            [$customer, NotificationRoleTypeEnum::CUSTOMER],
            [$sellerUser, NotificationRoleTypeEnum::SELLER],
        ] as [$user, $roleType]) {
            if (!$user) {
                continue;
            }

            try {
                $user->notify(new ShipmentDeliveredNotificationClass($shipment, $roleType));
            } catch (\Throwable $e) {
                Log::error('ShipmentDeliveredNotification listener failed: '.$e->getMessage(), [
                    'shipment_id' => $shipment->id,
                    'user_id' => $user->id ?? null,
                    'role_type' => $roleType->value,
                ]);
            }
        }
    }
}
