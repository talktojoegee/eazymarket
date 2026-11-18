<?php

namespace App\Services;

use App\Enums\LanguageSurfaceEnum;
use App\Models\Language;
use App\Models\LanguageTranslation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use JsonException;

class ClientLanguageService
{
    public function languages(LanguageSurfaceEnum $surface): Collection
    {
        return Cache::rememberForever("client-languages.{$surface->value}", fn () => Language::query()
            ->where('surface', $surface->value)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get());
    }

    public function defaultLanguage(LanguageSurfaceEnum $surface): ?Language
    {
        return $this->languages($surface)->firstWhere('is_default', true)
            ?? $this->languages($surface)->first();
    }

    public function resolveLanguage(LanguageSurfaceEnum $surface, ?string $code): ?Language
    {
        $normalized = app(LanguageService::class)->normalizeCode((string) $code);

        return $this->languages($surface)
            ->first(fn (Language $language) => $language->code === $normalized)
            ?? $this->defaultLanguage($surface);
    }

    public function source(LanguageSurfaceEnum $surface): array
    {
        $path = config("languages.sources.{$surface->value}");
        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        return Cache::rememberForever(
            "client-language-source.{$surface->value}.".hash('sha256', $contents),
            function () use ($contents) {
                try {
                    $labels = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    return [];
                }

                if (! is_array($labels) || ($labels !== [] && array_is_list($labels))) {
                    return [];
                }

                $flat = Arr::dot($labels);
                foreach ($flat as $key => $value) {
                    if ($key === '' || mb_strlen($key) > 191 || ! is_string($value)) {
                        return [];
                    }
                }
                ksort($flat);

                return $flat;
            },
        );
    }

    public function translations(LanguageSurfaceEnum $surface, Language $language): Collection
    {
        return Cache::rememberForever(
            "client-language-labels.{$surface->value}.{$language->id}",
            fn () => LanguageTranslation::query()
                ->where('language_id', $language->id)
                ->pluck('value', 'key'),
        );
    }

    public function resolvedLabels(LanguageSurfaceEnum $surface, Language $language): array
    {
        $source = collect($this->source($surface));
        if ($language->code !== 'en') {
            $source = $source->merge($this->translations($surface, $language)->only($source->keys()));
        }

        return Arr::undot($source->all());
    }

    public function saveTranslations(LanguageSurfaceEnum $surface, Language $language, array $rows): void
    {
        $source = $this->source($surface);

        foreach ($rows as $row) {
            $key = (string) ($row['key'] ?? '');
            $value = trim((string) ($row['value'] ?? ''));
            if (! array_key_exists($key, $source)) {
                continue;
            }
            if ($value === '') {
                LanguageTranslation::query()
                    ->where('language_id', $language->id)
                    ->where('key', $key)
                    ->delete();

                continue;
            }
            if ($this->placeholders($source[$key]) !== $this->placeholders($value)) {
                throw new \InvalidArgumentException(__('labels.language_pack_invalid_placeholders', ['key' => $key]));
            }
            LanguageTranslation::query()->updateOrCreate([
                'language_id' => $language->id,
                'key' => $key,
            ], ['value' => $value]);
        }

        $this->forget($language);
    }

    public function import(LanguageSurfaceEnum $surface, Language $language, array $labels): int
    {
        $flat = Arr::dot($labels);
        $source = $this->source($surface);
        $count = 0;

        foreach ($flat as $key => $value) {
            if (! array_key_exists($key, $source)) {
                continue;
            }
            if (! is_string($value)) {
                throw new \InvalidArgumentException(__('labels.language_pack_invalid_value', ['key' => $key]));
            }
            $value = trim($value);
            if ($value === '') {
                $language->translations()->where('key', $key)->delete();

                continue;
            }
            if ($this->placeholders($source[$key]) !== $this->placeholders($value)) {
                throw new \InvalidArgumentException(__('labels.language_pack_invalid_placeholders', ['key' => $key]));
            }
            $language->translations()->updateOrCreate(['key' => $key], ['value' => $value]);
            $count++;
        }

        $this->forget($language);

        return $count;
    }

    public function forgetLanguages(LanguageSurfaceEnum $surface): void
    {
        Cache::forget("client-languages.{$surface->value}");
    }

    public function forgetAllLanguages(): void
    {
        foreach (LanguageSurfaceEnum::clientValues() as $surface) {
            $this->forgetLanguages(LanguageSurfaceEnum::from($surface));
        }
    }

    public function forget(Language $language): void
    {
        Cache::forget("client-language-labels.{$language->surface}.{$language->id}");
        Cache::forget("client-languages.{$language->surface}");
    }

    private function placeholders(string $value): array
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $value, $matches);
        $placeholders = array_values(array_unique(array_map('strtolower', $matches[1] ?? [])));
        sort($placeholders);

        return $placeholders;
    }
}
