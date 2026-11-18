<?php

namespace App\Services;

use App\Enums\HomePage\BackgroundTypeEnum;
use App\Enums\HomePage\AppProductCardStyleEnum;
use App\Enums\HomePage\BrandStyleEnum;
use App\Enums\HomePage\CategoryStyleEnum;
use App\Enums\HomePage\HeroItemStyleEnum;
use App\Enums\HomePage\HeroLayoutEnum;
use App\Enums\HomePage\HeroMediaTypeEnum;
use App\Enums\HomePage\HomePagePlatformEnum;
use App\Enums\HomePage\HomeSectionTypeEnum;
use App\Enums\HomePage\ItemSourceEnum;
use App\Enums\HomePage\OrientationEnum;
use App\Enums\HomePage\ProductCardStyleEnum;
use App\Enums\HomePage\ProductSourceEnum;
use App\Enums\HomePage\SourceModeEnum;

/**
 * Single source of truth for which section types, styles, and config values each
 * platform's home builder may use. Both the builder UI (HomeBuilderController)
 * and the save validation (SaveHomeLayoutRequest) read from here, so the two can
 * never drift apart.
 *
 * App and web share the same enums, with platform-specific values narrowed below.
 */
class HomeBuilderOptions
{
    /**
     * Per-section spec for a platform. Each type maps to its allowed `style`
     * values (null = the type has no style picker) and its `config` field =>
     * allowed-values map.
     *
     * @return array<string, array{styles: ?array<int, string>, config: array<string, array<int, string>>}>
     */
    public function specsFor(string $platform): array
    {
        $specs = [
            HomeSectionTypeEnum::HERO() => [
                'styles' => HeroItemStyleEnum::values(),
                'config' => [
                    'media_type'  => HeroMediaTypeEnum::values(),
                    'layout'      => HeroLayoutEnum::values(),
                    'item_source' => ItemSourceEnum::values(),
                ],
            ],
            HomeSectionTypeEnum::PRODUCTS() => [
                'styles' => null,
                'config' => [
                    'source'          => ProductSourceEnum::values(),
                    'orientation'     => OrientationEnum::values(),
                    'background_type' => BackgroundTypeEnum::values(),
                ],
            ],
            HomeSectionTypeEnum::CATEGORIES() => [
                'styles' => CategoryStyleEnum::values(),
                'config' => ['source' => SourceModeEnum::values()],
            ],
            HomeSectionTypeEnum::BRANDS() => [
                'styles' => BrandStyleEnum::values(),
                'config' => ['source' => SourceModeEnum::values()],
            ],
            HomeSectionTypeEnum::PLAY_IMAGE() => [
                'styles' => ['grid', 'scroll'],
                'config' => [
                    'hover_effect'  => ['none', 'zoom', 'lift', 'opacity'],
                ],
            ],
        ];

        if ($platform === HomePagePlatformEnum::APP()) {
            $specs[HomeSectionTypeEnum::PRODUCTS()]['styles'] = AppProductCardStyleEnum::values();
            // These map one-to-one to the Flutter CategoryCardStyle widgets:
            // imageCards, overlayTitle and grid. The legacy web-only `card`
            // value has no distinct app renderer.
            $specs[HomeSectionTypeEnum::CATEGORIES()]['styles'] = [
                CategoryStyleEnum::FULL(),
                CategoryStyleEnum::OVERLAY(),
                CategoryStyleEnum::Default->value,
            ];
            $specs[HomeSectionTypeEnum::CATEGORIES()]['config']['source'] = [SourceModeEnum::CUSTOM()];
            $specs[HomeSectionTypeEnum::BRANDS()]['config']['source'] = [SourceModeEnum::CUSTOM()];
        } elseif ($platform === HomePagePlatformEnum::WEB()) {
            unset($specs[HomeSectionTypeEnum::HERO()]);
            $specs[HomeSectionTypeEnum::PRODUCTS()]['styles'] = ProductCardStyleEnum::values();
        }

        return $specs;
    }

    /** Section type values available to a platform, in builder display order. */
    public function sectionTypes(string $platform): array
    {
        return array_keys($this->specsFor($platform));
    }

    /** Allowed `style` values for a section type, or null when it has no style picker. */
    public function stylesFor(string $platform, string $type): ?array
    {
        return $this->specsFor($platform)[$type]['styles'] ?? null;
    }

    /** Allowed values for a single config field, or null when the field is unconstrained. */
    public function configValues(string $platform, string $type, string $field): ?array
    {
        return $this->specsFor($platform)[$type]['config'][$field] ?? null;
    }

    /**
     * Option bundle consumed by the builder UI (value lists per concern).
     * Platform-keyed so web can expose a different palette later.
     */
    public function uiOptions(string $platform): array
    {
        return [
            'platform'        => $platform,
            'section_types'   => $this->sectionTypes($platform),
            'hero_media'      => HeroMediaTypeEnum::values(),
            'hero_layout'     => HeroLayoutEnum::values(),
            'item_source'     => ItemSourceEnum::values(),
            'hero_item_style' => HeroItemStyleEnum::values(),
            'product_source'  => ProductSourceEnum::values(),
            'product_card_style' => $this->stylesFor($platform, HomeSectionTypeEnum::PRODUCTS()),
            'orientation'     => OrientationEnum::values(),
            'background_type' => BackgroundTypeEnum::values(),
            'source_mode'     => $this->configValues($platform, HomeSectionTypeEnum::CATEGORIES(), 'source'),
            'category_style'  => $this->stylesFor($platform, HomeSectionTypeEnum::CATEGORIES()),
            'brand_style'     => BrandStyleEnum::values(),
            'hover_effects'   => ['none', 'zoom', 'lift', 'opacity'],
        ];
    }
}
