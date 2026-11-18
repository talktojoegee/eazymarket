<?php

namespace App\Listeners\DeliveryBoy;

use App\Enums\GuardNameEnum;
use App\Events\DeliveryBoy\WithdrawalRequestProcessed;
use App\Notifications\DeliveryBoy\DeliveryBoyWithdrawalStatusUpdated;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class DeliveryBoyWithdrawalStatusUpdatedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(WithdrawalRequestProcessed $event): void
    {
        try {
            $req = $event->withdrawalRequest;

            $deliveryBoyUser = $req->user;
            if ($deliveryBoyUser) {
                $deliveryBoyUser->notify(new DeliveryBoyWithdrawalStatusUpdated($event));
            }

            $admins = User::where('access_panel', GuardNameEnum::ADMIN())->get();
            foreach ($admins as $admin) {
                $admin->notify(new DeliveryBoyWithdrawalStatusUpdated($event));
            }
        } catch (\Throwable $e) {
            Log::error('DeliveryBoyWithdrawalStatusUpdatedNotification failed: ' . $e->getMessage());
        }
    }
}
