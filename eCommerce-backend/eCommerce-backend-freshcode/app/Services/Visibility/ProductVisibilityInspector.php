<?php

namespace App\Services\Visibility;

use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Models\Product;

/**
 * Explains *why* a product is or isn't visible in the customer frontend.
 *
 * Returns the canonical inspector shape:
 *   [
 *     'status' => 'live' | 'partial' | 'hidden',
 *     'checks' => [{key, state, label, message, fix?}, ...],
 *   ]
 *
 * Mirrors the customer-side product gate: status=active + verification_status=approved
 * + at least one variant with stock in a store. The platform sells across markets
 * (price auto-converted via FX); there is no per-zone reachability gate.
 */
class ProductVisibilityInspector
{
    public function inspect(Product $product): array
    {
        $product->loadMissing('variants.storeProductVariants:id,product_variant_id,store_id');

        $checks = [
            $this->checkStatus($product),
            $this->checkVerification($product),
            $this->checkVariants($product),
            $this->checkMainImage($product),
        ];

        return [
            'status' => $this->rollupStatus($checks),
            'checks' => $checks,
        ];
    }

    protected function checkStatus(Product $product): array
    {
        $isActive = $product->status === ProductStatusEnum::ACTIVE();

        return [
            'key' => 'status',
            'state' => $isActive ? 'pass' : 'fail',
            'label' => __('labels.status'),
            'message' => $isActive
                ? __('labels.visibility_product_status_active')
                : __('labels.visibility_product_status_inactive'),
            'fix' => $isActive ? null : __('labels.visibility_product_status_fix'),
        ];
    }

    protected function checkVerification(Product $product): array
    {
        $isApproved = $product->verification_status === ProductVarificationStatusEnum::APPROVED();

        return [
            'key' => 'verification',
            'state' => $isApproved ? 'pass' : 'fail',
            'label' => __('labels.verification_status'),
            'message' => $isApproved
                ? __('labels.visibility_product_verification_ok')
                : __('labels.visibility_product_verification_pending', ['status' => $product->verification_status]),
            'fix' => $isApproved ? null : __('labels.visibility_product_verification_fix'),
        ];
    }

    /**
     * Two checks rolled up: are there any variants? Of those variants, do any
     * have at least one store_product_variants row anywhere?
     */
    protected function checkVariants(Product $product): array
    {
        $totalVariants = $product->variants->count();
        if ($totalVariants === 0) {
            return [
                'key' => 'variants',
                'state' => 'fail',
                'label' => __('labels.visibility_product_variants_label'),
                'message' => __('labels.visibility_product_no_variants'),
                'fix' => __('labels.visibility_product_no_variants_fix'),
            ];
        }

        $stockedVariants = $product->variants->filter(
            fn($v) => $v->storeProductVariants->isNotEmpty(),
        )->count();

        if ($stockedVariants === 0) {
            return [
                'key' => 'variants',
                'state' => 'fail',
                'label' => __('labels.visibility_product_variants_label'),
                'message' => __('labels.visibility_product_unstocked', ['total' => $totalVariants]),
                'fix' => __('labels.visibility_product_unstocked_fix'),
            ];
        }

        return [
            'key' => 'variants',
            'state' => 'pass',
            'label' => __('labels.visibility_product_variants_label'),
            'message' => __('labels.visibility_product_variants_ok', [
                'stocked' => $stockedVariants,
                'total' => $totalVariants,
            ]),
            'fix' => null,
        ];
    }

    protected function checkMainImage(Product $product): array
    {
        $hasImage = ! empty($product->main_image)
            && ! str_ends_with($product->main_image, 'product-placeholder.jpg');

        return [
            'key' => 'main_image',
            'state' => $hasImage ? 'pass' : 'warn',
            'label' => __('labels.main_image'),
            'message' => $hasImage
                ? __('labels.visibility_product_image_ok')
                : __('labels.visibility_product_image_missing'),
            'fix' => $hasImage ? null : __('labels.visibility_product_image_missing_fix'),
        ];
    }

    protected function rollupStatus(array $checks): string
    {
        foreach ($checks as $check) {
            if ($check['state'] === 'fail') {
                return 'hidden';
            }
        }

        foreach ($checks as $check) {
            if ($check['state'] === 'warn') {
                return 'partial';
            }
        }

        return 'live';
    }
}
