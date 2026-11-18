<?php

namespace App\Providers;

use App\Enums\SettingTypeEnum;
use App\Models\Setting;
use App\Support\DatabaseSchema;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class MailConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (!file_exists(storage_path('installed'))) {
            return;
        }
        if (!app(DatabaseSchema::class)->hasTable('settings')) {
            return;
        }

        $emailSettings = Setting::find(SettingTypeEnum::EMAIL())?->value;
        $hasSmtpSettings = is_array($emailSettings)
            && ! empty($emailSettings['smtpHost'])
            && ! empty($emailSettings['smtpPort'])
            && ! empty($emailSettings['smtpEmail']);

        if ($hasSmtpSettings) {
            $encryption = strtolower((string) ($emailSettings['smtpEncryption'] ?? 'tls'));

            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.transport', 'smtp');
            Config::set('mail.mailers.smtp.scheme', $encryption === 'ssl' ? 'smtps' : 'smtp');
            Config::set('mail.mailers.smtp.url', null);
            Config::set('mail.mailers.smtp.host', $emailSettings['smtpHost']);
            Config::set('mail.mailers.smtp.port', (int) $emailSettings['smtpPort']);
            Config::set('mail.mailers.smtp.username', $emailSettings['smtpEmail']);
            Config::set('mail.mailers.smtp.password', $emailSettings['smtpPassword'] ?? null);
            Config::set('mail.from.address', $emailSettings['smtpEmail']);
        }

        $systemSettings = Setting::find(SettingTypeEnum::SYSTEM())?->value;
        $fromName = $systemSettings['appName'] ?? config('mail.from.name', config('app.name'));

        Config::set('mail.from.name', $fromName);
    }
}
