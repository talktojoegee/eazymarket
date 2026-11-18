<?php

namespace App\Http\Requests\ProductFaq;

use App\Enums\ActiveInactiveStatusEnum;
use App\Models\ProductFaq;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class BulkStoreProductFaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sellerId = (int) (auth()->user()?->seller()?->id ?? 0);

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('seller_id', $sellerId),
            ],
            'faqs' => ['required', 'array', 'min:1', 'max:25'],
            'faqs.*.question' => ['required', 'string', 'max:1000'],
            'faqs.*.answer' => ['required', 'string', 'max:5000'],
            'faqs.*.status' => ['nullable', new Enum(ActiveInactiveStatusEnum::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => __('labels.validation_message_the_product_field_is_required'),
            'product_id.exists' => __('labels.validation_message_the_selected_product_is_invalid'),
            'faqs.required' => __('labels.at_least_one_faq_is_required'),
            'faqs.min' => __('labels.at_least_one_faq_is_required'),
            'faqs.max' => __('labels.maximum_25_faqs_per_batch'),
            'faqs.*.question.required' => __('labels.validation_message_the_question_field_is_required'),
            'faqs.*.question.max' => __('labels.validation_message_the_question_may_not_be_greater_than_1000_characters'),
            'faqs.*.answer.required' => __('labels.validation_message_the_answer_field_is_required'),
            'faqs.*.answer.max' => __('labels.validation_message_the_answer_may_not_be_greater_than_5000_characters'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('product_id') || $validator->errors()->has('faqs')) {
                return;
            }

            $rows = $this->input('faqs', []);
            $seen = [];
            $existing = ProductFaq::query()
                ->where('product_id', (int) $this->input('product_id'))
                ->pluck('question')
                ->mapWithKeys(fn (string $question) => [$this->normalizeQuestion($question) => true])
                ->all();

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $question = $this->normalizeQuestion((string) ($row['question'] ?? ''));
                if ($question === '') {
                    continue;
                }

                if (isset($seen[$question]) || isset($existing[$question])) {
                    $validator->errors()->add("faqs.{$index}.question", __('labels.product_faq_question_already_exists'));
                }

                $seen[$question] = true;
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $faqs = collect($this->input('faqs', []))->map(function ($faq) {
            if (! is_array($faq)) {
                return $faq;
            }

            return [
                'question' => trim((string) ($faq['question'] ?? '')),
                'answer' => trim((string) ($faq['answer'] ?? '')),
                'status' => $faq['status'] ?? 'active',
            ];
        })->all();

        $this->merge(['faqs' => $faqs]);
    }

    private function normalizeQuestion(string $question): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($question)));
    }
}
