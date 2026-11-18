<?php

namespace App\Services;

use App\Enums\Notification\NotificationAudienceTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Jobs\SendAppNotificationJob;
use App\Models\AppNotification;
use App\Models\DeliveryBoy;
use App\Models\Seller;
use App\Models\User;
use App\Notifications\AdminCustomNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppNotificationService
{
    /**
     * @throws \Throwable
     * @throws ValidationException
     */
    public function createAndDispatch(array $data, ?int $createdBy = null): AppNotification
    {
        $audienceType = $data['audience_type'] instanceof NotificationAudienceTypeEnum
            ? $data['audience_type']
            : NotificationAudienceTypeEnum::from($data['audience_type']);

        $targetType = $audienceType->value === NotificationAudienceTypeEnum::CUSTOMER()
            ? ($data['target_type'] ?? null)
            : null;
        $userIds = $this->normalizeIds($data['user_ids'] ?? []);
        $marketIds = $this->normalizeIds($data['market_ids'] ?? []);

        $this->validateSelectedUserIds($audienceType, $userIds);

        DB::beginTransaction();

        try {
            $appNotification = AppNotification::create([
                'audience_type' => $audienceType,
                'title' => $data['title'],
                'message' => $data['message'],
                'target_type' => $targetType,
                'metadata' => $data['metadata'] ?? null,
                'created_by' => $createdBy,
            ]);

            if (! empty($userIds)) {
                $timestamp = now();

                $appNotification->userMaps()->insert(
                    collect($userIds)
                        ->map(fn (int $userId) => [
                            'notification_id' => $appNotification->id,
                            'user_id' => $userId,
                            'user_type' => $audienceType->value,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ])->all()
                );
            }

            if (! empty($marketIds)) {
                $timestamp = now();

                $appNotification->marketMaps()->insert(
                    collect($marketIds)
                        ->map(fn (int $marketId) => [
                            'notification_id' => $appNotification->id,
                            'market_id' => $marketId,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ])->all()
                );
            }

            if (! empty($data['image'])) {
                $appNotification->addMediaFromRequest('image')
                    ->toMediaCollection(SpatieMediaCollectionName::APP_NOTIFICATION_IMAGE());
            }

            DB::commit();

            SendAppNotificationJob::dispatch($appNotification->id);

            return $appNotification->load(['userMaps', 'marketMaps']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function send(AppNotification $appNotification, int $chunkSize = 200): void
    {
        $claimed = AppNotification::whereKey($appNotification->id)
            ->whereNull('dispatched_at')
            ->update(['dispatched_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $this->recipientUserIdsQuery($appNotification)
            ->chunk($chunkSize, function ($rows) use ($appNotification) {
                $recipientIds = $rows->pluck('id')->filter()->map(fn ($id) => (int) $id)->values();

                if ($recipientIds->isEmpty()) {
                    return;
                }

                User::whereIn('id', $recipientIds)
                    ->get()
                    ->each(function (User $user) use ($appNotification) {
                        $user->notify(new AdminCustomNotification($appNotification));
                    });
            });
    }

    protected function recipientUserIdsQuery(AppNotification $appNotification): Builder
    {
        $selectedUserIds = $appNotification->userMaps->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $selectedMarketIds = $appNotification->marketMaps->pluck('market_id')->map(fn ($id) => (int) $id)->all();

        return match ($appNotification->audience_type->value) {
            NotificationAudienceTypeEnum::CUSTOMER() => $this->customerRecipientQuery($selectedUserIds, $selectedMarketIds),
            NotificationAudienceTypeEnum::SELLER() => $this->sellerRecipientQuery($selectedUserIds, $selectedMarketIds),
            NotificationAudienceTypeEnum::RIDER() => $this->riderRecipientQuery($selectedUserIds),
            default => throw new \InvalidArgumentException('Invalid audience type'),
        };
    }

    protected function customerRecipientQuery(array $selectedUserIds, array $selectedMarketIds): Builder
    {
        $query = User::query()
            ->select('users.id')
            ->where(function ($q) {
                $q->whereNull('users.access_panel')
                    ->orWhere('users.access_panel', 'web');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('delivery_boys')
                    ->whereColumn('delivery_boys.user_id', 'users.id')
                    ->whereNull('delivery_boys.deleted_at');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sellers')
                    ->whereColumn('sellers.user_id', 'users.id')
                    ->whereNull('sellers.deleted_at');
            })
            ->orderBy('users.id');

        if (! empty($selectedUserIds)) {
            $query->whereIn('users.id', $selectedUserIds);
        }

        if (! empty($selectedMarketIds)) {
            $query->inMarkets($selectedMarketIds);
        }

        return $query->distinct();
    }

    protected function sellerRecipientQuery(array $selectedUserIds, array $selectedMarketIds): Builder
    {
        $query = User::query()
            ->select('users.id')
            ->join('sellers', 'sellers.user_id', '=', 'users.id')
            ->whereNull('sellers.deleted_at')
            ->orderBy('users.id');

        if (! empty($selectedUserIds)) {
            $query->whereIn('users.id', $selectedUserIds);
        }

        if (! empty($selectedMarketIds)) {
            $query->join('stores', 'stores.seller_id', '=', 'sellers.id')
                ->join('store_market', 'store_market.store_id', '=', 'stores.id')
                ->whereNull('stores.deleted_at')
                ->whereIn('store_market.market_id', $selectedMarketIds);
        }

        return $query->distinct();
    }

    protected function riderRecipientQuery(array $selectedUserIds): Builder
    {
        $query = User::query()
            ->select('users.id')
            ->join('delivery_boys', 'delivery_boys.user_id', '=', 'users.id')
            ->whereNull('delivery_boys.deleted_at')
            ->orderBy('users.id');

        if (! empty($selectedUserIds)) {
            $query->whereIn('users.id', $selectedUserIds);
        }

        return $query->distinct();
    }

    protected function validateSelectedUserIds(NotificationAudienceTypeEnum $audienceType, array $userIds): void
    {
        if (empty($userIds)) {
            return;
        }

        $count = match ($audienceType) {
            NotificationAudienceTypeEnum::CUSTOMER => $this->customerRecipientQuery($userIds, [])->count(),
            NotificationAudienceTypeEnum::SELLER => Seller::whereIn('user_id', $userIds)->count(),
            NotificationAudienceTypeEnum::RIDER => DeliveryBoy::whereIn('user_id', $userIds)->count(),
        };

        if ($count !== count($userIds)) {
            throw ValidationException::withMessages([
                'user_ids' => ['One or more selected users are invalid for the selected audience type.'],
            ]);
        }
    }

    protected function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn ($id) => ! is_null($id) && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
