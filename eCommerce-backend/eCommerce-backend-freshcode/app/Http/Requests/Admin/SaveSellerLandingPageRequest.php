<?php

namespace App\Http\Requests\Admin;

use App\Services\SellerLandingRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use JsonException;

class SaveSellerLandingPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        try {
            $configuration = json_decode($this->string('configuration')->toString(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $configuration = null;
        }

        $this->merge(['page' => $configuration]);
    }

    public function rules(): array
    {
        $registry = app(SellerLandingRegistry::class);

        return [
            'configuration' => ['required', 'json'],
            'page' => ['required', 'array'],
            'page.schemaVersion' => ['required', 'integer', 'in:1'],
            'page.seo' => ['nullable', 'array'],
            'page.seoMedia' => ['nullable', 'array'],
            'page.seoMediaSettings' => ['nullable', 'array'],
            'page.seoMediaSettings.imageUseDefault' => ['nullable', 'boolean'],
            'page.sections' => ['required', 'array', 'min:1', 'max:20'],
            'page.sections.*.id' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'page.sections.*.type' => ['required', 'string', Rule::in(array_keys($registry->variants()))],
            'page.sections.*.variant' => ['required', 'string', 'max:80'],
            'page.sections.*.enabled' => ['required', 'boolean'],
            'page.sections.*.preset' => ['nullable', 'string', 'max:120'],
            'page.sections.*.content' => ['nullable', 'array'],
            'page.sections.*.settings' => ['nullable', 'array'],
            'page.sections.*.media' => ['nullable', 'array'],
            'page.sections.*.mediaDefaults' => ['nullable', 'array'],
            'page.sections.*.mediaDefaults.*' => ['boolean'],
            'page.sections.*.items' => ['nullable', 'array', 'max:12'],
            'page.sections.*.items.*.id' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'page.sections.*.items.*.preset' => ['nullable', 'string', 'max:120'],
            'page.sections.*.items.*.content' => ['nullable', 'array'],
            'page.sections.*.items.*.settings' => ['nullable', 'array'],
            'page.sections.*.items.*.settings.rating' => ['nullable', 'numeric', 'between:1,5'],
            'page.sections.*.items.*.media' => ['nullable', 'array'],
            'page.sections.*.items.*.mediaDefaults' => ['nullable', 'array'],
            'page.sections.*.items.*.mediaDefaults.*' => ['boolean'],
            'seo_media.image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'section_media.*.*' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'item_media.*.*.*' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'remove_seo_media.image' => ['nullable', 'boolean'],
            'remove_section_media.*.*' => ['nullable', 'boolean'],
            'remove_item_media.*.*.*' => ['nullable', 'boolean'],
            'clear_seo_media.image' => ['nullable', 'boolean'],
            'clear_section_media.*.*' => ['nullable', 'boolean'],
            'clear_item_media.*.*.*' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $page = $this->input('page', []);
            $sections = collect($page['sections'] ?? []);
            $registry = app(SellerLandingRegistry::class);
            $languages = collect($registry->languages())->pluck('code');

            if ($sections->pluck('id')->duplicates()->isNotEmpty()) {
                $validator->errors()->add('configuration', __('labels.seller_landing_duplicate_section'));
            }
            if ($sections->contains(fn (array $section) => collect($section['items'] ?? [])->pluck('id')->duplicates()->isNotEmpty())) {
                $validator->errors()->add('configuration', __('labels.seller_landing_duplicate_section'));
            }
            if ($sections->contains(fn (array $section) => ! in_array($section['variant'], $registry->variants()[$section['type']], true))) {
                $validator->errors()->add('configuration', __('labels.seller_landing_invalid_variant'));
            }
            $registration = $sections->where('type', 'registration_form');
            if ($registration->count() !== 1 || ! $registration->first()['enabled'] || $sections->where('type', 'hero')->count() > 1) {
                $validator->errors()->add('configuration', __('labels.seller_landing_structure_invalid'));
            }
            if ($sections->contains(fn (array $section) => ! $this->validCta(data_get($section, 'settings.ctaHref')))) {
                $validator->errors()->add('configuration', __('labels.seller_landing_link_invalid'));
            }

            $localized = collect([$page['seo'] ?? []])
                ->merge($sections->pluck('content'))
                ->merge($sections->flatMap(fn (array $section) => collect($section['items'] ?? [])->pluck('content')));
            if ($localized->contains(fn ($content) => ! $this->validLocalizedContent((array) $content, $languages))) {
                $validator->errors()->add('configuration', __('labels.seller_landing_content_invalid'));
            }
        }];
    }

    public function configuration(): array
    {
        return $this->validated('page');
    }

    private function validCta(mixed $href): bool
    {
        if (! is_string($href) || $href === '' || str_starts_with($href, '/') || str_starts_with($href, '#')) {
            return true;
        }

        return filter_var($href, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($href, PHP_URL_SCHEME)) === 'https';
    }

    private function validLocalizedContent(array $content, Collection $languages): bool
    {
        if (collect(array_keys($content))->diff($languages)->isNotEmpty()) {
            return false;
        }

        $valid = true;
        array_walk_recursive($content, function ($value) use (&$valid) {
            $valid = $valid && is_scalar($value) && mb_strlen((string) $value) <= 2000;
        });

        return $valid;
    }
}
