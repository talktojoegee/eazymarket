<?php

namespace App\Http\Resources\WatchBuy;

use App\Enums\GuardNameEnum;
use App\Models\Setting;
use App\Services\WatchBuyStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WatchBuyStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $products = app(WatchBuyStatusService::class)->eligibleProducts($this->resource);
        $customer = $request->user('sanctum');
        $customer = $customer?->access_panel === GuardNameEnum::WEB ? $customer : null;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'source' => $this->source,
            'content_type' => $this->content_type,
            'text' => $this->text,
            'media_url' => $this->media_url,
            'media_mime' => $this->media_mime,
            'media_size_bytes' => $this->media_size_bytes,
            'width' => $this->media_width,
            'height' => $this->media_height,
            'duration_ms' => $this->duration_ms,
            'products' => WatchBuyStatusProductResource::collection($products),
            'creator' => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ],
            'created_at' => $this->created_at,
            'expires_at' => Setting::isDemoModeEnabled() ? null : $this->expires_at,
            'seen_by_me' => $customer ? ($this->resource->relationLoaded('views')
                ? $this->resource->views->isNotEmpty()
                : $this->views()->where('user_id', $customer->id)->exists()) : false,
        ];
    }
}
