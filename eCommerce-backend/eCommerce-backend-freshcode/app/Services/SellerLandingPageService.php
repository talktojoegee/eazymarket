<?php

namespace App\Services;

use App\Models\LandingPage;
use App\Models\LandingPageItem;
use App\Models\LandingPageItemTranslation;
use App\Models\LandingPageSectionTranslation;
use App\Models\LandingPageTranslation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SellerLandingPageService
{
    public const PAGE_KEY = 'seller';

    public function __construct(
        private readonly SellerLandingRegistry $registry,
        private readonly SellerLandingPagePresenter $presenter,
        private readonly SellerLandingMediaService $media,
    ) {}

    public function get(): LandingPage
    {
        $page = LandingPage::query()->firstOrCreate(
            ['key' => self::PAGE_KEY],
            ['schema_version' => 1, 'is_active' => true],
        );

        if (! $page->sections()->exists()) {
            DB::transaction(fn () => $this->syncStructure($page, $this->registry->defaultConfiguration()));
        }

        return $page->fresh($this->presenter->relations());
    }

    public function save(array $configuration, Request $request): LandingPage
    {
        return DB::transaction(function () use ($configuration, $request) {
            $page = LandingPage::query()->updateOrCreate(
                ['key' => self::PAGE_KEY],
                ['schema_version' => $configuration['schemaVersion'], 'is_active' => true],
            );
            $this->syncStructure($page, $configuration);
            $page->load(['sections.items']);
            $this->media->sync($page, $configuration, $request);

            return $page->fresh($this->presenter->relations());
        });
    }

    public function configuration(): array
    {
        return $this->presenter->adminConfiguration($this->get());
    }

    private function syncStructure(LandingPage $page, array $configuration): void
    {
        $this->syncPageTranslations($page, $configuration['seo'] ?? []);
        $sections = $this->syncSections($page, $configuration['sections'] ?? []);
        $items = $this->syncItems($sections, $configuration['sections'] ?? []);
        $this->syncSectionTranslations($sections, $configuration['sections'] ?? []);
        $this->syncItemTranslations($sections, $items, $configuration['sections'] ?? []);
    }

    private function syncPageTranslations(LandingPage $page, array $seo): void
    {
        $page->translations()->delete();
        $now = now();
        $rows = collect($seo)->map(fn (array $content, string $locale) => [
            'landing_page_id' => $page->id,
            'locale' => $locale,
            'seo_title' => $content['title'] ?? null,
            'seo_description' => $content['description'] ?? null,
            'seo_keywords' => $content['keywords'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();
        if ($rows !== []) {
            LandingPageTranslation::query()->insert($rows);
        }
    }

    private function syncSections(LandingPage $page, array $sections): Collection
    {
        $records = collect($sections)->map(function (array $section, int $index) use ($page) {
            return $page->sections()->updateOrCreate(
                ['key' => $section['id']],
                [
                    'type' => $section['type'],
                    'variant' => $section['variant'],
                    'preset' => $section['preset'] ?? null,
                    'settings' => $section['settings'] ?? [],
                    'sort_order' => $index,
                    'is_active' => $section['enabled'],
                ],
            );
        })->keyBy('key');

        $page->sections()->whereNotIn('key', $records->keys()->all() ?: [''])->get()->each->delete();

        return $records;
    }

    private function syncItems(Collection $sections, array $payload): Collection
    {
        $desired = collect($payload)->flatMap(function (array $section) use ($sections) {
            $sectionRecord = $sections->get($section['id']);

            return collect($section['items'] ?? [])->map(fn (array $item, int $index) => [
                'section' => $sectionRecord,
                'payload' => $item,
                'sort_order' => $index,
            ]);
        });

        $records = $desired->map(function (array $entry) {
            return $entry['section']->items()->updateOrCreate(
                ['key' => $entry['payload']['id']],
                [
                    'preset' => $entry['payload']['preset'] ?? null,
                    'settings' => $entry['payload']['settings'] ?? [],
                    'sort_order' => $entry['sort_order'],
                ],
            );
        })->keyBy(fn (LandingPageItem $item) => $item->landing_page_section_id.':'.$item->key);

        $sectionIds = $sections->pluck('id');
        LandingPageItem::query()->whereIn('landing_page_section_id', $sectionIds)->get()
            ->reject(fn (LandingPageItem $item) => $records->has($item->landing_page_section_id.':'.$item->key))
            ->each->delete();

        return $records;
    }

    private function syncSectionTranslations(Collection $sections, array $payload): void
    {
        LandingPageSectionTranslation::query()->whereIn('landing_page_section_id', $sections->pluck('id'))->delete();
        $now = now();
        $rows = collect($payload)->flatMap(function (array $section) use ($sections, $now) {
            $sectionId = $sections->get($section['id'])->id;

            return collect($section['content'] ?? [])->map(fn (array $content, string $locale) => [
                'landing_page_section_id' => $sectionId,
                'locale' => $locale,
                'content' => json_encode($content, JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ])->values();
        })->values()->all();
        if ($rows !== []) {
            LandingPageSectionTranslation::query()->insert($rows);
        }
    }

    private function syncItemTranslations(Collection $sections, Collection $items, array $payload): void
    {
        LandingPageItemTranslation::query()->whereIn('landing_page_item_id', $items->pluck('id'))->delete();
        $now = now();
        $rows = collect($payload)->flatMap(function (array $section) use ($sections, $items, $now) {
            $sectionRecord = $sections->get($section['id']);

            return collect($section['items'] ?? [])->flatMap(function (array $item) use ($items, $sectionRecord, $now) {
                $record = $items->get($sectionRecord->id.':'.$item['id']);

                return collect($item['content'] ?? [])->map(fn (array $content, string $locale) => [
                    'landing_page_item_id' => $record->id,
                    'locale' => $locale,
                    'content' => json_encode($content, JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values();
            })->values();
        })->values()->all();
        if ($rows !== []) {
            LandingPageItemTranslation::query()->insert($rows);
        }
    }
}
