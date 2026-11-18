<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Events\Product\ProductStatusAfterUpdate;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use App\Notifications\Concerns\RespectsChannelSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ProductStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsChannelSettings;

    protected $event;

    /**
     * Create a new notification instance.
     */
    public function __construct(ProductStatusAfterUpdate $event)
    {
        $this->event = $event;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [RoleAwareDatabaseChannel::class, FirebaseChannel::class];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $this->filterChannels($channels);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->event->product;
        $status = Str::headline((string) $product->verification_status);
        $reason = $this->rejectionReason($product);

        $message = (new MailMessage)
            ->subject('Product '.$status.' - '.$product->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your product review is complete.')
            ->line('Product: '.$product->title)
            ->line('Verification Status: '.$status);

        if ($reason) {
            $message->line('Rejection Reason: '.$reason);
        }

        return $message
            ->action('View Product', route('seller.products.show', $product->id))
            ->line('Thank you for using our application!');
    }

    public function toFirebase(object $notifiable): array
    {
        $product = $this->event->product;
        $status = Str::headline((string) $product->verification_status);
        $reason = $this->rejectionReason($product);

        return [
            'title' => 'Product '.$status,
            'body' => '"'.$product->title.'" was '.Str::lower($status).'.'.($reason ? ' Reason: '.$reason : ''),
            'image' => $product->main_image ?? null,
            'data' => [
                'product_id' => $product->id,
                'verification_status' => (string) $product->verification_status,
                'rejection_reason' => $reason,
                'type' => NotificationTypeEnum::PRODUCT(),
                'role_type' => NotificationRoleTypeEnum::SELLER(),
                'click_action' => route('seller.products.show', $product->id),
            ],
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $product = $this->event->product;
        $status = Str::headline((string) $product->verification_status);
        $reason = $this->rejectionReason($product);

        return [
            'product_id' => $product->id,
            'title' => 'Product '.$status,
            'message' => '"'.$product->title.'" was '.Str::lower($status).'.'.($reason ? ' Reason: '.$reason : ''),
            'type' => 'product_status_updated',
            'metadata' => [
                'product_title' => $product->title,
                'product_status' => $product->status,
                'verification_status' => $product->verification_status,
                'rejection_reason' => $reason,
                'seller_id' => $product->seller_id,
            ],
        ];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $product = $this->event->product;
        $reason = $this->rejectionReason($product);

        return [
            'title' => 'Product '.Str::headline((string) $product->verification_status),
            'message' => '"'.$product->title.'" was '.Str::lower(Str::headline((string) $product->verification_status)).'.'.($reason ? ' Reason: '.$reason : ''),
            'type' => NotificationTypeEnum::PRODUCT,
            'sent_to' => 'seller',
            'role_type' => NotificationRoleTypeEnum::SELLER(),
            'user_id' => $notifiable->id,
            'store_id' => $product->seller_id,
            'order_id' => null,
            'metadata' => [
                'product_id' => $product->id,
                'product_title' => $product->title,
                'product_status' => $product->status,
                'verification_status' => $product->verification_status,
                'rejection_reason' => $reason,
                'seller_id' => $product->seller_id,
            ],
        ];
    }

    private function rejectionReason($product): ?string
    {
        if ((string) $product->verification_status !== 'rejected') {
            return null;
        }

        $reason = trim((string) $product->rejection_reason);

        return $reason !== '' ? $reason : null;
    }
}
