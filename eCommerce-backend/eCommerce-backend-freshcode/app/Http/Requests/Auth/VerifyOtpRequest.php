<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyOtpRequest extends FormRequest
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
            'mobile'       => ['required', 'string'],
            'otp'          => ['required', 'string', 'size:6'],
            'name'         => ['nullable', 'string', 'max:255'],
            'email'        => [
                'nullable',
                'email',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password'     => ['nullable', 'string', 'min:6', 'confirmed'],
            'country'      => ['nullable', 'string', 'max:255'],
            'iso_2'        => ['nullable', 'string', 'max:2'],
            'friends_code' => ['nullable', 'string', 'max:32', 'exists:users,referral_code'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mobile'       => __('labels.validation_attribute_mobile_number'),
            'otp'          => __('labels.validation_attribute_otp_code'),
            'name'         => __('labels.validation_attribute_name'),
            'email'        => __('labels.validation_attribute_email_address'),
            'password'     => __('labels.validation_attribute_password'),
            'country'      => __('labels.validation_attribute_country'),
            'iso_2'        => __('labels.validation_attribute_country_iso_2_code'),
            'friends_code' => __('labels.validation_attribute_referral_code'),
        ];
    }
}
