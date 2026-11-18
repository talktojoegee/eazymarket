<?php

namespace App\Http\Requests\Faq;

use App\Enums\ActiveInactiveStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreUpdateFaqRequest extends FormRequest
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
            'question' => 'required|string|max:1000',
            'answer' => 'required|string|max:5000',
            'status' => ['nullable', new Enum(ActiveInactiveStatusEnum::class)],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->isMethod('POST') && !$this->route('id')) {
            $this->merge([
                'status' => $this->status ?? 'active',
            ]);
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'question.required' => __('labels.validation_message_the_question_field_is_required'),
            'question.max' => __('labels.validation_message_the_question_may_not_be_greater_than_1000_characters'),
            'answer.required' => __('labels.validation_message_the_answer_field_is_required'),
            'answer.max' => __('labels.validation_message_the_answer_may_not_be_greater_than_5000_characters'),
            'status.in' => __('labels.validation_message_the_status_must_be_either_active_or_inactive'),
        ];
    }
}
