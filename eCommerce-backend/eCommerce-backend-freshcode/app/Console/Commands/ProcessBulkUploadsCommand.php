<?php

namespace App\Console\Commands;

use App\Enums\BulkUpload\BulkUploadJobStatusEnum;
use App\Interfaces\BulkImportable;
use App\Interfaces\GroupedBulkImportable;
use App\Models\BulkUploadJob;
use App\Services\BulkUpload\BulkUploadJobService;
use App\Services\BulkUpload\BulkUploadModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessBulkUploadsCommand extends Command
{
    protected $signature = 'bulk-uploads:run';

    protected $description = 'Process the next chunk of a pending bulk upload.';

    public function handle(BulkUploadJobService $jobService, BulkUploadModuleRegistry $registry): int
    {
        $lock = Cache::lock('bulk-uploads:processor', (int) config('bulk_upload.lock_seconds', 900));
        if (! $lock->get()) {
            return self::SUCCESS;
        }

        try {
            $job = BulkUploadJob::query()->runnable()->oldest('id')->first();
            if (! $job) {
                return self::SUCCESS;
            }

            $module = $registry->getByModel($job->uploadable_type);
            if (! $module) {
                $jobService->markFailed($job, __('labels.bulk_upload_unknown_uploadable_type'));

                return self::SUCCESS;
            }

            if ($job->status?->value === BulkUploadJobStatusEnum::PENDING()) {
                $jobService->markProcessing($job);
                $jobService->appendLog($job, __('labels.bulk_upload_started', ['total' => $job->total_rows]));
            }

            $stream = $jobService->openAtOffset(
                $job->file_disk ?: (string) config('bulk_upload.file_disk', 'local'),
                $job->file_path,
                $job->processed_rows,
            );
            if (! $stream) {
                $jobService->markFailed($job, __('labels.bulk_upload_file_unreadable'));

                return self::SUCCESS;
            }
            $handle = $stream['handle'];

            $headers = $jobService->readHeaders($job->file_disk, $job->file_path);
            if (! $headers) {
                fclose($handle);
                $jobService->markFailed($job, __('labels.bulk_upload_csv_no_headers'));

                return self::SUCCESS;
            }

            $importer = app($module->importerClass);
            $failedRows = [];
            $processed = 0;
            $rowNumber = $stream['row_number'];

            try {
                if ($importer instanceof GroupedBulkImportable) {
                    $this->processGroupedRows($handle, $headers, $rowNumber, $processed, $job, $importer, $jobService, $failedRows);
                } else {
                    while ($processed < (int) config('bulk_upload.rows_per_tick', 300) && ($raw = fgetcsv($handle)) !== false) {
                        if ($jobService->isBlankRow($raw)) {
                            $rowNumber++;

                            continue;
                        }
                        $row = $this->associateRow($headers, $raw);

                        [$outcome, $message] = $importer->processRow($row, $job);
                        $logMessage = $outcome === BulkImportable::OUTCOME_FAILED
                            ? $this->failedRecordMessage([$row], $job, $importer, $message ?: $outcome)
                            : ($message ?: $outcome);
                        $jobService->recordRowOutcome($job, $outcome, __('labels.bulk_upload_log_row', ['row' => $rowNumber, 'message' => $logMessage]));

                        if ($outcome === BulkImportable::OUTCOME_FAILED) {
                            $failedRows[] = [
                                'row_number' => $rowNumber,
                                'row' => $raw,
                                'error' => $message ?: $outcome,
                            ];
                        }

                        $processed++;
                        $rowNumber++;

                        if ($this->cancelled($job)) {
                            break;
                        }
                    }
                }
            } catch (\Throwable $exception) {
                Log::error('Bulk upload processing failed.', [
                    'job_id' => $job->id,
                    'error' => $exception->getMessage(),
                ]);
                $jobService->appendFailedRows($job, $headers, $failedRows);
                $failedRows = [];
                $jobService->markFailed($job, __('labels.bulk_upload_crash', ['reason' => $exception->getMessage()]));

                return self::SUCCESS;
            } finally {
                fclose($handle);
                $jobService->appendFailedRows($job, $headers, $failedRows);
            }

            $job->refresh();
            if ($job->status?->value === BulkUploadJobStatusEnum::CANCELLED()) {
                return self::SUCCESS;
            }

            if ($job->processed_rows >= $job->total_rows) {
                $jobService->appendLog($job, __('labels.bulk_upload_completed_log', [
                    'created' => $job->success_count,
                    'updated' => $job->updated_count,
                    'skipped' => $job->skipped_count,
                    'failed' => $job->failed_count,
                ]));
                $jobService->markCompleted($job);
            }

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function processGroupedRows($handle, array $headers, int &$rowNumber, int &$processed, BulkUploadJob $job, GroupedBulkImportable $importer, BulkUploadJobService $jobService, array &$failedRows): void
    {
        $limit = (int) config('bulk_upload.rows_per_tick', 300);
        $pending = null;
        while ($processed < $limit || $pending !== null) {
            $raw = $pending ?? fgetcsv($handle);
            $pending = null;
            if ($raw === false) {
                break;
            }
            if ($jobService->isBlankRow($raw)) {
                $rowNumber++;

                continue;
            }

            $groupRows = [['row_number' => $rowNumber, 'raw' => $raw, 'row' => $this->associateRow($headers, $raw)]];
            $groupKey = $importer->groupingKey($groupRows[0]['row'], $job);
            if ($groupKey === '') {
                $message = __('labels.bulk_upload_product_handle_required');
                $logMessage = $this->failedRecordMessage([$groupRows[0]['row']], $job, $importer, $message);
                $jobService->recordGroupOutcome($job, BulkImportable::OUTCOME_FAILED, 1, __('labels.bulk_upload_log_row', ['row' => $rowNumber, 'message' => $logMessage]));
                $failedRows[] = ['row_number' => $rowNumber, 'row' => $raw, 'error' => $message];
                $processed++;
                $rowNumber++;

                continue;
            }

            $rowNumber++;
            while (($next = fgetcsv($handle)) !== false) {
                if ($jobService->isBlankRow($next)) {
                    $rowNumber++;

                    continue;
                }
                $nextRow = $this->associateRow($headers, $next);
                if ($importer->groupingKey($nextRow, $job) !== $groupKey) {
                    $pending = $next;

                    break;
                }
                $groupRows[] = ['row_number' => $rowNumber, 'raw' => $next, 'row' => $nextRow];
                $rowNumber++;
            }

            [$outcome, $message] = $importer->processGroup(array_column($groupRows, 'row'), $job);
            $logMessage = $outcome === BulkImportable::OUTCOME_FAILED
                ? $this->failedRecordMessage(array_column($groupRows, 'row'), $job, $importer, $message)
                : $message;
            $firstRow = $groupRows[0]['row_number'];
            $lastRow = $groupRows[array_key_last($groupRows)]['row_number'];
            $log = count($groupRows) === 1
                ? __('labels.bulk_upload_log_row', ['row' => $firstRow, 'message' => $logMessage])
                : __('labels.bulk_upload_log_rows', ['first' => $firstRow, 'last' => $lastRow, 'message' => $logMessage]);
            $jobService->recordGroupOutcome($job, $outcome, count($groupRows), $log);
            if ($outcome === BulkImportable::OUTCOME_FAILED) {
                foreach ($groupRows as $groupRow) {
                    $failedRows[] = [
                        'row_number' => $groupRow['row_number'],
                        'row' => $groupRow['raw'],
                        'error' => $message,
                    ];
                }
            }
            $processed += count($groupRows);
            if ($processed >= $limit || $this->cancelled($job)) {
                break;
            }
        }
    }

    private function associateRow(array $headers, array $raw): array
    {
        $row = [];
        foreach ($headers as $index => $header) {
            $row[$header] = $raw[$index] ?? null;
        }

        return $row;
    }

    private function failedRecordMessage(array $rows, BulkUploadJob $job, BulkImportable $importer, string $reason): string
    {
        $mapping = is_array($job->column_mapping) ? $job->column_mapping : [];
        $value = static function (string $field) use ($rows, $mapping): string {
            foreach ($rows as $row) {
                $cell = trim(str_replace(["\r", "\n", "\t"], ' ', (string) ($row[$mapping[$field] ?? ''] ?? '')));
                if ($cell !== '') {
                    return $cell;
                }
            }

            return '';
        };
        $title = $value('title') ?: $value('handle') ?: ($value('id') !== '' ? '#'.$value('id') : __('labels.bulk_upload_untitled_row'));
        $module = match ($importer->moduleKey()) {
            'brands' => __('labels.brand'),
            'categories' => __('labels.category'),
            default => __('labels.product'),
        };
        $action = $job->operation?->value === 'update' ? __('labels.bulk_upload_log_update_action') : __('labels.bulk_upload_log_create_action');

        if ($importer->moduleKey() === 'products' && $value('handle') !== '') {
            return __('labels.bulk_upload_log_failed_product', ['action' => $action, 'title' => $title, 'handle' => $value('handle'), 'reason' => $reason]);
        }

        return __('labels.bulk_upload_log_failed_record', ['action' => $action, 'module' => $module, 'title' => $title, 'reason' => $reason]);
    }

    private function cancelled(BulkUploadJob $job): bool
    {
        return BulkUploadJob::query()->whereKey($job->id)->value('status') === BulkUploadJobStatusEnum::CANCELLED();
    }
}
