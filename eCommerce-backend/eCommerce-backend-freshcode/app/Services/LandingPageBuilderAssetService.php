<?php

namespace App\Services;

use App\Models\LandingPageBuilderDocument;
use App\Models\LandingPageMediaSlot;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class LandingPageBuilderAssetService
{
    private const ASSET_KEY = 'landing_page_builder_asset_key';

    private const STAGING_SLOT_PREFIX = 'landing-page-builder:';

    private function pendingPrefix(string $editorToken): string
    {
        return 'lpb:'.substr(hash('sha256', $editorToken), 0, 20).':';
    }

    public function upload(array $files, string $editorToken, User $user): array
    {
        $uploadedAssets = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $assetId = (string) Str::uuid();
            $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
            $slot = LandingPageMediaSlot::query()->create([
                'owner_type' => $user->getMorphClass(),
                'owner_id' => $user->getKey(),
                'slot' => $this->pendingPrefix($editorToken).substr(hash('sha256', $assetId), 0, 20),
                'use_default' => false,
            ]);
            try {
                $media = $slot->addMedia($file)
                    ->usingFileName($assetId.'.'.$extension)
                    ->withCustomProperties([
                        self::ASSET_KEY => $assetId,
                    ])
                    ->toMediaCollection(LandingPageMediaSlot::COLLECTION);
            } catch (Throwable $exception) {
                $slot->delete();
                throw $exception;
            }

            $uploadedAssets[] = [
                'id' => $assetId,
                'src' => $media->getUrl(),
                'name' => $file->getClientOriginalName(),
                'mimeType' => $media->mime_type,
                'size' => $media->size,
            ];
        }

        return $uploadedAssets;
    }

    public function attachEditorAssets(LandingPageBuilderDocument $document, string $editorToken, User $user): void
    {
        LandingPageMediaSlot::query()
            ->where('owner_type', $user->getMorphClass())
            ->where('owner_id', $user->getKey())
            ->where(fn ($query) => $query
                ->where('slot', 'like', $this->pendingPrefix($editorToken).'%')
                ->orWhere('slot', 'like', self::STAGING_SLOT_PREFIX.$editorToken.':%'))
            ->get()
            ->each(function (LandingPageMediaSlot $slot) use ($document): void {
                $slot->getMedia(LandingPageMediaSlot::COLLECTION)->each(function (Media $media) use ($document): void {
                    $media->model_type = $document->getMorphClass();
                    $media->model_id = $document->getKey();
                    $media->collection_name = LandingPageBuilderDocument::MEDIA_COLLECTION;
                    $media->save();
                });
                $slot->delete();
            });
    }

    public function deleteMarkedAssets(LandingPageBuilderDocument $document, array $content): void
    {
        $project = json_decode($content['project'] ?? '', true);
        if (! is_array($project)) {
            $project = [];
        }

        $projectContent = $project;
        unset($projectContent['assets']);
        $usedContent = json_encode($projectContent).($content['html'] ?? '').($content['css'] ?? '');
        $deletedIds = [];

        $document->getMedia(LandingPageBuilderDocument::MEDIA_COLLECTION)
            ->each(function (Media $media) use ($usedContent, &$deletedIds): void {
                $assetId = $media->getCustomProperty(self::ASSET_KEY);
                if (is_string($assetId) && $assetId !== '' && ! str_contains($usedContent, $assetId)) {
                    $media->delete();
                    $deletedIds[] = $assetId;
                }
            });

        if ($deletedIds !== [] && isset($project['assets']) && is_array($project['assets'])) {
            $project['assets'] = array_values(array_filter($project['assets'], function ($asset) use ($deletedIds): bool {
                $serialized = is_string($asset) ? $asset : json_encode($asset);

                return ! array_any($deletedIds, fn ($id) => str_contains($serialized, $id));
            }));
            $document->update(['project_data' => json_encode($project)]);
        }
    }

    public function purgeExpiredPendingAssets(): void
    {
        LandingPageMediaSlot::query()
            ->where(fn ($query) => $query
                ->where('slot', 'like', 'lpb:%')
                ->orWhere('slot', 'like', self::STAGING_SLOT_PREFIX.'%'))
            ->where('created_at', '<', now()->subDay())
            ->get()
            ->each(fn (LandingPageMediaSlot $slot) => $slot->delete());
    }
}
