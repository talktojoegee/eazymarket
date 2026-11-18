<?php

namespace App\Http\Resources\HomeBuilder;

use App\Enums\HomePage\HomeSectionTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class HomeBuilderSectionResource extends JsonResource
{
    public const array CONFIG_KEYS = [
        'hero' => ['media_type', 'layout', 'item_source', 'card_bg_type', 'card_bg_color', 'card_title_color'],
        'products' => ['source', 'orientation', 'background_type', 'background_color', 'limit'],
        'categories' => ['source', 'background_type', 'background_color'],
        'brands' => ['source'],
        'play_image' => ['rows', 'outer_padding', 'grid_gap', 'border_radius', 'hover_effect'],
    ];

    public function toArray(Request $request): array
    {
        $type = (string) $this->type;
        $config = Arr::except($this->config ?? [], ['show_title']);
        $data = [
            'id' => $this->id,
            'type' => $type,
            'title' => $this->title,
            'show_title' => (bool) $this->show_title,
            'config' => Arr::only($config, self::CONFIG_KEYS[$type] ?? []),
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'market_ids' => $this->markets->pluck('id')->all(),
            'market_positions' => $this->marketOrders
                ->sortBy('market_id')
                ->map(fn ($position) => [
                    'market_id' => (int) $position->market_id,
                    'sort_order' => (int) $position->sort_order,
                ])->values()->all(),
            'items' => HomeBuilderSectionItemResource::collection($this->items)->resolve($request),
        ];

        if (in_array($type, [
            HomeSectionTypeEnum::HERO(),
            HomeSectionTypeEnum::PRODUCTS(),
            HomeSectionTypeEnum::CATEGORIES(),
            HomeSectionTypeEnum::BRANDS(),
        ], true)) {
            $data['style'] = $this->style;
        }

        if ($type === HomeSectionTypeEnum::HERO()) {
            $data['hero_media'] = $this->hero_media;
            $data['card_background_image'] = $this->card_background_image;
        }

        if (in_array($type, [HomeSectionTypeEnum::PRODUCTS(), HomeSectionTypeEnum::CATEGORIES()], true)) {
            $data['background_image'] = $this->background_image;
        }

        return $data;
    }
}
