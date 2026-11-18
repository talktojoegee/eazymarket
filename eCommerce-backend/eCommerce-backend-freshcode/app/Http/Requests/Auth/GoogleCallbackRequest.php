<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class GoogleCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idToken'      => ['required', 'string'],
            'friends_code' => ['nullable', 'string', 'max:32', 'exists:users,referral_code'],
            'fcm_token'    => ['nullable', 'string'],
            'device_type'  => ['nullable', 'string', 'max:32'],
            'country'      => ['nullable', 'string', 'max:255'],
            'iso_2'        => ['nullable', 'string', 'max:2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'idToken'      => __('labels.validation_attribute_firebase_id_token'),
            'friends_code' => __('labels.validation_attribute_referral_code'),
            'fcm_token'    => __('labels.validation_attribute_fcm_token'),
            'device_type'  => __('labels.validation_attribute_device_type'),
            'country'      => __('labels.validation_attribute_country'),
            'iso_2'        => __('labels.validation_attribute_country_iso_2_code'),
        ];
    }
}
