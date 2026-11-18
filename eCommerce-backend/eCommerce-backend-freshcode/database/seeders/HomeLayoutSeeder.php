<?php

namespace Database\Seeders;

use App\Enums\HomePage\BackgroundTypeEnum;
use App\Enums\HomePage\AppProductCardStyleEnum;
use App\Enums\HomePage\BrandStyleEnum;
use App\Enums\HomePage\CategoryStyleEnum;
use App\Enums\HomePage\HeroItemStyleEnum;
use App\Enums\HomePage\HeroLayoutEnum;
use App\Enums\HomePage\HeroMediaTypeEnum;
use App\Enums\HomePage\HomeSectionTypeEnum;
use App\Enums\HomePage\ItemPurposeEnum;
use App\Enums\HomePage\HomePagePlatformEnum;
use App\Enums\HomePage\ItemSourceEnum;
use App\Enums\HomePage\OrientationEnum;
use App\Enums\HomePage\ProductSourceEnum;
use App\Enums\HomePage\SourceModeEnum;
use App\Enums\HomePageScopeEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomeLayout;
use App\Models\HomeSection;
use App\Models\Product;
use Illuminate\Database\Seeder;

class HomeLayoutSeeder extends Seeder
{
    /**
     * Seed one global home layout with a section of each type, mirroring the
     * worked example in docs/home-page-builder-plan.md. Idempotent.
     */
    public function run(): void
    {
        $layout = HomeLayout::updateOrCreate(
            ['platform' => HomePagePlatformEnum::APP(), 'scope_type' => HomePageScopeEnum::GLOBAL(), 'scope_id' => null],
            ['is_active' => true],
        );

        // Re-running should rebuild cleanly.
        $layout->sections()->each(fn (HomeSection $section) => $section->delete());

        $categories = Category::query()->take(6)->get();
        $brands     = Brand::query()->take(6)->get();
        $products   = Product::query()->take(6)->get();

        $sort = 0;

        // 1) Hero — category items, image + title
        $hero = $layout->sections()->create([
            'type'       => HomeSectionTypeEnum::HERO(),
            'style'      => HeroItemStyleEnum::IMAGE_TITLE(),
            'config'     => [
                'media_type'    => HeroMediaTypeEnum::IMAGE(),
                'layout'        => HeroLayoutEnum::WITH_ITEMS(),
                'item_source'   => ItemSourceEnum::CATEGORIES(),
                'card_bg_type'  => 'color',
                'card_bg_color' => '#F1EFE8',
            ],
            'sort_order' => $sort++,
        ]);
        foreach ($categories as $i => $category) {
            $hero->items()->create([
                'purpose'       => ItemPurposeEnum::DISPLAY(),
                'itemable_type' => Category::class,
                'itemable_id'   => $category->id,
                'title'         => $category->title,
                'sort_order'    => $i,
            ]);
        }

        // 2) Products — top rated, horizontal
        $layout->sections()->create([
            'type'       => HomeSectionTypeEnum::PRODUCTS(),
            'title'      => 'Trending now',
            'style'      => AppProductCardStyleEnum::QUICK_ADD(),
            'config'     => [
                'source'          => ProductSourceEnum::TOP_RATED(),
                'orientation'     => OrientationEnum::HORIZONTAL(),
                'background_type' => BackgroundTypeEnum::NONE(),
            ],
            'sort_order' => $sort++,
        ]);

        // 3) Products — custom pick, vertical
        $custom = $layout->sections()->create([
            'type'       => HomeSectionTypeEnum::PRODUCTS(),
            'title'      => 'Picked for you',
            'style'      => AppProductCardStyleEnum::QUICK_ADD(),
            'config'     => [
                'source'           => ProductSourceEnum::CUSTOM(),
                'orientation'      => OrientationEnum::VERTICAL(),
                'background_type'  => BackgroundTypeEnum::COLOR(),
                'background_color' => '#EAF3DE',
            ],
            'sort_order' => $sort++,
        ]);
        foreach ($products as $i => $product) {
            $custom->items()->create([
                'purpose'       => ItemPurposeEnum::DISPLAY(),
                'itemable_type' => Product::class,
                'itemable_id'   => $product->id,
                'sort_order'    => $i,
            ]);
        }

        // 4) Categories — custom pick, default cards
        $categoriesSection = $layout->sections()->create([
            'type'       => HomeSectionTypeEnum::CATEGORIES(),
            'title'      => 'Shop by category',
            'style'      => CategoryStyleEnum::Default->value,
            'config'     => ['source' => SourceModeEnum::CUSTOM()],
            'sort_order' => $sort++,
        ]);
        foreach ($categories as $i => $category) {
            $categoriesSection->items()->create([
                'purpose'       => ItemPurposeEnum::DISPLAY(),
                'itemable_type' => Category::class,
                'itemable_id'   => $category->id,
                'title'         => $category->title,
                'sort_order'    => $i,
            ]);
        }

        // 5) Brands — custom pick, image + title
        $brandsSection = $layout->sections()->create([
            'type'       => HomeSectionTypeEnum::BRANDS(),
            'title'      => 'Top brands',
            'style'      => BrandStyleEnum::IMAGE_TITLE(),
            'config'     => ['source' => SourceModeEnum::CUSTOM()],
            'sort_order' => $sort++,
        ]);
        foreach ($brands as $i => $brand) {
            $brandsSection->items()->create([
                'purpose'       => ItemPurposeEnum::DISPLAY(),
                'itemable_type' => Brand::class,
                'itemable_id'   => $brand->id,
                'sort_order'    => $i,
            ]);
        }

        $playImage = $layout->sections()->create([
            'type'       => HomeSectionTypeEnum::PLAY_IMAGE(),
            'style'      => 'scroll',
            'config'     => [
                'rows' => [[
                    'style'               => 'scroll',
                    'container_width'     => 'container',
                    'outer_padding'       => 0,
                    'outer_padding_custom' => '',
                    'grid_gap'            => 0,
                    'border_radius'       => 0,
                    'hover_effect'        => 'none',
                    'items_to_show'       => 1,
                    'items_to_show_mobile' => 1,
                    'show_scroll_buttons' => 1,
                    'show_bullets'        => 0,
                    'auto_scroll'         => 0,
                    'auto_scroll_time'    => 3,
                ]],
            ],
            'sort_order' => $sort++,
        ]);
        $itemSort = 0;
        if ($product = $products->first()) {
            $playImage->items()->create([
                'purpose'       => ItemPurposeEnum::DISPLAY(),
                'itemable_type' => Product::class,
                'itemable_id'   => $product->id,
                'sort_order'    => $itemSort++,
                'config'        => $this->playImageItemConfig(),
            ]);
        }
        if ($category = $categories->first()) {
            $playImage->items()->create([
                'purpose'       => ItemPurposeEnum::DISPLAY(),
                'itemable_type' => Category::class,
                'itemable_id'   => $category->id,
                'sort_order'    => $itemSort++,
                'config'        => $this->playImageItemConfig(),
            ]);
        }
        $playImage->items()->create([
            'purpose'    => ItemPurposeEnum::DISPLAY(),
            'link_url'   => 'https://example.com/promo',
            'sort_order' => $itemSort++,
            'config'     => $this->playImageItemConfig(),
        ]);
    }

    private function playImageItemConfig(): array
    {
        return [
            'row_index'   => 0,
            'cols'        => 'auto',
            'cols_mobile' => 'auto',
            'aspect_ratio' => 'original',
            'media_type'  => 'image',
            'alt'         => '',
        ];
    }
}
