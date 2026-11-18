<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OnboardingPageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'surface' => $this->surface,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'image' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
            'image_display_mode' => $this->image_path && ! $this->background_image_path && blank($this->title) && blank($this->subtitle)
                ? 'full_screen'
                : 'illustration',
            'font_color' => $this->font_color,
            'next_button_background_color' => $this->next_button_background_color,
            'next_button_text_color' => $this->next_button_text_color,
            'background_type' => $this->background_type,
            'background_color' => $this->background_color,
            'background_image' => $this->background_image_path
                ? Storage::disk('public')->url($this->background_image_path)
                : null,
        ];
    }
}
