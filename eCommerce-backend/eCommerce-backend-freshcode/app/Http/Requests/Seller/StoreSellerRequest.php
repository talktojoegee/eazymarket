<?php

namespace App\Http\Requests\Seller;

use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Enums\Seller\SellerVisibilityStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rule;
use App\Services\DynamicSellerFieldService;

class StoreSellerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $dynamicFields = $this->input('dynamic_fields', []);
        $dynamicFields = is_array($dynamicFields) ? $dynamicFields : [];
        foreach (app(DynamicSellerFieldService::class)->forRequest($this)->whereNotNull('default_value') as $field) {
            if ($field->type === 'image') {
                continue;
            }
            if (!array_key_exists($field->key, $dynamicFields)) {
                $dynamicFields[$field->key] = $field->default_value;
            }
        }
        $this->merge(['dynamic_fields' => $dynamicFields]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.k
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required_without:user_id|string|max:255',
            'email' => 'required_without:user_id|string|email|max:255|unique:users,email',
            'mobile' => 'required_without:user_id|regex:/^([0-9\s\-\+\(\)]*)$/|min:7|unique:users,mobile',
            'password' => 'required_without:user_id|string|min:8',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'landmark' => 'required|string|max:255',
            'zipcode' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'latitude' => 'nullable|string|max:255',
            'longitude' => 'nullable|string|max:255',
        ];
        foreach (app(DynamicSellerFieldService::class)->forRequest($this) as $field) {
            $key = 'dynamic_fields.'.$field->key;
            $fieldRules = [$field->required ? 'required' : 'nullable'];
            $fieldRules = array_merge($fieldRules, match ($field->type) {
                'number' => ['numeric'],
                'text' => ['string'],
                'select' => [Rule::in($field->options ?? [])],
                'image' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
                default => ['nullable'],
            });
            if ($field->type !== 'image') {
                $fieldRules[] = 'max:1000';
            }
            $rules[$key] = $fieldRules;
        }
        if (!Route::is('seller-api.register')) {
            $rules['verification_status'] = ['required', new Enum(SellerVerificationStatusEnum::class)];
            $rules['visibility_status'] = ['required', new Enum(SellerVisibilityStatusEnum::class)];
        }
        return $rules;
    }
    public function messages(): array
    {
        return [
            'name.required_without' => __('validation.required', ['attribute' => __('labels.validation_message_name')]),
            'email.required_without' => __('validation.required', ['attribute' => __('labels.validation_message_email')]),
            'mobile.required_without' => __('validation.required', ['attribute' => __('labels.validation_message_mobile')]),
            'password.required_without' => __('validation.required', ['attribute' => __('labels.validation_message_password')]),
            'business_license.required' => __('validation.required', ['attribute' => __('labels.validation_message_business_license')]),
            'articles_of_incorporation.required' => __('validation.required', ['attribute' => __('labels.validation_message_articles_of_incorporation')]),
            'national_identity_card.required' => __('validation.required', ['attribute' => __('labels.validation_message_national_identity_card')]),
            'authorized_signature.required' => __('validation.required', ['attribute' => __('labels.validation_message_authorized_signature')]),
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
