<?php

namespace App\Listeners\Order;

use App\Models\Shipment;
use App\Services\WhatsAppEventNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendShipmentShippedWhatsApp implements ShouldQueue
{
    public function handle(\App\Events\Order\ShipmentShipped $event): void
    {
        $shipment = Shipment::with(['sellerOrder.order.user', 'items.orderItem.product'])->find($event->shipmentId);
        $order = $shipment?->sellerOrder?->order;
        $customer = $order?->user;

        if (! $shipment || $shipment->is_return || ! $order || ! $customer) {
            return;
        }

        app(WhatsAppEventNotifier::class)->send($customer, 'customer_order_shipped', [
            'customer_name' => (string) ($customer->name ?? ''),
            'order_number' => (string) ($order->order_number ?: $order->id),
            'order_date' => optional($order->created_at)->format('Y-m-d') ?? now()->toDateString(),
        ], $shipment->items->first()?->orderItem?->product?->main_image);
    }
}
