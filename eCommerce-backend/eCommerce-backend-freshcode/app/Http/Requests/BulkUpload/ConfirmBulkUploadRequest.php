<?php

namespace App\Http\Requests\BulkUpload;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmBulkUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->string('operation')->lower()->value() === 'update';

        return [
            'preview_token' => ['required', 'uuid'],
            'original_filename' => ['required', 'string', 'max:255'],
            'operation' => ['nullable', 'string', 'in:create,update'],
            'match_field' => [$isUpdate ? 'required' : 'nullable', 'string', 'max:60'],
            'notify_on_finish' => ['nullable', 'boolean'],
            'mapping' => ['required', 'array'],
            'mapping.title' => [$isUpdate ? 'nullable' : 'required', 'string', 'max:255'],
            'mapping.*' => ['nullable', 'string', 'max:255'],
            'defaults' => ['nullable', 'array'],
            'defaults.*' => ['nullable'],
            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],
            'template' => ['nullable', 'string', 'in:simple,variant,default'],
        ];
    }

    public function messages(): array
    {
        return [
            'mapping.title.required' => __('labels.bulk_upload_title_mapping_required'),
            'match_field.required' => __('labels.bulk_upload_match_field_required'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $mapping = $this->input('mapping', []);
        $defaults = $this->input('defaults', []);

        $this->merge([
            'mapping' => is_array($mapping) ? array_filter($mapping, fn ($value) => $value !== null && $value !== '') : $mapping,
            'defaults' => is_array($defaults) ? array_filter($defaults, fn ($value) => $value !== null && $value !== '') : $defaults,
            'notify_on_finish' => filter_var($this->input('notify_on_finish', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
