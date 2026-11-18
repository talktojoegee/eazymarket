<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyReel;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use App\Notifications\Concerns\RespectsChannelSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WatchBuyModerationNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsChannelSettings;

    public function __construct(
        public WatchBuyProfile $profile,
        public string $reason,
        public ?WatchBuyReel $reel = null,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->filterChannels([RoleAwareDatabaseChannel::class, FirebaseChannel::class]);
    }

    public function toFirebase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->message(),
            'image' => $this->reel?->cover_url ?: $this->profile->photo_url,
            'data' => $this->notificationData() + [
                'role_type' => NotificationRoleTypeEnum::SELLER(),
                'click_action' => $this->clickAction(),
            ],
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message(),
            'type' => NotificationTypeEnum::WATCH_BUY(),
            'sent_to' => 'seller',
            'role_type' => NotificationRoleTypeEnum::SELLER(),
            'user_id' => $notifiable->id,
            'metadata' => $this->notificationData(),
        ];
    }

    private function notificationData(): array
    {
        return [
            'type' => NotificationTypeEnum::WATCH_BUY(),
            'target_type' => $this->reel ? 'reel' : 'profile',
            'action' => $this->reel ? 'block' : 'suspend',
            'profile_id' => $this->profile->id,
            'reel_id' => $this->reel?->id,
            'reel_slug' => $this->reel?->slug,
            'reason' => $this->reason,
        ];
    }

    private function title(): string
    {
        return $this->reel
            ? __('labels.watch_buy_reel_blocked_notification_title')
            : __('labels.watch_buy_profile_suspended_notification_title');
    }

    private function message(): string
    {
        return $this->reel
            ? __('labels.watch_buy_reel_blocked_notification_message', ['id' => $this->reel->id, 'reason' => $this->reason])
            : __('labels.watch_buy_profile_suspended_notification_message', ['username' => $this->profile->username, 'reason' => $this->reason]);
    }

    private function clickAction(): string
    {
        return $this->reel
            ? route('seller.watch-and-buy.reels.index')
            : route('seller.watch-and-buy.profile.edit');
    }
}
