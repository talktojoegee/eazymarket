<?php

namespace App\Observers;

use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Models\WalletTransaction;
use App\Notifications\WalletTransactionOccurred;
use Illuminate\Support\Facades\Log;

class WalletTransactionObserver
{
    /**
     * Handle the WalletTransaction "created" event.
     */
    public function created(WalletTransaction $transaction): void
    {
        try {
            if ((string) $transaction->status === (string) WalletTransactionStatusEnum::PENDING()) {
                return;
            }

            $user = $transaction->user;
            if ($user) {
                $user->notify(new WalletTransactionOccurred($transaction));
            }
        } catch (\Throwable $e) {
            Log::error('WalletTransactionObserver notify failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle the WalletTransaction "updated" event.
     */
    public function updated(WalletTransaction $transaction): void
    {
        try {
            $interesting = $transaction->wasChanged([
                'status',
                'amount',
                'transaction_type',
                'description',
                'payment_method',
            ]);

            if (!$interesting) {
                return;
            }

            $user = $transaction->user;
            if ($user) {
                $user->notify(new WalletTransactionOccurred($transaction));
            }
        } catch (\Throwable $e) {
            Log::error('WalletTransactionObserver update notify failed: ' . $e->getMessage());
        }
    }
}
