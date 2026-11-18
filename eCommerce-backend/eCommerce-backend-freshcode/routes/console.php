<?php

use App\Enums\CommandRunStatusEnum;
use App\Enums\CommandRunTriggerEnum;
use App\Enums\GuardNameEnum;
use App\Enums\SettingTypeEnum;
use App\Models\CommandRunLog;
use App\Models\User;
use App\Notifications\ScheduledCommandFailed;
use App\Models\SellerSubscription;
use App\Enums\Subscription\SellerSubscriptionStatusEnum;
use App\Services\SettingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---------------------------------------------------------------------------
// Cron monitor: helper to wrap each scheduled command with logging
// ---------------------------------------------------------------------------
$loggedCommand = function (string $command) {
    return Schedule::command($command)
        ->withoutOverlapping(60)
        ->before(function () use ($command) {
            try {
                CommandRunLog::create([
                    'command' => $command,
                    'status' => CommandRunStatusEnum::RUNNING(),
                    'triggered_by' => CommandRunTriggerEnum::SCHEDULE(),
                    'started_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning("Cron monitor: could not log start for {$command}", ['error' => $e->getMessage()]);
            }
        })
        ->after(function () use ($command) {
            try {
                $log = CommandRunLog::where('command', $command)
                    ->where('status', CommandRunStatusEnum::RUNNING())
                    ->where('triggered_by', CommandRunTriggerEnum::SCHEDULE())
                    ->latest('started_at')
                    ->first();

                if ($log) {
                    $log->update([
                        'status' => CommandRunStatusEnum::SUCCESS(),
                        'finished_at' => now(),
                        'duration_ms' => $log->started_at
                            ? (int) now()->diffInMilliseconds($log->started_at)
                            : null,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("Cron monitor: could not log finish for {$command}", ['error' => $e->getMessage()]);
            }
        })
        ->onFailure(function () use ($command) {
            try {
                $log = CommandRunLog::where('command', $command)
                    ->where('status', CommandRunStatusEnum::RUNNING())
                    ->where('triggered_by', CommandRunTriggerEnum::SCHEDULE())
                    ->latest('started_at')
                    ->first();

                if ($log) {
                    $log->update([
                        'status' => CommandRunStatusEnum::FAILED(),
                        'finished_at' => now(),
                        'duration_ms' => $log->started_at
                            ? (int) now()->diffInMilliseconds($log->started_at)
                            : null,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("Cron monitor: could not log failure for {$command}", ['error' => $e->getMessage()]);
            }

            try {
                User::where('access_panel', GuardNameEnum::ADMIN)
                    ->get()
                    ->each(fn (User $admin) => $admin->notify(new ScheduledCommandFailed($command)));
            } catch (\Throwable $e) {
                Log::error("Cron monitor: could not alert admins for {$command}", ['error' => $e->getMessage()]);
            }
        });
};

// ---------------------------------------------------------------------------
// Scheduled commands (with logging)
// ---------------------------------------------------------------------------

// Process cashback — daily at 2 AM
$loggedCommand('cashback:process')->dailyAt('02:00');

// Settle referral earnings — daily at 3 AM
$loggedCommand('referral:settle')->dailyAt('03:00');

// Expire seller subscriptions — hourly
$loggedCommand('subscription:expire')->hourly();

// Prune ad event dedup rows older than 7 days — daily at 4 AM
$loggedCommand('ads:prune-dedup')->dailyAt('04:00');

$loggedCommand('expiredsharedlist')->dailyAt('04:00');

$loggedCommand('watch-buy:delete-expired-statuses')->hourly();

// Refresh FX rates from the configured provider (Frankfurter by default).
// ECB publishes ~16:00 CET on business days, so 17:00 UTC is a safe window.
$loggedCommand('fx:refresh-rates')->dailyAt('17:00');

// Auto-archive fully-resolved orders past their return window + grace — daily at 1:30 AM.
$loggedCommand('orders:auto-archive')->dailyAt('01:30');

// Auto-settle matured seller earnings into the wallet — daily at 2:30 AM, after
// auto-archive has created the day's settlement entries. The ->when() gate means the
// SCHEDULED run only fires when the admin has enabled auto-settle; manual runs (cron
// monitor / earnings screen) invoke the command directly and always settle.
$loggedCommand('settlements:auto-settle')
    ->dailyAt('02:30')
    ->when(fn () => \App\Support\SettlementSettings::autoSettleEnabled());

// Anonymise + release accounts soft-deleted beyond the restore window — daily at 1:30 AM.
$loggedCommand('accounts:purge-expired')->dailyAt('01:30');

// Register store locations still pending/failed with Shiprocket — daily at 5 AM, only when enabled.
$loggedCommand('shiprocket:register-pending')
    ->dailyAt('05:00')
    ->when(fn () => (bool) (app(\App\Services\SettingService::class)->shiprocketConfig()['shiprocketEnabled'] ?? false));

// Poll Shiprocket tracking for in-flight shipments (webhook fallback) — every 30 minutes, only when enabled.
$loggedCommand('shiprocket:poll-tracking')
    ->everyThirtyMinutes()
    ->when(fn () => (bool) (app(\App\Services\SettingService::class)->shiprocketConfig()['shiprocketEnabled'] ?? false));

// Expire abandoned unpaid online orders past their 30-minute TTL and release reserved stock — every ten minutes.
$loggedCommand('orders:expire-pending')->everyTenMinutes();

// Auto-refund orphan captures (payment completed but order cancelled/missing) — hourly.
$loggedCommand('payments:reconcile-orphans')->hourly();

// Rebuild the product search index so brand/category renames propagate — daily at 3:45 AM.
$loggedCommand('search:rebuild-products')->dailyAt('03:45');

$loggedCommand('exports:prune')->hourly();

$loggedCommand('carts:notify-abandoned')
    ->everyMinute()
    ->when(fn () => (bool) (app(SettingService::class)
        ->getSettingValues(SettingTypeEnum::NOTIFICATION())['abandonedCartEnabled'] ?? true));

$loggedCommand('bulk-uploads:run')->everyMinute()->withoutOverlapping();

$loggedCommand('bulk-uploads:prune')->dailyAt('02:30')->withoutOverlapping();

// ---------------------------------------------------------------------------
// Closure command: subscription:expire
// ---------------------------------------------------------------------------
Artisan::command('subscription:expire', function () {
    $now = now();
    $expiredCount = 0;
    $affectedSellerIds = [];

    SellerSubscription::where('status', SellerSubscriptionStatusEnum::ACTIVE())
        ->whereNotNull('end_date')
        ->where('end_date', '<=', $now)
        ->orderBy('id')
        ->chunkById(500, function ($subs) use (&$expiredCount, &$affectedSellerIds) {
            $ids = $subs->pluck('id')->all();
            $sellerIds = $subs->pluck('seller_id')->unique()->values()->all();

            if (!empty($ids)) {
                SellerSubscription::whereIn('id', $ids)
                    ->update(['status' => SellerSubscriptionStatusEnum::EXPIRED()]);
                $expiredCount += count($ids);
                $affectedSellerIds = array_values(array_unique(array_merge($affectedSellerIds, $sellerIds)));
            }
        });

    foreach ($affectedSellerIds as $sellerId) {
        Cache::forget(SellerSubscription::cacheKeyForCurrent((int) $sellerId));
    }
    Log::info("Expired {$expiredCount} subscription(s) as of {$now->toDateTimeString()}.");
    $this->info("Expired {$expiredCount} subscription(s) as of {$now->toDateTimeString()}.");
})->purpose('Expire subscriptions when their timeline is over (end_date passed)');
