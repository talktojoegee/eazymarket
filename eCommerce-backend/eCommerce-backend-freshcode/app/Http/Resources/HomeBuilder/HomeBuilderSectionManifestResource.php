<?php

namespace App\Http\Resources\HomeBuilder;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class HomeBuilderSectionManifestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = (string) $this->type;
        $config = Arr::except($this->config ?? [], ['show_title']);

        return [
            'id' => $this->id,
            'type' => $type,
            'title' => $this->title,
            'show_title' => (bool) $this->show_title,
            'style' => $this->style,
            'config' => Arr::only($config, HomeBuilderSectionResource::CONFIG_KEYS[$type] ?? []),
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'item_count' => (int) ($this->items_count ?? 0),
            'market_ids' => $this->markets->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'market_positions' => $this->marketOrders
                ->sortBy('market_id')
                ->map(fn ($position) => [
                    'market_id' => (int) $position->market_id,
                    'sort_order' => (int) $position->sort_order,
                ])->values()->all(),
        ];
    }
}
