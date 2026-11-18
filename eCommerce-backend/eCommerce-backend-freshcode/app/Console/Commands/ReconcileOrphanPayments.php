<?php

namespace App\Console\Commands;

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\Payment\RefundOutcome;
use App\Enums\PaymentStatusEnum;
use App\Models\OrderPaymentTransaction;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Auto-refund orphan captures: a gateway payment that completed but whose order
 * is cancelled or missing (e.g. the customer paid after the order had already
 * expired). Order-first makes these rare, but this is the money-safety net that
 * guarantees no customer is ever charged with nothing to show.
 */
class ReconcileOrphanPayments extends Command
{
    protected $signature = 'payments:reconcile-orphans';

    protected $description = 'Auto-refund captured payments whose order was cancelled or is missing (orphan captures)';

    public function handle(PaymentService $paymentService): int
    {
        $onlineMethods = [
            (string) PaymentTypeEnum::RAZORPAY(),
            (string) PaymentTypeEnum::STRIPE(),
            (string) PaymentTypeEnum::PAYSTACK(),
            (string) PaymentTypeEnum::FLUTTERWAVE(),
            (string) PaymentTypeEnum::XENDIT(),
            (string) PaymentTypeEnum::MERCADO_PAGO(),
        ];

        $orphans = OrderPaymentTransaction::where('payment_status', PaymentStatusEnum::COMPLETED())
            ->whereNotNull('transaction_id')
            ->whereIn('payment_method', $onlineMethods)
            ->where(function ($q) {
                $q->whereDoesntHave('order')
                    ->orWhereHas('order', fn ($o) => $o->where('status', OrderStatusEnum::CANCELLED()));
            })
            ->get();

        $refunded = 0;
        $failed = 0;
        $pending = 0;

        foreach ($orphans as $txn) {
            $outcome = $paymentService->processOrderRefund($txn->payment_method, $txn->transaction_id);

            if ($outcome === RefundOutcome::COMPLETED) {
                $txn->update([
                    'payment_status' => PaymentStatusEnum::REFUNDED(),
                    'message' => 'Auto-refunded orphan capture',
                ]);
                $refunded++;
            } elseif ($outcome === RefundOutcome::PENDING) {
                $pending++;
            } else {
                Log::error('Orphan refund failed — needs manual attention', [
                    'transaction_id' => $txn->transaction_id,
                    'order_id' => $txn->order_id,
                ]);
                $failed++;
            }
        }

        $this->info("Orphan reconciliation: {$refunded} refunded, {$pending} pending, {$failed} failed.");

        return self::SUCCESS;
    }
}
