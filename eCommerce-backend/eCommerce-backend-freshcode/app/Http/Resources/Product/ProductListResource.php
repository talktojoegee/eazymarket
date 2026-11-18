<?php

namespace App\Http\Resources\Product;

use App\Enums\Product\ProductAttachmentModeEnum;
use App\Models\Review;
use App\Services\FulfillmentRouterService;
use App\Support\PricePresenter;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $reviews = Review::scopeProductRatingStats($this->id);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'seller_id' => $this->seller_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'type' => $this->type,
            'is_kit' => $this->type === 'kit',
            'short_description' => $this->short_description,
            'is_combo_pack' => (bool) data_get($this->metadata, 'is_combo_pack', false),
            'combo_contents' => $this->comboContents(),
            'category' => $this->category?->slug,
            'brand' => $this->brand?->slug,
            'category_name' => $this->category?->title,
            'brand_name' => $this->brand?->title,
            'product_condition' => $this->productCondition ? [
                'title' => $this->productCondition->title,
                'slug' => $this->productCondition->slug,
            ] : null,
            'seller' => $this->seller?->user->name ?? "N/A",
            'indicator' => $this->indicator,
            'favorite' => $this->favorite,
            'estimated_delivery_time' => $this->estimated_delivery_time,
            'shipping_details' => $this->when(
                $request?->boolean('include_shipping'),
                fn () => $this->resolveShippingDetails($request)
            ),
            'shipping_options' => $this->when(
                $request?->boolean('include_shipping'),
                fn () => $this->resolveShippingOptions($request)
            ),
            'base_prep_time' => $this->base_prep_time ?? 0,
            'ratings' => $reviews['average_rating'] ?? "0",
            'rating_count' => $reviews['total_reviews'] ?? 0,
            'main_image' => $this->main_image,
            'image_fit' => $this->image_fit,
            'item_count_in_cart' => $this->itemCountInCart ?? 0,
            'is_save_for_later' => $this->IsSaveForLater ?? false,
            'additional_images' => $this->additional_images,
            'minimum_order_quantity' => $this->minimum_order_quantity,
            'quantity_step_size' => $this->quantity_step_size,
            'total_allowed_quantity' => $this->total_allowed_quantity,
            'is_returnable' => (float)$this->is_returnable,
            'returnable_days' => $this->returnable_days,
            'allow_cod' => (bool)($this->allow_cod ?? '1'),
            'is_attachment_required' => (float)$this->is_attachment_required,
            'attachment_mode' => $this->attachment_mode ?? ProductAttachmentModeEnum::REQUIRED(),
            'requires_otp' => (float)$this->requires_otp,
            'tags' => $this->tags,
            'warranty_period' => $this->warranty_period,
            'guarantee_period' => $this->guarantee_period,
            'made_in' => $this->made_in,
            'is_inclusive_tax' => $this->is_inclusive_tax,
            'video_type' => $this->video_type,
            'video_link' => $this->video_link,
            'status' => $this->status,
            'featured' => $this->featured,
            'badge'    => $this->badge ? [
                'id'           => $this->badge->id,
                'label'        => $this->badge->label,
                'bg_color'     => $this->badge->bg_color,
                'text_color'   => $this->badge->text_color,
                'border_color' => $this->badge->border_color,
            ] : null,
            'metadata' => $this->metadataWithSeo(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'attributes' => $this->getFormattedVariantAttributes(),
            'is_sponsored' => (bool) ($this->is_sponsored ?? false),
            'campaign_id'  => $this->campaign_id ?? null,
            'visitor_key'  => $this->visitor_key ?? null,
        ];
    }

    protected function resolveShippingDetails($request): ?array
    {
        $country = $request?->input('country_iso2');
        if (empty($country)) {
            return null;
        }

        $offer = app(FulfillmentRouterService::class)
            ->resolveProductShippingOffer($this->resource, (string) $country);
        if (! $offer) {
            return null;
        }

        $market = $request?->attributes->get('market');
        $price = $market instanceof \App\Models\Market
            ? PricePresenter::forMarket($offer['rate'], $market)
            : PricePresenter::forCurrent($offer['rate']);

        return [
            'country' => $offer['country'],
            'rate' => $price['price'],
            'currency_code' => $price['currency_code'],
            'eta_min' => $offer['eta_min'],
            'eta_max' => $offer['eta_max'],
            'eta_unit' => $offer['eta_unit'],
        ];
    }

    protected function comboContents(): array
    {
        if (! data_get($this->metadata, 'is_combo_pack', false)) {
            return [];
        }

        return $this->comboPack?->items()->with(['product:id,title', 'variant:id,title'])->get()
            ->map(fn ($item) => [
                'product_id' => (int) $item->product_id,
                'product_title' => $item->product?->title,
                'variant_id' => (int) $item->product_variant_id,
                'variant_title' => $item->variant?->title,
                'quantity' => (int) $item->quantity,
            ])->all() ?? [];
    }

    protected function resolveShippingOptions($request): array
    {
        $countries = $request?->input('country_iso2s', []);
        if (! is_array($countries) || $countries === []) {
            return [];
        }

        $offers = app(FulfillmentRouterService::class)
            ->resolveProductShippingOffers($this->resource, $countries);
        $market = $request?->attributes->get('market');

        return array_map(function (array $offer) use ($market) {
            $price = $market instanceof \App\Models\Market
                ? PricePresenter::forMarket($offer['rate'], $market)
                : PricePresenter::forCurrent($offer['rate']);

            return [
                'country' => $offer['country'],
                'rate' => $price['price'],
                'currency_code' => $price['currency_code'],
                'eta_min' => $offer['eta_min'],
                'eta_max' => $offer['eta_max'],
                'eta_unit' => $offer['eta_unit'],
            ];
        }, $offers);
    }
}
