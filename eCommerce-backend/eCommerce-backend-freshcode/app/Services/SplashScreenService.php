<?php

namespace App\Services;

use App\Models\SplashScreen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SplashScreenService
{
    public function save(array $data, array $files): SplashScreen
    {
        $screen = SplashScreen::query()->firstOrNew([
            'surface' => $data['surface'],
            'slot' => $data['slot'],
        ]);
        $oldPaths = $this->paths($screen);

        if ($data['slot'] === 'current') {
            $data['scheduled_date'] = null;
        }

        $data['background_color'] = $data['background_type'] === 'color' ? $data['background_color'] : null;
        $data['overlay_type'] = $data['overlay_enabled'] ? ($data['overlay_type'] ?? null) : null;

        foreach ([
            'background_image' => 'background_image_path',
            'background_video' => 'background_video_path',
            'overlay_image' => 'overlay_image_path',
            'overlay_lottie' => 'overlay_lottie_path',
        ] as $fileKey => $pathKey) {
            if (($upload = $files[$fileKey] ?? null) instanceof UploadedFile) {
                $directory = str_starts_with($fileKey, 'overlay_') ? 'splash-screens/overlays' : 'splash-screens/backgrounds';
                $data[$pathKey] = $upload->store($directory, 'public');
            }
        }

        if ($data['background_type'] !== 'image') {
            $data['background_image_path'] = null;
        }
        if ($data['background_type'] !== 'video') {
            $data['background_video_path'] = null;
        }
        if (! $data['overlay_enabled'] || $data['overlay_type'] !== 'image') {
            $data['overlay_image_path'] = null;
        }
        if (! $data['overlay_enabled'] || $data['overlay_type'] !== 'lottie') {
            $data['overlay_lottie_path'] = null;
        }

        $screen->fill($data);
        $screen->save();

        foreach ($oldPaths as $path) {
            if (! in_array($path, $this->paths($screen), true)) {
                $this->deleteIfUnused($path);
            }
        }

        return $screen->refresh();
    }

    public function effective(string $surface): ?SplashScreen
    {
        $today = today(config('app.timezone'));
        $staged = SplashScreen::query()
            ->where('surface', $surface)
            ->where('slot', 'staged')
            ->whereDate('scheduled_date', $today)
            ->first();

        return $staged ?? SplashScreen::query()->where('surface', $surface)->where('slot', 'current')->first();
    }

    public function remove(string $surface, string $slot): void
    {
        $screen = SplashScreen::query()
            ->where('surface', $surface)
            ->where('slot', $slot)
            ->first();

        if (! $screen) {
            return;
        }

        $paths = $this->paths($screen);
        $screen->delete();

        foreach ($paths as $path) {
            $this->deleteIfUnused($path);
        }
    }

    private function paths(SplashScreen $screen): array
    {
        return array_values(array_filter([
            $screen->background_image_path,
            $screen->background_video_path,
            $screen->overlay_image_path,
            $screen->overlay_lottie_path,
        ]));
    }

    private function deleteIfUnused(string $path): void
    {
        if (! SplashScreen::query()->where(function ($query) use ($path) {
            $query->where('background_image_path', $path)
                ->orWhere('background_video_path', $path)
                ->orWhere('overlay_image_path', $path)
                ->orWhere('overlay_lottie_path', $path);
        })->exists()) {
            Storage::disk('public')->delete($path);
        }
    }
}
