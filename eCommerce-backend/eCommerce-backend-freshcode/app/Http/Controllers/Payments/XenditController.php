<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\XenditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class XenditController extends Controller
{
    public function __construct(private readonly XenditService $xenditService) {}

    public function handleWebhook(Request $request): JsonResponse
    {
        if (! $this->xenditService->webhookTokenConfigured()) {
            Log::error('Xendit webhook rejected because the verification token is not configured.');

            return response()->json(['error' => 'Webhook is not configured'], 503);
        }

        if (! $this->xenditService->validWebhookToken($request->header('x-callback-token'))) {
            Log::warning('Xendit webhook rejected because the verification token did not match.');

            return response()->json(['error' => 'Invalid webhook token'], 401);
        }

        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            return response()->json(['error' => 'Invalid webhook payload'], 400);
        }

        try {
            $status = $this->xenditService->handleWebhook($payload);

            return response()->json(['status' => $status]);
        } catch (InvalidArgumentException $exception) {
            Log::warning('Xendit webhook verification failed.', ['error' => $exception->getMessage()]);

            return response()->json(['error' => 'Webhook verification failed'], 400);
        } catch (\Throwable $exception) {
            Log::error('Xendit webhook processing failed.', ['error' => $exception->getMessage()]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }
}
