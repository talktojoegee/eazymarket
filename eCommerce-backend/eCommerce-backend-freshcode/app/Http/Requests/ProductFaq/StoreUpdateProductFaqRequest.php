<?php

namespace App\Http\Requests\ProductFaq;

use App\Enums\ActiveInactiveStatusEnum;
use App\Models\ProductFaq;
use App\Traits\PanelAware;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

class StoreUpdateProductFaqRequest extends FormRequest
{
    use PanelAware;

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
            'product_id' => ['required', $this->productExistsRule()],
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
        $this->merge([
            'question' => trim((string) $this->question),
            'answer' => trim((string) $this->answer),
            'status' => $this->status ?? 'active',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->routeIs('seller.product_faqs.store', 'seller.product_faqs.update')) {
                return;
            }

            if ($validator->errors()->has('product_id') || $validator->errors()->has('question')) {
                return;
            }

            $normalized = $this->normalizeQuestion((string) $this->input('question'));
            $faqId = (int) ($this->route('id') ?? 0);
            $duplicate = ProductFaq::query()
                ->where('product_id', (int) $this->input('product_id'))
                ->when($faqId > 0, fn ($query) => $query->whereKeyNot($faqId))
                ->get(['question'])
                ->contains(fn (ProductFaq $faq) => $this->normalizeQuestion($faq->question) === $normalized);

            if ($duplicate) {
                $validator->errors()->add('question', __('labels.product_faq_question_already_exists'));
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'product_id.required' => __('labels.validation_message_the_product_field_is_required'),
            'product_id.exists' => __('labels.validation_message_the_selected_product_is_invalid'),
            'question.required' => __('labels.validation_message_the_question_field_is_required'),
            'question.max' => __('labels.validation_message_the_question_may_not_be_greater_than_1000_characters'),
            'answer.required' => __('labels.validation_message_the_answer_field_is_required'),
            'answer.max' => __('labels.validation_message_the_answer_may_not_be_greater_than_5000_characters'),
            'status.in' => __('labels.validation_message_the_status_must_be_either_active_or_inactive'),
        ];
    }

    private function productExistsRule(): Exists
    {
        $rule = Rule::exists('products', 'id');

        if ($this->getPanel() === 'seller') {
            $sellerId = (int) (auth()->user()?->seller()?->id ?? 0);
            $rule->where('seller_id', $sellerId);
        }

        return $rule;
    }

    private function normalizeQuestion(string $question): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($question)));
    }
}
