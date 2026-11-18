<?php

namespace App\Console\Commands;

use App\Enums\Shipping\ShiprocketSyncStatusEnum;
use App\Jobs\SyncShiprocketPickupLocation;
use App\Models\StoreLocation;
use App\Services\Shipping\ShiprocketClient;
use Illuminate\Console\Command;

class ShiprocketRegisterPendingCommand extends Command
{
    protected $signature = 'shiprocket:register-pending';

    protected $description = 'Register store locations not yet registered with Shiprocket (retries pending/failed). Errors land in shiprocket_sync_error.';

    public function handle(ShiprocketClient $client): int
    {
        if (!$client->isEnabled()) {
            $this->comment('Shiprocket disabled — nothing to register.');
            return self::SUCCESS;
        }

        $registered = 0;
        $failed = 0;
        $scanned = 0;


        StoreLocation::query()
            ->where('is_active', true)
            ->where('fulfills_online_orders', true)
            ->where(function ($q) {
                $q->where('is_registered_with_shiprocket', false)
                    ->orWhereNull('shiprocket_sync_status')
                    ->orWhereIn('shiprocket_sync_status', [
                        ShiprocketSyncStatusEnum::PENDING(),
                        ShiprocketSyncStatusEnum::FAILED(),
                    ]);
            })
            ->chunkById(100, function ($locations) use (&$registered, &$failed, &$scanned) {
                foreach ($locations as $location) {
                    $scanned++;

                    if (!$location->needsShiprocketSync()) {
                        continue;
                    }

                    try {
                        SyncShiprocketPickupLocation::dispatchSync($location->id);
                    } catch (\Throwable $e) {
                        // Optional: log the exception
                    }

                    $location->refresh();

                    if ($location->isShiprocketRegistered()) {
                        $registered++;
                    } else {
                        $failed++;
                        $this->warn(
                            '#' . $location->id . ' ' . $location->name .
                            ' → ' . ($location->shiprocket_sync_error ?? 'failed')
                        );
                    }
                }
            });

        $this->info("Scanned {$scanned}, registered {$registered}, failed {$failed}.");

        return self::SUCCESS;
    }
}
