<?php

namespace App\Http\Resources\HomePage;

use App\Enums\HomePage\HomeSectionTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class HomeSectionResource extends JsonResource
{
    private const array CONFIG_KEYS = [
        'hero' => ['media_type', 'layout', 'item_source', 'card_bg_type', 'card_bg_color', 'card_title_color'],
        'products' => ['source', 'orientation', 'background_type', 'background_color', 'limit'],
        'categories' => ['source', 'background_type', 'background_color'],
        'brands' => ['source'],
        'play_image' => ['rows', 'outer_padding', 'grid_gap', 'border_radius', 'hover_effect'],
    ];

    private const array CONTENT_KEYS = [
        'hero' => 'items',
        'products' => 'products',
        'categories' => 'categories',
        'brands' => 'brands',
        'play_image' => 'rows',
    ];

    public function toArray(Request $request): array
    {
        $section = $this->resource;
        $type = (string) $section['type'];
        $contentKey = self::CONTENT_KEYS[$type] ?? null;
        $content = $section['content'] ?? [];

        $data = [
            'id' => $section['id'],
            'type' => $type,
            'title' => $section['title'] ?? null,
            'config' => Arr::only(
                Arr::except($section['config'] ?? [], ['show_title']),
                self::CONFIG_KEYS[$type] ?? [],
            ),
            'content' => $contentKey ? [$contentKey => $content[$contentKey] ?? []] : [],
        ];

        if (in_array($type, [
            HomeSectionTypeEnum::HERO(),
            HomeSectionTypeEnum::PRODUCTS(),
            HomeSectionTypeEnum::CATEGORIES(),
            HomeSectionTypeEnum::BRANDS(),
        ], true)) {
            $data['style'] = $section['style'] ?? null;
        }

        if ($type === HomeSectionTypeEnum::HERO()) {
            $data['hero_media'] = $section['hero_media'] ?? null;
            $data['card_background_image'] = $section['card_background_image'] ?? null;
        }

        if (in_array($type, [HomeSectionTypeEnum::PRODUCTS(), HomeSectionTypeEnum::CATEGORIES()], true)) {
            $data['background_image'] = $section['background_image'] ?? null;
        }

        return $data;
    }
}
