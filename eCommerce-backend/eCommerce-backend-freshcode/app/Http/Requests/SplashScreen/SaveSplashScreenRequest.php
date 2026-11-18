<?php

namespace App\Http\Requests\SplashScreen;

use App\Models\SplashScreen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSplashScreenRequest extends FormRequest
{
    public function rules(): array
    {
        $surface = $this->input('surface');
        $slot = $this->input('slot');
        $existing = in_array($surface, ['customer_app', 'seller_app'], true)
            && in_array($slot, ['current', 'staged'], true)
            ? SplashScreen::query()->where('surface', $surface)->where('slot', $slot)->first()
            : null;
        $backgroundType = $this->input('background_type');
        $overlayEnabled = $this->boolean('overlay_enabled');
        $overlayType = $this->input('overlay_type');

        return [
            'surface' => ['required', Rule::in(['customer_app', 'seller_app'])],
            'slot' => ['required', Rule::in(['current', 'staged'])],
            'scheduled_date' => [Rule::requiredIf($slot === 'staged'), 'nullable', 'date', 'after_or_equal:today'],
            'background_type' => ['required', Rule::in(['image', 'color', 'video'])],
            'background_color' => [Rule::requiredIf($backgroundType === 'color'), 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_image' => [Rule::requiredIf($backgroundType === 'image' && ! $existing?->background_image_path), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'background_video' => [Rule::requiredIf($backgroundType === 'video' && ! $existing?->background_video_path), 'nullable', 'mimes:mp4,webm,mov', 'max:51200'],
            'overlay_enabled' => ['required', 'boolean'],
            'overlay_type' => [Rule::requiredIf($overlayEnabled), 'nullable', Rule::in(['image', 'lottie'])],
            'overlay_image' => [Rule::requiredIf($overlayEnabled && $overlayType === 'image' && ! $existing?->overlay_image_path), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'overlay_lottie' => [Rule::requiredIf($overlayEnabled && $overlayType === 'lottie' && ! $existing?->overlay_lottie_path), 'nullable', 'file', 'mimes:json', 'max:5120'],
            'overlay_horizontal_position' => ['required', 'integer', 'between:0,100'],
            'overlay_vertical_position' => ['required', 'integer', 'between:0,100'],
            'overlay_size' => ['required', 'integer', 'between:5,100'],
            'min_display_seconds' => ['required', 'integer', 'between:1,600'],
        ];
    }
}
