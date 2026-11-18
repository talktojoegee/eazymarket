<?php

namespace App\Services;

class SellerLandingRegistry
{
    public const VARIANTS = [
        'hero' => ['background', 'split'],
        'benefits' => ['overlap_grid', 'feature_cards'],
        'steps' => ['sticky_story', 'timeline'],
        'registration_form' => ['stepped'],
        'testimonials' => ['card_grid', 'featured_carousel'],
    ];

    public function defaultConfiguration(): array
    {
        return [
            'schemaVersion' => 1,
            'seo' => [],
            'seoMedia' => [],
            'seoMediaSettings' => ['imageUseDefault' => true],
            'sections' => [
                $this->section('hero-default', 'hero', 'background', 'hypercommerce.hero'),
                $this->section('benefits-default', 'benefits', 'overlap_grid', 'hypercommerce.benefits', $this->items(['reach', 'manage', 'payments', 'insights'])),
                $this->section('steps-default', 'steps', 'sticky_story', 'hypercommerce.steps', $this->items(['register', 'list', 'start'])),
                $this->section('registration-default', 'registration_form', 'stepped', 'hypercommerce.registration'),
                $this->section('testimonials-default', 'testimonials', 'card_grid', 'hypercommerce.testimonials', $this->items(['localSeller', 'bakeryOwner', 'electronicsShop'])),
            ],
        ];
    }

    public function variants(): array
    {
        return self::VARIANTS;
    }

    public function languages(): array
    {
        return app(LanguageService::class)->active()->map(fn ($language) => [
            'code' => $language->code,
            'name' => $language->native_name,
            'direction' => $language->direction,
        ])->all();
    }

    public function sectionTemplates(): array
    {
        return collect($this->defaultConfiguration()['sections'])
            ->keyBy('type')
            ->all();
    }

    private function section(string $id, string $type, string $variant, string $preset, array $items = []): array
    {
        return compact('id', 'type', 'variant', 'preset', 'items') + [
            'enabled' => true,
            'content' => [],
            'settings' => [],
            'media' => [],
        ];
    }

    private function items(array $ids): array
    {
        return array_map(fn ($id) => [
            'id' => $id,
            'preset' => 'hypercommerce.'.$id,
            'content' => [],
            'settings' => [],
            'media' => [],
        ], $ids);
    }
}
