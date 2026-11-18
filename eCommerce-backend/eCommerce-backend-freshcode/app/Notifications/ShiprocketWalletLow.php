<?php

namespace App\Notifications;

use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use App\Notifications\Concerns\RespectsChannelSettings;

class ShiprocketWalletLow extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsChannelSettings;

    public function __construct(protected float $balance, protected float $threshold)
    {
    }

    public function via(object $notifiable): array
    {
        return $this->filterChannels([\App\Notifications\Channels\RoleAwareDatabaseChannel::class, 'mail']);
    }

    public function toMail(object $notifiable): ?MailMessage
    {
        try {
            return (new MailMessage)
                ->subject('Shiprocket wallet balance low')
                ->greeting('Hello ' . ($notifiable->name ?? ''))
                ->line('The Shiprocket wallet balance is ₹' . number_format($this->balance, 2) . ', below the alert threshold of ₹' . number_format($this->threshold, 2) . '.')
                ->line('Courier assignment and label generation fail when the wallet is empty. Recharge the wallet in the Shiprocket panel to keep shipments flowing.');
        } catch (\Throwable $e) {
            Log::error('ShiprocketWalletLow mail failed: ' . $e->getMessage());

            return null;
        }
    }

    public function toArray(object $notifiable): array
    {
        return [
            'balance'   => $this->balance,
            'threshold' => $this->threshold,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'     => 'Shiprocket wallet balance low',
            'message'   => 'Shiprocket wallet is at ₹' . number_format($this->balance, 2) . ' (alert threshold ₹' . number_format($this->threshold, 2) . '). Bookings fail on an empty wallet.',
            'type'      => NotificationTypeEnum::SYSTEM(),
            'sent_to'   => 'admin',
            'role_type' => NotificationRoleTypeEnum::ADMIN(),
            'user_id'   => $notifiable->id ?? null,
            'store_id'  => null,
            'order_id'  => null,
            'metadata'  => [
                'balance'   => $this->balance,
                'threshold' => $this->threshold,
            ],
        ];
    }
}
