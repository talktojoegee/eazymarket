<?php

namespace App\Services;

use App\Models\LandingPage;
use App\Models\LandingPageItem;
use App\Models\LandingPageMediaSlot;
use App\Models\LandingPageSection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class SellerLandingMediaService
{
    public function sync(LandingPage $page, array $configuration, Request $request): void
    {
        $sections = $page->sections->keyBy('key');
        $items = $page->sections->flatMap(fn (LandingPageSection $section) => $section->items->mapWithKeys(
            fn (LandingPageItem $item) => [$section->key.':'.$item->key => $item]
        ));

        if ($request->boolean('remove_seo_media.image')) {
            $this->removeSlot($page, 'seo_image');
        }

        $this->sectionValues($request->input('remove_section_media', []), $sections, 'remove')
            ->filter(fn (array $entry) => (bool) $entry['remove'])
            ->each(fn (array $entry) => $this->removeSlot($entry['owner'], $entry['slot']));

        $this->itemValues($request->input('remove_item_media', []), $items, 'remove')
            ->filter(fn (array $entry) => (bool) $entry['remove'])
            ->each(fn (array $entry) => $this->removeSlot($entry['owner'], $entry['slot']));

        $this->syncSlot($page, 'seo_image', $request->file('seo_media.image'));

        $this->sectionValues($request->file('section_media', []), $sections, 'file')
            ->each(fn (array $entry) => $this->syncSlot(
                $entry['owner'],
                $entry['slot'],
                $entry['file'],
            ));

        $this->itemValues($request->file('item_media', []), $items, 'file')
            ->each(fn (array $entry) => $this->syncSlot(
                $entry['owner'],
                $entry['slot'],
                $entry['file'],
            ));

        $this->syncUseDefault(
            $page,
            'seo_image',
            (bool) data_get(
                $request->input(),
                'clear_seo_media.image',
                data_get($configuration, 'seoMediaSettings.imageUseDefault', true),
            ),
        );

        $this->sectionModes($configuration, $sections)
            ->merge($this->sectionValues($request->input('clear_section_media', []), $sections, 'use_default'))
            ->each(fn (array $entry) => $this->syncUseDefault(
                $entry['owner'],
                $entry['slot'],
                (bool) $entry['use_default'],
            ));

        $this->itemModes($configuration, $items)
            ->merge($this->itemValues($request->input('clear_item_media', []), $items, 'use_default'))
            ->each(fn (array $entry) => $this->syncUseDefault(
                $entry['owner'],
                $entry['slot'],
                (bool) $entry['use_default'],
            ));
    }

    private function sectionValues(array $values, Collection $sections, string $attribute): Collection
    {
        return collect(Arr::dot($values))
            ->map(function (mixed $value, string $path) use ($sections, $attribute) {
                [$sectionKey, $slot] = explode('.', $path, 2);

                return ['owner' => $sections->get($sectionKey), 'slot' => $slot, $attribute => $value];
            })
            ->filter(fn (array $entry) => $entry['owner'] instanceof LandingPageSection);
    }

    private function itemValues(array $values, Collection $items, string $attribute): Collection
    {
        return collect(Arr::dot($values))
            ->map(function (mixed $value, string $path) use ($items, $attribute) {
                [$sectionKey, $itemKey, $slot] = explode('.', $path, 3);

                return ['owner' => $items->get($sectionKey.':'.$itemKey), 'slot' => $slot, $attribute => $value];
            })
            ->filter(fn (array $entry) => $entry['owner'] instanceof LandingPageItem);
    }

    private function sectionModes(array $configuration, Collection $sections): Collection
    {
        return collect($configuration['sections'] ?? [])->flatMap(function (array $section) use ($sections) {
            $owner = $sections->get($section['id']);
            $slots = collect($section['mediaDefaults'] ?? [])->keys()
                ->merge(array_keys($section['media'] ?? []))
                ->unique();

            return $slots->map(fn (string $slot) => [
                'owner' => $owner,
                'section_key' => $section['id'],
                'slot' => $slot,
                'use_default' => (bool) data_get($section, 'mediaDefaults.'.$slot, blank(data_get($section, 'media.'.$slot))),
            ]);
        })->filter(fn (array $entry) => $entry['owner'] instanceof LandingPageSection);
    }

    private function itemModes(array $configuration, Collection $items): Collection
    {
        return collect($configuration['sections'] ?? [])->flatMap(function (array $section) use ($items) {
            return collect($section['items'] ?? [])->flatMap(function (array $item) use ($items, $section) {
                $owner = $items->get($section['id'].':'.$item['id']);
                $slots = collect($item['mediaDefaults'] ?? [])->keys()
                    ->merge(array_keys($item['media'] ?? []))
                    ->unique();

                return $slots->map(fn (string $slot) => [
                    'owner' => $owner,
                    'section_key' => $section['id'],
                    'item_key' => $item['id'],
                    'slot' => $slot,
                    'use_default' => (bool) data_get($item, 'mediaDefaults.'.$slot, blank(data_get($item, 'media.'.$slot))),
                ]);
            });
        })->filter(fn (array $entry) => $entry['owner'] instanceof LandingPageItem);
    }

    private function syncSlot(Model $owner, string $name, mixed $file): void
    {
        if (! $file) {
            return;
        }

        $slot = $owner->mediaSlots()->firstOrCreate(
            ['slot' => $name],
            ['use_default' => true],
        );
        $slot->addMedia($file)->toMediaCollection(LandingPageMediaSlot::COLLECTION);
    }

    private function removeSlot(Model $owner, string $name): void
    {
        $owner->mediaSlots()
            ->where('slot', $name)
            ->first()
            ?->clearMediaCollection(LandingPageMediaSlot::COLLECTION);
    }

    private function syncUseDefault(Model $owner, string $name, bool $useDefault): void
    {
        $owner->mediaSlots()->updateOrCreate(
            ['slot' => $name],
            ['use_default' => $useDefault],
        );
    }
}
