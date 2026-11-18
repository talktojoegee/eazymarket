<?php

namespace App\Services;

use App\Enums\SpatieMediaCollectionName;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreProductVariant;
use App\Models\WatchBuyModerationLog;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyReel;
use App\Models\WatchBuyReelLike;
use App\Notifications\WatchBuyModerationNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class WatchBuyService
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly WatchBuyMediaInspector $mediaInspector,
    ) {}

    public function config(): array
    {
        $defaults = [
            'enabled' => true,
            'title' => __('labels.watch_buy'),
            'maxDurationSeconds' => 60,
            'maxUploadMb' => 50,
            'maxProductsPerReel' => 10,
            'maxStatusDurationSeconds' => 15,
            'maxProductsPerStatus' => 5,
        ];
        $stored = $this->settings->getSettingValues('watch_and_buy');
        $config = array_merge($defaults, array_intersect_key($stored, $defaults));
        $config['effectiveMaxUploadMb'] = min((int) $config['maxUploadMb'], $this->serverUploadLimitMb());

        return $config;
    }

    public function enabled(): bool
    {
        return (bool) $this->config()['enabled'];
    }

    public function eligibleReelsQuery(): Builder
    {
        return WatchBuyReel::query()
            ->publiclyVisible()
            ->whereHas('products', fn (Builder $attachment) => $this->eligibleAttachmentQuery($attachment));
    }

    public function productDetailsContent(Product $product): array
    {
        if (! $this->enabled()) {
            return ['profile' => null, 'reels' => collect()];
        }

        $profile = WatchBuyProfile::query()
            ->where('seller_id', $product->seller_id)
            ->where('status', 'active')
            ->first();
        $reels = $this->eligibleReelsQuery()
            ->whereHas('products', fn (Builder $attachment) => $this
                ->eligibleAttachmentQuery($attachment)
                ->where('product_id', $product->id))
            ->with('profile')
            ->latest('published_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return compact('profile', 'reels');
    }

    public function eligibleAttachmentQuery(Builder $query): Builder
    {
        return $query
            ->whereHas('product', fn (Builder $product) => $product
                ->where('status', 'active')
                ->where('verification_status', 'approved'))
            ->whereHas('store', fn (Builder $store) => $store
                ->where('verification_status', 'approved')
                ->where('visibility_status', 'visible'))
            ->where(function (Builder $attachment) {
                $attachment->whereNull('product_variant_id')->orWhereHas('variant', fn (Builder $variant) => $variant
                    ->where('availability', true)
                    ->where('visibility', 'published'));
            });
    }

    public function eligibleProducts(WatchBuyReel $reel): Collection
    {
        return $reel->products()->reorder()->with(['product', 'store', 'variant'])
            ->where(fn (Builder $query) => $this->eligibleAttachmentQuery($query))
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->get();
    }

    public function feed(?string $sessionId, ?string $cursor, ?string $slug, int $perPage): array
    {
        $sessionId = $sessionId ?: (string) Str::uuid();
        $perPage = max(1, min($perPage, 50));
        $seenIds = $this->decodeCursor($cursor, $sessionId);
        $slug = $slug !== null && trim($slug) !== '' ? trim($slug) : null;

        if (! $this->enabled()) {
            return [
                'items' => collect(),
                'session_id' => $sessionId,
                'next_cursor' => null,
                'slug_not_found' => $slug !== null,
            ];
        }

        $sharedReel = $slug
            ? $this->eligibleReelsQuery()->with('profile')->where('slug', $slug)->first()
            : null;
        $prependSharedReel = $sharedReel && $cursor === null;
        $randomPageSize = $perPage - ($prependSharedReel ? 1 : 0);
        $ids = $this->eligibleReelsQuery()
            ->when($sharedReel, fn (Builder $query) => $query->where('watch_buy_reels.id', '!=', $sharedReel->id))
            ->pluck('id')
            ->sortBy(fn (int $id) => hash('sha256', $sessionId.':'.$id), SORT_STRING)
            ->reject(fn (int $id) => in_array($id, $seenIds, true))
            ->values();

        $pageIds = $ids->take($randomPageSize)->values();
        $randomReels = $this->eligibleReelsQuery()
            ->whereKey($pageIds)
            ->with('profile')
            ->get()
            ->sortBy(fn (WatchBuyReel $reel) => $pageIds->search($reel->id))
            ->values();
        $reels = $prependSharedReel
            ? collect([$sharedReel])->concat($randomReels)->values()
            : $randomReels;
        $allSeenIds = array_values(array_unique([...$seenIds, ...$reels->pluck('id')->all()]));

        return [
            'items' => $reels,
            'session_id' => $sessionId,
            'next_cursor' => $pageIds->count() < $ids->count() ? $this->encodeCursor($sessionId, $allSeenIds) : null,
            'slug_not_found' => $slug !== null && $sharedReel === null,
        ];
    }

    public function eligibleStoreVariantsQuery(?int $sellerId = null): Builder
    {
        return StoreProductVariant::query()
            ->with(['store', 'productVariant.product.media'])
            ->whereHas('store', fn (Builder $store) => $store
                ->where('verification_status', 'approved')
                ->where('visibility_status', 'visible')
                ->when($sellerId, fn (Builder $query) => $query->where('seller_id', $sellerId)))
            ->whereHas('productVariant', fn (Builder $variant) => $variant
                ->where('availability', true)
                ->where('visibility', 'published')
                ->whereHas('product', fn (Builder $product) => $product
                    ->where('status', 'active')
                    ->where('verification_status', 'approved')
                    ->when($sellerId, fn (Builder $query) => $query->where('seller_id', $sellerId))));
    }

    public function eligibleProductVariantsQuery(?int $sellerId = null): Builder
    {
        return ProductVariant::query()
            ->with(['product.media', 'locationInventories'])
            ->where('availability', true)
            ->where('visibility', 'published')
            ->whereHas('product', fn (Builder $product) => $product
                ->where('status', 'active')
                ->where('verification_status', 'approved')
                ->when($sellerId, fn (Builder $query) => $query->where('seller_id', $sellerId)))
            ->whereHas('storeProductVariants.store', fn (Builder $store) => $store
                ->where('verification_status', 'approved')
                ->where('visibility_status', 'visible')
                ->when($sellerId, fn (Builder $query) => $query->where('seller_id', $sellerId)));
    }

    public function eligibleProductVariants(?int $sellerId = null, array $ids = []): Collection
    {
        return $this->eligibleProductVariantsQuery($sellerId)
            ->when($ids !== [], fn (Builder $query) => $query->whereKey($ids))
            ->get();
    }

    public function eligibleProductVariantOptions(?int $sellerId, string $search = '', array $findIn = []): Collection
    {
        $findIn = array_values(array_unique(array_filter(array_map('intval', $findIn))));
        $query = $this->eligibleProductVariantsQuery($sellerId);

        if ($findIn !== []) {
            $query->whereKey($findIn)->limit(20);
        } else {
            $query->when($search !== '', fn (Builder $builder) => $builder->where(function (Builder $match) use ($search) {
                $match->whereHas('product', fn (Builder $product) => $product->where('title', 'like', "%{$search}%"))
                    ->orWhere('title', 'like', "%{$search}%");
            }))->limit(15);
        }

        $options = $query->latest('product_variants.id')->get()->map(function (ProductVariant $variant) {
            $product = $variant->product;
            $isOutOfStock = $this->productVariantIsOutOfStock($variant);

            return [
                'value' => (string) $variant->id,
                'text' => collect([$product->title, $variant->title])->filter()->implode(' / '),
                'product_title' => $product->title,
                'variant_title' => $variant->title,
                'image_url' => $product->main_image,
                'is_out_of_stock' => $isOutOfStock,
                'stock_status_label' => $isOutOfStock ? __('labels.out_of_stock') : null,
            ];
        });

        if ($findIn === []) {
            return $options->values();
        }

        return $options->sortBy(fn (array $option) => array_search((int) $option['value'], $findIn, true))->values();
    }

    public function productVariantIsOutOfStock(ProductVariant $variant): bool
    {
        $inventories = $variant->locationInventories->where('is_fulfillment_location', true);

        if ($inventories->isEmpty()) {
            return true;
        }
        if ($inventories->contains(fn ($inventory) => ! $inventory->tracked)) {
            return false;
        }

        return $inventories->sum(fn ($inventory) => max(0, $inventory->stock - $inventory->reserved)) <= 0;
    }

    public function reelProductVariantSelection(WatchBuyReel $reel): array
    {
        if (! $reel->exists) {
            return ['attachment_ids' => [], 'primary_attachment_id' => null];
        }

        $attachments = $reel->products()->get();
        $selected = $attachments->pluck('product_variant_id')->filter()->unique()->values();
        $primary = $attachments->firstWhere('is_primary', true);
        $primaryId = $primary?->product_variant_id;

        return ['attachment_ids' => $selected->all(), 'primary_attachment_id' => $primaryId];
    }

    public function saveProfile(WatchBuyProfile $profile, array $data, ?UploadedFile $photo, ?int $actorId, bool $allowSuspended = false): WatchBuyProfile
    {
        abort_if($profile->exists && $profile->status === 'suspended' && ! $allowSuspended, 403, __('labels.watch_buy_profile_is_suspended'));
        if (! $photo && ! $profile->getFirstMedia(SpatieMediaCollectionName::WATCH_BUY_PROFILE_PHOTO())) {
            throw ValidationException::withMessages(['photo' => __('labels.watch_buy_profile_photo_required')]);
        }
        $data['username'] = Str::lower($data['username']);
        $data['created_by'] ??= $actorId;
        $profile->fill($data)->save();

        if ($photo) {
            $profile->addMedia($photo)->toMediaCollection(SpatieMediaCollectionName::WATCH_BUY_PROFILE_PHOTO());
        }

        return $profile->refresh();
    }

    public function saveReel(WatchBuyReel $reel, WatchBuyProfile $profile, array $data, ?UploadedFile $video, ?UploadedFile $cover, int $actorId): WatchBuyReel
    {
        abort_if($profile->status !== 'active', 403, __('labels.watch_buy_profile_is_suspended'));
        if (! $profile->getFirstMedia(SpatieMediaCollectionName::WATCH_BUY_PROFILE_PHOTO())) {
            throw ValidationException::withMessages(['profile_id' => __('labels.watch_buy_profile_must_have_photo')]);
        }
        $config = $this->config();
        $attachmentIds = array_values(array_unique(array_map('intval', $data['attachment_ids'] ?? [])));
        $primaryId = (int) ($data['primary_attachment_id'] ?? 0);

        if ($attachmentIds === [] || count($attachmentIds) > (int) $config['maxProductsPerReel']) {
            throw ValidationException::withMessages(['attachment_ids' => __('labels.watch_buy_select_products_range', ['max' => $config['maxProductsPerReel']])]);
        }
        if (! in_array($primaryId, $attachmentIds, true)) {
            throw ValidationException::withMessages(['primary_attachment_id' => __('labels.watch_buy_primary_must_be_attached')]);
        }

        $sellerId = $data['source'] === 'admin_direct' ? null : $profile->seller_id;
        $variants = $this->eligibleProductVariants($sellerId, $attachmentIds)->keyBy('id');
        if ($variants->count() !== count($attachmentIds)) {
            throw ValidationException::withMessages(['attachment_ids' => __('labels.watch_buy_products_unavailable')]);
        }
        $storeMappings = $this->eligibleStoreVariantsQuery($sellerId)
            ->whereIn('product_variant_id', $attachmentIds)
            ->get()
            ->groupBy('product_variant_id');

        if (! $reel->exists && ! $video) {
            throw ValidationException::withMessages(['video' => __('labels.watch_buy_video_required')]);
        }
        if (! $reel->exists && ! $cover) {
            throw ValidationException::withMessages(['cover' => __('labels.watch_buy_cover_required')]);
        }

        $attributes = [
            'profile_id' => $profile->id,
            'source' => $data['source'],
            'caption' => $data['caption'] ?? null,
            'updated_by' => $actorId,
        ];
        if ($video) {
            $attributes += $this->mediaInspector->inspect($video, (int) $config['maxDurationSeconds']);
        }
        if (! $reel->exists) {
            $attributes['created_by'] = $actorId;
            $attributes['status'] = 'published';
            $attributes['published_at'] = now();
        }
        return DB::transaction(function () use ($reel, $attributes, $attachmentIds, $primaryId, $variants, $storeMappings, $video, $cover) {
            $reel->fill($attributes)->save();
            $reel->products()->delete();
            foreach ($attachmentIds as $index => $id) {
                $variant = $variants[$id];
                $mapping = $storeMappings[$id]->first(fn (StoreProductVariant $candidate) => $candidate->is_in_stock)
                    ?? $storeMappings[$id]->first();
                $reel->products()->create([
                    'product_id' => $variant->product_id,
                    'store_id' => $mapping->store_id,
                    'product_variant_id' => $variant->id,
                    'sort_order' => $index + 1,
                    'is_primary' => $id === $primaryId,
                ]);
            }
            if ($video) {
                $reel->addMedia($video)->toMediaCollection(SpatieMediaCollectionName::WATCH_BUY_REEL_VIDEO());
            }
            if ($cover) {
                $reel->addMedia($cover)->toMediaCollection(SpatieMediaCollectionName::WATCH_BUY_REEL_COVER());
            }

            return $reel->refresh();
        });
    }

    public function setPublished(WatchBuyReel $reel, bool $published, int $actorId): WatchBuyReel
    {
        abort_if($reel->status === 'blocked' || $reel->profile->status === 'suspended', 403);
        $reel->update([
            'status' => $published ? 'published' : 'unpublished',
            'published_at' => $published ? ($reel->published_at ?: now()) : null,
            'updated_by' => $actorId,
        ]);

        return $reel;
    }

    public function block(WatchBuyReel $reel, string $reason, int $actorId): WatchBuyReel
    {
        if ($reel->status !== 'blocked') {
            $previous = $reel->status;
            $reel->update([
                'status_before_block' => $previous,
                'status' => 'blocked',
                'blocked_by' => $actorId,
                'block_reason' => $reason,
                'blocked_at' => now(),
                'updated_by' => $actorId,
            ]);
            $this->log('reel', $reel->id, 'block', $actorId, $previous, 'blocked', $reason);
            $this->notifySeller($reel->profile, $reason, $reel);
        }

        return $reel;
    }

    public function unblock(WatchBuyReel $reel, int $actorId): WatchBuyReel
    {
        if ($reel->status === 'blocked') {
            $next = $reel->status_before_block ?: 'unpublished';
            $reel->update([
                'status' => $next,
                'status_before_block' => null,
                'blocked_by' => null,
                'block_reason' => null,
                'blocked_at' => null,
                'updated_by' => $actorId,
            ]);
            $this->log('reel', $reel->id, 'unblock', $actorId, 'blocked', $next);
        }

        return $reel;
    }

    public function suspend(WatchBuyProfile $profile, string $reason, int $actorId): WatchBuyProfile
    {
        if ($profile->status !== 'suspended') {
            $previous = $profile->status;
            $profile->update(['status' => 'suspended', 'suspended_by' => $actorId, 'suspension_reason' => $reason, 'suspended_at' => now()]);
            $this->log('profile', $profile->id, 'suspend', $actorId, $previous, 'suspended', $reason);
            $this->notifySeller($profile, $reason);
        }

        return $profile;
    }

    public function unsuspend(WatchBuyProfile $profile, int $actorId): WatchBuyProfile
    {
        $profile->update(['status' => 'active', 'suspended_by' => null, 'suspension_reason' => null, 'suspended_at' => null]);
        $this->log('profile', $profile->id, 'unsuspend', $actorId, 'suspended', 'active');

        return $profile;
    }

    public function deleteReel(WatchBuyReel $reel, ?int $actorId): void
    {
        DB::transaction(function () use ($reel, $actorId) {
            $this->log('reel', $reel->id, 'delete', $actorId, $reel->status, null, null, [
                'reel_id' => $reel->id,
                'slug' => $reel->slug,
                'profile_id' => $reel->profile_id,
                'source' => $reel->source,
            ]);
            $reel->delete();
        });
    }

    public function like(WatchBuyReel $reel, int $userId): int
    {
        abort_unless($this->enabled() && $this->eligibleReelsQuery()->whereKey($reel->id)->exists(), 404);
        DB::transaction(function () use ($reel, $userId) {
            $inserted = DB::table('watch_buy_reel_likes')->insertOrIgnore([
                'reel_id' => $reel->id,
                'user_id' => $userId,
                'created_at' => now(),
            ]);
            if ($inserted === 1) {
                $reel->increment('like_count');
            }
        });

        return (int) $reel->fresh()->like_count;
    }

    public function unlike(WatchBuyReel $reel, int $userId): int
    {
        abort_unless($this->enabled(), 404);
        DB::transaction(function () use ($reel, $userId) {
            if (WatchBuyReelLike::where(['reel_id' => $reel->id, 'user_id' => $userId])->delete()) {
                $reel->whereKey($reel->id)->where('like_count', '>', 0)->decrement('like_count');
            }
        });

        return (int) $reel->fresh()->like_count;
    }

    public function setLiked(WatchBuyReel $reel, int $userId, bool $liked): int
    {
        return $liked
            ? $this->like($reel, $userId)
            : $this->unlike($reel, $userId);
    }

    public function setLikes(array $items, int $userId): Collection
    {
        abort_unless($this->enabled(), 404);
        $states = collect($items)->mapWithKeys(fn (array $item) => [
            (int) $item['reel_id'] => (bool) $item['liked'],
        ]);
        $likeIds = $states->filter()->keys();

        abort_unless(
            $likeIds->isEmpty() || $this->eligibleReelsQuery()->whereKey($likeIds)->count() === $likeIds->count(),
            404,
        );

        DB::transaction(function () use ($states, $userId) {
            $states->each(function (bool $liked, int $reelId) use ($userId) {
                if ($liked) {
                    $inserted = DB::table('watch_buy_reel_likes')->insertOrIgnore([
                        'reel_id' => $reelId,
                        'user_id' => $userId,
                        'created_at' => now(),
                    ]);
                    if ($inserted === 1) {
                        WatchBuyReel::whereKey($reelId)->increment('like_count');
                    }

                    return;
                }

                if (WatchBuyReelLike::where(['reel_id' => $reelId, 'user_id' => $userId])->delete()) {
                    WatchBuyReel::whereKey($reelId)->where('like_count', '>', 0)->decrement('like_count');
                }
            });
        });

        $counts = WatchBuyReel::whereKey($states->keys())->pluck('like_count', 'id');

        return $states->map(fn (bool $liked, int $reelId) => [
            'reel_id' => $reelId,
            'like_count' => (int) $counts[$reelId],
            'liked_by_me' => $liked,
        ])->values();
    }

    private function notifySeller(WatchBuyProfile $profile, string $reason, ?WatchBuyReel $reel = null): void
    {
        try {
            $profile->loadMissing('seller.user');
            $profile->seller?->user?->notify(new WatchBuyModerationNotification($profile, $reason, $reel));
        } catch (Throwable $exception) {
            Log::error('WatchBuyService::notifySeller failed', [
                'error' => $exception->getMessage(),
                'profile_id' => $profile->id,
                'reel_id' => $reel?->id,
            ]);
        }
    }

    private function encodeCursor(string $sessionId, array $seenIds): string
    {
        return Crypt::encryptString(json_encode(['session_id' => $sessionId, 'seen_ids' => $seenIds], JSON_THROW_ON_ERROR));
    }

    private function decodeCursor(?string $cursor, string $sessionId): array
    {
        if (! $cursor) {
            return [];
        }
        try {
            $payload = json_decode(Crypt::decryptString($cursor), true, flags: JSON_THROW_ON_ERROR);
            if (($payload['session_id'] ?? null) !== $sessionId || ! is_array($payload['seen_ids'] ?? null)) {
                throw new \RuntimeException;
            }

            return array_values(array_unique(array_map('intval', $payload['seen_ids'])));
        } catch (Throwable) {
            throw ValidationException::withMessages(['cursor' => __('labels.watch_buy_cursor_invalid')]);
        }
    }

    private function log(string $type, int $id, string $action, ?int $actorId, ?string $previous, ?string $next, ?string $reason = null, ?array $metadata = null): void
    {
        WatchBuyModerationLog::create([
            'target_type' => $type,
            'target_id' => $id,
            'action' => $action,
            'actor_id' => $actorId,
            'previous_status' => $previous,
            'new_status' => $next,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }

    private function serverUploadLimitMb(): int
    {
        $limits = collect([ini_get('upload_max_filesize'), ini_get('post_max_size')])
            ->filter(fn ($value) => is_string($value) && $value !== '' && (int) $value > 0)
            ->map(function (string $value) {
                $unit = strtolower(substr($value, -1));
                $number = (int) $value;

                return match ($unit) {
                    'g' => $number * 1024,
                    'k' => max(1, (int) floor($number / 1024)),
                    default => $number,
                };
            });

        return $limits->isEmpty() ? 500 : max(1, $limits->min());
    }
}
