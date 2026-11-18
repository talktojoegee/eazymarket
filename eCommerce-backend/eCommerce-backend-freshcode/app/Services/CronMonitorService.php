<?php

namespace App\Services;

use App\Enums\CommandRunStatusEnum;
use App\Enums\CommandRunTriggerEnum;
use App\Models\CommandRunLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CronMonitorService
{
    /**
     * Registry of all commands that should be monitored.
     */
    public function getRegisteredCommands(): array
    {
        return [
            [
                'command' => 'carts:notify-abandoned',
                'name' => __('labels.cron_abandoned_cart_reminders_name'),
                'description' => 'labels.cron_abandoned_cart_reminders_desc',
                'frequency' => 'labels.every_minute',
                'type' => 'scheduled',
            ],
            [
                'command' => 'cashback:process',
                'name' => 'Process Cashback',
                'description' => 'labels.cron_cashback_desc',
                'frequency' => 'labels.daily_at_2am',
                'type' => 'scheduled',
            ],
            [
                'command' => 'referral:settle',
                'name' => 'Settle Referrals',
                'description' => 'labels.cron_referral_desc',
                'frequency' => 'labels.daily_at_3am',
                'type' => 'scheduled',
            ],
            [
                'command' => 'orders:expire-pending',
                'name' => 'Expire Pending Orders',
                'description' => 'labels.cron_expire_pending_orders_desc',
                'frequency' => 'labels.every_10_minutes',
                'type' => 'scheduled',
            ],
            [
                'command' => 'payments:reconcile-orphans',
                'name' => 'Reconcile Orphan Payments',
                'description' => 'labels.cron_reconcile_orphans_desc',
                'frequency' => 'labels.hourly',
                'type' => 'scheduled',
            ],
            [
                'command' => 'watch-buy:delete-expired-statuses',
                'name' => __('labels.cron_watch_buy_status_cleanup'),
                'description' => 'labels.cron_watch_buy_status_cleanup_desc',
                'frequency' => 'labels.hourly',
                'type' => 'scheduled',
            ],
            [
                'command' => 'expiredsharedlist',
                'name' => __('labels.cron_shared_lists_cleanup'),
                'description' => 'labels.cron_shared_lists_cleanup_desc',
                'frequency' => 'labels.daily_at_4am',
                'type' => 'scheduled',
            ],
//            [
//                'command' => 'subscription:expire',
//                'name' => 'Expire Subscriptions',
//                'description' => 'labels.cron_subscription_expire_desc',
//                'frequency' => 'labels.hourly',
//                'type' => 'scheduled',
//            ],
//            [
//                'command' => 'ads:prune-dedup',
//                'name' => 'Prune Ad Dedup',
//                'description' => 'labels.cron_ads_prune_desc',
//                'frequency' => 'labels.daily_at_4am',
//                'type' => 'scheduled',
//            ],
            [
                'command' => 'fx:refresh-rates',
                'name' => 'Refresh FX Rates',
                'description' => 'labels.cron_fx_refresh_rates_desc',
                'frequency' => 'labels.daily_at_5pm',
                'type' => 'scheduled',
            ],
            [
                'command' => 'orders:auto-archive',
                'name' => 'Auto Archive Orders',
                'description' => 'labels.cron_auto_archive_desc',
                'frequency' => 'labels.daily_at_130pm',
                'type' => 'scheduled',
            ],
            [
                'command' => 'accounts:purge-expired',
                'name' => 'Purge Expired Accounts',
                'description' => 'labels.cron_purge_expired_desc',
                'frequency' => 'labels.daily_at_130pm',
                'type' => 'scheduled',
            ],
            [
                'command' => 'settlements:auto-settle',
                'name' => 'Auto-settle Seller Earnings',
                'description' => 'labels.cron_auto_settle_desc',
                'frequency' => 'labels.daily_at_230am',
                'type' => 'scheduled',
            ],
            [
                'command' => 'shiprocket:register-pending',
                'name' => 'Register Pending Shiprocket Pickups',
                'description' => 'labels.cron_shiprocket_register_desc',
                'frequency' => 'labels.daily_at_5am',
                'type' => 'scheduled',
            ],
            [
                'command' => 'shiprocket:poll-tracking',
                'name' => 'Poll Shiprocket Tracking',
                'description' => 'labels.cron_shiprocket_poll_desc',
                'frequency' => 'labels.every_30_minutes',
                'type' => 'scheduled',
            ],
            [
                'command' => 'exports:prune',
                'name' => 'Prune Data Exports',
                'description' => 'labels.cron_exports_prune_desc',
                'frequency' => 'labels.hourly',
                'type' => 'scheduled',
            ],
            [
                'command' => 'bulk-uploads:run',
                'name' => 'Process Bulk Uploads',
                'description' => 'labels.cron_bulk_uploads_desc',
                'frequency' => 'labels.every_minute',
                'type' => 'scheduled',
            ],
            [
                'command' => 'bulk-uploads:prune',
                'name' => 'Prune Bulk Uploads',
                'description' => 'labels.cron_bulk_uploads_prune_desc',
                'frequency' => 'labels.daily_at_0230',
                'type' => 'scheduled',
            ],
        ];
    }

    /**
     * Build status overview for every registered command.
     */
    public const STUCK_THRESHOLD_MINUTES = 60;

    /**
     * Fail run logs left RUNNING by a worker that died mid-run so the next
     * trigger is not blocked by a row nothing will ever close.
     */
    public function reconcileStuckRuns(): int
    {
        return CommandRunLog::where('status', CommandRunStatusEnum::RUNNING())
            ->where('started_at', '<', now()->subMinutes(self::STUCK_THRESHOLD_MINUTES))
            ->update([
                'status' => CommandRunStatusEnum::FAILED(),
                'finished_at' => now(),
                'output' => 'Marked failed: no completion recorded within '
                    . self::STUCK_THRESHOLD_MINUTES . ' minutes.',
            ]);
    }

    public function getCommandStatuses(): array
    {
        $this->reconcileStuckRuns();

        $commands = $this->getRegisteredCommands();
        $result = [];

        $commandNames = array_column($commands, 'command');

        $runsByCommand = CommandRunLog::query()
            ->whereIn('command', $commandNames)
            ->orderByDesc('started_at')
            ->get()
            ->groupBy('command');

        foreach ($commands as $cmd) {
            $runs = $runsByCommand->get($cmd['command']) ?? collect();
            $lastRun = $runs->first();
            $recentRuns = $runs->take(5)->values();

            $result[] = array_merge($cmd, [
                'last_run' => $lastRun,
                'recent_runs' => $recentRuns,
                'is_configured' => $lastRun !== null,
                'last_status' => $lastRun?->status?->value ?? 'never_run',
            ]);
        }

        return $result;
    }

    /**
     * Run a command synchronously and log the result.
     */
    public function runCommand(string $command): CommandRunLog
    {
        $log = $this->queueCommand($command);

        $this->executeLoggedRun($command, $log->id);

        return $log->refresh();
    }

    /**
     * Validate the command, open its run log and hand the work to the queue so
     * the HTTP request returns immediately.
     */
    public function queueCommand(string $command): CommandRunLog
    {
        $allowed = collect($this->getRegisteredCommands())->pluck('command')->all();

        if (!in_array($command, $allowed)) {
            throw new \InvalidArgumentException("Command [{$command}] is not in the monitored list.");
        }

        if ($this->isRunning($command)) {
            throw new \RuntimeException(__('labels.command_already_running'));
        }

        return CommandRunLog::create([
            'command' => $command,
            'status' => CommandRunStatusEnum::RUNNING(),
            'triggered_by' => CommandRunTriggerEnum::MANUAL(),
            'started_at' => now(),
        ]);
    }

    public function isRunning(string $command): bool
    {
        return CommandRunLog::forCommand($command)
            ->where('status', CommandRunStatusEnum::RUNNING())
            ->where('started_at', '>=', now()->subMinutes(20))
            ->exists();
    }

    public function executeLoggedRun(string $command, int $logId): void
    {
        $log = CommandRunLog::find($logId);

        if (!$log) {
            return;
        }

        $startTime = microtime(true);

        try {
            Artisan::call($command);
            $output = Artisan::output();

            $log->update([
                'status' => CommandRunStatusEnum::SUCCESS(),
                'finished_at' => now(),
                'duration_ms' => (int)((microtime(true) - $startTime) * 1000),
                'output' => trim($output) ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::error("Manual command run failed: {$command}", ['error' => $e->getMessage()]);

            $log->update([
                'status' => CommandRunStatusEnum::FAILED(),
                'finished_at' => now(),
                'duration_ms' => (int)((microtime(true) - $startTime) * 1000),
                'output' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get run history for a specific command.
     */
    public function getRunHistory(string $command, int $perPage = 15)
    {
        return CommandRunLog::forCommand($command)
            ->orderByDesc('started_at')
            ->paginate($perPage);
    }

    /**
     * Check if the scheduler is healthy by verifying its log file was recently modified.
     */
    public function isSchedulerHealthy(): bool
    {
        return $this->isLogFresh(storage_path('logs/schedule.txt'));
    }

    /**
     * Check if the queue worker is healthy by verifying its log file was recently modified.
     */
    public function isQueueWorkerHealthy(): bool
    {
        return $this->isLogFresh(storage_path('logs/cron-log.txt'));
    }

    private function isLogFresh(string $path, int $maxAgeMinutes = 15): bool
    {
        if (! file_exists($path)) {
            return false;
        }

        $modified = @filemtime($path);

        return $modified !== false && (time() - $modified) <= ($maxAgeMinutes * 60);
    }
}
