<?php

namespace App\Http\Requests\WatchBuy;

use App\Http\Requests\Concerns\RespondsWithApiEnvelope;
use App\Services\WatchBuyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WatchBuyStatusRequest extends FormRequest
{
    use RespondsWithApiEnvelope;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $config = app(WatchBuyService::class)->config();
        $maxUploadKb = (int) $config['effectiveMaxUploadMb'] * 1024;

        return [
            'text' => ['nullable', 'string', 'max:500'],
            'media' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4', 'max:'.$maxUploadKb],
            'attachment_ids' => ['nullable', 'array', 'max:'.(int) $config['maxProductsPerStatus']],
            'attachment_ids.*' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'source' => [Rule::requiredIf(str_starts_with((string) $this->route()?->getName(), 'admin.')), 'nullable', 'in:seller,seller_behalf,admin_direct'],
            'profile_id' => ['nullable', 'integer', 'exists:watch_buy_profiles,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (trim((string) $this->input('text')) === '' && ! $this->hasFile('media')) {
                $validator->errors()->add('text', __('labels.watch_buy_status_content_required'));
            }
            $media = $this->file('media');
            if ($media && str_starts_with(strtolower((string) $media->getMimeType()), 'image/') && $media->getSize() > 10 * 1024 * 1024) {
                $validator->errors()->add('media', __('labels.watch_buy_status_image_max_size'));
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'text' => __('labels.watch_buy_status_text'),
            'media' => __('labels.watch_buy_status_media'),
            'attachment_ids' => __('labels.watch_buy_product_attachments'),
            'attachment_ids.*' => __('labels.products'),
            'source' => __('labels.source'),
            'profile_id' => __('labels.watch_buy_seller_profile'),
        ];
    }
}
