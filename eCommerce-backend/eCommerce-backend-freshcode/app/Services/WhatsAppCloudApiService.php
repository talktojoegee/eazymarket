<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\WhatsAppMessageStatus;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class WhatsAppCloudApiService
{
    public function settings(): array
    {
        $value = Setting::query()->find('whatsapp')?->value;

        return is_array($value) ? $value : [];
    }

    public function fetchTemplateStatuses(?string $templateName = null): array
    {
        $settings = $this->settings();
        if (empty($settings['businessAccountId']) || empty($settings['accessToken'])) {
            throw new RuntimeException('Enter the WhatsApp Business Account ID and access token before syncing template status.');
        }

        $url = $this->graphBaseUrl($settings).'/'.rawurlencode((string) $settings['businessAccountId']).'/message_templates';
        $query = [
            'fields' => 'name,language,status,category,quality_score,rejected_reason,parameter_format,components',
            'limit' => 100,
        ];
        if (filled($templateName)) {
            $query['name'] = $templateName;
        }
        $templates = [];

        for ($page = 0; $page < 20; $page++) {
            $response = $this->http()
                ->withToken((string) $settings['accessToken'])
                ->get($url, $query);
            $data = $this->responseData($response);
            $templates = array_merge($templates, $data['data'] ?? []);
            $after = $data['paging']['cursors']['after'] ?? null;

            if (! is_string($after) || $after === '' || empty($data['paging']['next'])) {
                break;
            }

            $query['after'] = $after;
        }

        return $templates;
    }

    public function syncMappings(?string $templateKey, array $templates): array
    {
        $settings = $this->settings();
        $mappings = $settings['templateMappings'] ?? [];
        $keys = $templateKey === null ? array_keys($mappings) : [$templateKey];
        $syncedAt = now()->toIso8601String();

        foreach ($keys as $key) {
            if (! isset($mappings[$key]) || ! is_array($mappings[$key]) || empty($mappings[$key]['templateName'])) {
                continue;
            }

            $mapping = $mappings[$key];
            $template = collect($templates)->first(fn ($item) =>
                ($item['name'] ?? '') === ($mapping['templateName'] ?? '')
                && ($item['language'] ?? '') === ($mapping['language'] ?? '')
            );

            if ($template) {
                $mappings[$key]['status'] = strtoupper((string) ($template['status'] ?? 'DRAFT'));
                $mappings[$key]['quality'] = data_get($template, 'quality_score.score', $template['quality_score'] ?? 'UNKNOWN');
                $mappings[$key]['category'] = strtoupper((string) ($template['category'] ?? ''));
                $mappings[$key]['rejectedReason'] = (string) ($template['rejected_reason'] ?? '');
                $parameterMetadata = $this->templateParameterMetadata($template);
                $mappings[$key] = array_merge($mappings[$key], $parameterMetadata);
                $mappingError = $this->mappingErrorForMeta($mappings[$key]);
                $mappings[$key]['mappingError'] = $mappingError;
                if ($mappingError !== null) {
                    $mappings[$key]['active'] = false;
                } else {
                    $mappings[$key]['active'] = strtoupper((string) ($template['status'] ?? 'DRAFT')) === 'APPROVED';
                }
            } else {
                $mappings[$key]['status'] = 'DRAFT';
                $mappings[$key]['quality'] = 'UNKNOWN';
                $mappings[$key]['category'] = '';
                $mappings[$key]['rejectedReason'] = 'Matching Meta template was not found for the saved name and language.';
                $mappings[$key]['metaParametersVerified'] = false;
                $mappings[$key]['metaParameters'] = [];
                $mappings[$key]['metaFormat'] = null;
                $mappings[$key]['unsupportedComponents'] = [];
                $mappings[$key]['metaHeaderFormat'] = null;
                $mappings[$key]['mappingError'] = 'Sync could not find this template in Meta. Check its exact name and language.';
                $mappings[$key]['active'] = false;
            }

            $mappings[$key]['syncedAt'] = $syncedAt;
        }

        $settings['templateMappings'] = $mappings;
        app(SettingService::class)->saveSetting('whatsapp', $settings);

        return $mappings;
    }

    public function sendConfiguredTemplate(string $templateKey, string $recipient, array $fields, ?string $headerImageUrl = null): array
    {
        $settings = $this->settings();
        $mapping = $settings['templateMappings'][$templateKey] ?? null;

        if (empty($settings['enabled']) || ! is_array($mapping) || empty($mapping['active'])
            || ($mapping['status'] ?? '') !== 'APPROVED' || empty($mapping['templateName'])) {
            throw new RuntimeException('Choose an active, approved template mapping before sending a test message.');
        }

        if (empty($mapping['metaParametersVerified']) || ! is_array($mapping['metaParameters'] ?? null) || ! is_string($mapping['metaFormat'] ?? null)) {
            throw new RuntimeException('Sync this template with Meta before sending so the app can verify its required variables.');
        }

        if (! empty($mapping['unsupportedComponents'])) {
            throw new RuntimeException('This template has variables outside its body that the app cannot currently fill: '.implode(', ', $mapping['unsupportedComponents']).'.');
        }

        $parameters = [];
        $variables = $mapping['variables'] ?? [];
        $expectedKeys = array_map('strval', $mapping['metaParameters']);
        $variableKeys = array_keys($variables);
        if (($mapping['format'] ?? 'positional') === 'positional') {
            usort($variableKeys, fn ($left, $right) => (int) $left <=> (int) $right);
            usort($expectedKeys, fn ($left, $right) => (int) $left <=> (int) $right);
        } else {
            sort($variableKeys);
            sort($expectedKeys);
        }

        if (($mapping['format'] ?? 'positional') !== $mapping['metaFormat'] || array_map('strval', $variableKeys) !== $expectedKeys) {
            $expected = $expectedKeys === [] ? 'no variables' : implode(', ', array_map(fn ($key) => '{{'.$key.'}}', $expectedKeys));
            throw new RuntimeException('The mapping does not match the Meta template. Its body expects '.$expected.'. Update the mapping and save it before sending.');
        }

        foreach ($variableKeys as $variable) {
            $field = $variables[(string) $variable] ?? null;
            if (! is_string($field) || $field === '' || ! array_key_exists($field, $fields)) {
                throw new RuntimeException('Complete the variable mapping for this template before sending a test message.');
            }

            $key = ($mapping['format'] ?? 'positional') === 'named' ? (string) $variable : count($parameters);
            $parameters[$key] = (string) $fields[$field];
        }

        $headerFormat = strtoupper((string) ($mapping['metaHeaderFormat'] ?? ''));
        if ($headerFormat === 'IMAGE' && ! $this->isPublicHttpsUrl($headerImageUrl)) {
            throw new RuntimeException('This Meta template has an image header, but no publicly accessible HTTPS product image is available.');
        }

        $response = $this->sendTemplate(
            $settings,
            $recipient,
            (string) $mapping['templateName'],
            (string) ($mapping['language'] ?? $settings['defaultLanguage'] ?? 'en_US'),
            $parameters,
            (string) ($mapping['format'] ?? 'positional'),
            $headerFormat === 'IMAGE' ? $headerImageUrl : null,
        );

        $messageId = (string) data_get($response, 'messages.0.id', '');
        if ($messageId !== '') {
            $this->recordAcceptedMessage($messageId, $recipient, (string) ($mapping['event'] ?? ''), (string) $mapping['templateName']);
        }

        return $response;
    }

    public function recordAcceptedMessage(string $messageId, string $recipient, string $module, string $templateName): void
    {
        $record = WhatsAppMessageStatus::query()->firstOrCreate(
            ['message_id' => $messageId],
            ['status' => 'accepted']
        );

        $record->fill([
            'recipient_id' => preg_replace('/\D+/', '', $recipient) ?? '',
            'module' => $module,
            'template_name' => $templateName,
        ])->save();
    }

    public function recordWebhookStatus(array $status): void
    {
        $messageId = (string) ($status['id'] ?? '');
        $currentStatus = strtolower((string) ($status['status'] ?? ''));
        if ($messageId === '' || ! in_array($currentStatus, ['sent', 'delivered', 'read', 'failed'], true)) {
            return;
        }

        $timestamp = filter_var($status['timestamp'] ?? null, FILTER_VALIDATE_INT);
        $statusAt = $timestamp !== false && $timestamp !== null
            ? Carbon::createFromTimestampUTC((int) $timestamp)
            : now();
        $error = data_get($status, 'errors.0', []);
        $errorDetails = data_get($error, 'error_data.details')
            ?? data_get($error, 'message')
            ?? data_get($error, 'title');

        WhatsAppMessageStatus::query()->firstOrCreate(
            ['message_id' => $messageId],
            ['status' => 'accepted']
        );

        DB::transaction(function () use ($messageId, $currentStatus, $statusAt, $status, $error, $errorDetails) {
            $record = WhatsAppMessageStatus::query()
                ->where('message_id', $messageId)
                ->lockForUpdate()
                ->first();

            if (! $record) {
                return;
            }

            $sameTimestamp = $record->status_at && $statusAt->equalTo($record->status_at);
            $isNewer = ! $record->status_at || $statusAt->greaterThan($record->status_at);
            $isLaterStage = $sameTimestamp
                && $this->statusRank($currentStatus) >= $this->statusRank((string) $record->status);

            if (! $isNewer && ! $isLaterStage) {
                return;
            }

            $record->fill([
                'recipient_id' => (string) ($status['recipient_id'] ?? $record->recipient_id ?? ''),
                'status' => $currentStatus,
                'status_at' => $statusAt,
                'error_code' => isset($error['code']) ? (string) $error['code'] : null,
                'error_details' => is_string($errorDetails) ? Str::limit($errorDetails, 2000, '') : null,
            ])->save();
        });
    }

    private function statusRank(string $status): int
    {
        return match ($status) {
            'accepted' => 0,
            'sent' => 1,
            'delivered' => 2,
            'read' => 3,
            'failed' => 4,
            default => -1,
        };
    }

    private function templateParameterMetadata(array $template): array
    {
        $components = $template['components'] ?? null;
        $bodyParameters = [];
        $unsupportedComponents = [];
        $headerFormat = null;

        if (is_array($components)) {
            foreach ($components as $component) {
                if (! is_array($component)) {
                    continue;
                }

                $type = strtoupper((string) ($component['type'] ?? ''));
                if ($type === 'HEADER') {
                    $headerFormat = strtoupper((string) ($component['format'] ?? '')) ?: null;
                }
                $parameters = $this->extractTemplateParameters($component);
                if ($type === 'BODY') {
                    $bodyParameters = array_merge($bodyParameters, $parameters);
                } elseif ($parameters !== []) {
                    $unsupportedComponents[] = $type !== '' ? $type : 'OTHER';
                }
            }
        }

        $bodyParameters = array_values(array_unique($bodyParameters));
        $metaFormat = strtolower((string) ($template['parameter_format'] ?? ''));
        if (! in_array($metaFormat, ['named', 'positional'], true)) {
            $metaFormat = $bodyParameters !== [] && ! ctype_digit((string) $bodyParameters[0]) ? 'named' : 'positional';
        }

        return [
            'metaParametersVerified' => is_array($components),
            'metaParameters' => $bodyParameters,
            'metaFormat' => $metaFormat,
            'unsupportedComponents' => array_values(array_unique($unsupportedComponents)),
            'metaHeaderFormat' => $headerFormat,
        ];
    }

    private function mappingErrorForMeta(array $mapping): ?string
    {
        if (empty($mapping['metaParametersVerified'])) {
            return 'Meta did not return template components, so its variables could not be verified.';
        }

        if (! empty($mapping['unsupportedComponents'])) {
            return 'This template has variables outside its body, which this app cannot currently fill.';
        }

        $expectedParameters = array_map('strval', $mapping['metaParameters'] ?? []);
        $actualParameters = array_map('strval', array_keys($mapping['variables'] ?? []));
        if (($mapping['format'] ?? 'positional') === 'positional') {
            usort($expectedParameters, fn ($left, $right) => (int) $left <=> (int) $right);
            usort($actualParameters, fn ($left, $right) => (int) $left <=> (int) $right);
        } else {
            sort($expectedParameters);
            sort($actualParameters);
        }

        if (($mapping['format'] ?? 'positional') !== ($mapping['metaFormat'] ?? null) || $actualParameters !== $expectedParameters) {
            $expected = $expectedParameters === [] ? 'no variables' : implode(', ', array_map(fn ($parameter) => '{{'.$parameter.'}}', $expectedParameters));

            return 'Mapping does not match Meta. The body expects '.$expected.'.';
        }

        return null;
    }

    private function extractTemplateParameters(mixed $value): array
    {
        if (is_string($value)) {
            preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $value, $matches);

            return $matches[1] ?? [];
        }

        if (! is_array($value)) {
            return [];
        }

        $parameters = [];
        foreach ($value as $child) {
            $parameters = array_merge($parameters, $this->extractTemplateParameters($child));
        }

        return $parameters;
    }

    private function sendTemplate(array $settings, string $recipient, string $templateName, string $language, array $parameters, string $format, ?string $headerImageUrl = null): array
    {
        if (empty($settings['phoneNumberId']) || empty($settings['accessToken'])) {
            throw new RuntimeException('Enter the WhatsApp Phone Number ID and access token before sending messages.');
        }

        $recipient = preg_replace('/\D+/', '', $recipient) ?? '';
        if ($recipient === '') {
            throw new RuntimeException('Enter the recipient mobile number with its country code.');
        }

        $components = [];
        if ($headerImageUrl !== null) {
            $components[] = [
                'type' => 'header',
                'parameters' => [[
                    'type' => 'image',
                    'image' => ['link' => $headerImageUrl],
                ]],
            ];
        }
        if ($parameters !== []) {
            $bodyParameters = [];
            foreach ($parameters as $key => $value) {
                $parameter = ['type' => 'text', 'text' => (string) $value];
                if ($format === 'named') {
                    $parameter['parameter_name'] = (string) $key;
                }
                $bodyParameters[] = $parameter;
            }
            $components[] = ['type' => 'body', 'parameters' => $bodyParameters];
        }

        $response = $this->http()
            ->withToken((string) $settings['accessToken'])
            ->post($this->graphBaseUrl($settings).'/'.rawurlencode((string) $settings['phoneNumberId']).'/messages', [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipient,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $language],
                    'components' => $components,
                ],
            ]);

        return $this->responseData($response);
    }

    private function isPublicHttpsUrl(?string $url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && ! in_array(strtolower((string) ($parts['host'] ?? '')), ['localhost', '127.0.0.1', '::1'], true);
    }

    private function graphBaseUrl(array $settings): string
    {
        $version = preg_match('/^v[0-9]{1,3}\.[0-9]+$/', (string) ($settings['graphApiVersion'] ?? ''))
            ? $settings['graphApiVersion']
            : 'v26.0';

        return 'https://graph.facebook.com/'.$version;
    }

    private function http(): PendingRequest
    {
        $request = Http::acceptJson()->timeout(25);
        $caBundle = config('services.whatsapp.ca_bundle');

        if (filled($caBundle)) {
            if (! is_file($caBundle) || ! is_readable($caBundle)) {
                throw new RuntimeException('The configured WhatsApp CA bundle is missing or unreadable. Check WHATSAPP_CA_BUNDLE.');
            }
            $request = $request->withOptions(['verify' => $caBundle]);
        }

        return $request;
    }

    private function responseData(Response $response): array
    {
        $data = $response->json();
        if (! $response->successful()) {
            throw new RuntimeException((string) data_get($data, 'error.message', 'WhatsApp Cloud API request failed.'), $response->status());
        }

        return is_array($data) ? $data : [];
    }
}
