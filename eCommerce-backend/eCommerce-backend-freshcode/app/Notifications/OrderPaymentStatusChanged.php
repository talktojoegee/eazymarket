<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\Order;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use App\Notifications\Concerns\RespectsChannelSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderPaymentStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsChannelSettings;

    public const STATE_PAID = 'paid';
    public const STATE_FAILED = 'failed';

    public function __construct(protected Order $order, protected string $state)
    {
    }

    public function via(object $notifiable): array
    {
        return $this->filterChannels([RoleAwareDatabaseChannel::class, FirebaseChannel::class]);
    }

    public function toFirebase(object $notifiable): array
    {
        return $this->buildPayload();
    }

    public function toDatabase(object $notifiable): array
    {
        $payload = $this->buildPayload();

        return [
            'title' => $payload['title'],
            'message' => $payload['body'],
            'type' => NotificationTypeEnum::PAYMENT(),
            'sent_to' => 'customer',
            'role_type' => NotificationRoleTypeEnum::CUSTOMER->value,
            'user_id' => $notifiable->id ?? null,
            'store_id' => null,
            'order_id' => $this->order->id,
            'order_item_id' => $payload['data']['order_item_id'] ?? null,
            'metadata' => $payload['data'],
        ];
    }

    public function buildPayload(): array
    {
        $paid = $this->state === self::STATE_PAID;
        $firstItem = null;
        try {
            $firstItem = $this->order->items()->first();
        } catch (\Throwable $e) {
        }

        return [
            'title' => $paid ? 'Payment confirmed' : 'Payment failed',
            'body' => $paid
                ? 'Your payment for order #' . $this->order->id . ' is confirmed.'
                : 'Your payment for order #' . $this->order->id . ' could not be completed.',
            'image' => null,
            'data' => [
                'order_slug' => $this->order->slug,
                'order_id' => $this->order->id,
                'order_item_id' => $firstItem->id ?? null,
                'order_item_title' => $firstItem->title ?? null,
                'payment_status' => (string) $this->order->payment_status,
                'type' => NotificationTypeEnum::PAYMENT(),
                'role_type' => NotificationRoleTypeEnum::CUSTOMER->value,
            ],
        ];
    }
}
