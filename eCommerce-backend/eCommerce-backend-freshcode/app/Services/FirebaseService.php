<?php

namespace App\Services;

use App\Models\UserFcmToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
    protected ?Auth $auth = null;

    protected ?Messaging $messaging = null;

    protected ?array $credentials = null;

    protected ?Factory $factory = null;

    public static function parseServiceAccount(string $contents): array
    {
        $credentials = json_decode($contents, true);

        if (! is_array($credentials) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('The Firebase service account file must contain valid JSON.');
        }

        foreach (['type', 'project_id', 'client_email', 'private_key'] as $key) {
            if (empty($credentials[$key])) {
                throw new \InvalidArgumentException("The Firebase service account file is missing the {$key} field.");
            }
        }

        if ($credentials['type'] !== 'service_account') {
            throw new \InvalidArgumentException('The Firebase credential type must be service_account.');
        }

        if (filter_var($credentials['client_email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('The Firebase service account client_email is invalid.');
        }

        if (openssl_pkey_get_private($credentials['private_key']) === false) {
            throw new \InvalidArgumentException('The Firebase service account private_key is invalid.');
        }

        return $credentials;
    }

    public function __construct() {}

    public static function serviceAccountPath(): string
    {
        return storage_path('app/private/settings/service-account-file.json');
    }

    public function getAuth(): Auth
    {
        if ($this->auth === null) {
            $this->auth = $this->firebaseFactory()->createAuth();
        }

        return $this->auth;
    }

    public function getMessaging(): Messaging
    {
        if ($this->messaging === null) {
            $this->messaging = $this->firebaseFactory()->createMessaging();
        }

        return $this->messaging;
    }

    public function credentialMetadata(): array
    {
        try {
            $credentials = $this->serviceAccountCredentials();

            return [
                'configured' => true,
                'project_id' => (string) $credentials['project_id'],
                'client_email' => $this->maskEmail((string) $credentials['client_email']),
                'updated_at' => date(DATE_ATOM, (int) filemtime(self::serviceAccountPath())),
            ];
        } catch (\Throwable) {
            return [
                'configured' => false,
                'project_id' => '',
                'client_email' => '',
                'updated_at' => null,
            ];
        }
    }

    public function configurationStatus(array $authentication, array $notification = []): array
    {
        $required = [
            'fireBaseApiKey',
            'fireBaseAuthDomain',
            'fireBaseProjectId',
            'fireBaseMessagingSenderId',
            'fireBaseAppId',
        ];
        $missing = array_values(array_filter($required, fn (string $field) => trim((string) ($authentication[$field] ?? '')) === ''));
        $credentials = $this->credentialMetadata();
        $projectId = trim((string) ($authentication['fireBaseProjectId'] ?? ''));
        $projectMismatch = $credentials['configured']
            && $projectId !== ''
            && $credentials['project_id'] !== $projectId;
        $clientReady = $missing === [];
        $serverReady = $credentials['configured'] && ! $projectMismatch;
        $vapidReady = trim((string) ($notification['vapIdKey'] ?? '')) !== '';

        return [
            'client_ready' => $clientReady,
            'server_ready' => $serverReady,
            'project_mismatch' => $projectMismatch,
            'missing_fields' => $missing,
            'credentials' => $credentials,
            'phone_ready' => $clientReady && $serverReady,
            'social_ready' => $clientReady && $serverReady,
            'push_ready' => $clientReady && $serverReady && $vapidReady,
        ];
    }

    public function diagnose(?string $serviceAccountContents, string $expectedProjectId): array
    {
        $credentials = $serviceAccountContents !== null
            ? self::parseServiceAccount($serviceAccountContents)
            : $this->serviceAccountCredentials();

        if ($expectedProjectId !== '' && $credentials['project_id'] !== $expectedProjectId) {
            throw new \InvalidArgumentException(__('labels.firebase_project_mismatch'));
        }

        $auth = (new Factory)->withServiceAccount($credentials)->createAuth();
        foreach ($auth->listUsers(1) as $unused) {
            break;
        }

        return [
            'project_id' => (string) $credentials['project_id'],
            'client_email' => $this->maskEmail((string) $credentials['client_email']),
        ];
    }

    public function verifyIdentityToken(string $idToken, string $expectedProvider, ?string $serviceAccountContents = null): array
    {
        $auth = $serviceAccountContents === null
            ? $this->getAuth()
            : (new Factory)->withServiceAccount(self::parseServiceAccount($serviceAccountContents))->createAuth();
        $token = $auth->verifyIdToken($idToken);
        $claims = $token->claims();
        $firebase = (array) $claims->get('firebase', []);
        $provider = (string) ($firebase['sign_in_provider'] ?? '');
        $providerMap = [
            'phone' => 'phone',
            'google' => 'google.com',
            'apple' => 'apple.com',
        ];

        if (($providerMap[$expectedProvider] ?? null) !== $provider) {
            throw new \InvalidArgumentException(__('labels.firebase_test_provider_mismatch'));
        }

        $identity = (string) ($claims->get('email', '') ?: $claims->get('phone_number', ''));

        return [
            'provider' => $expectedProvider,
            'uid' => substr((string) $claims->get('sub'), 0, 6).'…',
            'identity' => $this->maskIdentity($identity),
        ];
    }

    protected function firebaseFactory(): Factory
    {
        if ($this->factory === null) {
            $this->factory = (new Factory)->withServiceAccount($this->serviceAccountCredentials());
        }

        return $this->factory;
    }

    protected function serviceAccountCredentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = self::serviceAccountPath();
        if (! file_exists($path)) {
            throw new \RuntimeException(__('labels.firebase_service_account_missing'));
        }

        return $this->credentials = self::parseServiceAccount((string) file_get_contents($path));
    }

    protected function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($name, 0, 2).'***@'.$domain;
    }

    protected function maskIdentity(string $identity): string
    {
        if (str_contains($identity, '@')) {
            return $this->maskEmail($identity);
        }

        return $identity === '' ? '' : str_repeat('*', max(0, strlen($identity) - 4)).substr($identity, -4);
    }

    /**
     * Send single notification
     */
    public function sendNotification(
        $token,
        $title,
        $body,
        $image = '',
        $data = []
    ): array {
        try {
            $messaging = $this->getMessaging();
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        try {
            $messageData = $this->normalizeData(array_merge([
                'title' => (string) $title,
                'body' => (string) $body,
                'image' => $image ? (string) $image : '',
            ], $data));

            $notification = Notification::create(
                title: $title,
                body: $body,
                imageUrl: $image
            );

            $message = CloudMessage::new()
                ->withData($messageData)
                ->withNotification($notification)
                ->withAndroidConfig($this->buildAndroidConfig($image))
                ->withApnsConfig($this->buildApnsConfig($image))
                ->toToken($token);

            $messaging->send($message);

            return ['success' => true];

        } catch (\Throwable $e) {
            Log::error('Firebase sendNotification failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send bulk notifications
     */
    public function sendBulkNotification(
        array $tokens,
        string $title,
        string $body,
        ?string $image = null,
        array $data = [],
        int $chunkSize = 50
    ): array {
        try {
            $messaging = $this->getMessaging();
        } catch (\Throwable $e) {
            Log::warning('Firebase bulk send skipped: not configured');

            return [
                'success' => 0,
                'failure' => count($tokens),
                'error' => $e->getMessage(),
            ];
        }

        $results = [
            'success' => 0,
            'failure' => 0,
            'removed_tokens' => [],
        ];

        $androidConfig = $this->buildAndroidConfig($image);
        $apnsConfig = $this->buildApnsConfig($image);
        $messageData = $this->normalizeData(array_merge([
            'title' => $title,
            'body' => $body,
            'image' => $image ?? '',
        ], $data));

        Collection::make($tokens)->chunk($chunkSize)->each(function ($chunk) use (&$results, $title, $body, $image, $messageData, $androidConfig, $apnsConfig, $messaging) {
            try {
                $notification = Notification::create(
                    title: $title,
                    body: $body,
                    imageUrl: $image
                );

                $message = CloudMessage::new()
                    ->withData($messageData)
                    ->withNotification($notification)
                    ->withAndroidConfig($androidConfig)
                    ->withApnsConfig($apnsConfig);

                $multicastResult = $messaging->sendMulticast(
                    $message,
                    $chunk->toArray()
                );

                $results['success'] += $multicastResult->successes()->count();
                $results['failure'] += $multicastResult->failures()->count();

                $invalidTokens = $multicastResult->invalidTokens();

                if (! empty($invalidTokens)) {
                    UserFcmToken::whereIn('fcm_token', $invalidTokens)->delete();
                    $results['removed_tokens'] = array_merge(
                        $results['removed_tokens'],
                        $invalidTokens
                    );
                }

            } catch (\Throwable $e) {
                Log::error('Firebase bulk send chunk failed', [
                    'error' => $e->getMessage(),
                ]);

                $results['failure'] += $chunk->count();
            }
        });

        return $results;
    }

    /**
     * FCM's data payload must be a flat map of string => string. Any null,
     * bool, int, or nested value will trigger MessageData::isBinary() errors,
     * so coerce every value to a string here (nulls become empty strings).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    protected function normalizeData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                $normalized[(string) $key] = '';
            } elseif (is_bool($value)) {
                $normalized[(string) $key] = $value ? 'true' : 'false';
            } elseif (is_scalar($value)) {
                $normalized[(string) $key] = (string) $value;
            } else {
                $normalized[(string) $key] = json_encode($value) ?: '';
            }
        }

        return $normalized;
    }

    /**
     * Build APNS payload that ensures iOS displays the notification image.
     * iOS requires `mutable-content: 1` so the Notification Service Extension
     * can fetch the URL from `fcm_options.image` and attach it.
     */
    protected function buildApnsConfig(?string $image): ApnsConfig
    {
        $config = [
            'headers' => [
                'apns-priority' => '10',
            ],
            'payload' => [
                'aps' => [
                    'mutable-content' => 1,
                    'sound' => 'notification_sound.caf',
                ],
            ],
        ];

        if (! empty($image)) {
            $config['fcm_options'] = ['image' => $image];
        }

        return ApnsConfig::fromArray($config);
    }

    /**
     * Build Android payload that explicitly carries the notification image,
     * mirroring the APNS payload for consistency across platforms.
     */
    protected function buildAndroidConfig(?string $image): AndroidConfig
    {
        $config = [
            'priority' => 'high',
            'notification' => [
                'channel_id' => 'custom_sound_channel',
                'sound' => 'notification_sound',
            ],
        ];

        if (! empty($image)) {
            $config['notification']['image'] = $image;
        }

        return AndroidConfig::fromArray($config);
    }
}
