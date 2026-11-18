<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettingTypeEnum;
use App\Enums\WhatsAppTemplateEventEnum;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppMessageStatus;
use App\Services\SettingService;
use App\Services\WhatsAppCloudApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class WhatsAppSettingController extends Controller
{
    public function syncTemplates(Request $request, WhatsAppCloudApiService $whatsApp): JsonResponse
    {
        $validated = $request->validate([
            'templateKey' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $settings = $whatsApp->settings();
            $templateKey = $validated['templateKey'] ?? null;
            if ($templateKey !== null && ! WhatsAppTemplateEventEnum::tryFrom($templateKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Select a valid app event before syncing its Meta template status.',
                ], 422);
            }

            $mappings = $settings['templateMappings'] ?? [];
            $configured = array_filter($mappings, fn ($mapping) => is_array($mapping) && filled($mapping['templateName'] ?? null) && filled($mapping['language'] ?? null));
            if ($templateKey !== null && (empty($mappings[$templateKey]['templateName']) || empty($mappings[$templateKey]['language']))) {
                return response()->json(['success' => false, 'message' => 'Enter and save a Meta template name and language before syncing.'], 422);
            }

            if ($templateKey === null && $configured === []) {
                return response()->json([
                    'success' => true,
                    'message' => 'Enter and save at least one Meta template name and language before syncing.',
                    'data' => [],
                ]);
            }

            $templateName = $templateKey !== null
                ? (string) $mappings[$templateKey]['templateName']
                : null;
            $templateStatuses = $whatsApp->fetchTemplateStatuses($templateName);
            $mappings = $whatsApp->syncMappings($templateKey, $templateStatuses);
            $selected = $templateKey !== null ? ($mappings[$templateKey] ?? null) : $mappings;

            return response()->json([
                'success' => true,
                'message' => 'Template status synced from Meta.',
                'data' => $selected,
            ]);
        } catch (Throwable $exception) {
            Log::warning('WhatsApp template status sync failed', ['error' => $exception->getMessage()]);

            return response()->json(['success' => false, 'message' => $this->errorMessage($exception)], 422);
        }
    }

    public function storeTemplates(Request $request, SettingService $settingService): JsonResponse
    {
        $validated = $request->validate([
            'templateMappings' => ['required', 'array', 'max:'.count(WhatsAppTemplateEventEnum::cases())],
            'templateMappings.*.templateName' => ['nullable', 'string', 'max:512', 'regex:/^[a-z0-9_]*$/'],
            'templateMappings.*.language' => ['required', 'string', 'max:35', 'regex:/^[a-z]{2,3}(?:_[A-Z]{2,4})?$/'],
        ]);

        $eventValues = array_map(fn (WhatsAppTemplateEventEnum $event) => $event->value, WhatsAppTemplateEventEnum::cases());
        if (array_diff(array_keys($validated['templateMappings']), $eventValues) !== []) {
            throw ValidationException::withMessages(['templateMappings' => 'The template list contains an unknown app event.']);
        }

        $settings = $settingService->getSettingValues(SettingTypeEnum::WHATSAPP());
        $existing = $settings['templateMappings'] ?? [];
        $mappings = [];

        foreach (WhatsAppTemplateEventEnum::cases() as $event) {
            $key = $event->value;
            $previous = $existing[$key] ?? collect($existing)->first(fn ($candidate) => is_array($candidate) && ($candidate['event'] ?? '') === $key) ?? [];
            if (! array_key_exists($key, $validated['templateMappings'])) {
                if (is_array($previous) && $previous !== []) {
                    $mappings[$key] = $previous;
                }
                continue;
            }

            $mapping = $validated['templateMappings'][$key] ?? [];
            $variables = array_combine(
                array_map('strval', range(1, count($event->fields()))),
                $event->fields(),
            ) ?: [];
            $format = 'positional';
            $templateName = trim((string) ($mapping['templateName'] ?? ''));
            $language = trim((string) ($mapping['language'] ?? ''));

            $sameTemplate = ($previous['templateName'] ?? '') === $templateName && ($previous['language'] ?? '') === $language;
            $expectedParameters = array_map('strval', $previous['metaParameters'] ?? []);
            $actualParameters = array_map('strval', array_keys($variables));
            usort($expectedParameters, fn ($left, $right) => (int) $left <=> (int) $right);
            usort($actualParameters, fn ($left, $right) => (int) $left <=> (int) $right);
            $mappingError = $sameTemplate ? ($previous['mappingError'] ?? null) : null;
            if ($sameTemplate && ! empty($previous['metaParametersVerified'])) {
                $mappingError = ! empty($previous['unsupportedComponents'])
                    ? 'This template has variables outside its body, which this app cannot currently fill.'
                    : (($previous['metaFormat'] ?? null) !== 'positional' || $expectedParameters !== $actualParameters
                        ? 'Mapping does not match Meta. The body expects '.($expectedParameters === [] ? 'no variables' : implode(', ', array_map(fn ($parameter) => '{{'.$parameter.'}}', $expectedParameters))).'.'
                        : null);
            }
            $active = $sameTemplate
                && ($previous['status'] ?? '') === 'APPROVED'
                && ! empty($previous['metaParametersVerified'])
                && $mappingError === null;

            $mappings[$key] = [
                'key' => $key,
                'event' => $key,
                'templateName' => $templateName,
                'language' => $language,
                'format' => $format,
                'parameters' => array_map('strval', array_keys($variables)),
                'active' => $active,
                'variables' => $variables,
                'status' => $sameTemplate ? ($previous['status'] ?? 'UNKNOWN') : 'UNKNOWN',
                'quality' => $sameTemplate ? ($previous['quality'] ?? 'UNKNOWN') : 'UNKNOWN',
                'category' => $sameTemplate ? ($previous['category'] ?? '') : '',
                'rejectedReason' => $sameTemplate ? ($previous['rejectedReason'] ?? '') : '',
                'syncedAt' => $sameTemplate ? ($previous['syncedAt'] ?? null) : null,
                'metaParametersVerified' => $sameTemplate ? ($previous['metaParametersVerified'] ?? false) : false,
                'metaParameters' => $sameTemplate ? ($previous['metaParameters'] ?? []) : [],
                'metaFormat' => $sameTemplate ? ($previous['metaFormat'] ?? null) : null,
                'unsupportedComponents' => $sameTemplate ? ($previous['unsupportedComponents'] ?? []) : [],
                'metaHeaderFormat' => $sameTemplate ? ($previous['metaHeaderFormat'] ?? null) : null,
                'mappingError' => $mappingError,
            ];
        }

        $settings['templateMappings'] = $mappings;
        $settingService->saveSetting(SettingTypeEnum::WHATSAPP(), $settings);

        return response()->json(['success' => true, 'message' => 'WhatsApp template mappings saved.', 'data' => $mappings]);
    }

    public function testMessage(Request $request, WhatsAppCloudApiService $whatsApp): JsonResponse
    {
        $validated = $request->validate([
            'recipient' => ['required', 'string', 'max:30'],
            'templateKey' => ['required', 'string', 'max:191'],
        ]);

        try {
            $mapping = $whatsApp->settings()['templateMappings'][$validated['templateKey']] ?? null;
            $event = is_array($mapping) ? (string) ($mapping['event'] ?? '') : '';
            if (! isset($this->moduleFields()[$event])) {
                return response()->json(['success' => false, 'message' => 'Assign this template to an app event before testing it.'], 422);
            }

            $result = $whatsApp->sendConfiguredTemplate(
                $validated['templateKey'],
                $validated['recipient'],
                $this->sampleFieldsFor($event),
                asset('assets/images/product-placeholder.jpg'),
            );

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp template message accepted by Meta.',
                'message_id' => data_get($result, 'messages.0.id'),
            ]);
        } catch (Throwable $exception) {
            Log::warning('WhatsApp test message failed', ['error' => $exception->getMessage()]);

            return response()->json(['success' => false, 'message' => $this->errorMessage($exception)], 422);
        }
    }

    public function messageStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => ['required', 'string', 'max:191'],
        ]);

        $message = WhatsAppMessageStatus::query()
            ->where('message_id', $validated['message_id'])
            ->first();

        if (! $message) {
            return response()->json(['success' => false, 'message' => 'Message status is not available yet.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message_id' => $message->message_id,
                'status' => $message->status,
                'status_at' => $message->status_at?->toIso8601String(),
                'error_code' => $message->error_code,
                'error_details' => $message->error_details,
            ],
        ]);
    }

    private function moduleFields(): array
    {
        return WhatsAppTemplateEventEnum::fieldsByValue();
    }

    private function errorMessage(Throwable $exception): string
    {
        if (str_contains(strtolower($exception->getMessage()), 'curl error 60')) {
            return 'PHP could not verify Meta’s HTTPS certificate. Set WHATSAPP_CA_BUNDLE in .env to a readable CA bundle file, then run php artisan config:clear and restart the app server.';
        }

        return $exception->getMessage();
    }

    private function sampleFieldsFor(string $module): array
    {
        $samples = [
            'customer_name' => 'Alex Customer',
            'seller_name' => 'Alex Seller',
            'rider_name' => 'Alex Rider',
            'order_number' => '100123',
            'total_amount' => '125.00',
            'order_date' => now()->toDateString(),
            'carrier_name' => 'Example Courier',
            'tracking_number' => 'TRK123456',
            'tracking_url' => 'https://example.com/track/TRK123456',
            'invoice_url' => 'https://example.com/invoice/100123',
            'cart_total' => '125.00',
            'cart_url' => 'https://example.com/cart',
            'payment_method' => 'Cash on delivery',
            'order_status' => 'Processing',
            'current_cash_amount' => '750.00',
            'cash_limit' => '1000.00',
        ];

        $fields = WhatsAppTemplateEventEnum::tryFrom($module)?->fields() ?? [];
        $sampleFields = [];
        foreach ($fields as $field) {
            $sampleFields[$field] = $samples[$field] ?? 'Sample '.str_replace('_', ' ', $field);
        }

        return $sampleFields;
    }
}
