<?php

namespace App\Http\Requests;

use App\Enums\Notification\NotificationAudienceTypeEnum;
use App\Enums\Notification\NotificationTargetTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Convert incoming data before validation
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->user_ids)) {
            $this->merge([
                'user_ids' => array_filter(
                    array_map('trim', explode(',', $this->user_ids))
                ),
            ]);
        }

        if (is_string($this->market_ids)) {
            $this->merge([
                'market_ids' => array_filter(
                    array_map('trim', explode(',', $this->market_ids))
                ),
            ]);
        }

        if ($this->input('audience_type') !== NotificationAudienceTypeEnum::CUSTOMER()) {
            $this->merge([
                'target_type' => null,
                'target_id' => null,
            ]);
        }
    }

    /**
     * Validation rules
     */
    public function rules(): array
    {
        return [
            'audience_type' => ['required', new Enum(NotificationAudienceTypeEnum::class)],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:1000'],

            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:1024'],

            'target_type' => [
                'nullable',
                new Enum(NotificationTargetTypeEnum::class),
            ],

            'target_id' => [
                'nullable',
                'required_with:target_type',
                'integer',
                'min:1',
            ],

            'market_ids' => ['nullable', 'array'],
            'market_ids.*' => ['integer', 'exists:markets,id'],

            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * Optional: Custom messages
     */
    public function messages(): array
    {
        return [
            'user_ids.*.integer' => __('labels.user_id_integer_error'),
            'market_ids.*.exists' => __('labels.user_id_exist_error'),
        ];
    }
}
