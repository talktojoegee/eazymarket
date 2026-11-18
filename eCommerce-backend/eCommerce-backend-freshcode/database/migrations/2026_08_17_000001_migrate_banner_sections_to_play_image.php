<?php

use App\Support\HomeCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $sections = DB::table('home_sections')->where('type', 'banners')->orderBy('id')->get();

            foreach ($sections as $section) {
                $legacyConfig = $this->decodeConfig($section->config);
                $config = array_merge($legacyConfig, [
                    'rows' => [$this->scrollRowConfig()],
                    '_legacy_banner_style' => $section->style,
                    '_legacy_banner_config' => $legacyConfig,
                ]);

                DB::table('home_sections')->where('id', $section->id)->update([
                    'type' => 'play_image',
                    'style' => 'scroll',
                    'config' => json_encode($config, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);

                $items = DB::table('home_section_items')->where('home_section_id', $section->id)->get();
                foreach ($items as $item) {
                    $legacyItemConfig = $this->decodeConfig($item->config);
                    $itemConfig = array_merge($legacyItemConfig, $this->playImageItemConfig(), [
                        '_legacy_banner_config' => $legacyItemConfig,
                    ]);

                    DB::table('home_section_items')->where('id', $item->id)->update([
                        'config' => json_encode($itemConfig, JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        HomeCache::flushLayout();
    }

    public function down(): void
    {
        DB::transaction(function () {
            $sections = DB::table('home_sections')->where('type', 'play_image')->orderBy('id')->get();

            foreach ($sections as $section) {
                $config = $this->decodeConfig($section->config);
                if (! array_key_exists('_legacy_banner_config', $config)) {
                    continue;
                }

                DB::table('home_sections')->where('id', $section->id)->update([
                    'type' => 'banners',
                    'style' => $config['_legacy_banner_style'] ?? null,
                    'config' => json_encode($config['_legacy_banner_config'], JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);

                $items = DB::table('home_section_items')->where('home_section_id', $section->id)->get();
                foreach ($items as $item) {
                    $itemConfig = $this->decodeConfig($item->config);
                    if (! array_key_exists('_legacy_banner_config', $itemConfig)) {
                        continue;
                    }

                    DB::table('home_section_items')->where('id', $item->id)->update([
                        'config' => json_encode($itemConfig['_legacy_banner_config'], JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        HomeCache::flushLayout();
    }

    private function scrollRowConfig(): array
    {
        return [
            'style' => 'scroll',
            'container_width' => 'container',
            'outer_padding' => 0,
            'outer_padding_custom' => '',
            'grid_gap' => 0,
            'border_radius' => 0,
            'hover_effect' => 'none',
            'items_to_show' => 1,
            'items_to_show_mobile' => 1,
            'show_scroll_buttons' => 1,
            'show_bullets' => 0,
            'auto_scroll' => 0,
            'auto_scroll_time' => 3,
        ];
    }

    private function playImageItemConfig(): array
    {
        return [
            'row_index' => 0,
            'cols' => 'auto',
            'cols_mobile' => 'auto',
            'aspect_ratio' => 'original',
            'media_type' => 'image',
            'alt' => '',
        ];
    }

    private function decodeConfig(mixed $config): array
    {
        if (is_array($config)) {
            return $config;
        }

        if (! is_string($config) || $config === '') {
            return [];
        }

        $decoded = json_decode($config, true);

        return is_array($decoded) ? $decoded : [];
    }
};
