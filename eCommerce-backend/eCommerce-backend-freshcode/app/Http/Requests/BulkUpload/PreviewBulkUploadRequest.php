<?php

namespace App\Http\Requests\BulkUpload;

use Illuminate\Foundation\Http\FormRequest;

class PreviewBulkUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'csv_file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel',
                'max:'.(int) config('bulk_upload.max_file_kb'),
            ],
            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],
            'template' => ['nullable', 'string', 'in:simple,variant,default'],
        ];
    }
}
