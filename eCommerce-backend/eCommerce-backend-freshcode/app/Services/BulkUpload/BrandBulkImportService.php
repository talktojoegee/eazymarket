<?php

namespace App\Services\BulkUpload;

use App\Enums\BrandStatusEnum;
use App\Enums\HomePageScopeEnum;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Interfaces\BulkImportable;
use App\Models\Brand;
use App\Models\BulkUploadJob;
use App\Models\Category;
use App\Services\BrandService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BrandBulkImportService implements BulkImportable
{
    public function __construct(protected BrandService $brandService) {}

    public function moduleKey(): string
    {
        return 'brands';
    }

    public function modelClass(): string
    {
        return Brand::class;
    }

    public function csvTemplate(): array
    {
        return [
            'columns' => ['title', 'description', 'status', 'scope_type', 'scope_id', 'category_title', 'logo_url', 'seo_title', 'seo_keywords', 'seo_description'],
            'sample' => ['Nike', 'Sports brand', 'active', 'global', '', '', 'https://example.com/nike.png', 'Nike sports gear', 'sports,shoes,apparel', 'Premium sports brand.'],
        ];
    }

    public function exportColumns(): array
    {
        return ['id', 'title', 'description', 'status', 'scope_type', 'scope_id', 'category_title', 'logo_url', 'seo_title', 'seo_keywords', 'seo_description'];
    }

    public function exportRow($model): array
    {
        $metadata = is_array($model->metadata) ? $model->metadata : [];
        $keywords = $metadata['seo_keywords'] ?? '';

        return [
            'id' => $model->id,
            'title' => $model->title,
            'description' => $model->description,
            'status' => $this->enumValue($model->status),
            'scope_type' => $this->enumValue($model->scope_type),
            'scope_id' => $model->scope_id,
            'category_title' => $model->scopeCategory?->title ?? '',
            'logo_url' => '',
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
                    ['value' => BrandStatusEnum::ACTIVE(), 'label' => 'labels.active'],
                    ['value' => BrandStatusEnum::INACTIVE(), 'label' => 'labels.inactive'],
                ],
            ],
            [
                'key' => 'scope_type',
                'label' => 'labels.scope_type',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => 'labels.all'],
                    ['value' => HomePageScopeEnum::GLOBAL(), 'label' => 'labels.global'],
                    ['value' => HomePageScopeEnum::CATEGORY(), 'label' => 'labels.category'],
                ],
            ],
            [
                'key' => 'scope_id',
                'label' => 'labels.scope_category',
                'type' => 'select_remote',
                'route' => 'admin.categories.search',
                'depends_on' => ['key' => 'scope_type', 'value' => HomePageScopeEnum::CATEGORY()],
            ],
        ];
    }

    public function applyExportFilters($query, array $filters)
    {
        $query->when(! empty($filters['status']), fn ($builder) => $builder->where('status', $filters['status']))
            ->when(! empty($filters['scope_type']), fn ($builder) => $builder->where('scope_type', $filters['scope_type']))
            ->when(! empty($filters['scope_id']) && is_numeric($filters['scope_id']), fn ($builder) => $builder->where('scope_id', (int) $filters['scope_id']));

        return $query->with('scopeCategory');
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
            ['key' => 'title', 'label' => 'labels.brand_name', 'required' => true, 'type' => 'string'],
            ['key' => 'description', 'label' => 'labels.description', 'required' => true, 'type' => 'string'],
            ['key' => 'status', 'label' => 'labels.status', 'required' => false, 'type' => 'enum'],
            ['key' => 'scope_type', 'label' => 'labels.scope_type', 'required' => false, 'type' => 'enum'],
            ['key' => 'scope_id', 'label' => 'labels.scope_category', 'required' => false, 'type' => 'integer'],
            ['key' => 'category_title', 'label' => 'labels.category', 'required' => false, 'type' => 'string'],
            ['key' => 'logo_url', 'label' => 'labels.logo_url', 'required' => false, 'type' => 'url'],
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

            $brand = $this->brandService->createBrand($attributes);
            $logoUrl = $this->value($row, $mapping, 'logo_url');
            if ($logoUrl !== '') {
                $error = $this->brandService->downloadAndAttachLogoFromUrl($brand, $logoUrl);
                if ($error !== null) {
                    return [BulkImportable::OUTCOME_CREATED, __('labels.bulk_upload_row_created_without_logo', ['title' => $brand->title, 'reason' => $error])];
                }
            }

            return [BulkImportable::OUTCOME_CREATED, __('labels.bulk_upload_row_created', ['title' => $brand->title])];
        } catch (\Throwable $exception) {
            Log::warning('Brand bulk row create failed', ['job_id' => $job->id, 'error' => $exception->getMessage()]);

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
            $brand = Brand::query()->where($matchField, $matchValue)->first();
            if (! $brand) {
                return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_row_no_match', ['field' => $matchField, 'value' => $matchValue])];
            }

            [$attributes, $changes] = $this->updateAttributes($brand, $row, $mapping);
            $logoUrl = $this->value($row, $mapping, 'logo_url');
            if ($attributes === [] && $logoUrl === '') {
                return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_row_no_changes', ['title' => $brand->title])];
            }

            if ($attributes !== []) {
                $validator = Validator::make($attributes, $this->updateRules($brand->id, array_keys($attributes)));
                if ($validator->fails()) {
                    return [BulkImportable::OUTCOME_FAILED, $validator->errors()->first()];
                }
            }

            $this->brandService->updateBrand($brand, $attributes);
            if ($logoUrl !== '') {
                $error = $this->brandService->downloadAndAttachLogoFromUrl($brand, $logoUrl);
                if ($error !== null) {
                    return [BulkImportable::OUTCOME_UPDATED, __('labels.bulk_upload_row_updated_logo_skipped', ['title' => $brand->title, 'fields' => implode(', ', $changes), 'reason' => $error])];
                }
                $changes[] = 'logo';
            }

            return [BulkImportable::OUTCOME_UPDATED, __('labels.bulk_upload_row_updated', ['title' => $brand->title, 'fields' => implode(', ', $changes)])];
        } catch (\Throwable $exception) {
            Log::warning('Brand bulk row update failed', ['job_id' => $job->id, 'error' => $exception->getMessage()]);

            return [BulkImportable::OUTCOME_FAILED, $exception->getMessage()];
        }
    }

    private function createAttributes(array $row, array $mapping, array $defaults): array
    {
        $scopeType = strtolower($this->value($row, $mapping, 'scope_type') ?: (string) ($defaults['scope_type'] ?? HomePageScopeEnum::GLOBAL()));
        $attributes = [
            'title' => $this->value($row, $mapping, 'title'),
            'description' => $this->value($row, $mapping, 'description') ?: (string) ($defaults['description'] ?? ''),
            'status' => strtolower($this->value($row, $mapping, 'status') ?: (string) ($defaults['status'] ?? BrandStatusEnum::INACTIVE())),
            'scope_type' => $scopeType,
            'scope_id' => $scopeType === HomePageScopeEnum::CATEGORY() ? $this->resolveScopeId($row, $mapping, $defaults) : null,
        ];
        $metadata = $this->seoMetadata($row, $mapping, $defaults);
        if ($metadata !== []) {
            $attributes['metadata'] = $metadata;
        }

        return $attributes;
    }

    private function updateAttributes(Brand $brand, array $row, array $mapping): array
    {
        $attributes = [];
        $changes = [];

        foreach (['title', 'description'] as $field) {
            $value = $this->value($row, $mapping, $field);
            if ($value !== '' && $value !== (string) $brand->{$field}) {
                $attributes[$field] = $value;
                $changes[] = $field;
            }
        }

        $status = strtolower($this->value($row, $mapping, 'status'));
        if ($status !== '' && $status !== $this->enumValue($brand->status)) {
            $attributes['status'] = $status;
            $changes[] = 'status';
        }

        $scopeType = strtolower($this->value($row, $mapping, 'scope_type'));
        if ($scopeType !== '') {
            if ($scopeType !== $this->enumValue($brand->scope_type)) {
                $attributes['scope_type'] = $scopeType;
                $changes[] = 'scope_type';
            }
            if ($scopeType === HomePageScopeEnum::GLOBAL() && $brand->scope_id !== null) {
                $attributes['scope_id'] = null;
                $changes[] = 'scope_id';
            } elseif ($scopeType === HomePageScopeEnum::CATEGORY()) {
                $scopeId = $this->resolveScopeId($row, $mapping);
                if ($scopeId !== null && $scopeId !== (int) $brand->scope_id) {
                    $attributes['scope_id'] = $scopeId;
                    $changes[] = 'scope_id';
                }
            }
        } elseif ($this->hasValue($row, $mapping, 'scope_id') || $this->hasValue($row, $mapping, 'category_title')) {
            $scopeId = $this->resolveScopeId($row, $mapping);
            if ($scopeId !== (int) $brand->scope_id) {
                $attributes['scope_id'] = $scopeId;
                $changes[] = 'scope_id';
            }
        }

        $metadata = $this->mergedSeoMetadata($brand, $row, $mapping);
        if ($metadata !== null) {
            $attributes['metadata'] = $metadata;
            $changes[] = 'seo';
        }

        return [$attributes, $changes];
    }

    private function resolveScopeId(array $row, array $mapping, array $defaults = []): ?int
    {
        $rawId = $this->value($row, $mapping, 'scope_id') ?: (string) ($defaults['scope_id'] ?? '');
        if ($rawId !== '') {
            if (! ctype_digit($rawId)) {
                throw new \InvalidArgumentException(__('labels.bulk_upload_invalid_scope_category', ['id' => $rawId]));
            }

            return (int) $rawId;
        }

        $title = $this->value($row, $mapping, 'category_title') ?: (string) ($defaults['category_title'] ?? '');
        if ($title === '') {
            return null;
        }

        $categoryId = Category::query()->whereNull('parent_id')->where('title', $title)->value('id');
        if ($categoryId === null) {
            throw new \InvalidArgumentException(__('labels.bulk_upload_invalid_scope_category', ['id' => $title]));
        }

        return (int) $categoryId;
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

    private function mergedSeoMetadata(Brand $brand, array $row, array $mapping): ?array
    {
        $current = is_array($brand->metadata) ? $brand->metadata : [];
        $incoming = $this->seoMetadata($row, $mapping, []);
        if ($incoming === []) {
            return null;
        }
        $merged = array_merge($current, $incoming);

        return $merged === $current ? null : $merged;
    }

    private function createRules(): array
    {
        $rules = StoreBrandRequest::baseRules();
        unset($rules['logo'], $rules['meta_title'], $rules['meta_keywords'], $rules['meta_description']);

        return $rules;
    }

    private function updateRules(int $brandId, array $fields): array
    {
        $rules = UpdateBrandRequest::baseRules($brandId);
        unset($rules['logo'], $rules['meta_title'], $rules['meta_keywords'], $rules['meta_description']);

        $selected = collect($rules)
            ->filter(fn ($rule, $key) => in_array(explode('.', $key)[0], $fields, true))
            ->map(fn ($rule) => $this->relaxRequired($rule))
            ->all();

        if (in_array('scope_type', $fields, true) && isset($rules['scope_id'])) {
            $selected['scope_id'] = $this->relaxRequired($rules['scope_id']);
        }

        return $selected;
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
