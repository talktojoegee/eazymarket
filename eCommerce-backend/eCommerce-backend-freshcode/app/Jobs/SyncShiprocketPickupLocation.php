<?php

namespace App\Jobs;

use App\Enums\Shipping\ShiprocketSyncStatusEnum;
use App\Models\StoreLocation;
use App\Services\Shipping\Exceptions\ShiprocketException;
use App\Services\Shipping\ShiprocketClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncShiprocketPickupLocation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(protected int $storeLocationId, protected bool $force = false)
    {
    }

    public function backoff(): array
    {
        return [15, 90, 300];
    }

    public function handle(ShiprocketClient $client): void
    {
        $location = StoreLocation::with('store')->find($this->storeLocationId);
        if (!$location) {
            return;
        }

        if (!$this->force && !$location->needsShiprocketSync()) {
            return;
        }

        if ($missing = $this->missingAddressFields($location)) {
            $this->markFailed($location, 'Incomplete pickup address — missing: ' . implode(', ', $missing));
            return;
        }

        $location->forceFill(['shiprocket_sync_status' => ShiprocketSyncStatusEnum::PENDING()])->save();

        $nickname = $location->generateShiprocketNickname();

        try {
            $response = $client->addPickup($this->payload($location, $nickname));
        } catch (ShiprocketException $e) {
            if ($this->alreadyExists($e)) {
                $this->markRegistered($location, $nickname);
                return;
            }
            throw $e;
        }

        $pickupCode = $response['address']['pickup_code'] ?? null;
        $this->markRegistered($location, is_string($pickupCode) && $pickupCode !== '' ? $pickupCode : $nickname);
    }

    public function failed(\Throwable $e): void
    {
        $location = StoreLocation::find($this->storeLocationId);
        if ($location) {
            $this->markFailed($location, $e->getMessage());
        }
        Log::error('SyncShiprocketPickupLocation failed', [
            'store_location_id' => $this->storeLocationId,
            'error'             => $e->getMessage(),
        ]);
    }

    private function missingAddressFields(StoreLocation $location): array
    {
        $store = $location->store;

        $required = [
            'address_1'     => $location->address_1,
            'city'          => $location->city,
            'state'         => $location->state,
            'pin_code'      => $location->pin_code,
            'contact_email' => $store->contact_email ?? null,
            'contact_phone' => $store->contact_number ?? null,
        ];

        return array_keys(array_filter($required, fn ($v) => blank($v)));
    }

    private function payload(StoreLocation $location, string $nickname): array
    {
        $store = $location->store;

        return [
            'pickup_location' => $nickname,
            'name'            => $store->name ?? $location->name,
            'email'           => $store->contact_email ?? '',
            'phone'           => $store->contact_number ?? '',
            'address'         => $location->address_1 ?? '',
            'address_2'       => $location->address_2 ?? '',
            'city'            => $location->city ?? '',
            'state'           => $location->state ?? '',
            'country'         => $location->country_name ?? 'India',
            'pin_code'        => $location->pin_code ?? '',
        ];
    }

    private function alreadyExists(ShiprocketException $e): bool
    {
        $haystack = strtolower($e->getMessage() . ' ' . json_encode($e->payload));

        return str_contains($haystack, 'already')
            && (str_contains($haystack, 'exist')
                || str_contains($haystack, 'use')
                || str_contains($haystack, 'nickname')
                || str_contains($haystack, 'taken'));
    }

    private function markRegistered(StoreLocation $location, string $nickname): void
    {
        $location->forceFill([
            'shiprocket_pickup_location_id' => $nickname,
            'is_registered_with_shiprocket' => true,
            'shiprocket_registered_at'      => now(),
            'shiprocket_sync_hash'          => $location->shiprocketSyncHash(),
            'shiprocket_sync_status'        => ShiprocketSyncStatusEnum::REGISTERED(),
            'shiprocket_sync_error'         => null,
        ])->save();
    }

    private function markFailed(StoreLocation $location, string $error): void
    {
        $location->forceFill([
            'shiprocket_sync_status' => ShiprocketSyncStatusEnum::FAILED(),
            'shiprocket_sync_error'  => mb_substr($error, 0, 1000),
        ])->save();
    }
}
