<?php

namespace App\Services;

use App\Models\OnboardingPage;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OnboardingPageService
{
    public function isEnabled(string $surface): bool
    {
        $setting = Setting::query()->find($this->enabledSettingVariable($surface));
        $values = $setting?->value;

        return is_array($values) ? (bool) ($values['enabled'] ?? true) : true;
    }

    public function setEnabled(string $surface, bool $enabled): void
    {
        Setting::query()->updateOrCreate(
            ['variable' => $this->enabledSettingVariable($surface)],
            ['value' => json_encode(['enabled' => $enabled])]
        );
    }

    public function isSkipEnabled(string $surface): bool
    {
        $setting = Setting::query()->find($this->skipSettingVariable($surface));
        if (! $setting) {
            $setting = Setting::query()->find('onboarding_skip_enabled');
        }
        $values = $setting?->value;

        return is_array($values) ? (bool) ($values['enabled'] ?? true) : true;
    }

    public function setSkipEnabled(string $surface, bool $enabled): void
    {
        Setting::query()->updateOrCreate(
            ['variable' => $this->skipSettingVariable($surface)],
            ['value' => json_encode(['enabled' => $enabled])]
        );
    }

    private function skipSettingVariable(string $surface): string
    {
        return 'onboarding_'.$surface.'_skip_enabled';
    }

    private function enabledSettingVariable(string $surface): string
    {
        return 'onboarding_'.$surface;
    }

    public function create(array $data, ?UploadedFile $image = null, ?UploadedFile $backgroundImage = null): OnboardingPage
    {
        $data['image_path'] = $image?->store('onboarding', 'public');
        $data['background_image_path'] = $data['background_type'] === 'image'
            ? $backgroundImage?->store('onboarding/backgrounds', 'public')
            : null;
        $data['background_color'] = $data['background_type'] === 'color' ? $data['background_color'] : '#ffffff';

        return OnboardingPage::create($data);
    }

    public function update(OnboardingPage $page, array $data, ?UploadedFile $image = null, ?UploadedFile $backgroundImage = null): OnboardingPage
    {
        $oldImagePath = $page->image_path;
        $oldBackgroundImagePath = $page->background_image_path;
        if ($image) {
            $newPath = $image->store('onboarding', 'public');
            $data['image_path'] = $newPath;
        }

        if ($data['background_type'] === 'color') {
            $data['background_image_path'] = null;
        } elseif ($backgroundImage) {
            $data['background_image_path'] = $backgroundImage->store('onboarding/backgrounds', 'public');
        }
        $data['background_color'] = $data['background_type'] === 'color' ? $data['background_color'] : '#ffffff';

        $page->update($data);

        if ($image) {
            $this->deleteImageIfUnused($oldImagePath);
        }
        if ($oldBackgroundImagePath && $oldBackgroundImagePath !== $page->background_image_path) {
            $this->deleteImageIfUnused($oldBackgroundImagePath);
        }

        return $page->refresh();
    }

    public function delete(OnboardingPage $page): void
    {
        $surface = $page->surface;
        $imagePath = $page->image_path;
        $backgroundImagePath = $page->background_image_path;
        $page->delete();
        $this->deleteImageIfUnused($imagePath);
        if ($backgroundImagePath) {
            $this->deleteImageIfUnused($backgroundImagePath);
        }
        if (! OnboardingPage::query()->where('surface', $surface)->exists()) {
            $this->setEnabled($surface, false);
        }
    }

    private function deleteImageIfUnused(?string $imagePath): void
    {
        if (! $imagePath) {
            return;
        }

        if (! OnboardingPage::query()
            ->where('image_path', $imagePath)
            ->orWhere('background_image_path', $imagePath)
            ->exists()) {
            Storage::disk('public')->delete($imagePath);
        }
    }
}
