<?php

namespace App\Observers;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Models\Store;
use App\Models\User;
use App\Notifications\Store\NewStoreCreatedNotification;
use App\Notifications\Store\StoreStatusUpdatedNotification;
use App\Notifications\Store\StoreVerificationUpdatedNotification;

class StoreObserver
{
    /**
     * Handle the Store "created" event.
     */
    public function created(Store $store): void
    {
        try {
            DB::transaction(fn () => $this->provisionDefaults($store));
        } catch (\Throwable $e) {
            Log::error('StoreObserver: provisioning defaults failed', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        try {
            $admins = $this->getAdmins();
            foreach ($admins as $admin) {
                $admin->notify(new NewStoreCreatedNotification($store));
            }

            $sellerUser = $store->seller?->user;
            if ($sellerUser) {
                $sellerUser->notify(new NewStoreCreatedNotification($store));
            }
        } catch (\Throwable $e) {
            Log::error('StoreObserver: store-created notification failed', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function provisionDefaults(Store $store): void
    {
        $defaultProfile = $store->shippingProfiles()->firstOrCreate(
            ['slug' => 'general'],
            ['name' => 'General profile', 'is_default' => true]
        );

        $defaultLocation = $store->locations()->firstOrCreate(
            ['is_default' => true],
            [
                'name'                   => 'Shop location',
                'is_active'              => true,
                'status'                 => 'active',
                'country_iso2'           => $this->defaultCountryIso2($store),
                'fulfills_online_orders' => true,
                'is_physical_storefront' => false,
                'offers_local_delivery'  => false,
                'offers_local_pickup'    => false,
                'offers_pos'             => false,
            ]
        );

        if (!$defaultProfile->locations()->where('store_location_id', $defaultLocation->id)->exists()) {
            $defaultProfile->locations()->attach($defaultLocation->id);
        }

        $store->packages()->firstOrCreate(
            ['is_default' => true],
            [
                'name'            => 'Default box',
                'type'            => 'box',
                'length_cm'       => 10,
                'breadth_cm'      => 10,
                'height_cm'       => 10,
                'empty_weight_kg' => 0,
            ]
        );

    }

    private function defaultCountryIso2(Store $store): ?string
    {
        $sellerCountry = $store->seller?->country_code;

        if (is_string($sellerCountry) && $sellerCountry !== '') {
            return strtoupper(substr($sellerCountry, 0, 2));
        }

        $systemDefault = Setting::systemSettings()['defaultISO2'] ?? null;

        return is_string($systemDefault) && $systemDefault !== ''
            ? strtoupper(substr($systemDefault, 0, 2))
            : null;
    }

    /**
     * Handle the Store "updated" event.
     */
    public function updated(Store $store): void
    {
        if ($store->wasChanged('verification_status')) {
            $old = $store->getOriginal('verification_status');
            $new = $store->verification_status;

            $oldVal = $old instanceof StoreVerificationStatusEnum ? $old->value : (string) $old;
            $newVal = $new instanceof StoreVerificationStatusEnum ? $new->value : (string) $new;

            $admins = $this->getAdmins();
            foreach ($admins as $admin) {
                $admin->notify(new StoreVerificationUpdatedNotification($store, $oldVal, $newVal));
            }

            $sellerUser = $store->seller?->user;
            if ($sellerUser) {
                $sellerUser->notify(new StoreVerificationUpdatedNotification($store, $oldVal, $newVal));
            }
        }

        if ($store->wasChanged('visibility_status')) {
            $sellerUser = $store->seller?->user;
            if ($sellerUser) {
                $oldVisibility = $this->normalizeEnumValue($store->getOriginal('visibility_status'), StoreVisibilityStatusEnum::class);
                $newVisibility = $this->normalizeEnumValue($store->visibility_status, StoreVisibilityStatusEnum::class);

                $sellerUser->notify(new StoreStatusUpdatedNotification(
                    $store,
                    $oldVisibility,
                    $newVisibility,
                    null,
                    null,
                ));
            }
        }
    }

    /**
     * Normalize a raw/enum value to its scalar string representation.
     */
    protected function normalizeEnumValue(mixed $value, string $enumClass): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }
        return (string) $value;
    }

    /**
     * Get all admin users (Super Admin role).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function getAdmins()
    {
        try {
            return User::where('access_panel', GuardNameEnum::ADMIN())->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
