<?php

namespace App\Services;

use App\Enums\HomePage\ItemPurposeEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomeLayout;
use App\Models\HomeSection;
use App\Models\HomeSectionItem;
use App\Models\Product;
use App\Support\HomeCache;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class HomeBuilderService
{
    /** Client-facing itemable keys mapped to their model classes. */
    public const ITEMABLE_MAP = [
        'product' => Product::class,
        'category' => Category::class,
        'brand' => Brand::class,
    ];

    /** Load (lazily creating) the layout for a platform + scope, eager-loading sections + items. */
    public function loadForScope(string $scopeType, ?int $scopeId, string $platform = 'app'): HomeLayout
    {
        $layout = $this->layoutForScope($scopeType, $scopeId, $platform);

        return $layout->load($this->sectionRelations());
    }

    public function loadPaginatedForScope(
        string $scopeType,
        ?int $scopeId,
        string $platform,
        int $page,
        int $perPage,
        bool $includeManifest,
    ): array {
        $layout = $this->layoutForScope($scopeType, $scopeId, $platform);
        $manifest = $includeManifest
            ? $layout->sections()
                ->select(['id', 'home_layout_id', 'type', 'title', 'show_title', 'style', 'config', 'sort_order', 'is_active'])
                ->with(['markets:id', 'marketOrders'])
                ->withCount('items')
                ->get()
            : collect();
        $sections = $layout->sections()
            ->with([
                'items' => fn ($q) => $q->orderBy('sort_order'),
                'items.itemable' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        Product::class => ['variants.locationInventories', 'brand', 'category', 'reviews', 'badge'],
                    ]);
                },
                'markets:id',
                'marketOrders',
            ])
            ->paginate($perPage, ['*'], 'page', $page);

        return compact('layout', 'manifest', 'sections');
    }

    private function layoutForScope(string $scopeType, ?int $scopeId, string $platform): HomeLayout
    {
        return HomeLayout::firstOrCreate(
            ['platform' => $platform, 'scope_type' => $scopeType, 'scope_id' => $scopeId],
            ['is_active' => true],
        );
    }

    private function sectionRelations(): array
    {
        return [
            'sections' => fn ($q) => $q->orderBy('sort_order'),
            'sections.items' => fn ($q) => $q->orderBy('sort_order'),
            'sections.items.itemable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Product::class => ['variants.locationInventories', 'brand', 'category', 'reviews', 'badge'],
                ]);
            },
            'sections.markets:id',
            'sections.marketOrders',
        ];
    }

    /**
     * Persist the whole layout in one transaction: upsert the layout, sync its
     * sections (preserving ids so attached media survives), each section's items,
     * and its market pivot. Sections/items absent from the payload are deleted.
     */
    public function save(array $data): HomeLayout
    {
        return DB::transaction(function () use ($data) {
            $platform = $data['platform'] ?? 'app';

            $layout = HomeLayout::updateOrCreate(
                ['platform' => $platform, 'scope_type' => $data['scope_type'], 'scope_id' => $data['scope_id'] ?? null],
                ['is_active' => $data['is_active'] ?? true],
            );

            $keptSectionIds = [];

            foreach ($data['sections'] ?? [] as $index => $sectionData) {
                $section = $this->upsertSection($layout, $sectionData, $index);
                $keptSectionIds[] = $section->id;

                $section->markets()->sync($sectionData['market_ids'] ?? []);
                if (array_key_exists('market_positions', $sectionData)) {
                    $this->syncMarketPositions($section, $sectionData['market_positions'] ?? []);
                }
                $this->syncItems($section, $sectionData['items'] ?? []);
            }

            $layout->sections()->whereNotIn('id', $keptSectionIds ?: [0])->get()
                ->each(fn (HomeSection $section) => $section->delete());

            HomeCache::flushLayout();

            return $this->loadForScope($layout->scope_type, $layout->scope_id, $layout->platform);
        });
    }

    private function upsertSection(HomeLayout $layout, array $data, int $index): HomeSection
    {
        $attributes = [
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
            'show_title' => $data['show_title'] ?? true,
            'style' => $data['style'] ?? null,
            'config' => $data['config'] ?? [],
            'sort_order' => $data['sort_order'] ?? $index,
            'is_active' => $data['is_active'] ?? true,
        ];

        if (! empty($data['id'])) {
            $section = $layout->sections()->whereKey($data['id'])->first();
            if ($section) {
                $section->update($attributes);

                return $section;
            }
        }

        return $layout->sections()->create($attributes);
    }

    private function syncItems(HomeSection $section, array $items): void
    {
        $keptItemIds = [];

        foreach ($items as $index => $itemData) {
            $attributes = [
                'purpose' => $itemData['purpose'] ?? ItemPurposeEnum::DISPLAY(),
                'itemable_type' => $this->resolveItemableClass($itemData['itemable_type'] ?? null),
                'itemable_id' => $itemData['itemable_id'] ?? null,
                'title' => $itemData['title'] ?? null,
                'link_url' => $itemData['link_url'] ?? null,
                'sort_order' => $itemData['sort_order'] ?? $index,
                'config' => $itemData['config'] ?? null,
            ];

            if (! empty($itemData['id'])) {
                $item = $section->items()->whereKey($itemData['id'])->first();
                if ($item) {
                    $item->update($attributes);
                    $keptItemIds[] = $item->id;

                    continue;
                }
            }

            $keptItemIds[] = $section->items()->create($attributes)->id;
        }

        $section->items()->whereNotIn('id', $keptItemIds ?: [0])->get()
            ->each(fn (HomeSectionItem $item) => $item->delete());
    }

    /** Persist the complete set of market-specific positions for a section. */
    private function syncMarketPositions(HomeSection $section, array $positions): void
    {
        $normalized = collect($positions)
            ->filter(fn ($position) => is_array($position) && ! empty($position['market_id']))
            ->mapWithKeys(fn ($position) => [
                (int) $position['market_id'] => max(0, (int) ($position['sort_order'] ?? 0)),
            ]);

        $stalePositions = $section->marketOrders();
        if ($normalized->isNotEmpty()) {
            $stalePositions->whereNotIn('market_id', $normalized->keys());
        }
        $stalePositions->delete();

        $normalized->each(fn (int $sortOrder, int $marketId) => $section->marketOrders()->updateOrCreate(
            ['market_id' => $marketId],
            ['sort_order' => $sortOrder],
        ));
    }

    private function resolveItemableClass(?string $key): ?string
    {
        return $key ? (self::ITEMABLE_MAP[$key] ?? null) : null;
    }
}
