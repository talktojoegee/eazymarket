<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ScheduledCommandFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected string $command, protected ?string $failedAt = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): ?MailMessage
    {
        try {
            return (new MailMessage)
                ->error()
                ->subject('Scheduled command failed: ' . $this->command)
                ->greeting('Hello ' . ($notifiable->name ?? ''))
                ->line('A scheduled command did not complete successfully.')
                ->line('Command: ' . $this->command)
                ->line('Failed at: ' . ($this->failedAt ?? now()->toDateTimeString()))
                ->line('Check the cron monitor for the full run history.');
        } catch (\Throwable $e) {
            Log::error('ScheduledCommandFailed mail failed: ' . $e->getMessage());

            return null;
        }
    }
}
