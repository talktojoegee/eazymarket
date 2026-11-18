<?php

namespace App\Services;

use App\Models\LandingPage;
use App\Models\LandingPageItem;
use App\Models\LandingPageMediaSlot;
use App\Models\LandingPageSection;

class SellerLandingPagePresenter
{
    public function adminConfiguration(LandingPage $page): array
    {
        return $this->configuration($page, true);
    }

    public function publicConfiguration(LandingPage $page): array
    {
        return $this->configuration($page, false);
    }

    public function relations(): array
    {
        return [
            'translations',
            'mediaSlots.media',
            'sections.translations',
            'sections.mediaSlots.media',
            'sections.items.translations',
            'sections.items.mediaSlots.media',
        ];
    }

    private function configuration(LandingPage $page, bool $admin): array
    {
        $page->loadMissing($this->relations());
        $seoSlot = $page->mediaSlots->firstWhere('slot', 'seo_image');
        $configuration = [
            'schemaVersion' => $page->schema_version,
            'seo' => $page->translations->mapWithKeys(fn ($translation) => [
                $translation->locale => array_filter([
                    'title' => $translation->seo_title,
                    'description' => $translation->seo_description,
                    'keywords' => $translation->seo_keywords,
                ], fn ($value) => $value !== null && $value !== ''),
            ])->all(),
            'seoMedia' => ['image' => $seoSlot ? $this->mediaUrl($seoSlot, $admin) : ''],
            'sections' => $page->sections->map(fn (LandingPageSection $section) => $this->section($section, $admin))->all(),
        ];

        if ($admin) {
            $configuration['seoMediaSettings'] = ['imageUseDefault' => $seoSlot?->use_default ?? true];
        }

        return $configuration;
    }

    private function section(LandingPageSection $section, bool $admin): array
    {
        $configuration = [
            'id' => $section->key,
            'type' => $section->type,
            'variant' => $section->variant,
            'preset' => $section->preset,
            'enabled' => $section->is_active,
            'content' => $section->translations->mapWithKeys(fn ($translation) => [
                $translation->locale => $translation->content ?? [],
            ])->all(),
            'settings' => $section->settings ?? [],
            'media' => $section->mediaSlots->mapWithKeys(fn (LandingPageMediaSlot $slot) => [
                $slot->slot => $this->mediaUrl($slot, $admin),
            ])->all(),
            'items' => $section->items->map(fn (LandingPageItem $item) => $this->item($item, $admin))->all(),
        ];

        if ($admin) {
            $configuration['mediaDefaults'] = $section->mediaSlots->mapWithKeys(fn ($slot) => [$slot->slot => $slot->use_default])->all();
        }

        return $configuration;
    }

    private function item(LandingPageItem $item, bool $admin): array
    {
        $configuration = [
            'id' => $item->key,
            'preset' => $item->preset,
            'content' => $item->translations->mapWithKeys(fn ($translation) => [
                $translation->locale => $translation->content ?? [],
            ])->all(),
            'settings' => $item->settings ?? [],
            'media' => $item->mediaSlots->mapWithKeys(fn (LandingPageMediaSlot $slot) => [
                $slot->slot => $this->mediaUrl($slot, $admin),
            ])->all(),
        ];

        if ($admin) {
            $configuration['mediaDefaults'] = $item->mediaSlots->mapWithKeys(fn ($slot) => [$slot->slot => $slot->use_default])->all();
        }

        return $configuration;
    }

    private function mediaUrl(LandingPageMediaSlot $slot, bool $admin): string
    {
        return $admin ? $slot->getFirstMediaUrl(LandingPageMediaSlot::COLLECTION) : $slot->url();
    }
}
