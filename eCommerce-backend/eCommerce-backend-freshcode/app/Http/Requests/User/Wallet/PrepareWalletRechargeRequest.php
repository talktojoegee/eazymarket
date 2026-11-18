<?php

namespace App\Http\Requests\User\Wallet;

use App\Enums\Payment\PaymentTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrepareWalletRechargeRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'amount' => ['required', 'numeric', 'min:1', 'max:100000', 'decimal:0,2'],
            'payment_method' => ['required', Rule::in(PaymentTypeEnum::RAZORPAY(), PaymentTypeEnum::STRIPE(), PaymentTypeEnum::PAYSTACK(), PaymentTypeEnum::FLUTTERWAVE(), PaymentTypeEnum::XENDIT(), PaymentTypeEnum::MERCADO_PAGO())],
            'description' => ['nullable', 'string', 'max:255'],
            'redirect_url' => ['nullable'],
        ];
        if (! empty($this->input('redirect_url'))) {
            $rules['redirect_url'] = ['required', 'url'];
        }

        return $rules;
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
            'payment_method.required' => __('labels.validation_message_the_payment_method_field_is_required'),
            'payment_method.max' => __('labels.validation_message_the_payment_method_must_not_exceed_50_characters'),
        ];
    }
}
