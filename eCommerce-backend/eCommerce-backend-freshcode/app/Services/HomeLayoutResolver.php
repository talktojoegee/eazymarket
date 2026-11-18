<?php

namespace App\Services;

use App\Enums\BrandStatusEnum;
use App\Enums\CategoryStatusEnum;
use App\Enums\HomePage\HomeSectionTypeEnum;
use App\Http\Resources\BrandResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\Product\ProductListResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomeLayout;
use App\Models\HomeSection;
use App\Models\Market;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Resolves a stored home layout into the storefront payload: active,
 * market-visible sections in order, each with its content materialised
 * (product lists run through Product::scopeForMarket, categories/brands,
 * and hero/banner items with their media + link targets).
 */
class HomeLayoutResolver
{
    /** ProductSourceEnum value => Product::scopeApplySorting key. */
    private const SORT_MAP = [
        'newly_added' => 'relevance',
        'top_rated'   => 'avg_rated',
        'best_seller' => 'best_seller',
        'featured'    => 'featured',
        'recommended' => 'has_badge',
    ];

    private const int DEFAULT_LIMIT = 12;

    public function present(HomeLayout $layout, ?Market $market, int $perPage, int $page): array
    {
        return $this->hydrate($this->structure($layout, $market, $perPage, $page), $market);
    }

    public function structure(HomeLayout $layout, ?Market $market, int $perPage, int $page): array
    {
        $sections = $layout->sections
            ->filter(fn (HomeSection $s) => $s->is_active && $this->visibleInMarket($s, $market))
            ->filter(fn (HomeSection $s) => $this->hasMarketContent($s, $market))
            ->sort(function (HomeSection $left, HomeSection $right) use ($market) {
                $leftKey = $this->marketSortKey($left, $market);
                $rightKey = $this->marketSortKey($right, $market);

                return $leftKey <=> $rightKey;
            })
            ->values();

        $total = $sections->count();

        $pageSections = $sections->forPage($page, $perPage)->values()
            ->map(fn (HomeSection $s) => $this->structureSection($s, $market))
            ->all();

        return [
            'scope_type'   => $layout->scope_type,
            'scope_id'     => $layout->scope_id,
            'current_page' => $page,
            'last_page'    => (int) max(1, ceil($total / max(1, $perPage))),
            'per_page'     => $perPage,
            'total'        => $total,
            'sections'     => $pageSections,
        ];
    }

    public function hydrate(array $structure, ?Market $market): array
    {
        $structure['sections'] = array_map(function (array $section) use ($market) {
            if (isset($section['content']['__plan'])) {
                $section['content'] = [
                    'products' => ProductListResource::collection(
                        $this->productsFromPlan($section['content']['__plan'], $market)
                    ),
                ];
            }

            return $section;
        }, $structure['sections']);

        return $structure;
    }

    private function structureSection(HomeSection $section, ?Market $market): array
    {
        $base = [
            'id' => $section->id,
            'type' => $section->type,
            'title' => $section->show_title ? $section->title : null,
            'config' => Arr::except($section->config ?? [], ['show_title']),
        ];

        if (in_array($section->type, [
            HomeSectionTypeEnum::HERO(),
            HomeSectionTypeEnum::PRODUCTS(),
            HomeSectionTypeEnum::CATEGORIES(),
            HomeSectionTypeEnum::BRANDS(),
        ], true)) {
            $base['style'] = $section->style;
        }

        if ($section->type === HomeSectionTypeEnum::HERO()) {
            $base['hero_media'] = $section->hero_media;
            $base['card_background_image'] = $section->card_background_image;
        }

        if (in_array($section->type, [
            HomeSectionTypeEnum::PRODUCTS(),
            HomeSectionTypeEnum::CATEGORIES(),
        ], true)) {
            $base['background_image'] = $section->background_image;
        }

        if ($section->type === HomeSectionTypeEnum::PRODUCTS()) {
            $base['content'] = ['__plan' => $this->productsPlan($section)];

            return $base;
        }

        $base['content'] = $this->toArrayContent($this->resolveContent($section, $market));

        return $base;
    }

    private function toArrayContent(array $content): array
    {
        return array_map(
            fn ($value) => $value instanceof \Illuminate\Http\Resources\Json\JsonResource
                || $value instanceof \Illuminate\Http\Resources\Json\ResourceCollection
                    ? $value->resolve()
                    : $value,
            $content,
        );
    }

    private function visibleInMarket(HomeSection $section, ?Market $market): bool
    {
        if (! $market || $section->markets->isEmpty()) {
            return true;
        }

        return $section->markets->contains('id', $market->id);
    }

    /** Market pages do not render product-driven sections with no sellable items. */
    private function hasMarketContent(HomeSection $section, ?Market $market): bool
    {
        if (! $market) {
            return true;
        }

        if ($section->type === HomeSectionTypeEnum::PRODUCTS()) {
            return $this->productsPlanHasResults($this->productsPlan($section), $market);
        }

        $isProductShowcase = $section->type === HomeSectionTypeEnum::HERO()
            && ($section->config['layout'] ?? null) === 'items'
            && ($section->config['item_source'] ?? null) === 'products';

        if (! $isProductShowcase) {
            return true;
        }

        $ids = $this->displayIds($section, Product::class);

        return ! empty($ids)
            && Product::scopeForMarket($market, Product::query()->whereIn('products.id', $ids), [])->exists();
    }

    /**
     * Explicit market positions come first. Sections without an override retain
     * their base order after the customized sequence, so newly added sections
     * remain visible until an administrator re-saves that market's order.
     */
    private function marketSortKey(HomeSection $section, ?Market $market): array
    {
        if (! $market) {
            return [0, (int) $section->sort_order, (int) $section->id];
        }

        $position = $section->marketOrders->firstWhere('market_id', $market->id);

        return $position
            ? [0, (int) $position->sort_order, (int) $section->sort_order, (int) $section->id]
            : [1, (int) $section->sort_order, (int) $section->sort_order, (int) $section->id];
    }

    private function resolveContent(HomeSection $section, ?Market $market): array
    {
        return match ($section->type) {
            HomeSectionTypeEnum::PRODUCTS() => [
                'products' => ProductListResource::collection($this->resolveProducts($section, $market)),
            ],
            HomeSectionTypeEnum::CATEGORIES() => [
                'categories' => CategoryResource::collection($this->resolveCategories($section, $market)),
            ],
            HomeSectionTypeEnum::BRANDS() => [
                'brands' => BrandResource::collection($this->resolveBrands($section)),
            ],
            HomeSectionTypeEnum::HERO() => [
                'items' => $this->heroItems($section, $market),
            ],
            HomeSectionTypeEnum::PLAY_IMAGE() => [
                'rows' => $this->playImageRows($section),
            ],
            default => [],
        };
    }

    private function resolveProducts(HomeSection $section, ?Market $market): Collection
    {
        return $this->productsFromPlan($this->productsPlan($section), $market);
    }

    private function productsPlan(HomeSection $section): array
    {
        $config = $section->config ?? [];
        $source = $config['source'] ?? 'newly_added';

        if ($source === 'custom') {
            return ['source' => 'custom', 'ids' => $this->displayIds($section, Product::class)];
        }

        return [
            'source'         => $source,
            'limit'          => (int) ($config['limit'] ?? self::DEFAULT_LIMIT),
            'category_slugs' => $this->filterCategorySlugs($section),
        ];
    }

    private function productsFromPlan(array $plan, ?Market $market): Collection
    {
        if (($plan['source'] ?? null) === 'custom') {
            $ids = $plan['ids'] ?? [];
            if (empty($ids)) {
                return collect();
            }

            $products = Product::scopeForMarket($market, Product::query()->whereIn('id', $ids), [])->get();

            return $this->orderByIds($products, $ids);
        }

        $filter = ['sort' => self::SORT_MAP[$plan['source']] ?? 'relevance'];
        if (! empty($plan['category_slugs'])) {
            $filter['categories'] = $plan['category_slugs'];
        }

        return Product::scopeForMarket($market, Product::query(), $filter)
            ->limit((int) ($plan['limit'] ?? self::DEFAULT_LIMIT))
            ->get();
    }

    private function productsPlanHasResults(array $plan, Market $market): bool
    {
        if (($plan['source'] ?? null) === 'custom') {
            $ids = $plan['ids'] ?? [];

            return ! empty($ids)
                && Product::scopeForMarket($market, Product::query()->whereIn('products.id', $ids), [])->exists();
        }

        $filter = ['sort' => self::SORT_MAP[$plan['source']] ?? 'relevance'];
        if (! empty($plan['category_slugs'])) {
            $filter['categories'] = $plan['category_slugs'];
        }

        return Product::scopeForMarket($market, Product::query(), $filter)->exists();
    }

    private function filterCategorySlugs(HomeSection $section): array
    {
        $ids = $section->items
            ->where('purpose', 'filter')
            ->filter(fn ($it) => $it->itemable_type === Category::class)
            ->pluck('itemable_id')->filter()->values()->all();

        return empty($ids) ? [] : Category::whereIn('id', $ids)->pluck('slug')->all();
    }

    private function resolveCategories(HomeSection $section, ?Market $market): Collection
    {
        if (($section->config['source'] ?? 'all') === 'custom') {
            $ids = $this->displayIds($section, Category::class);
            if (empty($ids)) {
                return collect();
            }

            $categories = $this->orderByIds(
                Category::whereIn('id', $ids)->where('status', CategoryStatusEnum::ACTIVE())->get(),
                $ids,
            );
        } else {
            $categories = Category::query()
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->whereNull('parent_id')
                ->orderBy('id')
                ->limit(8)
                ->get();
        }

        $categories = $this->withSubtreeProductCounts($categories, $market);

        return ($section->config['source'] ?? 'all') === 'custom'
            ? $this->applyCustomItemImages($section, $categories, Category::class)
            : $categories;
    }

    private function withSubtreeProductCounts(Collection $categories, ?Market $market): Collection
    {
        if ($categories->isEmpty()) {
            return $categories;
        }

        $useZoneFilter = $market instanceof Market;
        $storeIds = $useZoneFilter ? Product::getStoreIdsForMarket($market) : [];

        return app(CategoryService::class)->attachSubtreeProductCounts($categories, $storeIds, $useZoneFilter);
    }

    private function resolveBrands(HomeSection $section): Collection
    {
        if (($section->config['source'] ?? 'all') === 'custom') {
            $ids = $this->displayIds($section, Brand::class);
            if (empty($ids)) {
                return collect();
            }

            return $this->applyCustomItemImages($section, $this->orderByIds(
                Brand::whereIn('id', $ids)->where('status', BrandStatusEnum::ACTIVE())->withCount('products')->get(),
                $ids,
            ), Brand::class);
        }

        return Brand::query()
            ->where('status', BrandStatusEnum::ACTIVE())
            ->withCount('products')
            ->having('products_count', '>', 0)
            ->limit(6)
            ->orderBy('title')
            ->get();
    }

    private function heroItems(HomeSection $section, ?Market $market = null): array
    {
        $availableProductIds = null;
        if ($market) {
            $productIds = $this->displayIds($section, Product::class);
            $availableProductIds = empty($productIds)
                ? []
                : Product::scopeForMarket($market, Product::query()->whereIn('products.id', $productIds), [])
                    ->pluck('products.id')->map(fn ($id) => (int) $id)->all();
        }

        return $section->items
            ->where('purpose', 'display')
            ->filter(fn ($it) => $it->itemable_type !== Product::class
                || $availableProductIds === null
                || in_array((int) $it->itemable_id, $availableProductIds, true))
            ->sortBy('sort_order')
            ->map(fn ($it) => [
                'id'            => $it->id,
                'title'         => $it->title ?: ($it->itemable?->title ?? $it->itemable?->name),
                'image'         => $it->image ?: $this->itemableImage($it->itemable, $it->itemable_type),
                'default_image' => $this->itemableImage($it->itemable, $it->itemable_type),
                'type'          => $this->itemableKey($it->itemable_type),
                'target_id'     => $it->itemable_id,
                'slug'          => $it->itemable?->slug,
            ])->values()->all();
    }

    private function playImageItems(HomeSection $section): array
    {
        return $section->items
            ->where('purpose', 'display')
            ->sortBy('sort_order')
            ->map(fn ($it) => [
                'id'           => $it->id,
                'image'        => $it->image,
                'mobile_image' => $it->mobile_image,
                'link_type'    => $it->link_url ? 'custom' : $this->itemableKey($it->itemable_type),
                'link_url'     => $it->link_url ?: null,
                'target_id'    => $it->itemable_id,
                'slug'         => $it->itemable?->slug,
                'config'       => $it->config ?? [],
            ])->values()->all();
    }

    private function applyCustomItemImages(HomeSection $section, Collection $models, string $class): Collection
    {
        $images = $section->items
            ->where('purpose', 'display')
            ->where('itemable_type', $class)
            ->filter(fn ($item) => filled($item->image))
            ->mapWithKeys(fn ($item) => [(int) $item->itemable_id => $item->image]);

        return $models->each(function ($model) use ($images) {
            if ($images->has((int) $model->id)) {
                $model->setAttribute('home_builder_image', $images->get((int) $model->id));
            }
        });
    }

    private function playImageRows(HomeSection $section): array
    {
        $items = $this->playImageItems($section);
        $grouped = [];

        foreach ($items as $item) {
            $rIdx = (int) ($item['config']['row_index'] ?? 0);
            $grouped[$rIdx][] = $item;
        }

        ksort($grouped);

        $rowConfigs = $section->config['rows'] ?? [];

        $rows = [];
        foreach ($grouped as $rIdx => $rowItems) {
            $rowConfig = $rowConfigs[$rIdx] ?? [
                'style' => 'grid',
                'container_width' => 'container',
                'outer_padding' => 0,
                'outer_padding_custom' => '',
                'grid_gap' => 0,
                'border_radius' => 0,
                'hover_effect' => 'none',
                'background_type' => 'none',
                'background_color' => '#ffffff',
                'background_image' => null,
                'items_to_show' => 1,
                'items_to_show_mobile' => 1,
                'show_scroll_buttons' => 1,
                'show_bullets' => 0,
                'auto_scroll' => 0,
                'auto_scroll_time' => 3
            ];
            $rows[] = [
                'row_index' => $rIdx,
                'style'     => $rowConfig['style'] ?? 'grid',
                'config'    => $rowConfig,
                'items'     => $rowItems,
            ];
        }

        return $rows;
    }

    private function displayIds(HomeSection $section, string $class): array
    {
        return $section->items
            ->where('purpose', 'display')
            ->filter(fn ($it) => $it->itemable_type === $class)
            ->pluck('itemable_id')->filter()->values()->all();
    }

    private function orderByIds(Collection $models, array $ids): Collection
    {
        $order = array_flip($ids);

        return $models->sortBy(fn ($m) => $order[$m->id] ?? PHP_INT_MAX)->values();
    }

    private function itemableKey(?string $class): ?string
    {
        return $class ? (array_flip(HomeBuilderService::ITEMABLE_MAP)[$class] ?? null) : null;
    }

    /** Default image of the linked Product / Category / Brand (used when no per-item image is set). */
    private function itemableImage($itemable, ?string $class): ?string
    {
        if (! $itemable) {
            return null;
        }

        return match ($class) {
            Product::class  => $itemable->main_image,
            Category::class => $itemable->image,
            Brand::class    => $itemable->logo,
            default         => null,
        };
    }


    /** Paginated content for a single section (page + per_page driven). */
    public function paginateSection(HomeSection $section, ?Market $market, int $perPage, int $page): array
    {
        switch ($section->type) {
            case HomeSectionTypeEnum::PRODUCTS():
                $paginator = $this->productsPaginator($section, $market, $perPage, $page);

                return $this->paginatedResponse($paginator, ProductListResource::collection($paginator->getCollection()));

            case HomeSectionTypeEnum::CATEGORIES():
                $paginator = $this->categoriesPaginator($section, $perPage, $page);
                $categories = $this->withSubtreeProductCounts($paginator->getCollection(), $market);
                if (($section->config['source'] ?? 'all') === 'custom') {
                    $categories = $this->applyCustomItemImages($section, $categories, Category::class);
                }

                return $this->paginatedResponse(
                    $paginator,
                    CategoryResource::collection($categories),
                );

            case HomeSectionTypeEnum::BRANDS():
                $paginator = $this->brandsPaginator($section, $perPage, $page);
                $brands = $paginator->getCollection();
                if (($section->config['source'] ?? 'all') === 'custom') {
                    $brands = $this->applyCustomItemImages($section, $brands, Brand::class);
                }

                return $this->paginatedResponse($paginator, BrandResource::collection($brands));

            case HomeSectionTypeEnum::HERO():
                $paginator = $this->collectionPaginator(collect($this->heroItems($section, $market)), $perPage, $page);

                return $this->paginatedResponse($paginator, $paginator->getCollection());

            case HomeSectionTypeEnum::PLAY_IMAGE():
                $paginator = $this->collectionPaginator(collect($this->playImageRows($section)), $perPage, $page);

                return $this->paginatedResponse($paginator, $paginator->getCollection());

            default:
                $paginator = $this->emptyPaginator($perPage, $page);

                return $this->paginatedResponse($paginator, collect());
        }
    }

    private function productsPaginator(HomeSection $section, ?Market $market, int $perPage, int $page): LengthAwarePaginator
    {
        $config = $section->config ?? [];
        $source = $config['source'] ?? 'newly_added';

        if ($source === 'custom') {
            $ids = $this->displayIds($section, Product::class);
            if (empty($ids)) {
                return $this->emptyPaginator($perPage, $page);
            }

            $products = $this->orderByIds(
                Product::scopeForMarket($market, Product::query()->whereIn('id', $ids), [])->get(),
                $ids,
            );

            return $this->collectionPaginator($products, $perPage, $page);
        }

        $filter = ['sort' => self::SORT_MAP[$source] ?? 'relevance'];
        $categorySlugs = $this->filterCategorySlugs($section);
        if (! empty($categorySlugs)) {
            $filter['categories'] = $categorySlugs;
        }

        return Product::scopeForMarket($market, Product::query(), $filter)->paginate($perPage, ['*'], 'page', $page);
    }

    private function categoriesPaginator(HomeSection $section, int $perPage, int $page): LengthAwarePaginator
    {
        if (($section->config['source'] ?? 'all') === 'custom') {
            $ids = $this->displayIds($section, Category::class);
            if (empty($ids)) {
                return $this->emptyPaginator($perPage, $page);
            }

            $categories = $this->orderByIds(
                Category::whereIn('id', $ids)->where('status', CategoryStatusEnum::ACTIVE())->get(),
                $ids,
            );

            return $this->collectionPaginator($categories, $perPage, $page);
        }

        return Category::query()
            ->where('status', CategoryStatusEnum::ACTIVE())
            ->whereNull('parent_id')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    private function brandsPaginator(HomeSection $section, int $perPage, int $page): LengthAwarePaginator
    {
        if (($section->config['source'] ?? 'all') === 'custom') {
            $ids = $this->displayIds($section, Brand::class);
            if (empty($ids)) {
                return $this->emptyPaginator($perPage, $page);
            }

            $brands = $this->orderByIds(
                Brand::whereIn('id', $ids)->where('status', BrandStatusEnum::ACTIVE())->withCount('products')->get(),
                $ids,
            );

            return $this->collectionPaginator($brands, $perPage, $page);
        }

        return Brand::query()
            ->where('status', BrandStatusEnum::ACTIVE())
            ->withCount('products')
            ->having('products_count', '>', 0)
            ->orderBy('title')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    private function collectionPaginator(Collection $items, int $perPage, int $page): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    private function emptyPaginator(int $perPage, int $page): LengthAwarePaginator
    {
        return new LengthAwarePaginator(collect(), 0, $perPage, $page);
    }

    private function paginatedResponse(LengthAwarePaginator $paginator, $items): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'data'         => $items,
        ];
    }
}
