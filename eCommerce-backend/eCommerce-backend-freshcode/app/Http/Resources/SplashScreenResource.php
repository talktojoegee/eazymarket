<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SplashScreenResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'surface' => $this->surface,
            'slot' => $this->slot,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'background_type' => $this->background_type,
            'background_color' => $this->background_color,
            'background_image' => $this->background_image_path ? Storage::disk('public')->url($this->background_image_path) : null,
            'background_video' => $this->background_video_path ? Storage::disk('public')->url($this->background_video_path) : null,
            'overlay_enabled' => (bool) $this->overlay_enabled,
            'overlay_type' => $this->overlay_type,
            'overlay_image' => $this->overlay_image_path ? Storage::disk('public')->url($this->overlay_image_path) : null,
            'overlay_lottie' => $this->overlay_lottie_path ? Storage::disk('public')->url($this->overlay_lottie_path) : null,
            'overlay_position' => [
                'horizontal' => (int) $this->overlay_horizontal_position,
                'vertical' => (int) $this->overlay_vertical_position,
            ],
            'overlay_size' => (int) $this->overlay_size,
            'min_display_seconds' => (int) $this->min_display_seconds,
        ];
    }
}
