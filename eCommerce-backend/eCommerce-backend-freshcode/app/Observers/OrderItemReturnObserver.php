<?php

namespace App\Observers;

use App\Enums\GuardNameEnum;
use App\Models\OrderItemReturn;
use App\Models\User;
use App\Notifications\OrderItemReturnRequested;
use App\Notifications\OrderItemReturnUpdated;
use Illuminate\Support\Facades\Log;

class OrderItemReturnObserver
{
    /**
     * Handle the OrderItemReturn "created" event.
     * Notify customer, seller, and admins about a new return request.
     */
    public function created(OrderItemReturn $orderReturn): void
    {
        try {
            if ($orderReturn->user instanceof User) {
                $orderReturn->user->notify(new OrderItemReturnRequested($orderReturn, 'customer'));
            }

            $sellerUser = $orderReturn->seller?->user;
            if ($sellerUser instanceof User) {
                $sellerUser->notify(new OrderItemReturnRequested($orderReturn, 'seller'));
            }

            if ($this->isSellerFault($orderReturn)) {
                $this->notifyAdmins(new OrderItemReturnRequested($orderReturn, 'admin'));
            }
        } catch (\Throwable $e) {
            Log::error('OrderItemReturnObserver created notify failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /**
     * Handle the OrderItemReturn "updated" event.
     * Notify all parties when statuses change meaningfully.
     */
    public function updated(OrderItemReturn $orderReturn): void
    {
        try {
            if (!$orderReturn->wasChanged('return_status')) {
                return;
            }

            $changes = [
                'return_status' => [
                    'old' => $orderReturn->getOriginal('return_status'),
                    'new' => (string)$orderReturn->return_status,
                ],
            ];

            if ($orderReturn->user instanceof User) {
                $orderReturn->user->notify(new OrderItemReturnUpdated($orderReturn, $changes, 'customer'));
            }

            $sellerUser = $orderReturn->seller?->user;
            if ($sellerUser instanceof User) {
                $sellerUser->notify(new OrderItemReturnUpdated($orderReturn, $changes, 'seller'));
            }

            if ($this->isSellerFault($orderReturn)) {
                $this->notifyAdmins(new OrderItemReturnUpdated($orderReturn, $changes, 'admin'));
            }
        } catch (\Throwable $e) {
            Log::error('OrderItemReturnObserver updated notify failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /** True when the return is tagged seller-fault (drives admin notification). */
    private function isSellerFault(OrderItemReturn $orderReturn): bool
    {
        return ($orderReturn->reason_type?->value ?? '')
            === (string) \App\Enums\Order\ReturnReasonTypeEnum::SELLER_FAULT();
    }

    protected function notifyAdmins($notification): void
    {
        try {
            $admins = User::where('access_panel', GuardNameEnum::ADMIN())->get();
            foreach ($admins as $admin) {
                try {
                    $admin->notify($notification);
                } catch (\Throwable $e) {
                    Log::warning('Failed notifying admin about order item return: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('OrderItemReturnObserver failed to fetch admins: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
