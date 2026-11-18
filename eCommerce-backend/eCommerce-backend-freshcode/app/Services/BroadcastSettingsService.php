<?php

namespace App\Services;

use App\Enums\SettingTypeEnum;
use App\Models\Setting;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Config;

class BroadcastSettingsService
{
    public function __construct(private readonly SettingService $settings) {}

    public function apply(): array
    {
        $settings = $this->settings->getSettingValues(SettingTypeEnum::BROADCAST_DRIVER());
        $driver = in_array($settings['broadcastDriver'] ?? null, ['reverb', 'pusher'], true)
            ? $settings['broadcastDriver']
            : 'reverb';

        $reverbPort = (int) ($settings['reverbPort'] ?? 443);
        $reverbScheme = ($settings['reverbScheme'] ?? 'https') === 'http' ? 'http' : 'https';
        $reverbHost = trim((string) ($settings['reverbHost'] ?? ''));
        $pusherCluster = trim((string) ($settings['pusherCluster'] ?? 'mt1')) ?: 'mt1';

        Config::set([
            'broadcasting.default' => $driver,
            'broadcasting.connections.reverb.key' => (string) ($settings['reverbKey'] ?? ''),
            'broadcasting.connections.reverb.secret' => (string) ($settings['reverbSecret'] ?? ''),
            'broadcasting.connections.reverb.app_id' => (string) ($settings['reverbAppId'] ?? ''),
            'broadcasting.connections.reverb.options.host' => $reverbHost,
            'broadcasting.connections.reverb.options.port' => $reverbPort,
            'broadcasting.connections.reverb.options.scheme' => $reverbScheme,
            'broadcasting.connections.reverb.options.useTLS' => $reverbScheme === 'https',
            'broadcasting.connections.pusher.key' => (string) ($settings['pusherKey'] ?? ''),
            'broadcasting.connections.pusher.secret' => (string) ($settings['pusherSecret'] ?? ''),
            'broadcasting.connections.pusher.app_id' => (string) ($settings['pusherAppId'] ?? ''),
            'broadcasting.connections.pusher.options.cluster' => $pusherCluster,
            'broadcasting.connections.pusher.options.host' => 'api-'.$pusherCluster.'.pusher.com',
            'broadcasting.connections.pusher.options.port' => 443,
            'broadcasting.connections.pusher.options.scheme' => 'https',
            'broadcasting.connections.pusher.options.encrypted' => true,
            'broadcasting.connections.pusher.options.useTLS' => true,
            'reverb.servers.reverb.host' => (string) ($settings['reverbServerHost'] ?? '0.0.0.0'),
            'reverb.servers.reverb.port' => (int) ($settings['reverbServerPort'] ?? 8080),
            'reverb.servers.reverb.hostname' => $reverbHost,
            'reverb.apps.apps.0.key' => (string) ($settings['reverbKey'] ?? ''),
            'reverb.apps.apps.0.secret' => (string) ($settings['reverbSecret'] ?? ''),
            'reverb.apps.apps.0.app_id' => (string) ($settings['reverbAppId'] ?? ''),
            'reverb.apps.apps.0.options.host' => $reverbHost,
            'reverb.apps.apps.0.options.port' => $reverbPort,
            'reverb.apps.apps.0.options.scheme' => $reverbScheme,
            'reverb.apps.apps.0.options.useTLS' => $reverbScheme === 'https',
            'reverb.apps.apps.0.allowed_origins' => $this->origins((string) ($settings['allowedOrigins'] ?? 'localhost')),
        ]);

        if (app()->resolved(BroadcastManager::class)) {
            app(BroadcastManager::class)->forgetDrivers();
            require base_path('routes/channels.php');
        }

        $public = [
            'driver' => $driver,
            'key' => $driver === 'pusher' ? (string) ($settings['pusherKey'] ?? '') : (string) ($settings['reverbKey'] ?? ''),
            'host' => $driver === 'pusher' ? null : $reverbHost,
            'port' => $driver === 'pusher' ? 443 : $reverbPort,
            'scheme' => $driver === 'pusher' ? 'https' : $reverbScheme,
            'cluster' => $pusherCluster,
            'auth_endpoint' => config('support.public_realtime.auth_endpoint'),
        ];

        Config::set('support.public_realtime', $public);

        return $public;
    }

    public function clientConfig(string $authEndpoint): array
    {
        $public = $this->isConfigured()
            ? $this->apply()
            : config('support.public_realtime', []);

        return [
            ...$public,
            'auth_endpoint' => $authEndpoint,
        ];
    }

    public function isConfigured(): bool
    {
        if (! Setting::where('variable', SettingTypeEnum::BROADCAST_DRIVER())->exists()) {
            return false;
        }

        $settings = $this->settings->getSettingValues(SettingTypeEnum::BROADCAST_DRIVER());
        $driver = $settings['broadcastDriver'] ?? null;
        $required = match ($driver) {
            'reverb' => ['allowedOrigins', 'reverbAppId', 'reverbKey', 'reverbSecret', 'reverbHost', 'reverbPort', 'reverbScheme', 'reverbServerHost', 'reverbServerPort'],
            'pusher' => ['pusherAppId', 'pusherKey', 'pusherSecret', 'pusherCluster'],
            default => [],
        };

        return $required !== [] && collect($required)->every(
            fn (string $key): bool => isset($settings[$key]) && trim((string) $settings[$key]) !== ''
        );
    }

    private function origins(string $origins): array
    {
        return array_values(array_filter(array_map(static function (string $origin): string {
            $origin = trim($origin);
            if ($origin === '*') {
                return $origin;
            }

            return parse_url(str_contains($origin, '://') ? $origin : 'https://'.$origin, PHP_URL_HOST) ?: $origin;
        }, preg_split('/[\s,]+/', $origins) ?: [])));
    }
}
