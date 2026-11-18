<?php

namespace App\Services\BulkUpload;

use App\Enums\CategoryStatusEnum;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Interfaces\BulkImportable;
use App\Models\BulkUploadJob;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CategoryBulkImportService implements BulkImportable
{
    public function __construct(protected CategoryService $categoryService) {}

    public function moduleKey(): string
    {
        return 'categories';
    }

    public function modelClass(): string
    {
        return Category::class;
    }

    public function csvTemplate(): array
    {
        return [
            'columns' => ['title', 'parent_id', 'parent_title', 'description', 'status', 'requires_approval', 'commission', 'image_url', 'banner_url', 'seo_title', 'seo_keywords', 'seo_description'],
            'sample' => ['Fruits', '', '', 'Fresh fruits', 'active', 'false', '5', 'https://example.com/fruits.png', '', 'Fresh fruits', 'fruit,fresh', 'Shop fresh fruits.'],
        ];
    }

    public function exportColumns(): array
    {
        return ['id', 'title', 'parent_id', 'parent_title', 'description', 'status', 'requires_approval', 'commission', 'image_url', 'banner_url', 'seo_title', 'seo_keywords', 'seo_description'];
    }

    public function exportRow($model): array
    {
        $metadata = is_array($model->metadata) ? $model->metadata : [];
        $keywords = $metadata['seo_keywords'] ?? '';

        return [
            'id' => $model->id,
            'title' => $model->title,
            'parent_id' => $model->parent_id,
            'parent_title' => $model->parent?->title ?? '',
            'description' => $model->description,
            'status' => $this->enumValue($model->status),
            'requires_approval' => $model->requires_approval ? 'true' : 'false',
            'commission' => $model->commission,
            'image_url' => '',
            'banner_url' => '',
            'seo_title' => $metadata['seo_title'] ?? '',
            'seo_keywords' => is_array($keywords) ? implode(',', $keywords) : $keywords,
            'seo_description' => $metadata['seo_description'] ?? '',
        ];
    }

    public function exportFilters(): array
    {
        return [
            [
                'key' => 'status',
                'label' => 'labels.status',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => 'labels.all'],
                    ['value' => CategoryStatusEnum::ACTIVE(), 'label' => 'labels.active'],
                    ['value' => CategoryStatusEnum::INACTIVE(), 'label' => 'labels.inactive'],
                ],
            ],
            [
                'key' => 'parent_id',
                'label' => 'labels.parent',
                'type' => 'select_remote',
                'route' => 'admin.categories.search',
            ],
        ];
    }

    public function applyExportFilters($query, array $filters)
    {
        $query->when(! empty($filters['status']), fn ($builder) => $builder->where('status', $filters['status']));
        if (array_key_exists('parent_id', $filters) && $filters['parent_id'] !== '') {
            is_numeric($filters['parent_id'])
                ? $query->where('parent_id', (int) $filters['parent_id'])
                : $query->whereNull('parent_id');
        }

        return $query->with('parent');
    }

    public function availableMatchFields(): array
    {
        return [
            ['key' => 'id', 'label' => 'labels.id', 'column' => 'id'],
            ['key' => 'title', 'label' => 'labels.title', 'column' => 'title'],
        ];
    }

    public function availableFields(): array
    {
        return [
            ['key' => 'id', 'label' => 'labels.id', 'required' => false, 'type' => 'integer'],
            ['key' => 'title', 'label' => 'labels.title', 'required' => true, 'type' => 'string'],
            ['key' => 'parent_id', 'label' => 'labels.parent_id', 'required' => false, 'type' => 'integer'],
            ['key' => 'parent_title', 'label' => 'labels.parent', 'required' => false, 'type' => 'string'],
            ['key' => 'description', 'label' => 'labels.description', 'required' => false, 'type' => 'string'],
            ['key' => 'status', 'label' => 'labels.status', 'required' => false, 'type' => 'enum'],
            ['key' => 'requires_approval', 'label' => 'labels.requires_approval', 'required' => false, 'type' => 'boolean'],
            ['key' => 'commission', 'label' => 'labels.commission', 'required' => false, 'type' => 'decimal'],
            ['key' => 'image_url', 'label' => 'labels.image_url', 'required' => false, 'type' => 'url'],
            ['key' => 'banner_url', 'label' => 'labels.banner_url', 'required' => false, 'type' => 'url'],
            ['key' => 'seo_title', 'label' => 'labels.seo_title', 'required' => false, 'type' => 'string'],
            ['key' => 'seo_keywords', 'label' => 'labels.seo_keywords', 'required' => false, 'type' => 'string'],
            ['key' => 'seo_description', 'label' => 'labels.seo_description', 'required' => false, 'type' => 'string'],
        ];
    }

    public function validateMapping(array $mapping, array $headers): void
    {
        if (empty($mapping['title']) && empty($mapping['id'])) {
            throw new \InvalidArgumentException('labels.bulk_upload_title_mapping_required');
        }

        foreach ($mapping as $header) {
            if ($header !== null && $header !== '' && ! in_array($header, $headers, true)) {
                throw new \InvalidArgumentException('labels.bulk_upload_unknown_csv_column');
            }
        }
    }

    public function processRow(array $row, BulkUploadJob $job): array
    {
        $mapping = is_array($job->column_mapping) ? $job->column_mapping : [];
        $defaults = is_array($job->default_values) ? $job->default_values : [];

        return $this->enumValue($job->operation) === 'update'
            ? $this->processUpdateRow($row, $job, $mapping)
            : $this->processCreateRow($row, $job, $mapping, $defaults);
    }

    private function processCreateRow(array $row, BulkUploadJob $job, array $mapping, array $defaults): array
    {
        try {
            $attributes = $this->createAttributes($row, $mapping, $defaults);
            $validator = Validator::make($attributes, $this->createRules());
            if ($validator->fails()) {
                return [BulkImportable::OUTCOME_FAILED, $validator->errors()->first()];
            }

            $category = $this->categoryService->createCategory($attributes);
            $mediaError = $this->attachMedia($category, $row, $mapping);
            if ($mediaError !== null) {
                return [BulkImportable::OUTCOME_CREATED, __('labels.bulk_upload_category_row_created_with_media_skipped', ['title' => $category->title, 'reason' => $mediaError])];
            }

            return [BulkImportable::OUTCOME_CREATED, __('labels.bulk_upload_category_row_created', ['title' => $category->title])];
        } catch (\Throwable $exception) {
            Log::warning('Category bulk row create failed', ['job_id' => $job->id, 'error' => $exception->getMessage()]);

            return [BulkImportable::OUTCOME_FAILED, $exception->getMessage()];
        }
    }

    private function processUpdateRow(array $row, BulkUploadJob $job, array $mapping): array
    {
        $matchField = $job->match_field ?: 'id';
        $matchValue = $this->value($row, $mapping, $matchField);
        if ($matchValue === '') {
            return [BulkImportable::OUTCOME_FAILED, __('labels.bulk_upload_row_missing_match_value', ['field' => $matchField])];
        }

        try {
            $category = Category::query()->where($matchField, $matchValue)->first();
            if (! $category) {
                return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_row_no_match', ['field' => $matchField, 'value' => $matchValue])];
            }

            [$attributes, $changes] = $this->updateAttributes($category, $row, $mapping);
            $hasMedia = $this->hasValue($row, $mapping, 'image_url') || $this->hasValue($row, $mapping, 'banner_url');
            if ($attributes === [] && ! $hasMedia) {
                return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_category_row_no_changes', ['title' => $category->title])];
            }

            if ($attributes !== []) {
                $validator = Validator::make($attributes, $this->updateRules($category->id, array_keys($attributes)));
                if ($validator->fails()) {
                    return [BulkImportable::OUTCOME_FAILED, $validator->errors()->first()];
                }
            }

            $this->categoryService->updateCategory($category, $attributes);
            $mediaError = $hasMedia ? $this->attachMedia($category, $row, $mapping) : null;
            if ($hasMedia) {
                $changes[] = 'media';
            }
            if ($mediaError !== null) {
                return [BulkImportable::OUTCOME_UPDATED, __('labels.bulk_upload_category_row_updated_media_skipped', ['title' => $category->title, 'fields' => implode(', ', $changes), 'reason' => $mediaError])];
            }

            return [BulkImportable::OUTCOME_UPDATED, __('labels.bulk_upload_category_row_updated', ['title' => $category->title, 'fields' => implode(', ', $changes)])];
        } catch (\Throwable $exception) {
            Log::warning('Category bulk row update failed', ['job_id' => $job->id, 'error' => $exception->getMessage()]);

            return [BulkImportable::OUTCOME_FAILED, $exception->getMessage()];
        }
    }

    private function createAttributes(array $row, array $mapping, array $defaults): array
    {
        $title = $this->value($row, $mapping, 'title');
        $attributes = [
            'title' => $title,
            'parent_id' => $this->resolveParentId($row, $mapping, $title),
            'description' => $this->value($row, $mapping, 'description') ?: ($defaults['description'] ?? null),
            'status' => strtolower($this->value($row, $mapping, 'status') ?: (string) ($defaults['status'] ?? CategoryStatusEnum::INACTIVE())),
            'requires_approval' => $this->booleanValue($this->value($row, $mapping, 'requires_approval') ?: ($defaults['requires_approval'] ?? false)),
            'commission' => $this->value($row, $mapping, 'commission') ?: ($defaults['commission'] ?? null),
        ];
        $metadata = $this->seoMetadata($row, $mapping, $defaults);
        if ($metadata !== []) {
            $attributes['metadata'] = $metadata;
        }

        return $attributes;
    }

    private function updateAttributes(Category $category, array $row, array $mapping): array
    {
        $attributes = [];
        $changes = [];
        foreach (['title', 'description'] as $field) {
            $value = $this->value($row, $mapping, $field);
            if ($value !== '' && $value !== (string) ($category->{$field} ?? '')) {
                $attributes[$field] = $value;
                $changes[] = $field;
            }
        }

        if ($this->hasValue($row, $mapping, 'parent_id') || $this->hasValue($row, $mapping, 'parent_title')) {
            $parentId = $this->resolveParentId($row, $mapping, (string) ($attributes['title'] ?? $category->title), $category);
            if ($parentId !== (int) $category->parent_id) {
                $attributes['parent_id'] = $parentId;
                $changes[] = 'parent_id';
            }
        }

        $status = strtolower($this->value($row, $mapping, 'status'));
        if ($status !== '' && $status !== $this->enumValue($category->status)) {
            $attributes['status'] = $status;
            $changes[] = 'status';
        }

        if ($this->hasValue($row, $mapping, 'requires_approval')) {
            $approval = $this->booleanValue($this->value($row, $mapping, 'requires_approval'));
            if ($approval !== $category->requires_approval) {
                $attributes['requires_approval'] = $approval;
                $changes[] = 'requires_approval';
            }
        }

        $commission = $this->value($row, $mapping, 'commission');
        if ($commission !== '' && (! is_numeric($commission) || (float) $commission !== (float) $category->commission)) {
            $attributes['commission'] = $commission;
            $changes[] = 'commission';
        }

        $metadata = $this->mergedSeoMetadata($category, $row, $mapping);
        if ($metadata !== null) {
            $attributes['metadata'] = $metadata;
            $changes[] = 'seo';
        }

        return [$attributes, $changes];
    }

    private function resolveParentId(array $row, array $mapping, string $title, ?Category $category = null): ?int
    {
        $rawId = $this->value($row, $mapping, 'parent_id');
        $parent = null;
        if ($rawId !== '') {
            if (! ctype_digit($rawId)) {
                throw new \InvalidArgumentException(__('labels.invalid_parent'));
            }
            $parent = Category::query()->find((int) $rawId);
        } else {
            $parentTitle = $this->value($row, $mapping, 'parent_title');
            if ($parentTitle === '') {
                return null;
            }
            $parent = Category::query()->where('title', $parentTitle)->first();
        }

        if (! $parent) {
            throw new \InvalidArgumentException(__('labels.invalid_parent'));
        }
        if (strcasecmp($parent->title, $title) === 0 || ($category && $this->wouldCreateCycle($category, $parent))) {
            throw new \InvalidArgumentException(__('labels.parent_cannot_be_self'));
        }

        return $parent->id;
    }

    private function wouldCreateCycle(Category $category, Category $parent): bool
    {
        $current = $parent;
        while ($current) {
            if ($current->id === $category->id) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }

    private function attachMedia(Category $category, array $row, array $mapping): ?string
    {
        $errors = [];
        foreach (['image_url' => 'image', 'banner_url' => 'banner'] as $field => $mediaField) {
            $url = $this->value($row, $mapping, $field);
            if ($url === '') {
                continue;
            }
            $error = $this->categoryService->downloadAndAttachMediaFromUrl($category, $url, $mediaField);
            if ($error !== null) {
                $errors[] = $field.': '.$error;
            }
        }

        return $errors === [] ? null : implode('; ', $errors);
    }

    private function seoMetadata(array $row, array $mapping, array $defaults): array
    {
        $metadata = [];
        foreach (['seo_title', 'seo_description'] as $field) {
            $value = $this->value($row, $mapping, $field) ?: ($defaults[$field] ?? null);
            if ($value !== null && $value !== '') {
                $metadata[$field] = $value;
            }
        }
        $keywords = $this->value($row, $mapping, 'seo_keywords') ?: ($defaults['seo_keywords'] ?? '');
        if ($keywords !== '') {
            $metadata['seo_keywords'] = is_array($keywords) ? $keywords : array_values(array_filter(array_map('trim', explode(',', $keywords))));
        }

        return $metadata;
    }

    private function mergedSeoMetadata(Category $category, array $row, array $mapping): ?array
    {
        $current = is_array($category->metadata) ? $category->metadata : [];
        $incoming = $this->seoMetadata($row, $mapping, []);
        if ($incoming === []) {
            return null;
        }
        $merged = array_merge($current, $incoming);

        return $merged === $current ? null : $merged;
    }

    private function createRules(): array
    {
        $rules = StoreCategoryRequest::baseRules();
        unset($rules['image'], $rules['banner'], $rules['meta_title'], $rules['meta_keywords'], $rules['meta_description']);

        return $rules;
    }

    private function updateRules(int $categoryId, array $fields): array
    {
        $rules = UpdateCategoryRequest::baseRules($categoryId);
        unset($rules['image'], $rules['banner'], $rules['meta_title'], $rules['meta_keywords'], $rules['meta_description']);

        return collect($rules)
            ->filter(fn ($rule, $key) => in_array(explode('.', $key)[0], $fields, true))
            ->map(fn ($rule) => $this->relaxRequired($rule))
            ->all();
    }

    private function booleanValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no'], true)) {
            return false;
        }

        return $value;
    }

    private function relaxRequired(array|string $rule): array|string
    {
        if (is_string($rule)) {
            return implode('|', array_filter(explode('|', $rule), fn ($part) => $part !== 'required'));
        }

        return array_values(array_filter($rule, fn ($part) => $part !== 'required'));
    }

    private function hasValue(array $row, array $mapping, string $field): bool
    {
        return $this->value($row, $mapping, $field) !== '';
    }

    private function value(array $row, array $mapping, string $field): string
    {
        $header = $mapping[$field] ?? null;
        $value = $header ? ($row[$header] ?? null) : null;

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function enumValue(mixed $value): string
    {
        return is_object($value) && property_exists($value, 'value') ? (string) $value->value : (string) $value;
    }
}
