<?php

namespace App\Http\Resources\WatchBuy;

use App\Enums\GuardNameEnum;
use App\Services\WatchBuyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WatchBuyProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $customer = $request->user('sanctum');
        $customer = $customer?->access_panel === GuardNameEnum::WEB ? $customer : null;
        $config = app(WatchBuyService::class)->config();
        $watchBuyEnabled = (bool) $config['enabled'];
        $hasActiveStatus = $watchBuyEnabled && $this->status === 'active' && $this->statusFlag(
            'has_active_status',
            fn () => $this->resource->statuses()->active()->exists(),
        );
        $hasUnseenStatus = $hasActiveStatus && $this->statusFlag(
            'has_unseen_status',
            fn () => ! $customer || $this->resource->statuses()
                ->active()
                ->whereDoesntHave('views', fn ($views) => $views->where('user_id', $customer->id))
                ->exists(),
        );

        return [
            'id' => $this->id,
            'type' => $this->profile_type,
            'username' => $this->username,
            'bio' => $this->bio,
            'photo_url' => $this->photo_url,
            'status' => $this->status,
            'suspension_reason' => $this->suspension_reason,
            'suspended_at' => $this->suspended_at,
            'has_active_status' => $hasActiveStatus,
            'has_unseen_status' => $hasUnseenStatus,
        ];
    }

    private function statusFlag(string $attribute, callable $fallback): bool
    {
        if (array_key_exists($attribute, $this->resource->getAttributes())) {
            return (bool) $this->resource->getAttribute($attribute);
        }

        return (bool) $fallback();
    }
}
