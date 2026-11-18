<?php

namespace App\Http\Resources\Setting;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class NotificationSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = Auth::guard('sanctum')->user();
        $notification = app(NotificationService::class);
        $data = [
            'variable' => $this->variable,
            'value' => [
                'userRequest' => $this->resource->value['userRequest'] ?? '',
                'vapIdKey' => $this->resource->value['vapIdKey'] ?? '',
                'enablePush' => (bool) ($this->resource->value['enablePush'] ?? true),
                'enableEmail' => (bool) ($this->resource->value['enableEmail'] ?? false),
                'abandonedCartEnabled' => (bool) ($this->resource->value['abandonedCartEnabled'] ?? true),
                'abandonedCartDelayMinutes' => (int) ($this->resource->value['abandonedCartDelayMinutes'] ?? 1440),
                'abandonedCartRepeatMinutes' => (int) ($this->resource->value['abandonedCartRepeatMinutes'] ?? 180),
                'notification_unread_count' => $user?->id ? $notification->getUnreadCount($user->id) : 0,
            ],
        ];

        return $data;
    }
}
