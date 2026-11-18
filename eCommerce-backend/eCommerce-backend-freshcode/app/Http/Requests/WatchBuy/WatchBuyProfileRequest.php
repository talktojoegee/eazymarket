<?php

namespace App\Http\Requests\WatchBuy;

use App\Models\WatchBuyProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WatchBuyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge(['username' => Str::lower(trim((string) $this->input('username')))]);
        }
    }

    public function rules(): array
    {
        $profile = $this->route('profile');
        if (! $profile && $this->route()?->getName() === 'admin.watch-and-buy.official-profile.update') {
            $profile = WatchBuyProfile::where('profile_type', 'platform')->first();
        }
        if (! $profile && $this->user()?->seller()) {
            $profile = WatchBuyProfile::where('seller_id', $this->user()->seller()->id)->first();
        }

        return [
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._]+$/', Rule::unique('watch_buy_profiles')->ignore($profile?->id)],
            'bio' => ['required', 'string', 'max:160'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => __('labels.watch_buy_username'),
            'bio' => __('labels.watch_buy_bio'),
            'photo' => __('labels.watch_buy_profile_photo'),
        ];
    }
}
