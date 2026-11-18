<?php

namespace App\Jobs;

use App\Enums\NotificationTypeEnum;
use App\Models\DatatableExport;
use App\Services\DatatableExportService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateDatatableExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    public int $tries = 2;

    public array $backoff = [30, 120];

    public function __construct(private string $exportId) {}

    public function handle(DatatableExportService $exports, NotificationService $notifications): void
    {
        $export = DatatableExport::with('user')->find($this->exportId);
        if (! $export || $export->status === 'ready') {
            return;
        }
        $exports->generate($export);
        $this->notify($export->refresh(), $notifications, true);
    }

    public function failed(?\Throwable $exception = null): void
    {
        $export = DatatableExport::with('user')->find($this->exportId);
        if (! $export) {
            return;
        }
        $export->update([
            'status' => 'failed',
            'failure_reason' => str($exception?->getMessage() ?? 'Export failed')->limit(1000),
            'expires_at' => now()->addHours((int) config('datatable-exports.expires_after_hours', 24)),
        ]);
        $this->notify($export, app(NotificationService::class), false);
    }

    private function notify(DatatableExport $export, NotificationService $notifications, bool $success): void
    {
        if (! $export->user) {
            return;
        }
        $notifications->createNotification([
            'user_id' => $export->user_id,
            'sent_to' => $export->panel,
            'type' => NotificationTypeEnum::DATATABLE_EXPORT(),
            'title' => $success ? __('labels.export_ready') : __('labels.export_failed'),
            'message' => $success ? __('labels.export_ready_message') : __('labels.export_failed_message'),
            'metadata' => ['export_id' => $export->id, 'status' => $export->status],
        ]);
    }
}
