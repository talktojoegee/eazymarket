<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Store API resource — post hyperlocal-to-ecommerce migration.
 *
 * Dropped fields (mobile clients need a build that no longer reads these):
 *   - bank_*           → moved to Seller (see SellerResource)
 *   - address/city/landmark/state/country/country_code/zipcode/lat/lng/distance
 *                      → moved to StoreLocation (Block 1B)
 *   - address_proof, voided_check
 *                      → moved to Seller as Spatie media collections
 *   - zones, same_location
 *                      → delivery zones retired; visibility is market-driven
 *   - max_delivery_distance, order_preparation_time, delivery_policy,
 *     domestic_shipping_charges, international_shipping_charges, timing,
 *     fulfillment_type, status
 *                      → hyperlocal-only; shipping config now lives on
 *                        shipping_profile.shipping_zones
 */
class StoreResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'product_count' => $this->product_count ?? 0,
            'description' => $this->description,
            'contact_number' => $this->contact_number,
            'contact_email' => $this->contact_email,
            'seller_id' => $this->seller_id,
            'tax_name' => $this->tax_name,
            'tax_number' => $this->tax_number,
            'currency_code' => $this->currency_code,
            'promotional_text' => $this->promotional_text,
            'about_us' => $this->about_us,
            'return_replacement_policy' => $this->return_replacement_policy,
            'refund_policy' => $this->refund_policy,
            'terms_and_conditions' => $this->terms_and_conditions,
            'metadata' => $this->metadataWithSeo(),
            'logo' => $this->store_logo,
            'banner' => $this->store_banner,
            'avg_products_rating' => number_format($this->avg_products_rating ?? 0, 2),
            'avg_store_rating' => number_format($this->avg_store_rating ?? 0, 2),
            'total_store_feedback' => $this->total_store_feedback ?? "0",
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'verification_status' => $this->verification_status,
            'visibility_status' => $this->visibility_status,
            'is_recommended' => (bool) $this->is_recommended,
            'markets' => $this->whenLoaded('markets', function () {
                return $this->markets->map(fn ($m) => [
                    'id' => $m->id,
                    'code' => $m->code,
                    'name' => $m->name,
                ]);
            }),
            'locations' => $this->whenLoaded('locations'),
        ];
    }
}
