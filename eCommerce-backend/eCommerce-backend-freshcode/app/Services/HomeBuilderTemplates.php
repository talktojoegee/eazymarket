<?php

namespace App\Services;

use App\Enums\HomePage\HomePagePlatformEnum;

class HomeBuilderTemplates
{
    public function sectionTemplates(string $platform): array
    {
        return $platform === HomePagePlatformEnum::WEB()
            ? $this->webTemplates()
            : $this->appTemplates();
    }

    public function layoutThemes(string $platform): array
    {
        $themes = $platform === HomePagePlatformEnum::WEB()
            ? [
                $this->theme('marketplace_superstore', 'Marketplace Superstore', 'A balanced storefront with category discovery, products, brands, and campaigns.', 'Recommended', [
                    'categories_overlay', 'products_standard_carousel', 'campaign_split', 'brands_logo_name',
                ]),
                $this->theme('flash_sale', 'Flash Sale', 'A promotion-first layout for urgent deals and product discovery.', 'Sales', [
                    'campaign_full_width', 'products_compact_deals', 'categories_card_tiles', 'campaign_offer_grid',
                ]),
                $this->theme('brand_showcase', 'Brand Showcase', 'An editorial layout led by partner brands and curated collections.', 'Editorial', [
                    'campaign_editorial_mosaic', 'brands_logo_grid', 'products_showcase', 'categories_full_image',
                ]),
                $this->theme('minimal_storefront', 'Minimal Storefront', 'A clean, product-led layout with restrained visual density.', 'Minimal', [
                    'categories_compact_grid', 'products_minimal_carousel', 'campaign_story_carousel',
                ]),
            ]
            : [
                $this->theme('marketplace_superstore', 'Marketplace Superstore', 'A balanced mobile storefront with hero discovery, products, categories, and brands.', 'Recommended', [
                    'hero_category_launchpad', 'categories_compact_grid', 'products_quick_add_carousel', 'brands_logo_name',
                ]),
                $this->theme('flash_sale', 'Flash Sale', 'A promotion-first mobile layout for urgent deals and quick add shopping.', 'Sales', [
                    'hero_campaign_banner', 'products_quick_add_grid', 'campaign_offer_grid', 'categories_overlay',
                ]),
                $this->theme('brand_showcase', 'Brand Showcase', 'An image-led mobile layout for brands and curated collections.', 'Editorial', [
                    'hero_immersive_collection', 'brands_logo_grid', 'campaign_editorial_mosaic', 'products_catalog_carousel',
                ]),
                $this->theme('minimal_storefront', 'Minimal Storefront', 'A clean mobile layout with compact discovery sections.', 'Minimal', [
                    'hero_animated_campaign', 'categories_image_cards', 'products_catalog_grid', 'campaign_story_carousel',
                ]),
            ];

        return $themes;
    }

    private function appTemplates(): array
    {
        return array_merge([
            $this->template('hero_campaign_banner', 'hero', 'Campaign Banner', 'Full-width image campaign with no promotional cards.', 'ti-photo', 'Hero', 'full', [
                'media_type' => 'image', 'layout' => 'none', 'item_source' => 'categories',
            ]),
            $this->template('hero_animated_campaign', 'hero', 'Animated Campaign', 'Full-width Lottie campaign with no promotional cards.', 'ti-player-play', 'Animated', 'full', [
                'media_type' => 'lottie', 'layout' => 'none', 'item_source' => 'categories',
            ]),
            $this->template('hero_category_launchpad', 'hero', 'Category Launchpad', 'Hero background with compact image-and-title discovery cards.', 'ti-category', 'Popular', 'image_title', [
                'media_type' => 'image', 'layout' => 'items', 'item_source' => 'categories',
                'card_bg_type' => 'color', 'card_bg_color' => '#F1EFE8', 'card_title_color' => '#222222',
            ]),
            $this->template('hero_immersive_collection', 'hero', 'Immersive Collection', 'Hero background with full-image collection cards.', 'ti-layout-cards', 'Editorial', 'full', [
                'media_type' => 'image', 'layout' => 'items', 'item_source' => 'products',
            ]),
            $this->productTemplate('products_quick_add_carousel', 'Quick Add Carousel', 'Quick Add', 'quick_add', 'horizontal', 'newly_added'),
            $this->productTemplate('products_quick_add_grid', 'Quick Add Grid', 'Quick Add', 'quick_add', 'vertical', 'best_seller'),
            $this->productTemplate('products_catalog_carousel', 'Catalog Carousel', 'Catalog', 'catalog', 'horizontal', 'featured'),
            $this->productTemplate('products_catalog_grid', 'Catalog Grid', 'Catalog', 'catalog', 'vertical', 'recommended'),
            $this->categoryTemplate('categories_compact_grid', 'Compact Category Grid', 'Compact', 'default', 'custom'),
            $this->categoryTemplate('categories_image_cards', 'Category Image Cards', 'Images', 'full', 'custom'),
            $this->categoryTemplate('categories_overlay', 'Category Overlay Cards', 'Overlay', 'overlay', 'custom'),
        ], $this->brandTemplates('custom'), $this->playImageTemplates());
    }

    private function webTemplates(): array
    {
        return array_merge([
            $this->productTemplate('products_standard_carousel', 'Standard Product Carousel', 'Standard', 'standard', 'horizontal', 'newly_added'),
            $this->productTemplate('products_standard_grid', 'Standard Product Grid', 'Standard', 'standard', 'vertical', 'best_seller'),
            $this->productTemplate('products_showcase', 'Showcase Collection', 'Editorial', 'showcase', 'horizontal', 'featured'),
            $this->productTemplate('products_compact_deals', 'Compact Deal Rail', 'Deals', 'compact', 'horizontal', 'best_seller', '#FFF8E1'),
            $this->productTemplate('products_minimal_carousel', 'Minimal Discovery Rail', 'Minimal', 'minimal', 'horizontal', 'recommended'),
            $this->productTemplate('products_minimal_grid', 'Minimal Product Grid', 'Minimal', 'minimal', 'vertical', 'newly_added'),
            $this->categoryTemplate('categories_compact_grid', 'Compact Category Grid', 'Compact', 'default', 'all'),
            $this->categoryTemplate('categories_card_tiles', 'Category Card Tiles', 'Cards', 'card', 'all'),
            $this->categoryTemplate('categories_overlay', 'Category Overlay Tiles', 'Overlay', 'overlay', 'all'),
            $this->categoryTemplate('categories_full_image', 'Full-image Categories', 'Images', 'full', 'all'),
        ], $this->brandTemplates('all'), $this->playImageTemplates());
    }

    private function brandTemplates(string $source): array
    {
        return [
            $this->template('brands_logo_grid', 'brands', 'Brand Logo Grid', 'Clean logo-only partner grid.', 'ti-tag', 'Brands', 'full', ['source' => $source], 'Shop by Brand'),
            $this->template('brands_logo_name', 'brands', 'Logo and Brand Name', 'Partner logo grid with visible brand names.', 'ti-building-store', 'Brands', 'image_title', ['source' => $source], 'Featured Brands'),
        ];
    }

    private function playImageTemplates(): array
    {
        return [
            $this->playImageTemplate('campaign_full_width', 'Full-width Campaign', 'One responsive promotional image spanning the complete row.', 'Campaign', [
                $this->row('grid', 0, 0, 18),
            ], [$this->playItem(0, 12, 12, '16:9')]),
            $this->playImageTemplate('campaign_split', 'Promotional Split', 'Two equal promotional panels that stack naturally on smaller screens.', 'Split', [
                $this->row('grid', 12, 12, 18, 'lift'),
            ], [$this->playItem(0, 6, 12, '4:3'), $this->playItem(0, 6, 12, '4:3')]),
            $this->playImageTemplate('campaign_editorial_mosaic', 'Editorial Mosaic', 'One large feature paired with a smaller supporting visual.', 'Editorial', [
                $this->row('grid', 12, 12, 18, 'zoom'),
            ], [$this->playItem(0, 8, 12, '16:9'), $this->playItem(0, 4, 12, '4:3')]),
            $this->playImageTemplate('campaign_offer_grid', 'Offer Card Grid', 'Four promotional tiles with lift interaction and responsive wrapping.', 'Offers', [
                $this->row('grid', 12, 12, 16, 'lift'),
            ], array_fill(0, 4, $this->playItem(0, 3, 6, '1:1'))),
            $this->playImageTemplate('campaign_partner_carousel', 'Partner Carousel', 'Scrollable partner or logo strip with navigation.', 'Carousel', [
                $this->row('scroll', 12, 12, 14, 'opacity', 4, 2),
            ], array_fill(0, 6, $this->playItem(0, 2, 6, '1:1'))),
            $this->playImageTemplate('campaign_story_carousel', 'Collection Story Carousel', 'Large responsive campaign cards with bullets and autoplay.', 'Story', [
                $this->row('scroll', 12, 12, 18, 'zoom', 3, 1, true),
            ], array_fill(0, 4, $this->playItem(0, 4, 12, '16:9'))),
        ];
    }

    private function productTemplate(string $id, string $title, string $badge, string $style, string $orientation, string $source, ?string $backgroundColor = null): array
    {
        $config = ['source' => $source, 'orientation' => $orientation, 'background_type' => $backgroundColor ? 'color' : 'none'];
        if ($backgroundColor) {
            $config['background_color'] = $backgroundColor;
        }

        return $this->template($id, 'products', $title, 'A ready-made product presentation; select products or filter categories before saving.', 'ti-shopping-bag', $badge, $style, $config, $title);
    }

    private function categoryTemplate(string $id, string $title, string $badge, string $style, string $source): array
    {
        return $this->template($id, 'categories', $title, 'A ready-made category presentation; choose the categories before saving when required.', 'ti-category', $badge, $style, ['source' => $source], 'Shop by Category');
    }

    private function playImageTemplate(string $id, string $title, string $description, string $badge, array $rows, array $items): array
    {
        return $this->template($id, 'play_image', $title, $description, 'ti-layout-grid-add', $badge, $rows[0]['style'] ?? 'grid', ['rows' => $rows], null, $items);
    }

    private function row(string $style, int $outerPadding, int $gap, int $radius, string $hover = 'none', float $desktopItems = 1, float $mobileItems = 1, bool $autoplay = false): array
    {
        return [
            'style' => $style,
            'container_width' => 'container',
            'outer_padding' => $outerPadding,
            'outer_padding_custom' => '',
            'grid_gap' => $gap,
            'border_radius' => $radius,
            'hover_effect' => $hover,
            'background_type' => 'none',
            'background_color' => '#ffffff',
            'items_to_show' => $desktopItems,
            'items_to_show_mobile' => $mobileItems,
            'show_scroll_buttons' => true,
            'show_bullets' => $autoplay,
            'auto_scroll' => $autoplay,
            'auto_scroll_time' => 4,
        ];
    }

    private function playItem(int $row, int $desktopColumns, int $mobileColumns, string $aspectRatio): array
    {
        return [
            'title' => 'Upload image',
            'purpose' => 'display',
            'config' => [
                'row_index' => $row,
                'media_type' => 'image',
                'link_type' => 'none',
                'cols' => $desktopColumns,
                'cols_mobile' => $mobileColumns,
                'aspect_ratio' => $aspectRatio,
                'object_fit' => 'cover',
                'object_position' => 'center',
            ],
        ];
    }

    private function template(string $id, string $category, string $title, string $description, string $icon, string $badge, ?string $style, array $config, ?string $defaultTitle = null, array $items = []): array
    {
        return [
            'id' => $id,
            'category' => $category,
            'title' => $title,
            'description' => $description,
            'icon' => $icon,
            'badge' => $badge,
            'type' => $category,
            'style' => $style,
            'config' => $config,
            'title_default' => $defaultTitle,
            'items' => $items,
        ];
    }

    private function theme(string $id, string $title, string $description, string $badge, array $templateIds): array
    {
        return compact('id', 'title', 'description', 'badge', 'templateIds');
    }
}
