<?php

namespace App\Notifications\Delivery;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Concerns\RespectsChannelSettings;

class OrderReadyForPickupNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsChannelSettings;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return $this->filterChannels([\App\Notifications\Channels\RoleAwareDatabaseChannel::class, 'mail', FirebaseChannel::class]);
    }

    public function toMail(object $notifiable): ?MailMessage
    {
        $order = $this->order;

        return (new MailMessage)
            ->subject('Order Ready For Pickup')
            ->greeting('Hello '.($notifiable->name ?? ''))
            ->line('A new order is ready for pickup in your zone.')
            ->line('Order #'.($order->id ?? '-'))
            ->line('Drop-off: '.(($order->shipping_city ?? '').', '.($order->shipping_state ?? '')));
    }

    public function toFirebase($notifiable): array
    {
        $order = $this->order;
        $firstItem = $order->items->first();
        $image = optional($firstItem)->product->main_image ?? null;

        return [
            'title' => 'Order Ready For Pickup',
            'body' => 'Order #'.($order->id ?? '-').' is ready for pickup in your zone.',
            'image' => $image,
            'data' => [
                'order_id' => $order->id ?? null,
                'order_slug' => $order->slug ?? null,
                'order_item_id' => $firstItem->id ?? null,
                'order_item_title' => $firstItem->title ?? null,
                'zone_id' => $order->delivery_zone_id ?? null,
                'type' => NotificationTypeEnum::ORDER_READY_FOR_PICKUP(),
                'role_type' => NotificationRoleTypeEnum::RIDER(),
            ],
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $order = $this->order;
        $firstItem = $order->items->first();

        return [
            'title' => 'Order Ready For Pickup',
            'message' => 'Order #'.($order->id ?? '-').' is ready for pickup in your zone.',
            'type' => NotificationTypeEnum::ORDER_READY_FOR_PICKUP(),
            'sent_to' => 'delivery_boy',
            'role_type' => NotificationRoleTypeEnum::RIDER(),
            'user_id' => $notifiable->id ?? null,
            'order_id' => $order->id ?? null,
            'order_item_id' => $firstItem->id ?? null,
            'metadata' => [
                'order_id' => $order->id ?? null,
                'order_slug' => $order->slug ?? null,
                'order_item_id' => $firstItem->id ?? null,
                'order_item_title' => $firstItem->title ?? null,
                'zone_id' => $order->delivery_zone_id ?? null,
                'shipping_latitude' => $order->shipping_latitude ?? null,
                'shipping_longitude' => $order->shipping_longitude ?? null,
            ],
        ];
    }
}
