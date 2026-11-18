<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\SettingService;
use Carbon\CarbonInterface;

class SupportDateTime
{
    public static function storageTimezone(): string
    {
        $timezone = config('support.storage_timezone');
        if ($timezone === null) {
            $timezone = app(SettingService::class)->getSettingValues('support_timekeeping')['storage_timezone']
                ?? self::preserveStorageTimezone(config('app.timezone', 'UTC'));
            config(['support.storage_timezone' => $timezone]);
        }

        return $timezone;
    }

    public static function preserveStorageTimezone(mixed $timezone): string
    {
        $timezone = in_array($timezone, \DateTimeZone::listIdentifiers(), true) ? $timezone : 'UTC';
        $setting = Setting::firstOrCreate(
            ['variable' => 'support_timekeeping'],
            ['value' => json_encode(['storage_timezone' => $timezone])],
        );
        app(SettingService::class)->clearSettingCache('support_timekeeping');
        config(['support.storage_timezone' => $setting->value['storage_timezone']]);

        return $setting->value['storage_timezone'];
    }

    public static function forStorage(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->setTimezone(self::storageTimezone());
    }
}
