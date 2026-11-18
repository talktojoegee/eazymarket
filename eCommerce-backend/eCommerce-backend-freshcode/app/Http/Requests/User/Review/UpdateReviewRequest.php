<?php

namespace App\Http\Requests\User\Review;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating'  => 'nullable|integer|min:1|max:5',
            'title'   => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
            'replace_review_images' => 'nullable|boolean',
            'keep_review_images' => 'nullable|array|max:5',
            'keep_review_images.*' => 'string|url|max:2048',
            'review_images.*' => 'nullable|image|max:2048|mimes:jpeg,png,jpg,webp',
        ];
    }

    public function messages(): array
    {
        return [
            'rating.integer'      => __('messages.rating_must_be_integer'),
            'rating.min'          => __('messages.rating_must_be_at_least_1'),
            'rating.max'          => __('messages.rating_must_not_exceed_5'),
            'title.max'           => __('messages.title_max_length'),
            'comment.max'         => __('messages.comment_max_length'),
            'review_images.*.image' => __('messages.review_image_must_be_image'),
            'review_images.*.max' => __('messages.review_image_max_size'),
            'review_images.*.mimes' => __('messages.review_image_allowed_types'),
        ];
    }
}
