<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\SettingTypeEnum;
use App\Services\FirebaseService;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use App\Services\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AbandonedCartNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $itemCount)
    {
    }

    public function via(object $notifiable): array
    {
        $settings = app(SettingService::class)->getSettingValues(SettingTypeEnum::NOTIFICATION());
        $channels = [RoleAwareDatabaseChannel::class];

        if ($settings['enablePush'] ?? true) {
            $authentication = app(SettingService::class)->getSettingValues(SettingTypeEnum::AUTHENTICATION());
            $pushReady = app(FirebaseService::class)->configurationStatus($authentication, $settings)['push_ready'];

            if ($pushReady) {
                $channels[] = FirebaseChannel::class;
            }
        }

        if (($settings['enableEmail'] ?? false) && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('labels.abandoned_cart_notification_title'),
            'message' => __('labels.abandoned_cart_notification_message', ['count' => $this->itemCount]),
            'type' => 'abandoned_cart',
            'sent_to' => 'customer',
            'role_type' => 'customer',
            'user_id' => $notifiable->id,
            'metadata' => ['cart_item_count' => $this->itemCount],
        ];
    }

    public function toFirebase(object $notifiable): array
    {
        return [
            'title' => __('labels.abandoned_cart_notification_title'),
            'body' => __('labels.abandoned_cart_notification_message', ['count' => $this->itemCount]),
            'data' => [
                'type' => 'abandoned_cart',
                'role_type' => 'customer',
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('labels.abandoned_cart_notification_title'))
            ->line(__('labels.abandoned_cart_notification_message', ['count' => $this->itemCount]));
    }

}
