<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'type'         => ['required', 'string', Rule::in(['email', 'mobile'])],
            'value'        => ['required', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required'      => __('labels.validation_message_the_type_field_is_required'),
            'type.in'            => __('labels.validation_message_the_type_must_be_either_email_or_mobile'),
            'value.required'     => __('labels.validation_message_the_value_field_is_required'),
            'value.string'       => __('labels.validation_message_the_value_must_be_a_string'),
            'value.max'          => __('labels.validation_message_the_value_must_not_exceed_255_characters'),
            'country_code.max'   => __('labels.validation_message_the_country_code_must_not_exceed_10_characters'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type'         => __('labels.validation_attribute_verification_type'),
            'value'        => __('labels.validation_attribute_verification_value'),
            'country_code' => __('labels.validation_attribute_country_code'),
        ];
    }
}
