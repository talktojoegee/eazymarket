<?php

namespace App\Jobs;

use App\Services\CronMonitorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunMonitoredCommandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(private readonly string $command, private readonly int $logId)
    {
    }

    public function handle(CronMonitorService $cronMonitorService): void
    {
        $cronMonitorService->executeLoggedRun($this->command, $this->logId);
    }
}
