<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Services\AppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAppNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 2;

    public array $backoff = [30, 120];

    public function __construct(protected int $appNotificationId)
    {
    }

    public function handle(AppNotificationService $appNotificationService): void
    {
        $appNotification = AppNotification::with(['userMaps', 'marketMaps'])->find($this->appNotificationId);

        if (!$appNotification) {
            return;
        }

        $appNotificationService->send($appNotification);
    }

    public function failed(?\Throwable $e = null): void
    {
        Log::error('[AppNotification] dispatch job failed', [
            'app_notification_id' => $this->appNotificationId,
            'error' => $e?->getMessage(),
        ]);
    }
}
