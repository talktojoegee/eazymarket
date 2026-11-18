<?php

namespace App\Http\Requests\Admin;

use App\Models\LandingPageBuilderDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLandingPageBuilderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateAny', LandingPageBuilderDocument::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'alpha_dash',
                'max:180',
                Rule::unique('landing_page_builder_documents', 'slug')->ignore($this->route('document')?->id),
            ],
            'show_header_footer' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'page_content' => ['required', 'array'],
            'page_content.html' => ['present', 'string', 'max:1048576'],
            'page_content.css' => ['present', 'string', 'max:1048576'],
            'page_content.project' => ['nullable', 'json', 'max:20971520'],
            'page_content.asset_token' => ['required', 'uuid'],
            'page_content.removed_assets' => ['nullable', 'json', 'max:10000'],
        ];
    }
}
