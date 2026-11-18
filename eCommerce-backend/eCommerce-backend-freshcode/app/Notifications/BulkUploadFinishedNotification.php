<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\BulkUpload\BulkUploadJobStatusEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\BulkUploadJob;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use App\Notifications\Concerns\RespectsChannelSettings;
use App\Services\BulkUpload\BulkUploadModuleRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class BulkUploadFinishedNotification extends Notification implements ShouldQueue
{
    use Queueable, RespectsChannelSettings;

    public int $tries = 3;

    public array $backoff = [1, 5, 10];

    public function __construct(public BulkUploadJob $job) {}

    public function via(object $notifiable): array
    {
        return $this->filterChannels([RoleAwareDatabaseChannel::class, FirebaseChannel::class, 'mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->payload();
        $mail = (new MailMessage)
            ->subject($payload['title'])
            ->greeting(__('labels.hello').' '.($notifiable->name ?? ''))
            ->line($payload['message']);

        foreach ($this->summaryLines() as $line) {
            $mail->line($line);
        }

        return $mail->action(__('labels.bulk_upload_notification_view_job'), $payload['url']);
    }

    public function toFirebase(object $notifiable): array
    {
        $payload = $this->payload();
        $roleType = $this->job->panel === 'seller' ? NotificationRoleTypeEnum::SELLER() : NotificationRoleTypeEnum::ADMIN();

        return [
            'title' => $payload['title'],
            'body' => $payload['message'],
            'image' => null,
            'data' => [
                'type' => NotificationTypeEnum::BULK_UPLOAD_FINISHED(),
                'role_type' => $roleType,
                'job_id' => $this->job->id,
                'module' => $payload['module'],
                'status' => $this->job->status?->value,
                'url' => $payload['url'],
            ],
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $payload = $this->payload();
        $roleType = $this->job->panel === 'seller' ? NotificationRoleTypeEnum::SELLER() : NotificationRoleTypeEnum::ADMIN();

        return [
            'title' => $payload['title'],
            'message' => $payload['message'],
            'type' => NotificationTypeEnum::BULK_UPLOAD_FINISHED(),
            'sent_to' => $this->job->panel === 'seller' ? 'seller' : 'admin',
            'role_type' => $roleType,
            'user_id' => $notifiable->id ?? null,
            'metadata' => [
                'job_id' => $this->job->id,
                'module' => $payload['module'],
                'status' => $this->job->status?->value,
                'total_rows' => $this->job->total_rows,
                'processed_rows' => $this->job->processed_rows,
                'success_count' => $this->job->success_count,
                'updated_count' => $this->job->updated_count,
                'skipped_count' => $this->job->skipped_count,
                'failed_count' => $this->job->failed_count,
            ],
        ];
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Bulk upload completion notification failed permanently.', [
            'job_id' => $this->job->id,
            'error' => $exception?->getMessage(),
        ]);
    }

    private function payload(): array
    {
        $config = app(BulkUploadModuleRegistry::class)->getByModel($this->job->uploadable_type);
        $module = $config?->key ?? 'unknown';
        $label = $config ? __($config->displayNameKey) : $module;
        $failed = $this->job->status?->value === BulkUploadJobStatusEnum::FAILED();

        return [
            'title' => __($failed ? 'labels.bulk_upload_notification_subject_failed' : 'labels.bulk_upload_notification_subject_completed', ['module' => $label]),
            'message' => __($failed ? 'labels.bulk_upload_notification_body_failed' : 'labels.bulk_upload_notification_body_completed', [
                'module' => $label,
                'file' => $this->job->original_filename,
            ]),
            'module' => $module,
            'url' => $config ? route("{$this->job->panel}.{$module}.bulk-upload.jobs.show", $this->job) : url('/'),
        ];
    }

    private function summaryLines(): array
    {
        return array_values(array_filter([
            __('labels.bulk_upload_notification_summary_total', [
                'processed' => $this->job->processed_rows,
                'total' => $this->job->total_rows,
            ]),
            $this->job->success_count ? __('labels.bulk_upload_notification_summary_created', ['count' => $this->job->success_count]) : null,
            $this->job->updated_count ? __('labels.bulk_upload_notification_summary_updated', ['count' => $this->job->updated_count]) : null,
            $this->job->skipped_count ? __('labels.bulk_upload_notification_summary_skipped', ['count' => $this->job->skipped_count]) : null,
            $this->job->failed_count ? __('labels.bulk_upload_notification_summary_failed', ['count' => $this->job->failed_count]) : null,
        ]));
    }
}
