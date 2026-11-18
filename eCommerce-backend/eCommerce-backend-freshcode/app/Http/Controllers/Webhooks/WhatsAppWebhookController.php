<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppCloudApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, WhatsAppCloudApiService $whatsApp): Response
    {
        $settings = $whatsApp->settings();
        $expectedToken = (string) ($settings['webhookVerifyToken'] ?? '');
        $query = $request->query->all();
        $mode = (string) ($query['hub.mode'] ?? $query['hub_mode'] ?? '');
        $providedToken = (string) ($query['hub.verify_token'] ?? $query['hub_verify_token'] ?? '');

        if ($expectedToken === ''
            || $mode !== 'subscribe'
            || ! hash_equals($expectedToken, $providedToken)) {
            return response('', 403);
        }

        return response((string) ($query['hub.challenge'] ?? $query['hub_challenge'] ?? ''), 200)
            ->header('Content-Type', 'text/plain');
    }

    public function handle(Request $request, WhatsAppCloudApiService $whatsApp): JsonResponse
    {
        $settings = $whatsApp->settings();
        $appSecret = (string) ($settings['appSecret'] ?? '');
        $signature = (string) $request->header('X-Hub-Signature-256', '');

        if ($appSecret === '' || $signature === '') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);
        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->json()->all();
        if (($payload['object'] ?? '') !== 'whatsapp_business_account') {
            return response()->json(['success' => true]);
        }

        $expectedPhoneNumberId = (string) ($settings['phoneNumberId'] ?? '');
        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? '') !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? [];
                $phoneNumberId = (string) data_get($value, 'metadata.phone_number_id', '');
                if ($expectedPhoneNumberId !== '' && $phoneNumberId !== $expectedPhoneNumberId) {
                    continue;
                }

                foreach (($value['statuses'] ?? []) as $status) {
                    $whatsApp->recordWebhookStatus($status);
                }
            }
        }

        return response()->json(['success' => true]);
    }
}
