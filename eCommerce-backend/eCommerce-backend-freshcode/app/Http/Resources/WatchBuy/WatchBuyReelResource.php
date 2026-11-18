<?php

namespace App\Http\Resources\WatchBuy;

use App\Services\WatchBuyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class WatchBuyReelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $products = app(WatchBuyService::class)->eligibleProducts($this->resource);
        $coverUrl = $this->cover_url;
        $videoUrl = $this->video_url;
        $user = Auth::guard('sanctum')->user();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'caption' => $this->caption,
            'status' => $this->status,
            'source' => $this->source,
            'block_reason' => $this->block_reason,
            'blocked_at' => $this->blocked_at,
            'video_url' => $videoUrl,
            'cover_url' => $coverUrl,
            'preview_type' => $coverUrl ? 'image' : ($videoUrl ? 'video' : null),
            'preview_url' => $coverUrl ?: $videoUrl,
            'preview_time_seconds' => ! $coverUrl && $videoUrl ? 0.1 : null,
            'duration_ms' => $this->duration_ms,
            'width' => $this->video_width,
            'height' => $this->video_height,
            'profile' => new WatchBuyProfileResource($this->profile),
            'like_count' => $this->like_count,
            'liked_by_me' => $user
                ? $this->likes()->where('user_id', $user->id)->exists()
                : false,
            'products' => WatchBuyReelProductResource::collection($products),
            'published_at' => $this->published_at,
        ];
    }
}
