<?php

namespace App\Http\Requests\Onboarding;

use App\Models\OnboardingPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOnboardingPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $updating = $this->route('id') !== null;
        $page = $updating ? OnboardingPage::find($this->route('id')) : null;
        $backgroundImageRequired = $this->input('background_type') === 'image'
            && (! $page || ! $page->background_image_path);

        return [
            'surface' => ['required', Rule::in(['customer_app', 'seller_app'])],
            'title' => ['nullable', 'string', 'max:30'],
            'subtitle' => ['nullable', 'string', 'max:50'],
            'onboarding_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'font_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'next_button_background_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'next_button_text_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_type' => ['required', Rule::in(['color', 'image'])],
            'background_color' => ['required_if:background_type,color', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_image' => [Rule::requiredIf($backgroundImageRequired), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
