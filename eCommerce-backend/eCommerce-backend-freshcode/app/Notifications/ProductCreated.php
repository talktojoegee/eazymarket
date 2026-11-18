<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Events\Product\ProductAfterCreate;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use App\Notifications\Concerns\RespectsChannelSettings;
use App\Services\CurrencyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductCreated extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsChannelSettings;

    protected $event;

    /**
     * Create a new notification instance.
     */
    public function __construct(ProductAfterCreate $event)
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

        return (new MailMessage)
            ->subject('Product Awaiting Approval - '.$product->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('A seller submitted a new product for approval.')
            ->line('Product: '.$product->title)
            ->line('Description: '.$product->short_description)
            ->line('Price: '.app(CurrencyService::class)->getSymbol().number_format($product->price ?? 0, 2))
            ->line('Status: '.$product->status)
            ->action('Review Product', route('admin.products.show', $product->id))
            ->line('Thank you for using our application!');
    }

    public function toFirebase(object $notifiable): array
    {
        $product = $this->event->product;

        return [
            'title' => 'Product Awaiting Approval',
            'body' => 'A seller submitted "'.$product->title.'" for approval.',
            'image' => $product->main_image ?? null,
            'data' => [
                'product_id' => $product->id,
                'type' => NotificationTypeEnum::PRODUCT(),
                'role_type' => NotificationRoleTypeEnum::ADMIN(),
                'click_action' => route('admin.products.show', $product->id),
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

        return [
            'product_id' => $product->id,
            'title' => 'Product Awaiting Approval',
            'message' => 'A seller submitted "'.$product->title.'" for approval.',
            'type' => 'product_created',
            'metadata' => [
                'product_title' => $product->title,
                'product_price' => $product->price,
                'product_status' => $product->status,
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

        return [
            'title' => 'Product Awaiting Approval',
            'message' => 'A seller submitted "'.$product->title.'" for approval.',
            'type' => NotificationTypeEnum::PRODUCT,
            'sent_to' => 'admin',
            'role_type' => NotificationRoleTypeEnum::ADMIN(),
            'user_id' => $notifiable->id,
            'store_id' => $product->seller_id,
            'order_id' => null,
            'metadata' => [
                'product_id' => $product->id,
                'product_title' => $product->title,
                'product_price' => $product->price,
                'product_status' => $product->status,
                'seller_id' => $product->seller_id,
            ],
        ];
    }
}
