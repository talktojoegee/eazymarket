<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Models\SellerOrderItem;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Notifications\Concerns\RespectsChannelSettings;

class OrderStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsChannelSettings;

    protected $event;

    public function __construct($event, protected ?NotificationRoleTypeEnum $roleType = null)
    {
        $this->event = $event;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->filterChannels([RoleAwareDatabaseChannel::class, FirebaseChannel::class, 'mail']);
    }

    /**
     * Firebase push payload.
     */
    public function toFirebase(object $notifiable): array
    {
        return $this->buildPayload($notifiable);
    }

    /**
     * Mail representation.
     */
    public function toMail(object $notifiable): ?MailMessage
    {
        try {
            $payload = $this->buildPayload($notifiable);
            $order = $this->event->orderItem->order;

            $mail = (new MailMessage)
                ->subject($payload['title'])
                ->greeting('Hello '.($notifiable->name ?? '').'!')
                ->line($payload['body']);

            if ($orderId = $order->id ?? null) {
                $url = $this->resolveRoleType($notifiable) === NotificationRoleTypeEnum::SELLER
                    ? url('seller/orders/'.$orderId)
                    : url('orders/'.$orderId);
                $mail->action('View Order', $url);
            }

            return $mail;
        } catch (\Throwable $e) {
            Log::error('OrderStatusUpdated mail failed: '.$e->getMessage(), [
                'notifiable_id' => $notifiable->id ?? null,
                'notification' => static::class,
            ]);

            return null;
        }
    }

    /**
     * Persisted database notification row.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $payload = $this->buildPayload($notifiable);
        $orderItem = $this->event->orderItem;
        $order = $orderItem->order;
        $data = $payload['data'];

        return [
            'title' => $payload['title'],
            'message' => $payload['body'],
            'type' => $data['type'] ?? NotificationTypeEnum::ORDER_UPDATE(),
            'sent_to' => $this->resolveRoleType($notifiable) === NotificationRoleTypeEnum::SELLER ? 'seller' : 'customer',
            'role_type' => $this->resolveRoleType($notifiable)->value,
            'user_id' => $notifiable->id ?? null,
            'store_id' => null,
            'order_id' => $data['order_id'] ?? ($order->id ?? null),
            'order_item_id' => $data['order_item_id'] ?? ($orderItem->id ?? null),
            'metadata' => array_merge($data, [
                'order_id' => $order->id ?? null,
                'order_slug' => $order->slug ?? null,
                'order_item_id' => $orderItem->id ?? null,
                'order_item_title' => $orderItem->title ?? null,
                'seller_order_id' => $this->resolveSellerOrderId(),
            ]),
        ];
    }

    /**
     * Legacy array shape (kept for broadcast compatibility).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $orderItem = $this->event->orderItem;
        $order = $orderItem->order;

        return [
            'order_id' => $order->id ?? null,
            'order_slug' => $order->slug ?? null,
            'order_item_id' => $orderItem->id ?? null,
            'item_status' => (string) $this->event->newStatus,
            'status' => ucfirst(Str::replace('_', ' ', $order->status ?? '')),
        ];
    }

    /**
     * Build the title/body/image/data block for the given recipient.
     * Single source of truth for every channel.
     *
     * @return array{title:string,body:string,image:?string,data:array<string,mixed>}
     */
    public function buildPayload(object $notifiable): array
    {
        $isSeller = $this->resolveRoleType($notifiable) === NotificationRoleTypeEnum::SELLER;
        $audience = $isSeller ? 'seller' : 'customer';
        $orderItem = $this->event->orderItem;
        $order = $orderItem->order;
        $image = $orderItem->product->main_image ?? null;
        $orderId = $order->id ?? '-';
        $newStatus = $this->event->newStatus;
        $source = $this->event->source ?? 'system';

        if ((string) $newStatus === OrderItemStatusEnum::CANCELLED()) {
            $bySeller   = $source === 'seller';
            $byCustomer = $source === 'customer';
            $byAdmin    = $source === 'admin';

            $sellerBody = match (true) {
                $bySeller   => 'You cancelled "' . $orderItem->title . '" from order #' . $orderId . '. The customer has been refunded.',
                $byCustomer => 'The customer cancelled "' . $orderItem->title . '" from order #' . $orderId . '.',
                $byAdmin    => 'An administrator cancelled "' . $orderItem->title . '" from order #' . $orderId . '.',
                default     => '"' . $orderItem->title . '" from order #' . $orderId . ' has been cancelled.',
            };

            $customerBody = match (true) {
                $bySeller   => 'The seller cancelled "' . $orderItem->title . '" from your order #' . $orderId . '. Your refund is on its way.',
                $byCustomer => 'Your order item "' . $orderItem->title . '" has been cancelled successfully.',
                $byAdmin    => 'Your order item "' . $orderItem->title . '" has been cancelled by support. A refund has been issued.',
                default     => 'Your order item "' . $orderItem->title . '" has been cancelled.',
            };

            $riderBody = match (true) {
                $bySeller   => 'The seller cancelled "' . $orderItem->title . '" on order #' . $orderId . '. You no longer need to deliver this item.',
                $byCustomer => 'The customer cancelled "' . $orderItem->title . '" on order #' . $orderId . '.',
                $byAdmin    => 'Order item "' . $orderItem->title . '" on order #' . $orderId . ' was cancelled by support.',
                default     => 'Order item "' . $orderItem->title . '" on order #' . $orderId . ' has been cancelled.',
            };

            $body = match ($audience) {
                'seller'   => $sellerBody,
                'rider'    => $riderBody,
                default    => $customerBody,
            };

            return [
                'title' => 'Order Item Cancelled: '.$orderItem->title,
                'body' => $audience === 'seller'
                    ? 'The order item "'.$orderItem->title.'" from order #'.$orderId.' has been cancelled by the customer.'
                    : 'Your order item "'.$orderItem->title.'" has been cancelled successfully.',
                'image' => $image,
                'data' => [
                    'order_slug' => $order->slug ?? null,
                    'order_id' => $orderItem->order_id ?? ($order->id ?? null),
                    'order_item_id' => $orderItem->id ?? null,
                    'order_item_title' => $orderItem->title ?? null,
                    'seller_order_id' => $this->resolveSellerOrderId(),
                    'status' => $orderItem->status,
                    'source' => $source,
                    'type' => NotificationTypeEnum::ORDER_UPDATE(),
                    'role_type' => $this->resolveRoleType($notifiable)->value,
                ],
            ];
        }

        $status = ucfirst(Str::replace('_', ' ', $order->status ?? ''));

        $defaultBody = match ($audience) {
            'seller'   => 'Order #' . $orderId . ' is now ' . $status . '.',
            'rider'    => 'Order #' . $orderId . ' you are delivering is now ' . $status . '.',
            default    => 'Your order #' . $orderId . ' is now ' . $status . '.',
        };

        return [
            'title' => 'Order Status Updated',
            'body' => $audience === 'seller'
                ? 'Order #'.$orderId.' is now '.$status.'.'
                : 'Your order #'.$orderId.' is now '.$status.'.',
            'image' => $image,
            'data' => [
                'order_slug' => $order->slug ?? null,
                'order_id' => $order->id ?? null,
                'order_item_id' => $orderItem->id ?? null,
                'order_item_title' => $orderItem->title ?? null,
                'seller_order_id' => $this->resolveSellerOrderId(),
                'item_status' => (string) $newStatus,
                'status' => $status,
                'type' => NotificationTypeEnum::ORDER_UPDATE(),
                'role_type' => $this->resolveRoleType($notifiable)->value,
            ],
        ];
    }

    private function isSeller(object $notifiable): bool
    {
        return method_exists($notifiable, 'hasRole')
            && $notifiable->hasRole(DefaultSystemRolesEnum::SELLER());
    }

    private function resolveRoleType(object $notifiable): NotificationRoleTypeEnum
    {
        if ($this->roleType !== null) {
            return $this->roleType;
        }

        return $this->isSeller($notifiable)
            ? NotificationRoleTypeEnum::SELLER
            : NotificationRoleTypeEnum::CUSTOMER;
    }

    private function resolveSellerOrderId(): ?int
    {
        try {
            return SellerOrderItem::where('order_item_id', $this->event->orderItem->id)
                ->value('seller_order_id');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
