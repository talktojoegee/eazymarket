<?php

namespace App\Services\Payments;

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\MercadoPagoCurrencyCodeEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\Payment\RefundOutcome;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class MercadoPagoService
{
    private const BASE_URL = 'https://api.mercadopago.com';

    public function __construct(private readonly SettingService $settingService) {}

    public function initiateOrderPayment(Order $order, ?string $redirectUrl = null): array
    {
        $this->assertEnabled();

        return Cache::lock("mercado-pago:order:{$order->id}", 20)->block(5, function () use ($order, $redirectUrl) {
            $existing = $this->activeOrder($order);
            if ($existing !== null) {
                return ApiResponseType::toArray(true, __('labels.payment_initiated_successfully'), $existing);
            }

            $charge = $this->resolveCharge((float) $order->total_payable, (string) $order->currency_code);
            $reference = 'order_'.$order->id.'_'.Str::lower(Str::random(20));
            $expiresAt = CarbonImmutable::now()->addDay();
            $returnUrls = $this->returnUrls($redirectUrl, 'order', (string) $order->slug);
            $email = trim((string) ($order->email ?: $order->user?->email));

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('Mercado Pago requires a valid customer email.');
            }

            $email = $this->payerEmail($email, $reference);

            $created = $this->createPreference([
                'items' => [[
                    'id' => 'order_'.$order->id,
                    'title' => 'Order '.$order->id,
                    'quantity' => 1,
                    'currency_id' => $charge['currency'],
                    'unit_price' => $this->preferenceAmount($charge['minor'], $charge['decimals']),
                ]],
                'external_reference' => $reference,
                'payer' => ['email' => $email],
                'back_urls' => $returnUrls,
                'auto_return' => 'approved',
                'notification_url' => route('mercado-pago.webhook'),
                'metadata' => ['entity_type' => 'order', 'entity_id' => (string) $order->id],
                'expires' => true,
                'expiration_date_from' => now()->toIso8601String(),
                'expiration_date_to' => $expiresAt->toIso8601String(),
            ]);

            $link = $this->preferenceLink($created);
            $this->assertCreatedPreference($created, $link);

            OrderPaymentTransaction::upsertPending(
                orderId: (int) $order->id,
                userId: $order->user_id,
                transactionId: (string) $created['id'],
                amount: (float) $charge['amount'],
                currency: $charge['currency'],
                paymentMethod: PaymentTypeEnum::MERCADO_PAGO(),
                details: [
                    'mercado_pago_order_id' => $created['id'],
                    'mercado_pago_preference_id' => $created['id'],
                    'checkout_url' => $link,
                    'external_reference' => $reference,
                    'expires_at' => $expiresAt->toIso8601String(),
                    'preference_status' => 'created',
                    'charge_minor' => $charge['minor'],
                    'charge_decimals' => $charge['decimals'],
                    'charge_currency' => $charge['currency'],
                    'source_amount' => (float) $order->total_payable,
                    'source_currency' => strtoupper((string) $order->currency_code),
                    'conversion_rate' => round($charge['amount'] / (float) $order->total_payable, 12),
                    'entity_type' => 'order',
                    'entity_id' => (string) $order->id,
                ],
            );

            return ApiResponseType::toArray(true, __('labels.payment_initiated_successfully'), $this->preferenceResponse($created, $reference, $expiresAt, $link));
        });
    }

    public function initiateWalletRecharge(WalletTransaction $transaction, ?string $redirectUrl = null): array
    {
        $this->assertEnabled();

        return Cache::lock("mercado-pago:wallet:{$transaction->id}", 20)->block(5, function () use ($transaction, $redirectUrl) {
            $transaction = WalletTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ((string) $transaction->status !== (string) WalletTransactionStatusEnum::PENDING()) {
                throw new InvalidArgumentException('Wallet transaction is not pending.');
            }

            $charge = $this->resolveCharge((float) $transaction->amount, (string) $transaction->currency_code);
            $reference = 'wallet_'.$transaction->id.'_'.Str::lower(Str::random(20));
            $expiresAt = CarbonImmutable::now()->addDay();
            $returnUrls = $this->returnUrls($redirectUrl, 'wallet', (string) $transaction->id);
            $email = trim((string) $transaction->user?->email);

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('Mercado Pago requires a valid customer email.');
            }

            $email = $this->payerEmail($email, $reference);

            $created = $this->createPreference([
                'items' => [[
                    'id' => 'wallet_'.$transaction->id,
                    'title' => 'Wallet recharge '.$transaction->id,
                    'quantity' => 1,
                    'currency_id' => $charge['currency'],
                    'unit_price' => $this->preferenceAmount($charge['minor'], $charge['decimals']),
                ]],
                'external_reference' => $reference,
                'payer' => ['email' => $email],
                'back_urls' => $returnUrls,
                'auto_return' => 'approved',
                'notification_url' => route('mercado-pago.webhook'),
                'metadata' => ['entity_type' => 'wallet', 'entity_id' => (string) $transaction->id],
                'expires' => true,
                'expiration_date_from' => now()->toIso8601String(),
                'expiration_date_to' => $expiresAt->toIso8601String(),
            ]);

            $link = $this->preferenceLink($created);
            $this->assertCreatedPreference($created, $link);

            $transaction->transaction_reference = $this->walletFingerprint(
                (string) $created['id'],
                $charge['currency'],
                $charge['minor'],
                $reference,
            );
            $transaction->save();

            return ApiResponseType::toArray(true, __('labels.payment_initiated_successfully'), $this->preferenceResponse($created, $reference, $expiresAt, $link));
        });
    }

    public function webhookSecretConfigured(): bool
    {
        return trim((string) ($this->settings()['mercadoPagoWebhookSecret'] ?? '')) !== '';
    }

    public function acceptsWebhookEnvironment(bool $liveMode): bool
    {
        return $liveMode === (($this->settings()['mercadoPagoPaymentMode'] ?? 'test') === 'live');
    }

    public function validWebhookSignature(?string $signature, ?string $requestId, string $dataId): bool
    {
        $secret = trim((string) ($this->settings()['mercadoPagoWebhookSecret'] ?? ''));
        if ($secret === '' || ! is_string($signature) || $signature === '' || ! is_string($requestId) || $requestId === '' || $dataId === '') {
            return false;
        }

        $values = [];
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if (is_string($key) && is_string($value)) {
                $values[$key] = $value;
            }
        }

        $timestamp = (string) ($values['ts'] ?? '');
        $provided = strtolower((string) ($values['v1'] ?? ''));
        if ($timestamp === '' || $provided === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $manifest = 'id:'.strtolower($dataId).';request-id:'.$requestId.';ts:'.$timestamp.';';
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $provided);
    }

    public function testConnection(string $accessToken): array
    {
        $accessToken = trim($accessToken);
        if ($accessToken === '') {
            return [
                'success' => false,
                'message' => __('labels.test_payment_missing_credentials'),
                'gateway_status' => null,
                'gateway_body' => null,
                'gateway_error' => null,
            ];
        }

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->get(self::BASE_URL.'/v1/payment_methods');

            $body = $response->json();
            $safeBody = $response->successful()
                ? ['payment_methods' => is_array($body) ? count($body) : 0]
                : [
                    'code' => is_array($body) ? ($body['code'] ?? null) : null,
                    'message' => is_array($body) ? ($body['message'] ?? null) : null,
                ];

            return [
                'success' => $response->successful(),
                'message' => $response->successful()
                    ? __('labels.test_payment_success', ['gateway' => 'Mercado Pago'])
                    : __('labels.test_payment_failed', ['gateway' => 'Mercado Pago']),
                'gateway_status' => $response->status(),
                'gateway_body' => json_encode($safeBody, JSON_UNESCAPED_SLASHES),
                'gateway_error' => null,
            ];
        } catch (\Throwable) {
            return [
                'success' => false,
                'message' => __('labels.test_payment_failed', ['gateway' => 'Mercado Pago']),
                'gateway_status' => null,
                'gateway_body' => null,
                'gateway_error' => __('labels.test_payment_connection_error'),
            ];
        }
    }

    public function handleWebhook(array $payload, string $dataId, string $requestId): string
    {
        $topic = strtolower((string) ($payload['type'] ?? $payload['topic'] ?? ''));
        if ($topic !== 'payment') {
            return 'ignored';
        }

        if ($dataId === '') {
            throw new InvalidArgumentException('Invalid Mercado Pago webhook payload.');
        }

        $eventId = (string) ($payload['id'] ?? $requestId);
        if ($eventId === '') {
            $eventId = hash('sha256', $topic.':'.$dataId.':'.(string) ($payload['action'] ?? ''));
        }

        if (ProcessedWebhookEvent::where('provider', 'mercado_pago')->where('event_id', $eventId)->exists()) {
            return 'duplicate';
        }

        $verified = $this->getPayment($dataId);
        if ((string) ($verified['id'] ?? '') !== $dataId) {
            throw new InvalidArgumentException('Mercado Pago payment verification failed.');
        }

        return DB::transaction(function () use ($eventId, $verified) {
            if (! ProcessedWebhookEvent::firstDelivery('mercado_pago', $eventId)) {
                return 'duplicate';
            }

            return $this->reconcileVerifiedPayment($verified);
        });
    }

    public function refundPayment(string $transactionId, ?float $amount = null): RefundOutcome
    {
        $lockKey = 'mercado-pago:refund:'.hash('sha256', $transactionId);

        return Cache::lock($lockKey, 30)->block(5, fn () => $this->createRefund($transactionId, $amount));
    }

    private function createRefund(string $transactionId, ?float $amount): RefundOutcome
    {
        $transaction = OrderPaymentTransaction::where('transaction_id', $transactionId)
            ->where('payment_method', PaymentTypeEnum::MERCADO_PAGO())
            ->first();

        if (! $transaction) {
            return RefundOutcome::FAILED;
        }

        $details = $transaction->payment_details ?? [];
        $existingStatus = strtoupper((string) ($details['mercado_pago_refund_status'] ?? ''));
        if ($existingStatus === 'PROCESSING') {
            return RefundOutcome::PENDING;
        }

        if ($existingStatus === 'CREATING') {
            return RefundOutcome::FAILED;
        }

        $currency = strtoupper((string) $transaction->currency);
        $decimals = (int) ($details['charge_decimals'] ?? 2);
        $totalMinor = $this->toMinor((float) $transaction->amount, $decimals);
        $refundedMinor = (int) ($details['mercado_pago_refunded_minor'] ?? 0);
        $availableMinor = max(0, $totalMinor - $refundedMinor);
        $requestedMinor = $amount === null ? $availableMinor : $this->toMinor($amount, $decimals);
        $paymentId = (string) ($details['payment_id'] ?? '');

        if ($paymentId === '' || $requestedMinor <= 0 || $requestedMinor > $availableMinor) {
            return RefundOutcome::FAILED;
        }

        $idempotencyKey = (string) Str::uuid();
        $details['mercado_pago_refund_status'] = 'CREATING';
        $details['mercado_pago_refund_idempotency_key'] = $idempotencyKey;
        $details['mercado_pago_refund_minor'] = $requestedMinor;
        $details['mercado_pago_refund_payment_id'] = $paymentId;
        $transaction->payment_details = $details;
        $transaction->message = 'Mercado Pago refund creation in progress';
        $transaction->save();

        $payload = $amount === null
            ? []
            : ['amount' => $this->preferenceAmount($requestedMinor, $decimals)];

        try {
            $response = $this->request(
                'post',
                '/v1/payments/'.rawurlencode($paymentId).'/refunds',
                $payload,
                'refund creation',
                $idempotencyKey,
            );
        } catch (RuntimeException $exception) {
            $details['mercado_pago_refund_status'] = 'FAILED';
            $transaction->payment_details = $details;
            $transaction->message = 'Mercado Pago refund initiation failed';
            $transaction->save();
            throw $exception;
        }

        $responsePaymentId = (string) ($response['payment_id'] ?? '');
        $responseMinor = $this->toMinor((float) ($response['amount'] ?? 0), $decimals);
        if (empty($response['id']) || $responsePaymentId !== $paymentId || $responseMinor !== $requestedMinor) {
            $details['mercado_pago_refund_status'] = 'FAILED';
            $transaction->payment_details = $details;
            $transaction->message = 'Mercado Pago refund verification failed';
            $transaction->save();

            return RefundOutcome::FAILED;
        }

        $details['mercado_pago_refund_id'] = (string) $response['id'];
        $refundStatus = strtolower((string) ($response['status'] ?? ''));
        if ($refundStatus === 'approved') {
            $this->applyCompletedRefund($transaction, $details, $requestedMinor);

            return RefundOutcome::COMPLETED;
        }

        if (in_array($refundStatus, ['pending', 'processing', 'in_process'], true)) {
            $details['mercado_pago_refund_status'] = 'PROCESSING';
            $transaction->payment_details = $details;
            $transaction->message = 'Mercado Pago refund pending';
            $transaction->save();

            return RefundOutcome::PENDING;
        }

        $details['mercado_pago_refund_status'] = 'FAILED';
        $transaction->payment_details = $details;
        $transaction->message = 'Mercado Pago refund failed';
        $transaction->save();

        return RefundOutcome::FAILED;
    }

    private function reconcileVerifiedPayment(array $verified): string
    {
        $status = strtolower((string) ($verified['status'] ?? ''));
        $reference = (string) ($verified['external_reference'] ?? '');

        if ($reference === '') {
            throw new InvalidArgumentException('Mercado Pago external reference is missing.');
        }

        if (array_key_exists('live_mode', $verified)
            && ! $this->acceptsWebhookEnvironment((bool) $verified['live_mode'])) {
            throw new InvalidArgumentException('Mercado Pago payment environment mismatch.');
        }

        if ($status === 'refunded' || ! empty($verified['refunds'])) {
            return $this->reconcileRefund($verified);
        }

        if ($status === 'approved') {
            return str_starts_with($reference, 'order_')
                ? $this->captureOrder($verified)
                : $this->captureWallet($verified);
        }

        if (in_array($status, ['rejected', 'cancelled', 'canceled'], true)) {
            return $this->failPendingPayment($verified);
        }

        if (in_array($status, ['pending', 'authorized', 'in_process', 'in_mediation'], true)) {
            return $this->markPaymentPending($verified);
        }

        return 'ignored';
    }

    private function captureOrder(array $verified): string
    {
        $reference = (string) ($verified['external_reference'] ?? '');
        $transaction = $this->orderTransactionByReference($reference, true);

        if (! $transaction) {
            throw new InvalidArgumentException('Mercado Pago payment transaction not found.');
        }

        $this->assertStoredCharge($transaction->payment_details ?? [], $verified, true);

        $details = $transaction->payment_details ?? [];
        $details['payment_id'] = (string) $verified['id'];
        $details['payment_status'] = $verified['status'] ?? null;
        $details['payment_status_detail'] = $verified['status_detail'] ?? null;
        $transaction->payment_details = $details;
        $transaction->payment_status = PaymentStatusEnum::COMPLETED();
        $transaction->message = 'Mercado Pago payment completed';
        $transaction->save();

        $order = Order::whereKey($transaction->order_id)->lockForUpdate()->first();
        if (! $order) {
            return 'orphaned';
        }

        $lateCapture = (string) $order->status === (string) OrderStatusEnum::CANCELLED()
            || $order->stock_status === OrderInventoryService::STATUS_RELEASED;

        if ($lateCapture) {
            $transaction->message = 'Mercado Pago payment captured after order cancellation; refund required';
            $transaction->save();

            return 'orphaned';
        }

        if ((string) $order->payment_status === (string) PaymentStatusEnum::COMPLETED()) {
            return 'duplicate';
        }

        if (! Order::capturePaymentFromOrder($order)) {
            throw new RuntimeException('Order payment capture failed.');
        }

        OrderItem::capturePayment((int) $order->id);

        return 'completed';
    }

    private function captureWallet(array $verified): string
    {
        $transactionId = $this->entityId((string) ($verified['external_reference'] ?? ''), 'wallet');
        $transaction = WalletTransaction::whereKey($transactionId)->lockForUpdate()->first();
        if (! $transaction || (string) $transaction->payment_method !== (string) PaymentTypeEnum::MERCADO_PAGO()) {
            throw new InvalidArgumentException('Mercado Pago wallet transaction not found.');
        }

        if (strtolower((string) ($verified['status'] ?? '')) !== 'approved') {
            throw new InvalidArgumentException('Mercado Pago wallet payment is not accredited.');
        }

        $currency = strtoupper((string) ($verified['currency_id'] ?? ''));
        $minor = $this->toMinor((float) ($verified['transaction_amount'] ?? 0), $this->mercadoPagoDecimals($currency));
        $this->assertWalletFingerprint((string) $transaction->transaction_reference, $currency, $minor, (string) $verified['external_reference']);

        if ((string) $transaction->status === (string) WalletTransactionStatusEnum::COMPLETED()) {
            return 'duplicate';
        }

        if (! in_array((string) $transaction->status, [(string) WalletTransactionStatusEnum::PENDING(), (string) WalletTransactionStatusEnum::FAILED()], true)) {
            throw new InvalidArgumentException('Mercado Pago wallet transaction cannot be captured.');
        }

        if ((string) $transaction->status === (string) WalletTransactionStatusEnum::FAILED()) {
            $transaction->status = WalletTransactionStatusEnum::PENDING();
            $transaction->save();
        }

        $captured = Wallet::captureRecharge($transaction->id);
        if (! ($captured['success'] ?? false)) {
            throw new RuntimeException('Wallet recharge capture failed.');
        }

        return 'completed';
    }

    private function failPendingPayment(array $verified): string
    {
        $reference = (string) ($verified['external_reference'] ?? '');
        if (str_starts_with($reference, 'order_')) {
            $transaction = $this->orderTransactionByReference($reference, true);

            if (! $transaction || (string) $transaction->payment_status !== (string) PaymentStatusEnum::PENDING()) {
                return 'ignored';
            }

            $this->assertStoredCharge($transaction->payment_details ?? [], $verified);
            $details = $transaction->payment_details ?? [];
            $details['payment_id'] = (string) ($verified['id'] ?? '');
            $details['payment_status'] = $verified['status'] ?? null;
            $details['payment_status_detail'] = $verified['status_detail'] ?? null;
            $transaction->payment_details = $details;
            $transaction->payment_status = PaymentStatusEnum::FAILED();
            $transaction->message = 'Mercado Pago payment failed';
            $transaction->save();

            return 'failed';
        }

        $transactionId = $this->entityId($reference, 'wallet');
        $transaction = WalletTransaction::whereKey($transactionId)->lockForUpdate()->first();
        if ($transaction && (string) $transaction->status === (string) WalletTransactionStatusEnum::PENDING()) {
            $transaction->status = WalletTransactionStatusEnum::FAILED();
            $transaction->save();

            return 'failed';
        }

        return 'ignored';
    }

    private function markPaymentPending(array $verified): string
    {
        $reference = (string) ($verified['external_reference'] ?? '');
        if (! str_starts_with($reference, 'order_')) {
            return 'pending';
        }

        $transaction = $this->orderTransactionByReference($reference, true);
        if (! $transaction) {
            throw new InvalidArgumentException('Mercado Pago pending transaction not found.');
        }

        $this->assertStoredCharge($transaction->payment_details ?? [], $verified);
        $details = $transaction->payment_details ?? [];
        $details['payment_id'] = (string) ($verified['id'] ?? '');
        $details['payment_status'] = $verified['status'] ?? null;
        $details['payment_status_detail'] = $verified['status_detail'] ?? null;
        $transaction->payment_details = $details;
        $transaction->save();

        return 'pending';
    }

    private function reconcileRefund(array $verified): string
    {
        $reference = (string) ($verified['external_reference'] ?? '');
        if (! str_starts_with($reference, 'order_')) {
            return 'ignored';
        }

        $transaction = $this->orderTransactionByReference($reference, true);

        if (! $transaction) {
            throw new InvalidArgumentException('Mercado Pago refund transaction not found.');
        }

        $this->assertStoredCharge($transaction->payment_details ?? [], $verified);
        $details = $transaction->payment_details ?? [];
        $requestedMinor = (int) ($details['mercado_pago_refund_minor'] ?? 0);
        $paymentId = (string) ($details['mercado_pago_refund_payment_id'] ?? '');
        $currency = strtoupper((string) $transaction->currency);

        if ($requestedMinor <= 0 || $paymentId === '') {
            return 'ignored';
        }

        $refund = $this->refundFromPayment(
            $verified,
            $paymentId,
            $requestedMinor,
            (int) ($details['charge_decimals'] ?? $this->mercadoPagoDecimals($currency)),
        );
        if ($refund === null) {
            throw new InvalidArgumentException('Mercado Pago refund verification failed.');
        }

        $storedRefundId = (string) ($details['mercado_pago_refund_id'] ?? '');
        if ($storedRefundId !== '' && $storedRefundId !== (string) ($refund['id'] ?? '')) {
            throw new InvalidArgumentException('Mercado Pago refund identifier mismatch.');
        }

        if ($storedRefundId !== ''
            && strtoupper((string) ($details['mercado_pago_refund_status'] ?? '')) === 'COMPLETED') {
            return 'refunded';
        }

        $details['mercado_pago_refund_id'] = $refund['id'] ?? null;
        $refundStatus = strtolower((string) ($refund['status'] ?? ''));
        if ($refundStatus === 'approved') {
            $this->applyCompletedRefund($transaction, $details, $requestedMinor);

            return 'refunded';
        }

        if (in_array($refundStatus, ['pending', 'processing', 'in_process'], true)) {
            $details['mercado_pago_refund_status'] = 'PROCESSING';
            $transaction->payment_details = $details;
            $transaction->message = 'Mercado Pago refund pending';
            $transaction->save();

            return 'refund_pending';
        }

        $details['mercado_pago_refund_status'] = 'FAILED';
        $transaction->payment_details = $details;
        $transaction->message = 'Mercado Pago refund failed';
        $transaction->save();

        return 'refund_failed';
    }

    private function applyCompletedRefund(OrderPaymentTransaction $transaction, array $details, int $minor): void
    {
        $currency = strtoupper((string) $transaction->currency);
        $refundedMinor = (int) ($details['mercado_pago_refunded_minor'] ?? 0) + $minor;
        $decimals = (int) ($details['charge_decimals'] ?? $this->mercadoPagoDecimals($currency));
        $totalMinor = $this->toMinor((float) $transaction->amount, $decimals);
        $details['mercado_pago_refunded_minor'] = min($refundedMinor, $totalMinor);
        $details['mercado_pago_refund_status'] = 'COMPLETED';
        $transaction->payment_status = $refundedMinor >= $totalMinor
            ? PaymentStatusEnum::REFUNDED()
            : PaymentStatusEnum::PARTIALLY_REFUNDED();
        $transaction->payment_details = $details;
        $transaction->message = 'Mercado Pago refund completed';
        $transaction->save();
    }

    private function createPreference(array $payload): array
    {
        return $this->request('post', '/checkout/preferences', $payload, 'preference creation');
    }

    private function getPayment(string $paymentId): array
    {
        return $this->request('get', '/v1/payments/'.rawurlencode($paymentId), [], 'payment verification');
    }

    private function request(string $method, string $path, array $payload, string $action, ?string $idempotencyKey = null): array
    {
        $accessToken = trim((string) ($this->settings()['mercadoPagoAccessToken'] ?? ''));
        if ($accessToken === '') {
            throw new RuntimeException('Mercado Pago access token is not configured.');
        }

        $request = Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15);

        if (is_string($idempotencyKey) && $idempotencyKey !== '') {
            $request = $request->withHeaders(['X-Idempotency-Key' => $idempotencyKey]);
        }

        $response = $method === 'get'
            ? $request->get(self::BASE_URL.$path, $payload)
            : $request->post(self::BASE_URL.$path, $payload);

        return $this->decodeResponse($response, $action);
    }

    private function decodeResponse(Response $response, string $action): array
    {
        if (! $response->successful()) {
            $code = (string) ($response->json('code')
                ?? $response->json('error')
                ?? $response->json('errors.0.code')
                ?? $response->json('cause.0.code')
                ?? 'UPSTREAM_ERROR');
            $code = preg_replace('/[^A-Za-z0-9_.-]/', '_', $code) ?: 'UPSTREAM_ERROR';
            $details = $this->sanitizedErrorDetails($response);
            Log::warning('Mercado Pago API request failed.', [
                'action' => $action,
                'status' => $response->status(),
                'code' => $code,
                'request_id' => $response->header('x-request-id'),
                'errors' => $details,
            ]);
            $detail = in_array($code, ['unsupported_properties', 'invalid_items'], true)
                ? trim((string) ($details[0]['message'] ?? $response->json('message') ?? ''))
                : '';
            $detail = preg_replace('/https?:\/\/\S+|\b[^\s@]+@[^\s@]+\b|\b[A-Za-z0-9_-]{24,}\b/i', '[redacted]', $detail) ?? '';
            $suffix = $detail !== '' ? ' '.$detail : '';
            throw new RuntimeException("Mercado Pago {$action} failed with status {$response->status()} ({$code}).{$suffix}");
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException("Mercado Pago {$action} returned an invalid response.");
        }

        return $data;
    }

    private function sanitizedErrorDetails(Response $response): array
    {
        $details = $response->json('errors') ?? $response->json('cause');
        if (! is_array($details)) {
            return [];
        }

        return collect(array_is_list($details) ? $details : [$details])
            ->take(5)
            ->map(function ($detail): array {
                if (! is_array($detail)) {
                    return [];
                }

                $message = (string) ($detail['message'] ?? $detail['description'] ?? $detail['detail'] ?? '');
                $message = preg_replace('/https?:\/\/\S+|\b[^\s@]+@[^\s@]+\b|\b[A-Za-z0-9_-]{24,}\b/i', '[redacted]', $message) ?? '';

                return array_filter([
                    'code' => preg_replace('/[^A-Za-z0-9_.-]/', '_', (string) ($detail['code'] ?? '')),
                    'message' => Str::limit($message, 300, '...'),
                ], fn ($value) => $value !== '');
            })
            ->filter()
            ->values()
            ->all();
    }

    private function payerEmail(string $customerEmail, string $reference): string
    {
        if (($this->settings()['mercadoPagoPaymentMode'] ?? 'test') === 'live') {
            return $customerEmail;
        }

        return 'hypercommerce-'.substr(hash('sha256', $reference), 0, 20).'@testuser.com';
    }

    private function resolveCharge(float $amount, string $sourceCurrency): array
    {
        if ($amount <= 0) {
            throw new RuntimeException('Mercado Pago charge amount must be greater than zero.');
        }

        $currency = $this->accountCurrency();
        $charge = GatewayAmount::resolve($amount, strtoupper(trim($sourceCurrency)), $currency);
        $decimals = $this->mercadoPagoDecimals($currency);
        $normalizedAmount = round((float) $charge['amount'], $decimals);
        $minor = $this->toMinor($normalizedAmount, $decimals);

        if ($minor <= 0) {
            throw new RuntimeException('Mercado Pago charge amount is too small after currency conversion.');
        }

        return [
            'amount' => $normalizedAmount,
            'minor' => $minor,
            'currency' => $currency,
            'decimals' => $decimals,
        ];
    }

    private function mercadoPagoDecimals(string $currency): int
    {
        return in_array(strtoupper($currency), ['CLP', 'COP'], true) ? 0 : 2;
    }

    private function toMinor(float $amount, int $decimals): int
    {
        return (int) round($amount * (10 ** $decimals));
    }

    private function accountCurrency(): string
    {
        $currency = strtoupper(trim((string) ($this->settings()['mercadoPagoAccountCurrency'] ?? '')));
        if (MercadoPagoCurrencyCodeEnum::tryFrom($currency) === null) {
            throw new RuntimeException('Mercado Pago account currency is not configured.');
        }

        return $currency;
    }

    private function returnUrls(?string $redirectUrl, string $entityType, string $entityId): array
    {
        $base = $this->returnUrl($redirectUrl);
        $entity = $entityType === 'order'
            ? ['order_slug' => $entityId]
            : ['transaction' => $entityId];

        return [
            'success' => $this->appendQuery($base, $entity + ['mercado_pago_return' => '1', 'mercado_pago_result' => 'success']),
            'failure' => $this->appendQuery($base, $entity + ['mercado_pago_return' => '1', 'mercado_pago_result' => 'failure']),
            'pending' => $this->appendQuery($base, $entity + ['mercado_pago_return' => '1', 'mercado_pago_result' => 'pending']),
        ];
    }

    private function returnUrl(?string $redirectUrl): string
    {
        $webSettings = $this->settingService->getSettingValues(SettingTypeEnum::WEB());
        $url = trim((string) ($redirectUrl ?: ($webSettings['customerWebUrl'] ?? '')));
        $parts = parse_url($url);

        if ($url === ''
            || ($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
            || ! empty($parts['user'])
            || ! empty($parts['pass'])) {
            throw new RuntimeException('Payment redirect URL must be a valid HTTPS URL for Mercado Pago.');
        }

        return $url;
    }

    private function appendQuery(string $url, array $parameters): string
    {
        $parts = parse_url($url);
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query = array_merge($query, $parameters);

        $authority = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $authority .= ':'.$parts['port'];
        }

        $result = $authority.($parts['path'] ?? '');
        if ($query !== []) {
            $result .= '?'.http_build_query($query);
        }

        return $result;
    }

    private function assertCreatedPreference(array $created, string $link): void
    {
        if (empty($created['id'])
            || ! $this->isMercadoPagoUrl($link)) {
            throw new RuntimeException('Mercado Pago returned an inconsistent preference.');
        }
    }

    private function assertStoredCharge(array $details, array $verified, bool $requirePaid = false): void
    {
        $currency = strtoupper((string) ($verified['currency_id'] ?? ''));
        $decimals = (int) ($details['charge_decimals'] ?? $this->mercadoPagoDecimals($currency));
        $minor = $this->toMinor((float) ($verified['transaction_amount'] ?? 0), $decimals);

        if ((string) ($details['external_reference'] ?? '') !== (string) ($verified['external_reference'] ?? '')
            || strtoupper((string) ($details['charge_currency'] ?? '')) !== $currency
            || (int) ($details['charge_minor'] ?? -1) !== $minor) {
            throw new InvalidArgumentException('Mercado Pago payment amount or reference mismatch.');
        }

        if ($requirePaid && strtolower((string) ($verified['status'] ?? '')) !== 'approved') {
            throw new InvalidArgumentException('Mercado Pago payment is not approved.');
        }
    }

    private function refundFromPayment(array $payment, string $paymentId, int $minor, int $decimals): ?array
    {
        $refunds = $payment['refunds'] ?? [];
        if (! is_array($refunds)) {
            return null;
        }

        foreach ($refunds as $refund) {
            if (! is_array($refund)) {
                continue;
            }

            $refundMinor = $this->toMinor((float) ($refund['amount'] ?? 0), $decimals);
            $refundPaymentId = (string) ($refund['payment_id'] ?? $payment['id'] ?? '');
            if ($refundPaymentId === $paymentId && $refundMinor === $minor && ! empty($refund['id'])) {
                return $refund;
            }
        }

        return null;
    }

    private function activeOrder(Order $order): ?array
    {
        $transaction = OrderPaymentTransaction::where('order_id', $order->id)
            ->where('payment_method', PaymentTypeEnum::MERCADO_PAGO())
            ->where('payment_status', PaymentStatusEnum::PENDING())
            ->latest('id')
            ->first();

        if (! $transaction) {
            return null;
        }

        $details = $transaction->payment_details ?? [];
        $expiresAt = $details['expires_at'] ?? null;
        $link = $details['checkout_url'] ?? null;
        if (! is_string($expiresAt) || ! is_string($link) || ! $this->isMercadoPagoUrl($link)) {
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
            'mercado_pago_order_id' => $details['mercado_pago_order_id'] ?? $transaction->transaction_id,
            'mercado_pago_preference_id' => $details['mercado_pago_preference_id'] ?? $transaction->transaction_id,
            'external_reference' => $details['external_reference'] ?? null,
            'link' => $link,
            'expires_at' => $expiresAt,
        ];
    }

    private function preferenceResponse(array $created, string $reference, CarbonImmutable $expiresAt, string $link): array
    {
        return [
            'mercado_pago_order_id' => $created['id'],
            'mercado_pago_preference_id' => $created['id'],
            'external_reference' => $reference,
            'link' => $link,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    private function preferenceLink(array $created): string
    {
        if (($this->settings()['mercadoPagoPaymentMode'] ?? 'test') === 'live') {
            return trim((string) ($created['init_point'] ?? ''));
        }

        return trim((string) ($created['sandbox_init_point'] ?? $created['init_point'] ?? ''));
    }

    private function walletFingerprint(string $preferenceId, string $currency, int $minor, string $reference): string
    {
        return implode(':', ['mpo', $preferenceId, strtoupper($currency), $minor, substr(hash('sha256', $reference), 0, 12)]);
    }

    private function assertWalletFingerprint(string $fingerprint, string $currency, int $minor, string $reference): void
    {
        $parts = explode(':', $fingerprint);
        if (count($parts) !== 5
            || $parts[0] !== 'mpo'
            || $parts[1] === ''
            || $parts[2] !== strtoupper($currency)
            || (int) $parts[3] !== $minor
            || ! hash_equals($parts[4], substr(hash('sha256', $reference), 0, 12))) {
            throw new InvalidArgumentException('Mercado Pago wallet payment verification failed.');
        }
    }

    private function entityId(string $reference, string $type): int
    {
        if (! preg_match('/^'.preg_quote($type, '/').'_(\d+)_/', $reference, $matches)) {
            throw new InvalidArgumentException('Invalid Mercado Pago external reference.');
        }

        return (int) $matches[1];
    }

    private function majorAmount(int $minor, int $decimals): string
    {
        return number_format($minor / (10 ** $decimals), $decimals, '.', '');
    }

    private function preferenceAmount(int $minor, int $decimals): float|int
    {
        $amount = $minor / (10 ** $decimals);

        return $decimals === 0 ? (int) $amount : (float) $this->majorAmount($minor, $decimals);
    }

    private function orderTransactionByReference(string $reference, bool $lock = false): ?OrderPaymentTransaction
    {
        $query = OrderPaymentTransaction::where('payment_method', PaymentTypeEnum::MERCADO_PAGO())
            ->where('payment_details->external_reference', $reference)
            ->latest('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function isMercadoPagoUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return ($parts['scheme'] ?? '') === 'https'
            && $host !== ''
            && (str_contains($host, '.mercadopago.') || str_starts_with($host, 'mercadopago.'))
            && empty($parts['user'])
            && empty($parts['pass']);
    }

    private function assertEnabled(): void
    {
        if (empty($this->settings()['mercadoPagoPayment'])) {
            throw new RuntimeException('Mercado Pago payment is not enabled.');
        }
    }

    private function settings(): array
    {
        return $this->settingService->getSettingValues(SettingTypeEnum::PAYMENT());
    }
}
