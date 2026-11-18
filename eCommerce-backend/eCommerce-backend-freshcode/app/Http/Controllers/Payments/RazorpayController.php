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
use App\Models\SellerSubscription;
use App\Models\SubscriptionTransaction;
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
use Razorpay\Api\Api;

class RazorpayController extends Controller
{
    private Api $razorpayApi;
    private string $keyId;
    private string $secretKey;
    private string $webhookSecret;

    public function __construct(SettingService $settingService)
    {
        $setting = $settingService->getSettingByVariable(SettingTypeEnum::PAYMENT());

        $this->keyId = $setting->value['razorpayKeyId'] ?? "";
        $this->secretKey = $setting->value['razorpaySecretKey'] ?? "";
        $this->webhookSecret = $setting->value['razorpayWebhookSecret'] ?? "";

        $this->razorpayApi = new Api($this->keyId, $this->secretKey);
    }

    /**
     * Render a web view for paying a subscription transaction via Razorpay Checkout
     */
    public function showSubscriptionPaymentPage(string $transaction): Factory|View
    {
        $txn = SubscriptionTransaction::with('subscription')
            ->where('uuid', $transaction)
            ->firstOrFail();

        if ((string)$txn->payment_gateway !== (string)PaymentTypeEnum::RAZORPAY()) {
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

        $orderId = $txn->transaction_id;
        if (empty($orderId)) {
            abort(400, 'Payment is not initialized');
        }

        $charge = GatewayAmount::resolve((float) $txn->amount, GatewayAmount::orderSourceCurrency(), 'INR');
        $amountPaise = $charge['minor'];
        $sellerId = $txn->seller_id;
        $subscriptionId = $txn->seller_subscription_id;

        $plan = $txn->plan;
        $planName = optional($plan)->name ?? 'Subscription Plan';

        return view('payments.payment', [
            'gateway' => 'razorpay',
            'keyId' => $this->keyId,
            'orderId' => $orderId,
            'amount' =>     $charge['amount'],
            'amountPaise' => $amountPaise,
            'currency' => $charge['currency'],
            'transactionId' => $txn->id,
            'transactionUuid' => $txn->uuid,
            'sellerId' => $sellerId,
            'subscriptionId' => $subscriptionId,
            'plan' => $plan,
            'planName' => $planName,
            'payButtonLabel' => __('labels.pay_with_razorpay'),
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
     * Handle Razorpay Webhook
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        if (trim((string) $this->webhookSecret) === '') {
            Log::error('Razorpay webhook secret not configured');

            return response()->json(['error' => 'Webhook secret not configured'], 400);
        }

        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');

        DB::beginTransaction();
        try {
            if (!$this->isValidSignature($payload, $signature)) {
                Log::error("Invalid Razorpay Webhook signature.");
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $data = json_decode($payload, true);
            $event = $data['event'] ?? null;
            $eventId = $request->header('X-Razorpay-Event-Id') ?? (($event ?? '') . ':' . ($data['payload']['payment']['entity']['id'] ?? ''));
            if (!ProcessedWebhookEvent::firstDelivery('razorpay', (string) $eventId)) {
                DB::rollBack();
                return response()->json(['status' => 'duplicate'], 200);
            }
            $paymentEntity = $data['payload']['payment']['entity'] ?? [];
            $paymentType = $paymentEntity['notes']['type'] ?? 'order_payment';

            Log::info('Razorpay webhook received', ['event' => $event, 'event_id' => $eventId]);

            $transaction = $this->findTransaction($paymentType, $paymentEntity);

            $this->processEvent($event, $paymentType, $paymentEntity, $transaction);

            DB::commit();
            return response()->json(['status' => 'success'], 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Razorpay Webhook Error: " . $e->getMessage());
            return response()->json(['error' => 'Server Error'], 500);
        }
    }

    public function initiateOrderPayment(Order $order): array
    {
        try {
            if (empty($this->keyId) || empty($this->secretKey)) {
                return ['success' => false, 'message' => 'Razorpay not configured', 'data' => []];
            }

            $charge = GatewayAmount::resolve((float) $order->total_payable, $order->currency_code, strtoupper($order->currency_code));

            $razorpayOrder = $this->razorpayApi->order->create([
                'amount' => $charge['minor'],
                'currency' => $charge['currency'],
                'receipt' => 'order_' . $order->id,
                'payment_capture' => 1,
                'notes' => [
                    'user_id' => $order->user_id,
                    'type' => 'order_payment',
                    'order_id' => $order->id,
                ],
            ]);

            OrderPaymentTransaction::upsertPending(
                orderId: $order->id,
                userId: $order->user_id,
                transactionId: $razorpayOrder['id'],
                amount: (float) $order->total_payable,
                currency: $order->currency_code,
                paymentMethod: PaymentTypeEnum::RAZORPAY(),
                details: [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'payment_id' => $razorpayOrder['id'],
                ]
            );

            return [
                'success' => true,
                'message' => 'Razorpay order created successfully',
                'data' => [
                    'razorpay_order_id' => $razorpayOrder['id'],
                    'key_id' => $this->keyId,
                    'amount' => $charge['minor'],
                    'currency' => $charge['currency'],
                ],
            ];
        } catch (Exception $e) {
            Log::error('Razorpay order payment initiation failed: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Unable to initiate Razorpay payment', 'data' => ['error' => $e->getMessage()]];
        }
    }

    /**
     * Render the payment page for a wallet recharge transaction via Razorpay.
     */
    public function showWalletPaymentPage(int $transaction): Factory|View
    {
        $txn = WalletTransaction::where('id', $transaction)->firstOrFail();

        if ($txn->payment_method !== PaymentTypeEnum::RAZORPAY()) {
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

        $orderId = $txn->transaction_reference;
        if (empty($orderId)) {
            $orderRes = $this->createWalletRechargeOrder([
                'amount' => (float) $txn->amount,
                'currency' => 'INR',
                'description' => $txn->description ?? 'Wallet Top-up',
                'transaction_id' => (string) $txn->id,
            ]);
            if (!($orderRes['success'] ?? false)) {
                abort(400, $orderRes['data']['error'] ?? $orderRes['message'] ?? 'Unable to initialize Razorpay payment');
            }
            $orderId = $orderRes['data']['id'] ?? null;
            if ($orderId) {
                $txn->transaction_reference = $orderId;
                $txn->save();
            }
        }

        $charge = GatewayAmount::resolve((float) $txn->amount, GatewayAmount::orderSourceCurrency(), 'INR');
        $amountPaise = $charge['minor'];

        return view('payments.payment', [
            'gateway' => 'razorpay',
            'keyId' => $this->keyId,
            'orderId' => $orderId,
            'amount' => $charge['amount'],
            'amountPaise' => $amountPaise,
            'currency' => $charge['currency'],
            'transactionId' => $txn->id,
            'transactionUuid' => $txn->id,
            'sellerId' => $txn->user_id,
            'subscriptionId' => 0,
            'planName' => $txn->description ?? __('labels.wallet_topup'),
            'statusRoute' => route('wallet-payment.status', ['transaction' => $txn->id]),
            'payButtonLabel' => __('labels.pay_with_razorpay'),
            'paymentContext' => [
                'title' => __('labels.wallet_topup_payment_summary'),
                'lineItems' => [
                    ['label' => __('labels.description'), 'value' => $txn->description ?? __('labels.wallet_topup'), 'bold' => true],
                ],
            ],
        ]);
    }

    /**
     * Create a Razorpay order for wallet recharge
     */
    public function createWalletRechargeOrder(array $data): array
    {
        try {
            $validated = validator($data, [
                'amount' => 'required|numeric|min:1',
                'currency' => 'nullable|string|in:INR',
                'description' => 'nullable|string',
                'transaction_id' => 'required|string',
            ])->validate();
            $source = GatewayAmount::orderSourceCurrency();

            $charge = GatewayAmount::resolve((float) $validated['amount'], GatewayAmount::orderSourceCurrency(), 'INR');

            $order = $this->razorpayApi->order->create([
                'amount' => (int) round($validated['amount'] * 100),
                'currency' => $source,
                'receipt' => $validated['description'] ?? "Wallet Recharge",
                'payment_capture' => 1,
                'notes' => [
                    'user_id' => auth()->id(),
                    'type' => 'wallet_recharge',
                    'transaction_id' => $validated['transaction_id'],
                ],
            ]);

            return [
                'success' => true,
                'message' => 'Razorpay Wallet Recharge Order created successfully',
                'data' => $order->toArray()
            ];

        } catch (Exception $e) {
            Log::error('Razorpay wallet recharge order creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unable to create wallet recharge order',
                'data' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Create a Razorpay order for a subscription payment
     * Expects: amount, currency(optional), description(optional), subscription_transaction_id, seller_subscription_id, seller_id, user_id
     */
    public function createSubscriptionOrder(array $data): array
    {
        try {
            $validated = validator($data, [
                'amount' => 'required|numeric|min:1',
                'currency' => 'nullable|string|in:INR',
                'description' => 'nullable|string',
                'subscription_transaction_id' => 'required|integer',
                'seller_subscription_id' => 'required|integer',
                'seller_id' => 'required|integer',
                'user_id' => 'required|integer',
            ])->validate();

            $charge = GatewayAmount::resolve((float) $validated['amount'], GatewayAmount::orderSourceCurrency(), 'INR');

            $order = $this->razorpayApi->order->create([
                'amount' => $charge['minor'],
                'currency' => $charge['currency'],
                'receipt' => 'Seller Subscription',
                'payment_capture' => 1,
                'notes' => [
                    'user_id' => $validated['user_id'],
                    'type' => 'subscription_payment',
                    'subscription_transaction_id' => (string)$validated['subscription_transaction_id'],
                    'seller_subscription_id' => (string)$validated['seller_subscription_id'],
                    'seller_id' => (string)$validated['seller_id'],
                ],
            ]);

            return [
                'success' => true,
                'message' => 'Razorpay Subscription Order created successfully',
                'data' => $order->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Razorpay subscription order creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unable to create subscription order',
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Fetch payment details by payment ID
     */
    public function getPaymentDetails(string $paymentId): JsonResponse
    {
        try {
            $payment = $this->razorpayApi->payment->fetch($paymentId);
            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'Payment details fetched successfully',
                data: $payment
            );
        } catch (Exception $e) {
            Log::error('Razorpay fetch payment failed: ' . $e->getMessage());
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'Unable to fetch payment details',
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Refund a payment
     */
    public function refundPayment($paymentId, $amount = null): array
    {
        try {

            $payment = $this->razorpayApi->payment->fetch($paymentId);

            $refundData = [];
            if (isset($amount)) {
                $refundData['amount'] = $amount * 100;
            }

            $refund = $payment->refund($refundData);

            return [
                "success" => true,
                "message" => 'Refund processed successfully',
                "data" => $refund,
            ];

        } catch (Exception $e) {
            Log::error('Razorpay refund failed: ' . $e->getMessage());
            return [
                "success" => false,
                "message" => 'Refund failed: ' . $e->getMessage(),
                "data" => ['error' => $e->getMessage()],
            ];
        }
    }


    private function isValidSignature(string $payload, ?string $signature): bool
    {
        if ($signature === null || $this->webhookSecret === '') {
            Log::error('Razorpay webhook rejected: missing signature or unconfigured webhook secret.');

            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }


    private function processEvent(string $event, string $paymentType, array $paymentEntity, $transaction = null): void
    {
        switch ($event) {
            case 'payment.authorized':
                Log::info('Payment authorized', ['payment_id' => $paymentEntity['id']]);
                break;

            case 'payment.captured':
                $this->handlePaymentCaptured(
                    paymentId: $paymentEntity['id'],
                    paymentEntity: $paymentEntity,
                    userId: $paymentEntity['notes']['user_id'] ?? null,
                    paymentType: $paymentType,
                    transaction: $transaction
                );
                Log::info('Payment captured', ['payment_id' => $paymentEntity['id']]);
                break;

            case 'order.paid':
                $this->handleOrderPaid(
                    event: $event,
                    paymentType: $paymentType,
                    transaction: $transaction,
                    paidMinor: $paymentEntity['amount'] ?? null,
                    paidCurrency: $paymentEntity['currency'] ?? null
                );
                break;

            case 'payment.failed':
                $this->handlePaymentFailed($paymentType, $transaction, $event);
                break;

            case 'refund.processed':
                $this->handleRefund(
                    paymentType: $paymentType,
                    transaction: $transaction,
                    data: $paymentEntity
                );
                break;

            default:
                Log::warning("Unhandled Razorpay Webhook Event: {$event}");
                break;
        }
    }

    private function findTransaction(string $paymentType, array $paymentEntity)
    {
        if ($paymentType === 'order_payment') {
            $transactionId = $paymentEntity['order_id'] ?? '';
            return OrderPaymentTransaction::where('transaction_id', $transactionId)->first();
        }

        if ($paymentType === 'wallet_recharge') {
            $transactionId = $paymentEntity['notes']['transaction_id'] ?? '';
            $transaction = WalletTransaction::find($transactionId);

            if (!$transaction) {
                Log::warning("Wallet Transaction not found for ID: {$transactionId}");
                throw new Exception('Wallet Transaction not found');
            }

            return $transaction;
        }

        if ($paymentType === 'subscription_payment') {
            $transactionId = $paymentEntity['notes']['subscription_transaction_id'] ?? '';
            if (!$transactionId) {
                return null;
            }
            return SubscriptionTransaction::find($transactionId);
        }

        return null;
    }

    private function handleOrderPaid($event, $paymentType, $transaction, $paidMinor = null, $paidCurrency = null): void
    {
        if ($transaction !== null) {
            if ($paymentType === 'wallet_recharge') {
                $result = Wallet::captureRecharge($transaction->id);
                if (!$result['success']) {
                    Log::error("Wallet Recharge Failed: " . $result['message']);
                    return;
                }
                Log::info("Wallet Recharge Completed: {$event}");

            } elseif ($paymentType === 'order_payment') {
                $transaction->update([
                    'payment_status' => PaymentStatusEnum::COMPLETED(),
                    'message' => $event
                ]);
                if ($transaction->order_id === null) {
                    Log::warning("Order ID is null for transaction: {$transaction->id}");
                    return;
                }
                $order = Order::find($transaction->order_id);
                if ($order === null || $paidMinor === null || $paidCurrency === null
                    || !GatewayAmount::covers((float)$order->total_payable, $order->currency_code, (int)$paidMinor, strtoupper((string)$paidCurrency))) {
                    Log::critical('Razorpay paid amount not verified against order total; capture blocked', [
                        'order_id' => $transaction->order_id,
                        'paid_minor' => $paidMinor === null ? null : (int)$paidMinor,
                        'paid_currency' => $paidCurrency === null ? null : strtoupper((string)$paidCurrency),
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
                Log::info("Order Updated And Ready to Go: {$event}");
            }
        }
    }

    private function handleRefund($paymentType, $transaction, $data = null): void
    {
        if ($paymentType === 'wallet_recharge') {
            Wallet::captureRefund($transaction->id);
            Log::info('event refund.processed Wallet Refund Processed', ['payment_id' => $transaction->transaction_reference ?? null]);
            return;
        }
        $transaction->update([
            'payment_status' => PaymentStatusEnum::REFUNDED(),
            'message' => "Payment Refunded",
            'payment_details' => $data
        ]);
        Log::info('event refund.processed Payment Refunded', ['payment_id' => $transaction->transaction_id ?? null]);
    }

    private function handlePaymentCaptured($paymentId, $paymentEntity, $userId, $paymentType, $transaction = null): void
    {
        if ($paymentType === 'wallet_recharge') {
            $transaction->update([
                'transaction_reference' => $paymentId,
                'description' => 'Wallet Recharge Payment Captured'
            ]);
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
            Log::info('Subscription payment captured', ['payment_id' => $paymentId, 'transaction_id' => $transaction->id]);
            return;
        }

        if ($transaction) {
            $transaction->update([
                'transaction_reference' => $paymentId,
                'payment_status' => PaymentStatusEnum::COMPLETED(),
            ]);
        }
    }

    private function handlePaymentFailed(string $paymentType, $transaction = null, string $event = ''): void
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
        } elseif ($paymentType === 'order_payment') {
            if ((string) $transaction->payment_status !== (string) PaymentStatusEnum::PENDING()) {
                Log::info('Order payment attempt failure ignored', ['order_id' => $transaction->order_id]);
                return;
            }

            $transaction->update([
                'payment_status' => PaymentStatusEnum::FAILED(),
                'message' => $event,
            ]);

            Log::info('Order payment attempt failed', ['order_id' => $transaction->order_id]);
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
        }
    }
}
