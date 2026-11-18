<?php

namespace App\Console\Commands;

use App\Enums\BulkUpload\BulkUploadJobStatusEnum;
use App\Models\BulkUploadJob;
use App\Models\BulkUploadPreviewSession;
use App\Services\BulkUpload\BulkUploadJobService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneBulkUploadJobsCommand extends Command
{
    protected $signature = 'bulk-uploads:prune';

    protected $description = 'Remove expired bulk upload jobs and orphaned artifacts.';

    public function handle(BulkUploadJobService $jobService): int
    {
        $completedBefore = now()->subDays((int) config('bulk_upload.retention_days', 7));
        $failedBefore = now()->subDays((int) config('bulk_upload.retention_failed_days', 30));

        BulkUploadJob::withTrashed()
            ->where(function ($query) use ($completedBefore, $failedBefore) {
                $query->where(function ($completed) use ($completedBefore) {
                    $completed->where('status', BulkUploadJobStatusEnum::COMPLETED())
                        ->where('finished_at', '<', $completedBefore);
                })->orWhere(function ($failed) use ($failedBefore) {
                    $failed->whereIn('status', [
                        BulkUploadJobStatusEnum::FAILED(),
                        BulkUploadJobStatusEnum::CANCELLED(),
                    ])->where('finished_at', '<', $failedBefore);
                });
            })
            ->orderBy('id')
            ->chunkById(100, function ($jobs) use ($jobService) {
                foreach ($jobs as $job) {
                    $jobService->deleteAllArtifacts($job);
                    $job->forceDelete();
                }
            });

        BulkUploadPreviewSession::query()
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->chunkById(100, function ($sessions) {
                foreach ($sessions as $session) {
                    Storage::disk($session->file_disk)->deleteDirectory(dirname($session->file_path));
                    $session->delete();
                }
            });

        $this->pruneOrphans();

        return self::SUCCESS;
    }

    private function pruneOrphans(): void
    {
        $disk = (string) config('bulk_upload.file_disk', 'local');
        $directory = trim((string) config('bulk_upload.temp_dir', 'bulk-uploads'), '/');
        $storage = Storage::disk($disk);
        $referencedDirectories = BulkUploadJob::withTrashed()
            ->whereNotNull('file_path')
            ->pluck('file_path')
            ->map(fn (string $path) => dirname($path))
            ->merge(BulkUploadPreviewSession::query()->where('expires_at', '>=', now())->pluck('file_path')->map(fn (string $path) => dirname($path)))
            ->all();
        $cutoff = now()->subHours((int) config('bulk_upload.orphan_csv_hours', 6))->getTimestamp();

        foreach ($storage->directories($directory) as $candidate) {
            if (in_array($candidate, $referencedDirectories, true)) {
                continue;
            }

            $files = $storage->files($candidate);
            $latest = collect($files)->map(fn (string $file) => $storage->lastModified($file))->max();
            if ($latest === null || $latest < $cutoff) {
                $storage->deleteDirectory($candidate);
            }
        }
    }
}
