<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\SupportTicketMessage;
use App\Notifications\Concerns\RespectsChannelSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SupportMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsChannelSettings;

    public function __construct(public readonly SupportTicketMessage $message) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->filterChannels([FirebaseChannel::class]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toFirebase(object $notifiable): array
    {
        $message = $this->message->loadMissing(['user', 'ticket.thread']);

        return [
            'title' => 'New customer support message',
            'body' => $this->preview($message),
            'image' => $message->user?->profile_image ?: null,
            'data' => [
                'notification_id' => $this->id,
                'type' => NotificationTypeEnum::SUPPORT_MESSAGE(),
                'role_type' => NotificationRoleTypeEnum::CUSTOMER(),
                'thread_uuid' => $message->ticket->thread->uuid,
                'ticket_slug' => $message->ticket->slug,
                'message_id' => (string) $message->id,
                'native_only' => true,
            ],
        ];
    }

    private function preview(SupportTicketMessage $message): string
    {
        $sender = $message->user?->name ?: 'Support';
        $text = str($message->message)->squish()->limit(120)->toString();

        return $text !== '' ? "{$sender}: {$text}" : "{$sender} sent an attachment.";
    }
}
