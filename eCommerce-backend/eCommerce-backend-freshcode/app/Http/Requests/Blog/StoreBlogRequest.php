<?php

namespace App\Http\Requests\Blog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191', Rule::unique('blogs', 'slug')],
            'short_description' => ['nullable', 'string', 'max:10000'],
            'content' => ['required', 'string', 'max:1000000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'editor_pick' => ['nullable', 'boolean'],
            'author_profile_id' => ['required', 'integer', 'exists:watch_buy_profiles,id'],
            'published_at' => ['nullable', 'date'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['required', 'integer', 'distinct', 'exists:blog_categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['required', 'string', 'max:80'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'metadata.seo_title' => ['nullable', 'string', 'max:255'],
            'metadata.seo_keywords' => ['nullable', 'string', 'max:1000'],
            'metadata.seo_description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
