<?php

namespace App\Http\Requests\User\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class DeductWalletBalanceRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'description' => ['nullable', 'string', 'max:255'],
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
            'amount.required' => __('labels.validation_message_the_amount_field_is_required'),
            'amount.numeric' => __('labels.validation_message_the_amount_must_be_a_number'),
            'amount.min' => __('labels.validation_message_the_amount_must_be_at_least_0_01'),
            'order_id.exists' => __('labels.validation_message_the_selected_order_does_not_exist'),
            'store_id.exists' => __('labels.validation_message_the_selected_store_does_not_exist'),
        ];
    }
}
