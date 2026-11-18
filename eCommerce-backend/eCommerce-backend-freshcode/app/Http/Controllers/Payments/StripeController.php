<?php

namespace App\Http\Controllers\Payments;

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\Subscription\SellerSubscriptionStatusEnum;
use App\Enums\Subscription\SubscriptionTransactionStatusEnum;
use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPaymentTransaction;
use App\Models\ProcessedWebhookEvent;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\SettingService;
use App\Support\GatewayAmount;
use App\Types\Api\ApiResponseType;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\SubscriptionTransaction;
use App\Models\SellerSubscription;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeController extends Controller
{

    private string $secretKey;
    private string $webhookSecret;
    private string $publishableKey;
    private string $currencyCode;

    public function __construct(SettingService $settingService)
    {
        $setting = $settingService->getSettingByVariable(SettingTypeEnum::PAYMENT());
        $this->secretKey = $setting->value['stripeSecretKey'] ?? "";
        $this->webhookSecret = $setting->value['stripeWebhookSecretKey'] ?? "";
        $this->publishableKey = $setting->value['stripePublishableKey'] ?? "";
        $this->currencyCode = $setting->value['stripeCurrencyCode'] ?? "";
    }

    public function initiateOrderPayment(Order $order): array
    {
        try {
            Stripe::setApiKey($this->secretKey);

            $targetCurrency = $this->currencyCode ?: $order->currency_code;
            try {
                $charge = GatewayAmount::resolve((float) $order->total_payable, $order->currency_code, $targetCurrency);
            } catch (\RuntimeException $e) {
                Log::warning('Stripe gateway currency unreachable; charging in order currency', [
                    'order_id' => $order->id,
                    'order_currency' => $order->currency_code,
                    'target_currency' => $targetCurrency,
                ]);
                $charge = GatewayAmount::resolve((float) $order->total_payable, $order->currency_code, $order->currency_code);
            }

            $paymentIntent = PaymentIntent::create([
                'amount' => $charge['minor'],
                'currency' => strtolower($charge['currency']),
                'metadata' => [
                    'user_id' => $order->user_id,
                    'type' => 'order_payment',
                    'order_id' => $order->id,
                ],
            ]);

            OrderPaymentTransaction::upsertPending(
                orderId: $order->id,
                userId: $order->user_id,
                transactionId: $paymentIntent->id,
                amount: (float) $order->total_payable,
                currency: $order->currency_code,
                paymentMethod: PaymentTypeEnum::STRIPE(),
                details: [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'payment_id' => $paymentIntent->id,
                    'charge_minor' => $charge['minor'],
                    'charge_currency' => $charge['currency'],
                    'charge_amount' => $charge['amount'],
                ]
            );

            return [
                'success' => true,
                'message' => 'Payment intent created successfully',
                'data' => [
                    'clientSecret' => $paymentIntent->client_secret,
                    'paymentIntentId' => $paymentIntent->id,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Stripe order payment initiation failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Unable to initiate Stripe payment',
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Render a web view for paying a subscription transaction via Stripe Elements
     */
    public function showSubscriptionPaymentPage(string $transaction): Factory|View
    {
        $txn = SubscriptionTransaction::with('plan')
            ->where('uuid', $transaction)
            ->firstOrFail();

        if ((string)$txn->payment_gateway !== (string)PaymentTypeEnum::STRIPE()) {
            abort(404);
        }

        if ((string)$txn->status !== 'pending') {
            $plan = $txn->plan;
            $planName = optional($plan)->name ?? 'Subscription Plan';

            return view('payments.payment-status', [
                'transaction' => $txn,
                'completedStatus' => SubscriptionTransactionStatusEnum::COMPLETED(),
                'failedStatus' => SubscriptionTransactionStatusEnum::FAILED(),
                'paymentContext' => [
                    'title' => __('labels.subscription_payment_summary'),
                    'lineItems' => [
                        ['label' => __('labels.plan'), 'value' => $planName, 'bold' => true],
                        ['label' => __('labels.duration'), 'value' => ($plan->duration_type ?? null) === 'unlimited' ? __('labels.unlimited') : (($plan->duration_days ?? 0) . ' ' . __('labels.days'))],
                    ],
                    'backUrl' => route('seller.subscription-plans.current'),
                    'backLabel' => __('labels.back_to_subscription'),
                ],
            ]);
        }

        Stripe::setApiKey($this->secretKey);

        $paymentIntentId = $txn->transaction_id;
        if (empty($paymentIntentId)) {
            $intentRes = $this->createSubscriptionPaymentIntent([
                'amount' => (float)$txn->amount,
                'description' => 'Subscription: ' . (optional($txn->plan)->name ?? 'Plan'),
                'subscription_transaction_id' => $txn->id,
                'seller_subscription_id' => $txn->seller_subscription_id,
                'seller_id' => $txn->seller_id,
                'user_id' => auth()->id(),
            ]);
            if (!($intentRes['success'] ?? false)) {
                abort(400, $intentRes['message'] ?? 'Unable to initialize Stripe payment');
            }
            $paymentIntentId = $intentRes['data']['id'] ?? null;
            if ($paymentIntentId) {
                $txn->transaction_id = $paymentIntentId;
                $txn->save();
            }
        }

        $intent = PaymentIntent::retrieve($paymentIntentId);
        $clientSecret = $intent->client_secret;

        $plan = $txn->plan;
        $planName = optional($plan)->name ?? 'Subscription Plan';

        return view('payments.payment', [
            'gateway' => 'stripe',
            'publishableKey' => $this->publishableKey,
            'clientSecret' => $clientSecret,
            'amount' => (float)$txn->amount,
            'currency' => $intent->currency ?? 'usd',
            'transactionId' => $txn->id,
            'transactionUuid' => $txn->uuid,
            'sellerId' => $txn->seller_id,
            'subscriptionId' => $txn->seller_subscription_id,
            'plan' => $plan,
            'planName' => $planName,
            'payButtonLabel' => __('labels.pay_with_stripe'),
            'statusRoute' => route('subscription-payment.status', ['transaction' => $txn->uuid]),
            'paymentContext' => [
                'title' => __('labels.subscription_payment_summary'),
                'lineItems' => [
                    ['label' => __('labels.plan'), 'value' => $planName, 'bold' => true],
                    ['label' => __('labels.duration'), 'value' => ($plan->duration_type ?? null) === 'unlimited' ? __('labels.unlimited') : (($plan->duration_days ?? 0) . ' ' . __('labels.days'))],
                ],
            ],
        ]);
    }

    /**
     * Create a PaymentIntent for subscription purchases
     */
    public function createSubscriptionPaymentIntent(array $data): array
    {
        try {
            $validated = validator(
                $data,
                [
                    'amount' => 'required|numeric|min:1',
                    'currency' => 'nullable|string',
                    'description' => 'nullable|string',
                    'subscription_transaction_id' => 'required|integer',
                    'seller_subscription_id' => 'required|integer',
                    'seller_id' => 'required|integer',
                    'user_id' => 'nullable|integer',
                ])->validate();

            Stripe::setApiKey($this->secretKey);

            $source = GatewayAmount::orderSourceCurrency();

            $paymentIntent = PaymentIntent::create([
                'amount' => (int) round($validated['amount'] * 100),
                'currency' => strtolower($source),
                'description' => $validated['description'] ?? 'Seller Subscription',
                'metadata' => [
                    'type' => 'subscription_payment',
                    'subscription_transaction_id' => (string)$validated['subscription_transaction_id'],
                    'seller_subscription_id' => (string)$validated['seller_subscription_id'],
                    'seller_id' => (string)$validated['seller_id'],
                    'user_id' => (string)($validated['user_id'] ?? ''),
                ],
            ]);

            return [
                'success' => true,
                'message' => 'Payment intent created successfully',
                'data' => [
                    'id' => $paymentIntent->id,
                    'clientSecret' => $paymentIntent->client_secret,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Render the payment page for a wallet recharge transaction via Stripe.
     */
    public function showWalletPaymentPage(int $transaction): Factory|View
    {
        $txn = WalletTransaction::where('id', $transaction)->firstOrFail();

        if ($txn->payment_method !== PaymentTypeEnum::STRIPE()) {
            abort(404);
        }

        if ($txn->status !== WalletTransactionStatusEnum::PENDING()) {
            return view('payments.payment-status', [
                'transaction' => $txn,
                'completedStatus' => WalletTransactionStatusEnum::COMPLETED(),
                'failedStatus' => WalletTransactionStatusEnum::FAILED(),
                'paymentContext' => [
                    'title' => __('labels.wallet_topup_payment_summary'),
                    'lineItems' => [
                        ['label' => __('labels.description'), 'value' => $txn->description ?? __('labels.wallet_topup'), 'bold' => true],
                    ],
                    'backUrl' => route('seller.ads.wallet.index'),
                    'backLabel' => __('labels.back_to_ad_wallet'),
                ],
            ]);
        }

        Stripe::setApiKey($this->secretKey);

        $paymentIntentId = $txn->transaction_reference;
        if (empty($paymentIntentId)) {
            $intentRes = $this->createWalletRechargeOrderPaymentIntent([
                'amount' => (float)$txn->amount,
                'currency' => $txn->currency_code ?? 'usd',
                'description' => $txn->description ?? 'Wallet Top-up',
                'transaction_id' => (string)$txn->id,
            ]);
            if (!($intentRes['success'] ?? false)) {
                abort(400, $intentRes['message'] ?? 'Unable to initialize Stripe payment');
            }
            $paymentIntentId = $intentRes['data']['id'] ?? null;
            if ($paymentIntentId) {
                $txn->transaction_reference = $paymentIntentId;
                $txn->save();
            }
        }

        $intent = PaymentIntent::retrieve($paymentIntentId);
        $clientSecret = $intent->client_secret;

        return view('payments.payment', [
            'gateway' => 'stripe',
            'publishableKey' => $this->publishableKey,
            'clientSecret' => $clientSecret,
            'amount' => (float)$txn->amount,
            'currency' => $intent->currency ?? $txn->currency_code ?? 'usd',
            'transactionId' => $txn->id,
            'transactionUuid' => $txn->id,
            'statusRoute' => route('wallet-payment.status', ['transaction' => $txn->id]),
            'payButtonLabel' => __('labels.pay_with_stripe'),
            'paymentContext' => [
                'title' => __('labels.wallet_topup_payment_summary'),
                'lineItems' => [
                    ['label' => __('labels.description'), 'value' => $txn->description ?? __('labels.wallet_topup'), 'bold' => true],
                ],
            ],
        ]);
    }

    public function createWalletRechargeOrderPaymentIntent(array $data): array
    {
        try {
            $validated = validator(
                $data,
                [
                    'amount' => 'required|numeric|min:1',
                    'currency' => 'nullable|string',
                    'description' => 'nullable|string',
                    'transaction_id' => 'required|string',
                ])->validate();
            Stripe::setApiKey($this->secretKey);

            $source = GatewayAmount::orderSourceCurrency();

            $paymentIntent = PaymentIntent::create([
                'amount' => (int) round($validated['amount'] * 100),
                'currency' => strtolower($source),
                'metadata' => [
                    'user_id' => auth()->id(),
                    'type' => 'wallet_recharge',
                    'transaction_id' => $validated['transaction_id'],
                ],
            ]);

            return [
                'success' => true,
                'message' => 'Payment intent created successfully',
                'data' => [
                    'id' => $paymentIntent->id,
                    'clientSecret' => $paymentIntent->client_secret,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $endpointSecret = $this->webhookSecret;

        if ($endpointSecret === '') {
            Log::error('Stripe webhook rejected: webhook secret not configured.');

            return response()->json(['error' => 'Webhook secret not configured'], 400);
        }

        DB::beginTransaction();
        try {
            $event = Webhook::constructEvent($payload, $signature, $endpointSecret);
            if (!ProcessedWebhookEvent::firstDelivery('stripe', (string) $event->id)) {
                DB::rollBack();
                return response()->json(['status' => 'duplicate'], 200);
            }
            $data = $event->data->object;


            Log::info('Stripe webhook received', ['event' => $event->type, 'event_id' => $event->id]);
            $paymentType = $data->metadata->type ?? 'order_payment';
            $transaction = $this->findTransaction(paymentType: $paymentType, data: $data);

            Log::info("webhook " . ($transaction ? "transaction found" : "transaction not found"));
            Log::info("Stripe Event Type: " . $event->type);


            $this->handleWebhookEvent($event, $paymentType, $data, $transaction);

            DB::commit();
            return response()->json(['status' => 'success'], 200);

        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            DB::rollBack();
            Log::error('Stripe webhook signature verification failed: ' . $e->getMessage());

            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Stripe Webhook Error: " . $e->getMessage());
            return response()->json(['error' => 'Server Error'], 500);
        }
    }

    private function handleWebhookEvent(object $event, string $paymentType, object $data, $transaction): void
    {
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentSucceeded(paymentType: $paymentType, data: $data, transaction: $transaction);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentFailed($paymentType, $transaction, $event);
                break;

            case 'payment_intent.amount_capturable_updated':
                Log::info('event payment_intent.amount_capturable_updated Payment authorized (amount capturable updated)', ['payment_id' => $transactionId ?? ""]);
                break;

            case 'charge.refunded':
                $this->handleRefund($paymentType, $transaction, $data);
                break;

            default:
                Log::warning("Unhandled Stripe Webhook Event: {$event->type}");
                break;
        }
    }

    private function handlePaymentSucceeded(string $paymentType, object $data, $transaction): void
    {
        $paymentId = $data->id;
        $chargeSnapshot = is_array($transaction?->payment_details ?? null) ? $transaction->payment_details : [];
        $userId = $data->metadata->user_id ?? null;
        if ($userId === null) {
            Log::warning('event payment_intent.succeeded Missing user_id in metadata', ['payment_id' => $paymentId]);
        }
        if ($paymentType === 'wallet_recharge') {
            $paidMinor = (int) $data->amount_received;
            $paidCurrency = strtoupper((string) $data->currency);
            if (!GatewayAmount::covers((float) $transaction->amount, (string) $transaction->currency_code, $paidMinor, $paidCurrency)) {
                Log::critical('Stripe wallet recharge paid amount does not cover the requested amount; capture blocked', [
                    'wallet_transaction_id' => $transaction->id,
                    'paid_minor' => $paidMinor,
                    'paid_currency' => $paidCurrency,
                ]);
                $transaction->update(['status' => PaymentStatusEnum::FAILED()]);

                return;
            }

            $transaction->update([
                'transaction_reference' => $paymentId,
                'description' => 'Wallet Recharge Payment Captured'
            ]);
            $result = Wallet::captureRecharge($transaction->id);
            if (!$result['success']) {
                Log::error("Wallet Recharge Failed: " . $result['message']);
                return;
            }
            Log::info("Wallet Recharge Completed: payment succeeded");
            return;
        }

        if ($paymentType === 'subscription_payment' && $transaction instanceof SubscriptionTransaction) {
            $transaction->update([
                'status' => SubscriptionTransactionStatusEnum::COMPLETED(),
                'transaction_id' => $paymentId,
            ]);
            $subscription = SellerSubscription::find($transaction->seller_subscription_id);
            if ($subscription) {
                $subscription->status = SellerSubscriptionStatusEnum::ACTIVE();
                $subscription->price_paid = $transaction->amount;
                $subscription->save();
            }
            Log::info('Subscription payment succeeded', ['payment_id' => $paymentId, 'transaction_id' => $transaction->id]);
            return;
        }
        $paymentData = [
            'amount' => $data->amount_received,
            'currency' => $data->currency,
            'data' => $data->toArray(),
            'order_id' => $transaction->order_id ?? null,
            'user_id' => $userId
        ];
        OrderPaymentTransaction::saveTransaction(data: $paymentData, paymentId: $paymentId, paymentMethod: PaymentTypeEnum::STRIPE(), paymentStatus: PaymentStatusEnum::COMPLETED());

        Log::info('event payment_intent.succeeded Payment succeeded', ['payment_id' => $paymentId]);

        if ($transaction !== null && $transaction->order_id !== null) {
            $order = Order::find($transaction->order_id);
            $paidMinor = (int)$data->amount_received;
            $paidCurrency = strtoupper($data->currency);
            $expectedMinor = $chargeSnapshot['charge_minor'] ?? null;
            $chargeCurrency = isset($chargeSnapshot['charge_currency']) ? strtoupper((string)$chargeSnapshot['charge_currency']) : null;
            $covered = $order !== null && (($expectedMinor !== null && $chargeCurrency === $paidCurrency)
                ? $paidMinor >= ((int)$expectedMinor - 1)
                : GatewayAmount::covers((float)$order->total_payable, $order->currency_code, $paidMinor, $paidCurrency));
            if (!$covered) {
                Log::critical('Stripe paid amount not verified against order total; capture blocked', [
                    'order_id' => $transaction->order_id,
                    'paid_minor' => (int)$data->amount_received,
                    'paid_currency' => strtoupper($data->currency),
                    'order_total_payable' => $order->total_payable ?? null,
                    'order_currency' => $order->currency_code ?? null,
                ]);
                $transaction->update([
                    'payment_status' => PaymentStatusEnum::FAILED(),
                    'message' => 'Paid amount does not match order total',
                ]);
                Order::paymentFailed($transaction->order_id);
                OrderItem::paymentFailed($transaction->order_id);
                return;
            }
            Order::capturePayment($transaction->order_id);
            OrderItem::capturePayment($transaction->order_id);
            Log::info("event payment_intent.succeeded Order Updated After Payment Succeeded: {$transaction->order_id}");
        }
    }

    private function handlePaymentFailed(string $paymentType, $transaction, $event): void
    {
        if ($transaction === null) {
            return;
        }

        if ($paymentType === 'wallet_recharge') {
            $transaction->update([
                'status' => PaymentStatusEnum::FAILED(),
                'message' => $event,
            ]);
            Log::info('Wallet Recharge Failed', ['payment_id' => $transaction->id]);
        } elseif ($paymentType === 'subscription_payment' && $transaction instanceof SubscriptionTransaction) {
            $transaction->update([
                'status' => SubscriptionTransactionStatusEnum::FAILED(),
            ]);
            $subscription = SellerSubscription::find($transaction->seller_subscription_id);
            if ($subscription) {
                $subscription->status = SellerSubscriptionStatusEnum::CANCELLED();
                $subscription->save();
            }
            Log::info('Subscription Payment Failed', ['subscription_id' => $transaction->seller_subscription_id]);
        } else {
            if ((string) $transaction->payment_status !== (string) PaymentStatusEnum::PENDING()) {
                Log::info('Order payment attempt failure ignored', ['order_id' => $transaction->order_id]);
                return;
            }

            $transaction->update([
                'payment_status' => PaymentStatusEnum::FAILED(),
                'message' => 'Payment Failed'
            ]);

            Log::warning('event payment_intent.payment_failed Payment attempt failed', ['order_id' => $transaction->order_id]);
        }
    }

    private function handleRefund(string $paymentType, $transaction, object $data): void
    {
        Log::info(" data = " . $data);
        if ($transaction === null) {
            Log::warning('event refund.updated Transaction not found for refund update', ['payment_id' => $data->id ?? null]);
            return;
        }
        if ($paymentType === 'wallet_recharge') {
            $refundAmount = $data->amount_refunded / 100;
            $result = Wallet::captureRefund(transactionId: $transaction->id, refundAmount: $refundAmount);
            if (!$result['success']) {
                Log::error("Wallet Refund Failed: " . $result['message']);
                return;
            }
            Log::info('event refund.processed Wallet Refund Processed', ['payment_id' => $transaction->transaction_reference ?? null]);
            return;
        }
        $transaction->update([
            'payment_status' => PaymentStatusEnum::REFUNDED(),
            'message' => "Payment Refunded",
            'payment_details' => $data
        ]);
        Log::info('event charge.refunded Payment Refunded', ['payment_id' => $transaction->id]);
    }

    private function findTransaction(string $paymentType, object $data)
    {
        if ($paymentType === 'order_payment') {
            $transactionId = $data->id ?? null;
            return OrderPaymentTransaction::where('transaction_id', $transactionId)->first();
        }

        if ($paymentType === 'wallet_recharge') {
            $transactionId = $data->metadata->transaction_id ?? '';
            $transaction = WalletTransaction::find($transactionId);

            if (!$transaction) {
                Log::warning("Wallet Transaction not found for ID: {$transactionId}");
                throw new Exception('Wallet Transaction not found');
            }

            return $transaction;
        }

        if ($paymentType === 'subscription_payment') {
            $subscriptionTxnId = $data->metadata->subscription_transaction_id ?? '';
            if (!$subscriptionTxnId) {
                return null;
            }
            return SubscriptionTransaction::find($subscriptionTxnId);
        }

        return null;
    }


    public function refundPayment($paymentIntentId, $amount = null): array
    {
        try {
            Stripe::setApiKey($this->secretKey);

            $params = [
                'payment_intent' => $paymentIntentId,
            ];

            if ($amount) {
                $params['amount'] = $amount * 100;
            }

            $refund = Refund::create($params);

            return [
                'success' => true,
                'message' => 'Refund processed successfully',
                'data' => $refund,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
