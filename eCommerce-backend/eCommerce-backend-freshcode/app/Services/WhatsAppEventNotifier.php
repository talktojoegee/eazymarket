<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppEventNotifier
{
    public function send(User $customer, string $event, array $fields, ?string $headerImageUrl = null, ?string $recipient = null): void
    {
        $phone = $recipient ?: trim((string) ($customer->country_code ?? '').(string) ($customer->mobile ?? ''));
        if ($phone === '') {
            return;
        }

        try {
            app(WhatsAppCloudApiService::class)->sendConfiguredTemplate($event, $phone, $fields, $headerImageUrl);
        } catch (Throwable $exception) {
            Log::warning('WhatsApp event notification failed', [
                'event' => $event,
                'user_id' => $customer->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
