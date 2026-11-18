<?php

namespace App\Http\Requests\Seller;

use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Enums\Seller\SellerVisibilityStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rule;
use App\Services\DynamicSellerFieldService;

class UpdateSellerRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'zipcode' => 'required|string|max:255',
            'country' => 'nullable|string|max:255',
            'country_code' => 'sometimes|string|max:255',
            'latitude' => 'nullable|string|max:255',
            'longitude' => 'nullable|string|max:255',
            'verification_status' => ['required', new Enum(SellerVerificationStatusEnum::class)],
            'visibility_status' => ['required', new Enum(SellerVisibilityStatusEnum::class)],
        ];
        foreach (app(DynamicSellerFieldService::class)->forRequest($this) as $field) {
            $key = 'dynamic_fields.'.$field->key;
            $options = $field->options ?? [];
            if ($field->type === 'select' && array_intersect(['current', 'checking'], $options)) {
                $options = array_values(array_unique(array_merge($options, ['current', 'checking'])));
            }
            $fieldRules = [$field->type === 'image' ? 'sometimes' : ($field->required ? 'required' : 'nullable')];
            $fieldRules = array_merge($fieldRules, match ($field->type) {
                'number' => ['numeric'],
                'text' => ['string'],
                'select' => [Rule::in($options)],
                'image' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
                default => ['nullable'],
            });
            if ($field->type !== 'image') {
                $fieldRules[] = 'max:1000';
            }
            $rules[$key] = $fieldRules;
        }
        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.sometimes' => __('validation.sometimes', ['attribute' => __('labels.validation_message_name')]),
            'address.required' => __('validation.required', ['attribute' => __('labels.validation_message_address')]),
            'city.required' => __('validation.required', ['attribute' => __('labels.validation_message_city')]),
            'state.required' => __('validation.required', ['attribute' => __('labels.validation_message_state')]),
            'zipcode.required' => __('validation.required', ['attribute' => __('labels.validation_message_zipcode')]),
            'country.required' => __('validation.required', ['attribute' => __('labels.validation_message_country')]),
            'business_license.image' => __('validation.image', ['attribute' => __('labels.validation_message_business_license')]),
            'articles_of_incorporation.image' => __('validation.image', ['attribute' => __('labels.validation_message_articles_of_incorporation')]),
            'national_identity_card.image' => __('validation.image', ['attribute' => __('labels.validation_message_national_identity_card')]),
            'authorized_signature.image' => __('validation.image', ['attribute' => __('labels.validation_message_authorized_signature')]),
        ];
    }

    public function attributes(): array
    {
        return app(DynamicSellerFieldService::class)
            ->forRequest($this)
            ->mapWithKeys(fn ($field) => ['dynamic_fields.'.$field->key => $field->label])
            ->all();
    }
}
