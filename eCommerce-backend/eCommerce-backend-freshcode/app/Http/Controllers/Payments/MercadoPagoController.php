<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MercadoPagoController extends Controller
{
    public function __construct(private readonly MercadoPagoService $mercadoPagoService) {}

    public function handleWebhook(Request $request): JsonResponse
    {
        if (! $this->mercadoPagoService->webhookSecretConfigured()) {
            Log::error('Mercado Pago webhook rejected because the secret is not configured.');

            return response()->json(['error' => 'Webhook is not configured'], 503);
        }

        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            return response()->json(['error' => 'Invalid webhook payload'], 400);
        }

        $dataId = trim((string) ($request->query('data_id') ?? $request->query('data.id') ?? data_get($payload, 'data.id', '')));
        $requestId = trim((string) $request->header('x-request-id', ''));
        if (! $this->mercadoPagoService->validWebhookSignature($request->header('x-signature'), $requestId, $dataId)) {
            Log::warning('Mercado Pago webhook rejected because the signature did not match.');

            return response()->json(['error' => 'Invalid webhook signature'], 401);
        }

        if (! array_key_exists('live_mode', $payload)
            || ! $this->mercadoPagoService->acceptsWebhookEnvironment((bool) $payload['live_mode'])) {
            return response()->json(['error' => 'Webhook environment mismatch'], 400);
        }

        try {
            $status = $this->mercadoPagoService->handleWebhook($payload, $dataId, $requestId);

            return response()->json(['status' => $status]);
        } catch (InvalidArgumentException $exception) {
            Log::warning('Mercado Pago webhook verification failed.', ['error' => $exception->getMessage()]);

            return response()->json(['error' => 'Webhook verification failed'], 400);
        } catch (\Throwable $exception) {
            Log::error('Mercado Pago webhook processing failed.', ['error' => $exception->getMessage()]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }
}
