<?php

namespace App\Http\Resources\Seller;

use App\Traits\FormatsOrderMoney;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One addon line on an order item, as the seller app reads it. Mirrors the
 * shape `OrderResource::formatOrderItemAddons()` emits, plus `price_formatted`
 * rendered in the order's own currency.
 *
 * The currency descriptor comes from `PricePresenter::payload()` and must be
 * passed by the caller — an addon row has no path to its order's market on its
 * own, and resolving one per row would be an N+1.
 */
class OrderItemAddonResource extends JsonResource
{
    use FormatsOrderMoney;

    /**
     * @param array{code:string,symbol:string,format:array<string,mixed>}|null $currency
     */
    public function __construct($resource, private readonly ?array $currency = null)
    {
        parent::__construct($resource);
    }

    /**
     * @param iterable<int, mixed> $addons
     * @param array{code:string,symbol:string,format:array<string,mixed>}|null $currency
     * @return array<int, array<string, mixed>>
     */
    public static function listFor($addons, ?array $currency, Request $request): array
    {
        return collect($addons)
            ->map(fn ($addon) => (new self($addon, $currency))->toArray($request))
            ->values()
            ->all();
    }

    public function toArray(Request $request): array
    {
        $group = $this->addonGroup;
        $item = $this->addonItem;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'addon_group_id' => $this->addon_group_id,
            'addon_item_id' => $this->addon_item_id,
            'price' => (float) $this->price,
            'price_formatted' => $this->formatOrderMoney((float) $this->price, $this->currency),
            'group' => $group ? [
                'id' => $group->id,
                'title' => $group->title,
                'selection_type' => $group->selection_type?->value,
                'is_required' => (bool) $group->is_required,
            ] : null,
            'item' => $item ? [
                'id' => $item->id,
                'title' => $item->title,
                'indicator' => $item->indicator?->value,
            ] : null,
        ];
    }
}
