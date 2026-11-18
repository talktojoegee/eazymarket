<?php

namespace App\Services;

use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductModerationLog;
use App\Types\Settings\SystemSettingType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductApprovalService
{
    private const DIRECT_FIELDS = [
        'category_id',
        'brand_id',
        'title',
        'type',
        'short_description',
        'description',
        'indicator',
    ];

    public function applyInitial(Product $product, Category $category): void
    {
        if ($category->requires_approval) {
            $product->setStatusAttribute(ProductStatusEnum::DRAFT());
            $product->setVerificationStatus(ProductVarificationStatusEnum::PENDING());
        } else {
            $product->setStatusAttribute(ProductStatusEnum::ACTIVE());
            $product->setVerificationStatus(ProductVarificationStatusEnum::APPROVED());
        }

        $product->save();
    }

    public function criticalChanges(Product $product, array $validated, Request $request): array
    {
        $configuredFields = $this->configuredCriticalFields();
        $changes = [];
        $fieldComparisons = [];

        foreach (self::DIRECT_FIELDS as $field) {
            $submitted = array_key_exists($field, $validated);
            $oldValue = $this->normalize($product->getRawOriginal($field));
            $newValue = $submitted ? $this->normalize($validated[$field]) : $oldValue;
            $changed = $submitted && $oldValue !== $newValue;

            $fieldComparisons[$field] = [
                'submitted' => $submitted,
                'changed' => $changed,
                'old' => $this->diagnosticValue($oldValue),
                'new' => $this->diagnosticValue($newValue),
            ];

            if (in_array($field, $configuredFields, true) && $changed) {
                $changes[] = $field;
            }
        }

        $variantComparison = $this->variantStructureComparison($product, $validated);
        if (in_array('variants', $configuredFields, true) && $variantComparison['changed']) {
            $changes[] = 'variants';
        }

        $mediaComparison = [
            'main_image_uploaded' => $this->hasMainImageReplacement($product, $request),
            'additional_images_uploaded' => $request->hasFile('additional_images'),
            'product_video_uploaded' => $request->hasFile('product_video'),
            'image_src' => $this->diagnosticValue($validated['image_src'] ?? ''),
        ];
        $mediaComparison['changed'] = $mediaComparison['main_image_uploaded']
            || $mediaComparison['additional_images_uploaded']
            || $mediaComparison['product_video_uploaded']
            || ! empty($validated['image_src']);
        if (in_array('media', $configuredFields, true) && $mediaComparison['changed']) {
            $changes[] = 'media';
        }

        $variantImageComparison = $this->variantImageComparison($request, $validated);
        if (in_array('variant_images', $configuredFields, true) && $variantImageComparison['changed']) {
            $changes[] = 'variant_images';
        }

        $changes = array_values(array_unique($changes));

        Log::info('product_approval_change_audit', [
            'product_id' => $product->id,
            'seller_id' => $product->seller_id,
            'actor_id' => $request->user()?->id,
            'current_category_id' => $product->getRawOriginal('category_id'),
            'submitted_category_id' => $validated['category_id'] ?? null,
            'current_verification_status' => (string) $product->verification_status,
            'configured_critical_fields' => $configuredFields,
            'detected_critical_changes' => $changes,
            'fields' => $fieldComparisons,
            'variants' => $variantComparison,
            'media' => $mediaComparison,
            'variant_images' => $variantImageComparison,
        ]);

        return $changes;
    }

    private function hasMainImageReplacement(Product $product, Request $request): bool
    {
        if (! $request->hasFile('main_image')) {
            return false;
        }

        if (! $request->exists('main_image_changed')) {
            return true;
        }

        return $request->boolean('main_image_changed')
            || ! $product->hasMedia(SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE());
    }

    public function applySellerUpdate(Product $product, array $validated, array $criticalChanges): void
    {
        $category = Category::findOrFail($validated['category_id']);
        $from = (string) $product->verification_status;

        if (! $category->requires_approval) {
            $product->setVerificationStatus(ProductVarificationStatusEnum::APPROVED());
            if ($from !== (string) ProductVarificationStatusEnum::APPROVED()) {
                $product->setStatusAttribute(ProductStatusEnum::ACTIVE());
            }
            $product->rejection_reason = null;
            $product->save();
            $this->logDecision($product, $category, $from, $criticalChanges, 'category_does_not_require_approval');

            return;
        }

        $requiresReview = $from !== (string) ProductVarificationStatusEnum::APPROVED() || $criticalChanges !== [];
        if (! $requiresReview) {
            $this->logDecision($product, $category, $from, $criticalChanges, 'approval_preserved');

            return;
        }

        $product->setVerificationStatus(ProductVarificationStatusEnum::PENDING());
        $product->setStatusAttribute(ProductStatusEnum::DRAFT());
        $product->rejection_reason = null;
        $product->save();
        $this->logDecision($product, $category, $from, $criticalChanges, 'moved_to_pending_and_draft');

        if ($from !== (string) ProductVarificationStatusEnum::PENDING()) {
            ProductModerationLog::create([
                'product_id' => $product->id,
                'admin_id' => null,
                'from_status' => $from,
                'to_status' => ProductVarificationStatusEnum::PENDING(),
                'reason' => $criticalChanges === []
                    ? 'Seller resubmitted the product for approval.'
                    : 'Seller changed: '.implode(', ', $criticalChanges).'.',
            ]);
        }
    }

    public function criticalFieldLabels(): array
    {
        return array_intersect_key(
            $this->availableCriticalFieldLabels(),
            array_flip($this->configuredCriticalFields()),
        );
    }

    public function availableCriticalFieldLabels(): array
    {
        return [
            'category_id' => __('labels.category'),
            'brand_id' => __('labels.brand'),
            'title' => __('labels.product_title'),
            'type' => __('labels.product_type'),
            'short_description' => __('labels.short_description'),
            'description' => __('labels.description'),
            'indicator' => __('labels.indicator'),
            'variants' => __('labels.product_variants'),
            'media' => __('labels.product_media'),
            'variant_images' => __('labels.variant_images'),
        ];
    }

    public function configuredCriticalFields(): array
    {
        try {
            $settings = app(SettingService::class)->getSettingValues(SettingTypeEnum::SYSTEM());
            $configured = array_key_exists('productApprovalCriticalFields', $settings)
                ? $settings['productApprovalCriticalFields']
                : SystemSettingType::DEFAULT_PRODUCT_APPROVAL_CRITICAL_FIELDS;

            if (! is_array($configured)) {
                return SystemSettingType::DEFAULT_PRODUCT_APPROVAL_CRITICAL_FIELDS;
            }

            $available = array_keys($this->availableCriticalFieldLabels());

            return array_values(array_intersect($available, $configured));
        } catch (\Throwable) {
            return SystemSettingType::DEFAULT_PRODUCT_APPROVAL_CRITICAL_FIELDS;
        }
    }

    private function normalize(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function variantStructureComparison(Product $product, array $validated): array
    {
        if (($validated['type'] ?? $product->type) !== 'variant') {
            return [
                'changed' => false,
                'current' => [],
                'submitted' => [],
            ];
        }

        $incoming = json_decode((string) ($validated['variants_json'] ?? '[]'), true);
        if (! is_array($incoming)) {
            return [
                'changed' => false,
                'current' => [],
                'submitted' => [],
            ];
        }

        $currentSignature = $product->variants()
            ->with('attributes:id,product_variant_id,global_attribute_id,global_attribute_value_id')
            ->get()
            ->map(fn ($variant) => $this->variantSignature(
                (string) $variant->title,
                $variant->attributes->map(fn ($attribute) => [
                    'attribute_id' => $attribute->global_attribute_id,
                    'value_id' => $attribute->global_attribute_value_id,
                ])->all(),
            ))
            ->sort()
            ->values()
            ->all();

        $incomingSignature = collect($incoming)
            ->map(fn (array $variant) => $this->variantSignature(
                (string) ($variant['title'] ?? ''),
                is_array($variant['attributes'] ?? null) ? $variant['attributes'] : [],
            ))
            ->sort()
            ->values()
            ->all();

        return [
            'changed' => $currentSignature !== $incomingSignature,
            'current' => $currentSignature,
            'submitted' => $incomingSignature,
        ];
    }

    private function variantSignature(string $title, array $attributes): string
    {
        $pairs = collect($attributes)
            ->map(fn (array $attribute) => (int) ($attribute['attribute_id'] ?? 0).':'.(int) ($attribute['value_id'] ?? 0))
            ->sort()
            ->values()
            ->implode('|');

        return trim($title).'|'.$pairs;
    }

    private function variantImageComparison(Request $request, array $validated): array
    {
        $uploadedFields = collect(array_keys($request->allFiles()))
            ->filter(fn ($key) => str_starts_with((string) $key, 'variant_image'))
            ->values()
            ->all();

        $variants = json_decode((string) ($validated['variants_json'] ?? '[]'), true);
        $removedVariantIds = is_array($variants)
            ? collect($variants)
                ->filter(fn ($variant) => ! empty($variant['remove_image']))
                ->map(fn ($variant) => $variant['id'] ?? null)
                ->filter()
                ->values()
                ->all()
            : [];

        return [
            'changed' => $uploadedFields !== [] || $removedVariantIds !== [],
            'uploaded_fields' => $uploadedFields,
            'removed_variant_ids' => $removedVariantIds,
        ];
    }

    private function diagnosticValue(mixed $value): array
    {
        $normalized = $this->normalize($value);

        return [
            'value' => Str::limit($normalized, 5000, ''),
            'length' => strlen($normalized),
            'sha256' => hash('sha256', $normalized),
        ];
    }

    private function logDecision(Product $product, Category $category, string $from, array $criticalChanges, string $decision): void
    {
        Log::info('product_approval_decision', [
            'product_id' => $product->id,
            'seller_id' => $product->seller_id,
            'category_id' => $category->id,
            'category_requires_approval' => (bool) $category->requires_approval,
            'from_verification_status' => $from,
            'to_verification_status' => (string) $product->verification_status,
            'product_status' => (string) $product->status,
            'critical_changes' => $criticalChanges,
            'decision' => $decision,
        ]);
    }
}
