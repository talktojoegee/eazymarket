<?php

namespace App\Services;

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\Subscription\SellerSubscriptionStatusEnum;
use App\Enums\Subscription\SubscriptionDurationTypeEnum;
use App\Enums\Subscription\SubscriptionTransactionStatusEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Models\SellerSubscription;
use App\Models\SellerSubscriptionSnapshot;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
use App\Http\Controllers\Payments\RazorpayController;
use App\Http\Controllers\Payments\StripeController;
use App\Http\Controllers\Payments\PaystackController;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SellerSubscriptionService
{
    public function __construct(
        protected SubscriptionUsageService $usageService,
        protected WalletService            $walletService
    )
    {
    }

    /**
     * Purchase a subscription plan for seller.
     * Flow:
     *  - Check eligibility for the provided plan.
     *  - If payment type is wallet: deduct immediately, mark subscription active and transaction completed.
     *  - Else: create subscription in pending, transaction pending.
     */
    public function buyPlanForSeller(int $sellerId, int $userId, int $planId, string $paymentType): array
    {
        $plan = $this->getPurchasablePlan($planId);
        if (!$plan) {
            return $this->error(__('labels.subscription_plan_not_available'));
        }

        $now = Carbon::now();
        $existing = $this->findOverlappingSubscription($sellerId, $now);
        $eligibility = $this->usageService->checkEligibilityForPlan($sellerId, $planId);
        if (!$eligibility['eligible']) {
            return $this->error(__('labels.subscription_purchase_ineligible'), $eligibility);
        }

        if ($existing) {
            if ((string)$existing->status === (string)SellerSubscriptionStatusEnum::ACTIVE()) {
                $existingPlan = $existing->plan;
                $existingIsFree = ($existingPlan?->is_free ?? false) || (float)($existingPlan?->price ?? 0) <= 0;
                $requestedIsPaid = (float)$plan->price > 0 && !$plan->is_free;

                if ($existingIsFree && $requestedIsPaid) {
                    $this->cancelSubscription($existing, $sellerId);

                    return $this->createNewPurchase($sellerId, $userId, $plan, $paymentType, $now);
                }

                return $this->error(
                    __('labels.subscription_already_active_timeframe'),
                    [
                        'existing_subscription_id' => $existing->id,
                        'existing_status' => $existing->status,
                        'existing_end_date' => $existing->end_date,
                    ]
                );
            }
            return $this->reuseOrUpdatePending($existing, $sellerId, $userId, $paymentType, $plan);
        }

        return $this->createNewPurchase($sellerId, $userId, $plan, $paymentType, $now);
    }

    /**
     * Helpers: keep behavior identical while simplifying the main flow
     */
    protected function getPurchasablePlan(int $planId): ?SubscriptionPlan
    {
        $plan = SubscriptionPlan::with('limits')->find($planId);
        return ($plan && $plan->status) ? $plan : null;
    }

    protected function findOverlappingSubscription(int $sellerId, Carbon $now): ?SellerSubscription
    {
        return SellerSubscription::with('plan')
            ->where('seller_id', $sellerId)
            ->whereIn('status', [
                SellerSubscriptionStatusEnum::ACTIVE(),
                SellerSubscriptionStatusEnum::PENDING(),
            ])
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Mark a subscription as cancelled and bust its cache entry.
     */
    protected function cancelSubscription(SellerSubscription $subscription, int $sellerId): void
    {
        $subscription->status = SellerSubscriptionStatusEnum::CANCELLED();
        $subscription->save();
        Cache::forget(SellerSubscription::cacheKeyForCurrent($sellerId));
    }

    /** @return array{0: Carbon, 1: ?Carbon} */
    protected function computeDates(SubscriptionPlan $plan, Carbon $now): array
    {
        $startDate = $now;
        $endDate = null;
        if ($plan->duration_type === SubscriptionDurationTypeEnum::DAYS() && !empty($plan->duration_days)) {
            $endDate = (clone $now)->addDays((int)$plan->duration_days);
        }
        return [$startDate, $endDate];
    }

    protected function createSubscription(int $sellerId, SubscriptionPlan $plan, Carbon $startDate, ?Carbon $endDate, bool $isWallet): SellerSubscription
    {
        $subscription = SellerSubscription::create([
            'seller_id' => $sellerId,
            'plan_id' => $plan->id,
            'status' => $isWallet ? SellerSubscriptionStatusEnum::ACTIVE() : SellerSubscriptionStatusEnum::PENDING(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'price_paid' => 0,
        ]);
        if ((string)$subscription->status === (string)SellerSubscriptionStatusEnum::ACTIVE()) {
            Cache::forget(SellerSubscription::cacheKeyForCurrent($sellerId));
        }
        return $subscription;
    }

    protected function snapshotPlan(SellerSubscription $subscription, SubscriptionPlan $plan): void
    {
        $limits = $plan->limits->pluck('value', 'key')->toArray();
        SellerSubscriptionSnapshot::create([
            'seller_subscription_id' => $subscription->id,
            'plan_name' => $plan->name,
            'price' => $plan->price,
            'duration_days' => $plan->duration_days,
            'limits_json' => $limits,
        ]);
    }

    /**
     * Handle reuse of an existing PENDING subscription, syncing details and initializing gateway.
     */
    protected function reuseOrUpdatePending(
        SellerSubscription $existing,
        int $sellerId,
        int $userId,
        string $paymentType,
        SubscriptionPlan $plan
    ): array {
        $paymentGateway = $paymentType;
        $isWallet = $paymentType === PaymentTypeEnum::WALLET();
        if ($isWallet) {
            return $this->error(__('labels.subscription_pending_payment_exists'));
        }

        try {
            return DB::transaction(function () use ($existing, $sellerId, $userId, $paymentGateway, $plan) {
                [$pendingSub] = $this->ensurePendingSubscriptionUpToDate($existing, $plan);

                $amount = (float)$plan->price;
                if ($amount <= 0) {
                    $pendingSub->status = SellerSubscriptionStatusEnum::ACTIVE();
                    $pendingSub->price_paid = 0;
                    $pendingSub->save();
                    Cache::forget(SellerSubscription::cacheKeyForCurrent($sellerId));

                    $txn = SubscriptionTransaction::where('seller_subscription_id', $pendingSub->id)
                        ->where('status', SubscriptionTransactionStatusEnum::PENDING())
                        ->orderByDesc('id')
                        ->first();

                    if ($txn) {
                        $txn->payment_gateway = 'FREE';
                        $txn->amount = 0;
                        $txn->status = SubscriptionTransactionStatusEnum::COMPLETED();
                        $txn->transaction_id = $txn->transaction_id ?: 'FREE';
                        $txn->save();
                    } else {
                        $txn = SubscriptionTransaction::create([
                            'seller_id' => $sellerId,
                            'seller_subscription_id' => $pendingSub->id,
                            'plan_id' => $plan->id,
                            'payment_gateway' => 'FREE',
                            'amount' => 0,
                            'status' => SubscriptionTransactionStatusEnum::COMPLETED(),
                            'transaction_id' => 'FREE',
                        ]);
                    }

                    return $this->success(subscription: $pendingSub, txn: $txn);
                }

                [$txn] = $this->syncOrCreatePendingTransaction(
                    subscription: $pendingSub,
                    sellerId: $sellerId,
                    paymentGateway: $paymentGateway,
                    plan: $plan
                );

                $paymentUrl = $this->initializeGatewayOrderIfNeeded(
                    paymentGateway: $paymentGateway,
                    txn: $txn,
                    subscription: $pendingSub,
                    plan: $plan,
                    sellerId: $sellerId,
                    userId: $userId,
                );

                if (!$paymentUrl && !empty($txn->transaction_id)) {
                    if ($paymentGateway === (string)PaymentTypeEnum::RAZORPAY()) {
                        $paymentUrl = route('subscription-payment.razorpay', ['transaction' => $txn->uuid]);
                    } elseif ($paymentGateway === (string)PaymentTypeEnum::STRIPE()) {
                        $paymentUrl = route('subscription-payment.stripe', ['transaction' => $txn->uuid]);
                    } elseif ($paymentGateway === (string)PaymentTypeEnum::PAYSTACK()) {
                        $paymentUrl = route('subscription-payment.paystack', ['transaction' => $txn->uuid]);
                    } elseif ($paymentGateway === (string)PaymentTypeEnum::FLUTTERWAVE()) {
                        $paymentUrl = route('subscription-payment.flutterwave', ['transaction' => $txn->uuid]);
                    }
                }

                return $this->success(subscription: $pendingSub, txn: $txn, paymentUrl: $paymentUrl);
            });
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Create a new subscription purchase flow (wallet or online gateway).
     */
    protected function createNewPurchase(
        int $sellerId,
        int $userId,
        SubscriptionPlan $plan,
        string $paymentType,
        Carbon $now
    ): array {
        [$startDate, $endDate] = $this->computeDates($plan, $now);

        $paymentGateway = $paymentType;
        $isWallet = $paymentType === PaymentTypeEnum::WALLET();
        $isFree = (float)$plan->price <= 0;

        try {
            return DB::transaction(function () use ($sellerId, $userId, $plan, $startDate, $endDate, $isWallet, $paymentGateway, $isFree) {
                $subscription = $this->createSubscription($sellerId, $plan, $startDate, $endDate, $isWallet || $isFree);
                $this->snapshotPlan($subscription, $plan);

                $amount = (float)$plan->price;
                if ($isWallet) {
                    [$transactionStatus] = $this->processWalletPayment($userId, $subscription, $amount, $plan->name);
                } elseif ($isFree) {
                    $transactionStatus = SubscriptionTransactionStatusEnum::COMPLETED();
                    $subscription->price_paid = 0;
                    $subscription->save();
                    $paymentGateway = 'FREE';
                    Cache::forget(SellerSubscription::cacheKeyForCurrent($sellerId));
                } else {
                    $transactionStatus = SubscriptionTransactionStatusEnum::PENDING();
                }

                $txn = $this->createTransaction($sellerId, $subscription, $plan, $paymentGateway, $amount, $transactionStatus);
                if ($isFree) {
                    $txn->transaction_id = 'FREE';
                    $txn->save();
                }

                $paymentUrl = null;
                if (!$isWallet && !$isFree) {
                    $paymentUrl = $this->postProcessGateway(
                        paymentGateway: $paymentGateway,
                        txn: $txn,
                        subscription: $subscription,
                        plan: $plan,
                        sellerId: $sellerId,
                        userId: $userId,
                        amount: $amount,
                    );
                }

                return $this->success(subscription: $subscription, txn: $txn, paymentUrl: $paymentUrl);
            });
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Ensure the existing pending subscription matches the requested plan.
     */
    protected function ensurePendingSubscriptionUpToDate(SellerSubscription $existing, SubscriptionPlan $requestedPlan): array
    {
        $existing->plan_id = $requestedPlan->id;

        $start = $existing->start_date ?: Carbon::now();
        if ($requestedPlan->duration_type === SubscriptionDurationTypeEnum::DAYS() && !empty($requestedPlan->duration_days)) {
            $existing->end_date = (clone $start)->addDays((int)$requestedPlan->duration_days);
        } else {
            $existing->end_date = null;
        }
        $existing->save();

        $this->upsertSnapshot($existing, $requestedPlan);

        return [$existing];
    }

    /**
     * Create or update the snapshot for a subscription to mirror the given plan.
     */
    protected function upsertSnapshot(SellerSubscription $subscription, SubscriptionPlan $plan): void
    {
        $limits = $plan->limits->pluck('value', 'key')->toArray();
        $snapshot = $subscription->snapshot;
        if ($snapshot) {
            $snapshot->plan_name = $plan->name;
            $snapshot->price = $plan->price;
            $snapshot->duration_days = $plan->duration_days;
            $snapshot->limits_json = $limits;
            $snapshot->save();
        } else {
            SellerSubscriptionSnapshot::create([
                'seller_subscription_id' => $subscription->id,
                'plan_name' => $plan->name,
                'price' => $plan->price,
                'duration_days' => $plan->duration_days,
                'limits_json' => $limits,
            ]);
        }
    }

    /**
     * Find or create a pending transaction for the subscription and ensure it reflects
     * the requested payment gateway, plan, and amount. If any of these change, reset the
     * gateway transaction_id to force re-initialization.
     * Returns [txn, reconfigured:boolean]
     */
    protected function syncOrCreatePendingTransaction(
        SellerSubscription $subscription,
        int                $sellerId,
        string             $paymentGateway,
        SubscriptionPlan   $plan
    ): array
    {
        $amount = (float)$plan->price;

        $txn = SubscriptionTransaction::where('seller_subscription_id', $subscription->id)
            ->where('status', SubscriptionTransactionStatusEnum::PENDING())
            ->orderByDesc('id')
            ->first();

        $reconfigured = false;

        if (!$txn) {
            $txn = SubscriptionTransaction::create([
                'seller_id' => $sellerId,
                'seller_subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'payment_gateway' => $paymentGateway,
                'amount' => $amount,
                'status' => SubscriptionTransactionStatusEnum::PENDING(),
            ]);
            $reconfigured = true;
        } else {
            $needsUpdate = false;
            if ((int)$txn->plan_id !== (int)$plan->id) {
                $txn->plan_id = $plan->id;
                $needsUpdate = true;
            }
            if ((string)$txn->payment_gateway !== (string)$paymentGateway) {
                $txn->payment_gateway = $paymentGateway;
                $needsUpdate = true;
            }
            if ((float)$txn->amount != $amount) {
                $txn->amount = $amount;
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $txn->transaction_id = null;
                $txn->save();
                $reconfigured = true;
            }
        }

        return [$txn, $reconfigured];
    }

    /**
     * Initialize a gateway order if the transaction lacks a gateway transaction id.
     * Returns a hosted payment URL if available.
     */
    protected function initializeGatewayOrderIfNeeded(
        string                  $paymentGateway,
        SubscriptionTransaction $txn,
        SellerSubscription      $subscription,
        SubscriptionPlan        $plan,
        int                     $sellerId,
        int                     $userId,
    ): ?string
    {
        if (empty($txn->transaction_id)) {
            return $this->postProcessGateway(
                paymentGateway: $paymentGateway,
                txn: $txn,
                subscription: $subscription,
                plan: $plan,
                sellerId: $sellerId,
                userId: $userId,
                amount: (float)$plan->price,
            );
        }
        return null;
    }

    /** @return array{0: string, 1: ?string} */
    protected function processWalletPayment(int $userId, SellerSubscription $subscription, float $amount, ?string $planName): array
    {
        $deduct = WalletService::deductBalance($userId, [
            'amount' => $amount,
            'description' => 'Subscription purchase: ' . ($planName ?? 'Plan'),
        ], WalletTypeEnum::SELLER);
        if (!$deduct['success']) {
            throw new \RuntimeException(__('labels.subscription_wallet_insufficient_balance'));
        }

        $subscription->price_paid = $amount;
        $subscription->save();

        $transactionId = (string)($deduct['data']['transaction']->id ?? null);
        return [SubscriptionTransactionStatusEnum::COMPLETED(), $transactionId];
    }

    protected function createTransaction(
        int                $sellerId,
        SellerSubscription $subscription,
        SubscriptionPlan   $plan,
        string             $paymentGateway,
        float              $amount,
        string             $transactionStatus
    ): SubscriptionTransaction
    {
        return SubscriptionTransaction::create([
            'seller_id' => $sellerId,
            'seller_subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'payment_gateway' => $paymentGateway,
            'amount' => $amount,
            'status' => $transactionStatus,
        ]);
    }

    /**
     * Post-process gateway specifics after creating the subscription and transaction.
     * Returns a hosted payment URL when applicable.
     */
    protected function postProcessGateway(
        string                  $paymentGateway,
        SubscriptionTransaction $txn,
        SellerSubscription      $subscription,
        SubscriptionPlan        $plan,
        int                     $sellerId,
        int                     $userId,
        float                   $amount
    ): ?string
    {
        switch ($paymentGateway) {
            case PaymentTypeEnum::WALLET():
                return null;

            case PaymentTypeEnum::RAZORPAY():
                /** @var RazorpayController $razor */
                $razor = app(RazorpayController::class);
                $orderRes = $razor->createSubscriptionOrder([
                    'amount' => $amount,
                    'description' => 'Subscription: ' . ($plan->name ?? 'Plan'),
                    'subscription_transaction_id' => $txn->id,
                    'seller_subscription_id' => $subscription->id,
                    'seller_id' => $sellerId,
                    'user_id' => $userId,
                ]);
                if (!($orderRes['success'] ?? false)) {
                    throw new \RuntimeException($orderRes['message'] ?? 'Unable to initialize Razorpay payment');
                }
                $razorOrderId = $orderRes['data']['id'] ?? null;
                if ($razorOrderId) {
                    $txn->transaction_id = $razorOrderId;
                    $txn->save();
                }
                return route('subscription-payment.razorpay', ['transaction' => $txn->uuid]);

            case PaymentTypeEnum::STRIPE():
                /** @var StripeController $stripe */
                $stripe = app(StripeController::class);
                $intentRes = $stripe->createSubscriptionPaymentIntent([
                    'amount' => $amount,
                    'currency' => null,
                    'description' => 'Subscription: ' . ($plan->name ?? 'Plan'),
                    'subscription_transaction_id' => $txn->id,
                    'seller_subscription_id' => $subscription->id,
                    'seller_id' => $sellerId,
                    'user_id' => $userId,
                ]);
                if (!($intentRes['success'] ?? false)) {
                    throw new \RuntimeException($intentRes['message'] ?? 'Unable to initialize Stripe payment');
                }
                $piId = $intentRes['data']['id'] ?? null;
                if ($piId) {
                    $txn->transaction_id = $piId;
                    $txn->save();
                }
                return route('subscription-payment.stripe', ['transaction' => $txn->uuid]);

            case PaymentTypeEnum::PAYSTACK():
                /** @var PaystackController $paystack */
                $paystack = app(PaystackController::class);
                $initRes = $paystack->createSubscriptionInitialize([
                    'amount' => $amount,
                    'subscription_transaction_id' => $txn->id,
                    'seller_subscription_id' => $subscription->id,
                    'seller_id' => $sellerId,
                    'user_id' => $userId,
                ]);
                if (!($initRes['success'] ?? false)) {
                    throw new \RuntimeException($initRes['message'] ?? 'Unable to initialize Paystack payment');
                }
                $ref = $initRes['data']['reference'] ?? null;
                if ($ref) {
                    $txn->transaction_id = $ref;
                    $txn->save();
                }
                return route('subscription-payment.paystack', ['transaction' => $txn->uuid]);

            case PaymentTypeEnum::FLUTTERWAVE():
                /** @var \App\Http\Controllers\Payments\FlutterwaveController $flutterwave */
                $flutterwave = app(\App\Http\Controllers\Payments\FlutterwaveController::class);
                $initRes = $flutterwave->createSubscriptionInitialize([
                    'amount' => $amount,
                    'subscription_transaction_id' => $txn->id,
                    'seller_subscription_id' => $subscription->id,
                    'seller_id' => $sellerId,
                    'user_id' => $userId,
                ]);
                if (!($initRes['success'] ?? false)) {
                    throw new \RuntimeException($initRes['message'] ?? 'Unable to initialize Flutterwave payment');
                }
                $txRef = $initRes['data']['tx_ref'] ?? null;
                if ($txRef) {
                    $txn->transaction_id = $txRef;
                    $txn->save();
                }
                return route('subscription-payment.flutterwave', ['transaction' => $txn->uuid]);

            default:
                return null;
        }
    }

    protected function error(string $message, array $data = []): array
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => $data,
        ];
    }

    protected function success(SellerSubscription $subscription, SubscriptionTransaction $txn, ?string $paymentUrl = null): array
    {
        return [
            'success' => true,
            'message' => empty($paymentUrl) ? __('labels.subscription_purchase_success') : __('labels.subscription_purchase_pending'),
            'data' => [
                'subscription_id' => $subscription->id,
                'transaction_id' => $txn->id,
                'status' => $subscription->status,
                'payment_url' => $paymentUrl,
            ],
        ];
    }
}
