<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class QuickRegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'max:10'],
            'mobile'       => ['required', 'string', 'max:20', 'unique:users,mobile'],
            'email'        => ['nullable', 'email', 'max:255', 'unique:users,email'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'         => __('labels.validation_attribute_customer_name'),
            'country_code' => __('labels.validation_attribute_country_code'),
            'mobile'       => __('labels.validation_attribute_mobile_number'),
            'email'        => __('labels.validation_attribute_email_address'),
        ];
    }
}
