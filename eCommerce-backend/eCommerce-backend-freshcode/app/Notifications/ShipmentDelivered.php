<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\Shipment;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use App\Notifications\Concerns\RespectsChannelSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShipmentDelivered extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsChannelSettings;

    public function __construct(
        protected Shipment $shipment,
        protected NotificationRoleTypeEnum $roleType
    ) {
    }

    public function via(object $notifiable): array
    {
        return $this->filterChannels([RoleAwareDatabaseChannel::class, FirebaseChannel::class, 'mail']);
    }

    public function toFirebase(object $notifiable): array
    {
        return $this->buildPayload();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->buildPayload();
        $order = $this->shipment->sellerOrder?->order;
        $url = $this->roleType === NotificationRoleTypeEnum::SELLER
            ? url('seller/orders/'.$order?->id)
            : url('orders/'.$order?->id);

        return (new MailMessage)
            ->subject($payload['title'])
            ->greeting('Hello '.($notifiable->name ?? '').'!')
            ->line($payload['body'])
            ->action('View Order', $url);
    }

    public function toDatabase(object $notifiable): array
    {
        $payload = $this->buildPayload();
        $order = $this->shipment->sellerOrder?->order;

        return [
            'title' => $payload['title'],
            'message' => $payload['body'],
            'type' => NotificationTypeEnum::DELIVERY(),
            'sent_to' => $this->roleType === NotificationRoleTypeEnum::SELLER ? 'seller' : 'customer',
            'role_type' => $this->roleType->value,
            'user_id' => $notifiable->id ?? null,
            'store_id' => $this->roleType === NotificationRoleTypeEnum::SELLER ? $this->shipment->sellerOrder?->seller_id : null,
            'order_id' => $order?->id,
            'order_item_id' => $this->shipment->items->first()?->order_item_id,
            'metadata' => $payload['data'],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->buildPayload()['data'];
    }

    private function buildPayload(): array
    {
        $order = $this->shipment->sellerOrder?->order;
        $orderId = $order?->id ?? '-';
        $isSeller = $this->roleType === NotificationRoleTypeEnum::SELLER;
        $items = $this->shipment->items
            ->map(fn ($line) => $line->orderItem?->title)
            ->filter()
            ->values();
        $itemSummary = $items->implode(', ');
        return [
            'title' => 'Order Delivered',
    'body' => $isSeller
        ? 'Shipment for order #'.$orderId.' has been delivered'.($itemSummary !== '' ? ': '.$itemSummary : '').'.'
        : 'Your order #'.$orderId.' has been delivered'.".\nRate your order ⭐⭐⭐⭐⭐",
    'image' => $this->shipment->items->first()?->orderItem?->product?->main_image,
    // 👇 ADD THIS BLOCK FOR ANDROID (Enables 'Rate now' button when app is closed):
    'android' => [
        'notification' => [
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'actions' => [
                [
                    'action' => 'rate_now',
                    'title' => 'Rate now',
                ],
            ],
        ],
    ],
    // 👇 ADD THIS BLOCK FOR IOS (Enables 'Rate now' button on iOS):
    'apns' => [
        'payload' => [
            'aps' => [
                'category' => 'order_delivered_category',
            ],
        ],
    ],
    'data' => [
        'type' => NotificationTypeEnum::DELIVERY(),
        'role_type' => $this->roleType->value,
        'status' => 'delivered',
        'actions' => [
            [
                'action' => 'rate_now',
                'title' => 'Rate now',
            ],
        ],
        'order_id' => $order?->id,
        'order_slug' => $order?->slug,
        'seller_order_id' => $this->shipment->seller_order_id,
        'shipment_id' => $this->shipment->id,
        'order_item_ids' => $this->shipment->items->pluck('order_item_id')->values()->all(),
    ],
        ];
        // return [
        //     'title' => 'Order Delivered',
        //     'body' => $isSeller
        //         ? 'Shipment for order #'.$orderId.' has been delivered'.($itemSummary !== '' ? ': '.$itemSummary : '').'.'
        //         : 'Your order #'.$orderId.' has been delivered'.".\nRate your order ⭐⭐⭐⭐⭐",
        //     'image' => $this->shipment->items->first()?->orderItem?->product?->main_image,
        //     'data' => [
        //         'type' => NotificationTypeEnum::DELIVERY(),
        //         'role_type' => $this->roleType->value,
        //         'status' => 'delivered',
        //         'actions' => [
        //             [
        //                 'action' => 'rate_now',
        //                 'title' => 'Rate now',
        //             ],
        //         ],
        //         'order_id' => $order?->id,
        //         'order_slug' => $order?->slug,
        //         'seller_order_id' => $this->shipment->seller_order_id,
        //         'shipment_id' => $this->shipment->id,
        //         'order_item_ids' => $this->shipment->items->pluck('order_item_id')->values()->all(),
        //     ],
        // ];
    }
}
