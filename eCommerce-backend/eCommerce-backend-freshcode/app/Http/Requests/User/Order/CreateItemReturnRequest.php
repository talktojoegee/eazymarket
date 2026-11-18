<?php

namespace App\Http\Requests\User\Order;

use App\Enums\Order\ReturnReasonCodeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateItemReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        $code = $this->input('reason_code');
        $critical = is_string($code)
            && ($enum = ReturnReasonCodeEnum::tryFrom($code)) !== null
            && $enum->requiresEvidence();

        return [
            'quantity'    => ['nullable', 'integer', 'min:1'],
            'shipment_id' => ['nullable', 'integer', 'min:1'],
            'reason_code' => ['required_without:reason', 'nullable', Rule::in(ReturnReasonCodeEnum::values())],
            'reason'      => $critical
                ? ['required', 'string', 'max:1000']
                : ['required_without:reason_code', 'nullable', 'string', 'max:1000'],
            'images'      => $critical ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'images.*'    => $critical
                ? ['required', 'image', 'mimes:jpg,png,webp,jpeg', 'max:5120']
                : ['nullable', 'image', 'mimes:jpg,png,webp,jpeg', 'max:5120'],
        ];
    }

    /**
     * Custom validation messages (optional).
     */
    public function messages(): array
    {
        return [
            'required' => __('labels.validation_message_the_attribute_field_is_required'),
            'exists' => __('labels.validation_message_the_selected_attribute_is_invalid'),
            'string' => __('labels.validation_message_the_attribute_must_be_a_valid_string'),
            'array' => __('labels.validation_message_the_attribute_must_be_an_array'),
            'reason.required' => __('labels.validation_message_please_describe_the_problem_for_this_return_reason'),
            'images.required' => __('labels.validation_message_please_attach_at_least_one_photo_for_this_return_reason'),
            'images.min'      => __('labels.validation_message_please_attach_at_least_one_photo_for_this_return_reason'),
            'images.*.required' => __('labels.validation_message_please_attach_at_least_one_valid_photo'),
            'images.*.max' => __('labels.validation_message_each_photo_must_be_5_mb_or_smaller'),
        ];
    }

    /**
     * Human-friendly field labels.
     */
    public function attributes(): array
    {
        return [
            'reason' => __('labels.validation_attribute_return_reason'),
            'images' => __('labels.validation_attribute_return_images'),
        ];
    }
}
