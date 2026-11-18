<?php

namespace App\Support;

use App\Enums\SettingTypeEnum;
use App\Services\SettingService;

/**
 * Thin reader for the seller-settlement system settings, so the settlement service
 * and the auto-settle cron read the same source of truth.
 *
 *  - autoSettleEnabled     bool  master switch for the settlements:auto-settle cron (default off)
 *  - settlementClearingDays int  buffer (days) after an entry is created before it matures (default 0)
 */
class SettlementSettings
{
    protected static function values(): array
    {
        return app(SettingService::class)->getSettingValues(SettingTypeEnum::SYSTEM());
    }

    public static function autoSettleEnabled(): bool
    {
        return (bool) (self::values()['autoSettleEnabled'] ?? false);
    }

    public static function clearingDays(): int
    {
        if (is_file(storage_path('dev'))) {
            return 0;
        }

        return max(0, (int) (self::values()['settlementClearingDays'] ?? 0));
    }
}
