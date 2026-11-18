<?php

namespace App\Services\Payments;

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\Payment\RefundOutcome;
use App\Enums\Payment\XenditCountryEnum;
use App\Enums\Payment\XenditCurrencyCodeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPaymentTransaction;
use App\Models\ProcessedWebhookEvent;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\OrderInventoryService;
use App\Services\SettingService;
use App\Support\GatewayAmount;
use App\Types\Api\ApiResponseType;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class XenditService
{
    private const BASE_URL = 'https://api.xendit.co';

    public function __construct(private readonly SettingService $settingService) {}

    public function initiateOrderPayment(Order $order, ?string $redirectUrl = null): array
    {
        $this->assertEnabled();

        return Cache::lock("xendit:order:{$order->id}", 20)->block(5, function () use ($order, $redirectUrl) {
            $existing = $this->activeOrderSession($order);
            if ($existing !== null) {
                return ApiResponseType::toArray(true, __('labels.payment_initiated_successfully'), $existing);
            }

            $charge = $this->resolveCharge((float) $order->total_payable, (string) $order->currency_code);
            $reference = 'order_'.$order->id.'_'.Str::lower(Str::random(20));
            $returnUrl = $this->returnUrl($redirectUrl);
            $session = $this->createSession([
                'reference_id' => $reference,
                'session_type' => 'PAY',
                'mode' => 'PAYMENT_LINK',
                'amount' => $charge['amount'],
                'currency' => $charge['currency'],
                'country' => $this->country(),
                'capture_method' => 'AUTOMATIC',
                'description' => 'Payment for order '.$order->id,
                'success_return_url' => $returnUrl,
                'cancel_return_url' => $returnUrl,
                'metadata' => [
                    'type' => 'order_payment',
                    'entity_id' => (string) $order->id,
                ],
            ]);

            $this->assertCreatedSession($session, $reference, $charge);

            OrderPaymentTransaction::upsertPending(
                orderId: (int) $order->id,
                userId: $order->user_id,
                transactionId: (string) $session['payment_session_id'],
                amount: (float) $charge['amount'],
                currency: $charge['currency'],
                paymentMethod: PaymentTypeEnum::XENDIT(),
                details: [
                    'reference_id' => $reference,
                    'payment_session_id' => $session['payment_session_id'],
                    'payment_link_url' => $session['payment_link_url'],
                    'expires_at' => $session['expires_at'] ?? null,
                    'session_status' => $session['status'] ?? 'ACTIVE',
                    'charge_minor' => $charge['minor'],
                    'charge_decimals' => $charge['decimals'],
                    'charge_currency' => $charge['currency'],
                    'source_amount' => (float) $order->total_payable,
                    'source_currency' => strtoupper((string) $order->currency_code),
                    'conversion_rate' => round($charge['amount'] / (float) $order->total_payable, 12),
                ]
            );

            return ApiResponseType::toArray(true, __('labels.payment_initiated_successfully'), $this->sessionResponse($session));
        });
    }

    public function initiateWalletRecharge(WalletTransaction $transaction, ?string $redirectUrl = null): array
    {
        $this->assertEnabled();

        if ((string) $transaction->status !== (string) WalletTransactionStatusEnum::PENDING()) {
            throw new InvalidArgumentException('Wallet transaction is not pending.');
        }

        $charge = $this->resolveCharge((float) $transaction->amount, (string) $transaction->currency_code);
        $reference = 'wallet_'.$transaction->id.'_'.Str::lower(Str::random(20));
        $returnUrl = $this->returnUrl($redirectUrl);
        $session = $this->createSession([
            'reference_id' => $reference,
            'session_type' => 'PAY',
            'mode' => 'PAYMENT_LINK',
            'amount' => $charge['amount'],
            'currency' => $charge['currency'],
            'country' => $this->country(),
            'capture_method' => 'AUTOMATIC',
            'description' => 'Wallet recharge '.$transaction->id,
            'success_return_url' => $returnUrl,
            'cancel_return_url' => $returnUrl,
            'metadata' => [
                'type' => 'wallet_recharge',
                'entity_id' => (string) $transaction->id,
            ],
        ]);

        $this->assertCreatedSession($session, $reference, $charge);

        $transaction->transaction_reference = $this->walletFingerprint(
            (string) $session['payment_session_id'],
            $charge['currency'],
            $charge['minor'],
            $reference
        );
        $transaction->save();

        return ApiResponseType::toArray(true, __('labels.payment_initiated_successfully'), $this->sessionResponse($session));
    }

    public function webhookTokenConfigured(): bool
    {
        return trim((string) ($this->settings()['xenditWebhookVerificationToken'] ?? '')) !== '';
    }

    public function testConnection(string $secretKey): array
    {
        $secretKey = trim($secretKey);
        if ($secretKey === '') {
            return [
                'success' => false,
                'message' => __('labels.test_payment_missing_credentials'),
                'gateway_status' => null,
                'gateway_body' => null,
                'gateway_error' => null,
            ];
        }

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->get(self::BASE_URL.'/balance');

            $body = $response->json();
            $body = is_array($body) ? $body : [];
            $errorMessage = is_string($body['message'] ?? null)
                ? str_replace($secretKey, '[redacted]', $body['message'])
                : null;
            $safeBody = $response->successful()
                ? ['balance' => $body['balance'] ?? null]
                : [
                    'error_code' => $body['error_code'] ?? null,
                    'message' => $errorMessage,
                ];

            return [
                'success' => $response->successful(),
                'message' => $response->successful()
                    ? __('labels.test_payment_success', ['gateway' => 'Xendit'])
                    : __('labels.test_payment_failed', ['gateway' => 'Xendit']),
                'gateway_status' => $response->status(),
                'gateway_body' => json_encode($safeBody, JSON_UNESCAPED_SLASHES),
                'gateway_error' => null,
            ];
        } catch (\Throwable) {
            return [
                'success' => false,
                'message' => __('labels.test_payment_failed', ['gateway' => 'Xendit']),
                'gateway_status' => null,
                'gateway_body' => null,
                'gateway_error' => __('labels.test_payment_connection_error'),
            ];
        }
    }

    public function validWebhookToken(?string $provided): bool
    {
        $expected = (string) ($this->settings()['xenditWebhookVerificationToken'] ?? '');

        return $expected !== '' && is_string($provided) && $provided !== '' && hash_equals($expected, $provided);
    }

    public function handleWebhook(array $payload): string
    {
        $event = (string) ($payload['event'] ?? '');

        return match ($event) {
            'payment_session.completed' => $this->handleCompletedSession($payload),
            'payment_session.expired' => $this->handleExpiredSession($payload),
            'refund.succeeded', 'refund.failed' => $this->handleRefund($payload),
            default => 'ignored',
        };
    }

    public function refundPayment(string $transactionId, ?float $amount = null): RefundOutcome
    {
        $lockKey = 'xendit:refund:'.hash('sha256', $transactionId);

        return Cache::lock($lockKey, 30)->block(
            5,
            fn () => $this->createRefund($transactionId, $amount)
        );
    }

    private function createRefund(string $transactionId, ?float $amount): RefundOutcome
    {
        $transaction = OrderPaymentTransaction::where('transaction_id', $transactionId)
            ->where('payment_method', PaymentTypeEnum::XENDIT())
            ->first();

        if (! $transaction) {
            return RefundOutcome::FAILED;
        }

        $details = $transaction->payment_details ?? [];
        $existingStatus = strtoupper((string) ($details['xendit_refund_status'] ?? ''));

        if ($existingStatus === 'PENDING') {
            return RefundOutcome::PENDING;
        }

        if ($existingStatus === 'SUCCEEDED' && (string) $transaction->payment_status === (string) PaymentStatusEnum::REFUNDED()) {
            return RefundOutcome::COMPLETED;
        }

        if (in_array($existingStatus, ['CREATING', 'FAILED'], true)) {
            return RefundOutcome::FAILED;
        }

        $paymentRequestId = (string) ($details['payment_request_id'] ?? '');
        if ($paymentRequestId === '') {
            return RefundOutcome::FAILED;
        }

        $currency = strtoupper((string) $transaction->currency);
        $decimals = (int) ($details['charge_decimals'] ?? 2);
        $totalMinor = GatewayAmount::toMinor((float) $transaction->amount, $currency);
        $refundedMinor = (int) ($details['xendit_refunded_minor'] ?? 0);
        $availableMinor = max(0, $totalMinor - $refundedMinor);
        $requestedMinor = $amount === null
            ? $availableMinor
            : GatewayAmount::toMinor($amount, $currency);

        if ($requestedMinor <= 0 || $requestedMinor > $availableMinor) {
            return RefundOutcome::FAILED;
        }

        $reference = 'refund_'.$transaction->id.'_'.Str::lower(Str::random(20));
        $details['xendit_refund_reference_id'] = $reference;
        $details['xendit_refund_status'] = 'CREATING';
        $details['xendit_refund_minor'] = $requestedMinor;
        $transaction->payment_details = $details;
        $transaction->message = 'Xendit refund creation requires reconciliation';
        $transaction->save();

        $response = $this->request('post', '/refunds', [
            'reference_id' => $reference,
            'payment_request_id' => $paymentRequestId,
            'currency' => $currency,
            'amount' => round($requestedMinor / (10 ** $decimals), $decimals),
            'reason' => 'CANCELLATION',
            'metadata' => [
                'order_transaction_id' => (string) $transaction->id,
            ],
        ], 'refund');

        if (empty($response['id'])) {
            $details['xendit_refund_status'] = 'FAILED';
            $transaction->payment_details = $details;
            $transaction->message = 'Xendit refund initiation failed';
            $transaction->save();

            return RefundOutcome::FAILED;
        }

        $details['xendit_refund_id'] = $response['id'];
        $details['xendit_refund_status'] = strtoupper((string) ($response['status'] ?? ''));
        $transaction->payment_details = $details;
        $transaction->message = $details['xendit_refund_status'] === 'PENDING'
            ? 'Xendit refund pending'
            : 'Xendit refund initiation failed';
        $transaction->save();

        return $details['xendit_refund_status'] === 'PENDING'
            ? RefundOutcome::PENDING
            : RefundOutcome::FAILED;
    }

    private function handleCompletedSession(array $payload): string
    {
        $data = $payload['data'] ?? null;
        if (! is_array($data) || empty($data['payment_session_id'])) {
            throw new InvalidArgumentException('Invalid Xendit session webhook payload.');
        }

        $sessionId = (string) $data['payment_session_id'];
        $eventId = 'payment_session.completed:'.$sessionId;
        if (ProcessedWebhookEvent::where('provider', 'xendit')->where('event_id', $eventId)->exists()) {
            return 'duplicate';
        }

        $verified = $this->getSession($sessionId);
        if ((string) ($verified['payment_session_id'] ?? '') !== $sessionId
            || strtoupper((string) ($verified['status'] ?? '')) !== 'COMPLETED'
            || (string) ($data['reference_id'] ?? '') !== (string) ($verified['reference_id'] ?? '')
            || empty($verified['payment_request_id'])
            || empty($verified['payment_id'])) {
            throw new InvalidArgumentException('Xendit session verification failed.');
        }

        return DB::transaction(function () use ($eventId, $verified) {
            if (! ProcessedWebhookEvent::firstDelivery('xendit', $eventId)) {
                return 'duplicate';
            }

            $type = (string) ($verified['metadata']['type'] ?? '');

            return match ($type) {
                'order_payment' => $this->captureOrder($verified),
                'wallet_recharge' => $this->captureWallet($verified),
                default => throw new InvalidArgumentException('Unsupported Xendit session metadata.'),
            };
        });
    }

    private function handleExpiredSession(array $payload): string
    {
        $data = $payload['data'] ?? null;
        if (! is_array($data) || empty($data['payment_session_id'])) {
            throw new InvalidArgumentException('Invalid Xendit session webhook payload.');
        }

        $sessionId = (string) $data['payment_session_id'];
        $eventId = 'payment_session.expired:'.$sessionId;

        return DB::transaction(function () use ($eventId, $sessionId) {
            if (! ProcessedWebhookEvent::firstDelivery('xendit', $eventId)) {
                return 'duplicate';
            }

            $orderTransaction = OrderPaymentTransaction::where('transaction_id', $sessionId)
                ->where('payment_method', PaymentTypeEnum::XENDIT())
                ->lockForUpdate()
                ->first();

            if ($orderTransaction && (string) $orderTransaction->payment_status === (string) PaymentStatusEnum::PENDING()) {
                $details = $orderTransaction->payment_details ?? [];
                $details['session_status'] = 'EXPIRED';
                $orderTransaction->payment_details = $details;
                $orderTransaction->message = 'Xendit session expired';
                $orderTransaction->save();

                return 'expired';
            }

            $walletTransaction = WalletTransaction::where('payment_method', PaymentTypeEnum::XENDIT())
                ->where('transaction_reference', 'like', 'xdt:'.$sessionId.':%')
                ->lockForUpdate()
                ->first();

            if ($walletTransaction && (string) $walletTransaction->status === (string) WalletTransactionStatusEnum::PENDING()) {
                $walletTransaction->status = WalletTransactionStatusEnum::FAILED();
                $walletTransaction->save();

                return 'expired';
            }

            return 'ignored';
        });
    }

    private function captureOrder(array $session): string
    {
        $sessionId = (string) $session['payment_session_id'];
        $transaction = OrderPaymentTransaction::where('transaction_id', $sessionId)
            ->where('payment_method', PaymentTypeEnum::XENDIT())
            ->lockForUpdate()
            ->first();

        if (! $transaction) {
            throw new InvalidArgumentException('Xendit order transaction not found.');
        }

        $this->assertStoredCharge($transaction->payment_details ?? [], $session);
        $details = $transaction->payment_details ?? [];
        $details['session_status'] = 'COMPLETED';
        $details['payment_request_id'] = $session['payment_request_id'] ?? null;
        $details['payment_id'] = $session['payment_id'] ?? null;
        $transaction->payment_details = $details;
        $transaction->payment_status = PaymentStatusEnum::COMPLETED();
        $transaction->message = 'Xendit payment completed';
        $transaction->save();

        $order = Order::whereKey($transaction->order_id)->lockForUpdate()->first();
        if (! $order) {
            return 'orphaned';
        }

        $lateCapture = (string) $order->status === (string) OrderStatusEnum::CANCELLED()
            || $order->stock_status === OrderInventoryService::STATUS_RELEASED;

        if ($lateCapture) {
            $transaction->message = 'Xendit payment captured after order cancellation; refund pending';
            $transaction->save();

            return 'orphaned';
        }

        if (! Order::capturePaymentFromOrder($order)) {
            throw new RuntimeException('Order payment capture failed.');
        }

        OrderItem::capturePayment((int) $order->id);

        return 'completed';
    }

    private function captureWallet(array $session): string
    {
        $transactionId = filter_var($session['metadata']['entity_id'] ?? null, FILTER_VALIDATE_INT);
        if (! $transactionId) {
            throw new InvalidArgumentException('Invalid Xendit wallet metadata.');
        }

        $transaction = WalletTransaction::whereKey($transactionId)->lockForUpdate()->first();
        if (! $transaction || (string) $transaction->payment_method !== (string) PaymentTypeEnum::XENDIT()) {
            throw new InvalidArgumentException('Xendit wallet transaction not found.');
        }

        $this->assertWalletFingerprint((string) $transaction->transaction_reference, $session);

        if ((string) $transaction->status === (string) WalletTransactionStatusEnum::COMPLETED()) {
            return 'duplicate';
        }

        if ((string) $transaction->status !== (string) WalletTransactionStatusEnum::PENDING()) {
            throw new InvalidArgumentException('Xendit wallet transaction is not pending.');
        }

        $captured = Wallet::captureRecharge($transaction->id);
        if (! ($captured['success'] ?? false)) {
            throw new RuntimeException('Wallet recharge capture failed.');
        }

        return 'completed';
    }

    private function handleRefund(array $payload): string
    {
        $event = (string) ($payload['event'] ?? '');
        $data = $payload['data'] ?? null;
        if (is_array($data) && isset($data['event'], $data['data']) && is_array($data['data'])) {
            $event = (string) $data['event'];
            $data = $data['data'];
        }

        if (! is_array($data) || empty($data['id']) || empty($data['reference_id'])) {
            throw new InvalidArgumentException('Invalid Xendit refund webhook payload.');
        }

        if (! preg_match('/^refund_(\d+)_/', (string) $data['reference_id'], $matches)) {
            throw new InvalidArgumentException('Invalid Xendit refund reference.');
        }

        $eventId = $event.':'.$data['id'];

        return DB::transaction(function () use ($event, $eventId, $data, $matches) {
            if (! ProcessedWebhookEvent::firstDelivery('xendit', $eventId)) {
                return 'duplicate';
            }

            $transaction = OrderPaymentTransaction::whereKey((int) $matches[1])
                ->where('payment_method', PaymentTypeEnum::XENDIT())
                ->lockForUpdate()
                ->first();

            if (! $transaction) {
                throw new InvalidArgumentException('Xendit refund transaction not found.');
            }

            $details = $transaction->payment_details ?? [];
            $currency = strtoupper((string) ($data['currency'] ?? ''));
            $minor = GatewayAmount::toMinor((float) ($data['amount'] ?? 0), $currency);

            if ((string) ($details['xendit_refund_id'] ?? '') !== (string) $data['id']
                || (string) ($details['xendit_refund_reference_id'] ?? '') !== (string) $data['reference_id']
                || (string) ($details['payment_request_id'] ?? '') !== (string) ($data['payment_request_id'] ?? '')
                || strtoupper((string) $transaction->currency) !== $currency
                || (int) ($details['xendit_refund_minor'] ?? 0) !== $minor) {
                throw new InvalidArgumentException('Xendit refund verification failed.');
            }

            $details['xendit_refund_status'] = $event === 'refund.succeeded' ? 'SUCCEEDED' : 'FAILED';
            $details['xendit_refund_failure_code'] = $data['failure_code'] ?? null;

            if ($event === 'refund.succeeded') {
                $refundedMinor = (int) ($details['xendit_refunded_minor'] ?? 0) + $minor;
                $details['xendit_refunded_minor'] = $refundedMinor;
                $totalMinor = GatewayAmount::toMinor((float) $transaction->amount, $currency);
                $transaction->payment_status = $refundedMinor >= $totalMinor
                    ? PaymentStatusEnum::REFUNDED()
                    : PaymentStatusEnum::PARTIALLY_REFUNDED();
                $transaction->message = 'Xendit refund completed';
            } else {
                $transaction->message = 'Xendit refund failed';
            }

            $transaction->payment_details = $details;
            $transaction->save();

            return $event === 'refund.succeeded' ? 'refunded' : 'refund_failed';
        });
    }

    private function activeOrderSession(Order $order): ?array
    {
        $transaction = OrderPaymentTransaction::where('order_id', $order->id)
            ->where('payment_method', PaymentTypeEnum::XENDIT())
            ->where('payment_status', PaymentStatusEnum::PENDING())
            ->latest('id')
            ->first();

        if (! $transaction) {
            return null;
        }

        $details = $transaction->payment_details ?? [];
        $expiresAt = $details['expires_at'] ?? null;
        $link = $details['payment_link_url'] ?? null;

        if (! is_string($expiresAt) || ! is_string($link) || ! $this->isHttpsUrl($link)) {
            return null;
        }

        try {
            if (CarbonImmutable::parse($expiresAt)->lessThanOrEqualTo(now())) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return [
            'payment_session_id' => $details['payment_session_id'] ?? $transaction->transaction_id,
            'reference_id' => $details['reference_id'] ?? null,
            'payment_request_id' => $details['payment_request_id'] ?? null,
            'link' => $link,
            'expires_at' => $expiresAt,
        ];
    }

    private function createSession(array $payload): array
    {
        return $this->request('post', '/sessions', $payload, 'session creation');
    }

    private function getSession(string $sessionId): array
    {
        return $this->request('get', '/sessions/'.rawurlencode($sessionId), [], 'session verification');
    }

    private function request(string $method, string $path, array $payload, string $action): array
    {
        $secret = trim((string) ($this->settings()['xenditSecretKey'] ?? ''));
        if ($secret === '') {
            throw new RuntimeException('Xendit secret key is not configured.');
        }

        $request = Http::withBasicAuth($secret, '')
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15);

        $response = $method === 'get'
            ? $request->get(self::BASE_URL.$path)
            : $request->post(self::BASE_URL.$path, $payload);

        return $this->decodeResponse($response, $action);
    }

    private function decodeResponse(Response $response, string $action): array
    {
        if (! $response->successful()) {
            $code = (string) ($response->json('error_code') ?? 'UPSTREAM_ERROR');
            throw new RuntimeException("Xendit {$action} failed with status {$response->status()} ({$code}).");
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException("Xendit {$action} returned an invalid response.");
        }

        return $data;
    }

    private function resolveCharge(float $amount, string $sourceCurrency): array
    {
        if ($amount <= 0) {
            throw new RuntimeException('Xendit charge amount must be greater than zero.');
        }

        $settings = $this->settings();
        $enabled = array_values(array_unique(array_map(
            fn ($currency) => strtoupper((string) $currency),
            $settings['xenditEnabledCurrencies'] ?? []
        )));
        $fallback = strtoupper((string) ($settings['xenditFallbackCurrency'] ?? ''));
        $source = strtoupper(trim($sourceCurrency));

        $allowedCurrencies = XenditCurrencyCodeEnum::values();
        if ($enabled === []
            || array_diff($enabled, $allowedCurrencies) !== []
            || $fallback === ''
            || ! in_array($fallback, $enabled, true)) {
            throw new RuntimeException('Xendit currencies are not configured correctly.');
        }

        $target = in_array($source, $enabled, true) ? $source : $fallback;

        return GatewayAmount::resolve($amount, $source, $target);
    }

    private function country(): string
    {
        $country = strtoupper((string) ($this->settings()['xenditCountry'] ?? ''));
        if (XenditCountryEnum::tryFrom($country) === null) {
            throw new RuntimeException('Xendit country is not configured.');
        }

        return $country;
    }

    private function returnUrl(?string $redirectUrl): string
    {
        $webSettings = $this->settingService->getSettingValues(SettingTypeEnum::WEB());
        $url = trim((string) ($redirectUrl ?: ($webSettings['customerWebUrl'] ?? '')));
        $parts = parse_url($url);

        if ($url === '' || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new RuntimeException('Payment redirect URL must be a valid HTTPS URL for Xendit.');
        }

        return $url;
    }

    private function assertCreatedSession(array $session, string $reference, array $charge): void
    {
        if (empty($session['payment_session_id'])
            || empty($session['payment_link_url'])
            || ! $this->isHttpsUrl((string) $session['payment_link_url'])
            || (string) ($session['reference_id'] ?? '') !== $reference
            || strtoupper((string) ($session['currency'] ?? '')) !== $charge['currency']
            || GatewayAmount::toMinor((float) ($session['amount'] ?? 0), $charge['currency']) !== $charge['minor']) {
            throw new RuntimeException('Xendit returned an inconsistent session.');
        }
    }

    private function assertStoredCharge(array $details, array $session): void
    {
        $currency = strtoupper((string) ($session['currency'] ?? ''));
        $minor = GatewayAmount::toMinor((float) ($session['amount'] ?? 0), $currency);

        if ((string) ($details['payment_session_id'] ?? '') !== (string) ($session['payment_session_id'] ?? '')
            || (string) ($details['reference_id'] ?? '') !== (string) ($session['reference_id'] ?? '')
            || strtoupper((string) ($details['charge_currency'] ?? '')) !== $currency
            || (int) ($details['charge_minor'] ?? -1) !== $minor) {
            throw new InvalidArgumentException('Xendit payment amount or reference mismatch.');
        }
    }

    private function sessionResponse(array $session): array
    {
        return [
            'payment_session_id' => $session['payment_session_id'],
            'reference_id' => $session['reference_id'] ?? null,
            'payment_request_id' => $session['payment_request_id'] ?? null,
            'link' => $session['payment_link_url'],
            'expires_at' => $session['expires_at'] ?? null,
        ];
    }

    private function walletFingerprint(string $sessionId, string $currency, int $minor, string $reference): string
    {
        return implode(':', ['xdt', $sessionId, strtoupper($currency), $minor, substr(hash('sha256', $reference), 0, 12)]);
    }

    private function assertWalletFingerprint(string $fingerprint, array $session): void
    {
        $parts = explode(':', $fingerprint);
        $currency = strtoupper((string) ($session['currency'] ?? ''));
        $minor = GatewayAmount::toMinor((float) ($session['amount'] ?? 0), $currency);
        $referenceHash = substr(hash('sha256', (string) ($session['reference_id'] ?? '')), 0, 12);

        if (count($parts) !== 5
            || $parts[0] !== 'xdt'
            || $parts[1] !== (string) ($session['payment_session_id'] ?? '')
            || $parts[2] !== $currency
            || (int) $parts[3] !== $minor
            || ! hash_equals($parts[4], $referenceHash)) {
            throw new InvalidArgumentException('Xendit wallet payment verification failed.');
        }
    }

    private function assertEnabled(): void
    {
        if (empty($this->settings()['xenditPayment'])) {
            throw new RuntimeException('Xendit payment is not enabled.');
        }
    }

    private function isHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? '') === 'https'
            && ! empty($parts['host'])
            && empty($parts['user'])
            && empty($parts['pass']);
    }

    private function settings(): array
    {
        return $this->settingService->getSettingValues(SettingTypeEnum::PAYMENT());
    }
}
