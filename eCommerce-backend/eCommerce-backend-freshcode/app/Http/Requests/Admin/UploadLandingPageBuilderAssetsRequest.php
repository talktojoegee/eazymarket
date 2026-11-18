<?php

namespace App\Http\Requests\Admin;

use App\Models\LandingPageBuilderDocument;
use Illuminate\Foundation\Http\FormRequest;

class UploadLandingPageBuilderAssetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateAny', LandingPageBuilderDocument::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'asset_token' => ['required', 'uuid'],
            'document_id' => ['nullable', 'integer', 'exists:landing_page_builder_documents,id'],
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', 'file', 'max:102400', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov,pdf,woff,woff2,ttf,otf'],
        ];
    }
}
