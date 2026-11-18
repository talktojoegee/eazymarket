<?php

namespace App\Services;

use App\Enums\SpatieMediaCollectionName;
use App\Models\Setting;
use App\Models\StoreProductVariant;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class WatchBuyStatusService
{
    public function __construct(
        private readonly WatchBuyService $watchBuy,
        private readonly WatchBuyMediaInspector $mediaInspector,
    ) {}

    public function enabled(): bool
    {
        return $this->watchBuy->enabled();
    }

    public function activeStatusesQuery(): Builder
    {
        return WatchBuyStatus::query()->publiclyVisible();
    }

    public function eligibleProducts(WatchBuyStatus $status): Collection
    {
        if ($status->relationLoaded('eligibleProducts')) {
            return $status->eligibleProducts;
        }

        return $status->products()->with(['product', 'store', 'variant'])
            ->where(fn (Builder $query) => $this->watchBuy->eligibleAttachmentQuery($query))
            ->get();
    }

    public function withApiRelations(Builder|HasMany $query, ?int $userId = null): Builder|HasMany
    {
        return $query->with([
            'media',
            'creator',
            'eligibleProducts' => fn (HasMany $products) => $this->watchBuy
                ->eligibleAttachmentQuery($products->getQuery())
                ->with(['product', 'store', 'variant']),
        ])->when($userId, fn (Builder $statuses) => $statuses->with([
            'views' => fn (HasMany $views) => $views->where('user_id', $userId),
        ]));
    }

    public function feed(?string $sessionId, ?string $cursor, int $perPage, ?int $userId): array
    {
        $sessionId = $sessionId ?: (string) Str::uuid();
        $perPage = max(1, min($perPage, 50));
        $seenProfileIds = $this->decodeCursor($cursor, $sessionId);

        if (! $this->enabled()) {
            return [
                'items' => collect(),
                'session_id' => $sessionId,
                'next_cursor' => null,
            ];
        }

        $officialId = WatchBuyProfile::query()
            ->where('profile_type', 'platform')
            ->where('status', 'active')
            ->whereHas('statuses', fn (Builder $status) => $status->active()->where('source', 'admin_direct'))
            ->value('id');
        $officialId = $officialId ? (int) $officialId : null;
        $prioritizeOfficial = $officialId && (! $userId || WatchBuyStatus::query()
            ->active()
            ->where('profile_id', $officialId)
            ->whereDoesntHave('views', fn (Builder $views) => $views->where('user_id', $userId))
            ->exists());
        $selectedIds = collect();

        if ($prioritizeOfficial && ! in_array($officialId, $seenProfileIds, true)) {
            $selectedIds->push($officialId);
        }

        $profileIds = WatchBuyProfile::query()
            ->where('profile_type', 'seller')
            ->where('status', 'active')
            ->whereHas('statuses', fn (Builder $status) => $status->active())
            ->pluck('id')
            ->when($officialId && ! $prioritizeOfficial, fn (Collection $ids) => $ids->push($officialId))
            ->sortBy(fn (int $id) => hash('sha256', $sessionId.':'.$id), SORT_STRING)
            ->reject(fn (int $id) => in_array($id, $seenProfileIds, true))
            ->values();

        if ($userId && $profileIds->isNotEmpty()) {
            $unseenProfileIds = WatchBuyStatus::query()
                ->active()
                ->whereIn('profile_id', $profileIds)
                ->whereDoesntHave('views', fn (Builder $views) => $views->where('user_id', $userId))
                ->distinct()
                ->pluck('profile_id')
                ->flip();
            [$unseenIds, $seenIds] = $profileIds->partition(fn (int $id) => $unseenProfileIds->has($id));
            $profileIds = $unseenIds->concat($seenIds)->values();
        }

        $availableSlots = max(0, $perPage - $selectedIds->count());
        $selectedIds = $selectedIds->concat($profileIds->take($availableSlots))->values();
        $profiles = WatchBuyProfile::query()
            ->whereKey($selectedIds)
            ->with('media')
            ->withCount([
                'statuses as status_count' => fn (Builder $status) => $status->active(),
                'statuses as unseen_status_count' => fn (Builder $status) => $status
                    ->active()
                    ->when($userId, fn (Builder $active) => $active->whereDoesntHave(
                        'views',
                        fn (Builder $views) => $views->where('user_id', $userId),
                    )),
            ])
            ->get()
            ->sortBy(fn (WatchBuyProfile $profile) => $selectedIds->search($profile->id))
            ->values();

        $profiles->each(function (WatchBuyProfile $profile) {
            $profile->setAttribute('has_active_status', $profile->status_count > 0);
            $profile->setAttribute('has_unseen_status', $profile->unseen_status_count > 0);
        });

        $allSeenProfileIds = array_values(array_unique([...$seenProfileIds, ...$selectedIds->all()]));
        $remaining = $profileIds->count() > $availableSlots;

        return [
            'items' => $profiles,
            'session_id' => $sessionId,
            'next_cursor' => $remaining ? $this->encodeCursor($sessionId, $allSeenProfileIds) : null,
        ];
    }

    public function profileStatuses(string $username, ?int $userId, ?string $cursor, int $perPage): array
    {
        abort_unless($this->enabled(), 404);
        $perPage = max(1, min($perPage, 50));
        $profile = WatchBuyProfile::query()
            ->where('username', Str::lower($username))
            ->where('status', 'active')
            ->whereHas('statuses', fn (Builder $status) => $status->active())
            ->with('media')
            ->firstOrFail();
        $statusesQuery = $this->withApiRelations(
            $profile->statuses()->active()->oldest('created_at')->orderBy('id'),
            $userId,
        );
        $total = (clone $statusesQuery)->count();
        $statuses = $statusesQuery->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
        $profile->setAttribute('has_active_status', true);
        $profile->setAttribute('has_unseen_status', $this->profileHasUnseenStatus($profile, $userId));

        return [
            'profile' => $profile,
            'statuses' => $statuses,
            'total' => $total,
        ];
    }

    public function save(WatchBuyProfile $profile, array $data, ?UploadedFile $media, int $actorId): WatchBuyStatus
    {
        abort_unless($this->enabled(), 404);
        abort_if($profile->status !== 'active', 403, __('labels.watch_buy_profile_is_suspended'));
        $text = trim((string) ($data['text'] ?? '')) ?: null;

        if (! $text && ! $media) {
            throw ValidationException::withMessages(['text' => __('labels.watch_buy_status_content_required')]);
        }

        $config = $this->watchBuy->config();
        $attachmentIds = array_values(array_unique(array_map('intval', $data['attachment_ids'] ?? [])));
        if (count($attachmentIds) > (int) $config['maxProductsPerStatus']) {
            throw ValidationException::withMessages(['attachment_ids' => __('labels.watch_buy_status_product_limit', ['max' => $config['maxProductsPerStatus']])]);
        }

        $sellerId = $data['source'] === 'admin_direct' ? null : $profile->seller_id;
        $variants = $attachmentIds === []
            ? collect()
            : $this->watchBuy->eligibleProductVariants($sellerId, $attachmentIds)->keyBy('id');
        if ($variants->count() !== count($attachmentIds)) {
            throw ValidationException::withMessages(['attachment_ids' => __('labels.watch_buy_products_unavailable')]);
        }
        $storeMappings = $attachmentIds === []
            ? collect()
            : $this->watchBuy->eligibleStoreVariantsQuery($sellerId)
                ->whereIn('product_variant_id', $attachmentIds)
                ->get()
                ->groupBy('product_variant_id');
        $attributes = $this->mediaAttributes($media, (int) $config['maxStatusDurationSeconds']);
        $createdAt = now();

        return DB::transaction(function () use ($profile, $data, $text, $actorId, $createdAt, $attributes, $attachmentIds, $variants, $storeMappings, $media) {
            $status = WatchBuyStatus::create([
                'profile_id' => $profile->id,
                'source' => $data['source'],
                'text' => $text,
                'created_by' => $actorId,
                'created_at' => $createdAt,
                'expires_at' => $createdAt->copy()->addDay(),
                ...$attributes,
            ]);

            foreach ($attachmentIds as $index => $id) {
                $variant = $variants[$id];
                $mapping = $storeMappings[$id]->first(fn (StoreProductVariant $candidate) => $candidate->is_in_stock)
                    ?? $storeMappings[$id]->first();
                $status->products()->create([
                    'product_id' => $variant->product_id,
                    'store_id' => $mapping->store_id,
                    'product_variant_id' => $variant->id,
                    'sort_order' => $index + 1,
                ]);
            }

            if ($media) {
                $status->addMedia($media)->toMediaCollection(SpatieMediaCollectionName::WATCH_BUY_STATUS_MEDIA());
            }

            return $status->refresh()->load(['profile', 'media', 'creator']);
        });
    }

    public function markSeen(array $statusIds, int $userId): Collection
    {
        abort_unless($this->enabled(), 404);
        $statusIds = array_values(array_unique(array_map('intval', $statusIds)));
        abort_unless($this->activeStatusesQuery()->whereKey($statusIds)->count() === count($statusIds), 404);
        $seenAt = now();

        DB::transaction(function () use ($statusIds, $userId, $seenAt) {
            foreach ($statusIds as $statusId) {
                DB::table('watch_buy_status_views')->updateOrInsert(
                    ['status_id' => $statusId, 'user_id' => $userId],
                    ['seen_at' => $seenAt]
                );
            }
        });

        return collect($statusIds)->map(fn (int $statusId) => [
            'status_id' => $statusId,
            'seen_at' => $seenAt,
        ]);
    }

    public function delete(WatchBuyStatus $status): void
    {
        DB::transaction(fn () => $status->delete());
    }

    public function deleteExpired(): int
    {
        if (Setting::isDemoModeEnabled()) {
            return 0;
        }

        $deleted = 0;
        WatchBuyStatus::query()->where('expires_at', '<=', now())->orderBy('id')->chunkById(100, function ($statuses) use (&$deleted) {
            foreach ($statuses as $status) {
                $this->delete($status);
                $deleted++;
            }
        });

        return $deleted;
    }

    private function profileHasUnseenStatus(WatchBuyProfile $profile, ?int $userId): bool
    {
        $statuses = $profile->statuses()->active();

        return $userId
            ? $statuses->whereDoesntHave('views', fn (Builder $views) => $views->where('user_id', $userId))->exists()
            : $statuses->exists();
    }

    private function mediaAttributes(?UploadedFile $media, int $maxDurationSeconds): array
    {
        if (! $media) {
            return [
                'content_type' => 'text',
                'media_mime' => null,
                'media_size_bytes' => null,
                'media_width' => null,
                'media_height' => null,
                'duration_ms' => null,
            ];
        }

        $mime = strtolower((string) $media->getMimeType());
        if (str_starts_with($mime, 'image/')) {
            $dimensions = getimagesize($media->getRealPath());
            if (! $dimensions) {
                throw ValidationException::withMessages(['media' => __('labels.watch_buy_status_image_invalid')]);
            }

            return [
                'content_type' => 'image',
                'media_mime' => $mime,
                'media_size_bytes' => (int) $media->getSize(),
                'media_width' => (int) $dimensions[0],
                'media_height' => (int) $dimensions[1],
                'duration_ms' => null,
            ];
        }

        try {
            $metadata = $this->mediaInspector->inspect($media, $maxDurationSeconds, 'status');
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['media' => $exception->errors()['video'] ?? [$exception->getMessage()]]);
        }

        return [
            'content_type' => 'video',
            'media_mime' => $mime,
            'media_size_bytes' => $metadata['video_size_bytes'],
            'media_width' => $metadata['video_width'],
            'media_height' => $metadata['video_height'],
            'duration_ms' => $metadata['duration_ms'],
        ];
    }

    private function encodeCursor(string $sessionId, array $seenProfileIds): string
    {
        return Crypt::encryptString(json_encode(['session_id' => $sessionId, 'seen_profile_ids' => $seenProfileIds], JSON_THROW_ON_ERROR));
    }

    private function decodeCursor(?string $cursor, string $sessionId): array
    {
        if (! $cursor) {
            return [];
        }

        try {
            $payload = json_decode(Crypt::decryptString($cursor), true, flags: JSON_THROW_ON_ERROR);
            if (($payload['session_id'] ?? null) !== $sessionId || ! is_array($payload['seen_profile_ids'] ?? null)) {
                throw new \RuntimeException;
            }

            return array_values(array_unique(array_map('intval', $payload['seen_profile_ids'])));
        } catch (Throwable) {
            throw ValidationException::withMessages(['cursor' => __('labels.watch_buy_status_cursor_invalid')]);
        }
    }
}
