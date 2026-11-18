<?php

namespace App\Services\BulkUpload;

use App\Enums\BulkUpload\BulkUploadJobStatusEnum;
use App\Interfaces\BulkImportable;
use App\Models\BulkUploadJob;
use App\Models\User;
use App\Notifications\BulkUploadFinishedNotification;
use App\Support\OutboundHost;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BulkUploadJobService
{
    public function storeUploadedFile(UploadedFile $file): array
    {
        $disk = (string) config('bulk_upload.file_disk', 'local');
        $directory = trim((string) config('bulk_upload.temp_dir', 'bulk-uploads'), '/').'/'.Str::uuid();
        $originalFilename = $file->getClientOriginalName();
        $filename = Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME)).'.csv';
        $path = $file->storeAs($directory, $filename, $disk);

        if (! is_string($path)) {
            throw new \RuntimeException('Unable to store bulk upload file.');
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'token' => basename($directory),
            'original_filename' => $originalFilename,
        ];
    }

    public function previewCsv(string $disk, string $path, int $previewRows): array
    {
        $handle = $this->open($disk, $path);
        if (! $handle) {
            return ['headers' => [], 'rows' => [], 'total_rows' => 0, 'ignored_blank_rows' => 0];
        }

        try {
            $headers = $this->cleanHeaders(fgetcsv($handle) ?: []);
            $rows = [];
            $totalRows = 0;
            $ignoredBlankRows = 0;

            while (($row = fgetcsv($handle)) !== false) {
                if ($this->isBlankRow($row)) {
                    $ignoredBlankRows++;

                    continue;
                }
                $totalRows++;
                if (count($rows) < $previewRows) {
                    $rows[] = $row;
                }
            }

            return [
                'headers' => $headers,
                'rows' => $rows,
                'total_rows' => $totalRows,
                'ignored_blank_rows' => $ignoredBlankRows,
            ];
        } finally {
            fclose($handle);
        }
    }

    public function missingPrimaryMedia(string $disk, string $path, string $primaryField, int $entryLimit = 50): array
    {
        $handle = $this->open($disk, $path);
        if (! $handle) {
            return ['total_count' => 0, 'entries' => [], 'truncated' => false];
        }

        try {
            $headers = $this->cleanHeaders(fgetcsv($handle) ?: []);
            $normalized = array_map([$this, 'normalizeHeader'], $headers);
            $primaryIndex = array_search($this->normalizeHeader($primaryField), $normalized, true);
            $titleIndex = array_search('title', $normalized, true);
            $total = 0;
            $entries = [];
            $rowNumber = 2;

            while (($row = fgetcsv($handle)) !== false) {
                if ($this->isBlankRow($row)) {
                    $rowNumber++;

                    continue;
                }
                $media = $primaryIndex === false ? '' : trim((string) ($row[$primaryIndex] ?? ''));
                if ($media === '') {
                    $total++;
                    if (count($entries) < $entryLimit) {
                        $entries[] = [
                            'row_number' => $rowNumber,
                            'title' => $titleIndex === false ? '' : trim((string) ($row[$titleIndex] ?? '')),
                            'field' => $primaryField,
                        ];
                    }
                }
                $rowNumber++;
            }

            return [
                'total_count' => $total,
                'entries' => $entries,
                'truncated' => $total > count($entries),
            ];
        } finally {
            fclose($handle);
        }
    }

    public function groupedMissingPrimaryMedia(string $disk, string $path, string $primaryField, int $entryLimit = 50): array
    {
        $handle = $this->open($disk, $path);
        if (! $handle) {
            return ['total_count' => 0, 'entries' => [], 'truncated' => false];
        }

        try {
            $headers = $this->cleanHeaders(fgetcsv($handle) ?: []);
            $normalized = array_map([$this, 'normalizeHeader'], $headers);
            $handleIndex = array_search('handle', $normalized, true);
            $titleIndex = array_search('title', $normalized, true);
            $mediaIndex = array_search($this->normalizeHeader($primaryField), $normalized, true);
            $groups = [];
            $rowNumber = 2;
            while (($row = fgetcsv($handle)) !== false) {
                if ($this->isBlankRow($row)) {
                    $rowNumber++;

                    continue;
                }
                $key = $handleIndex === false ? (string) $rowNumber : strtolower(trim((string) ($row[$handleIndex] ?? '')));
                $groups[$key] ??= ['row_number' => $rowNumber, 'title' => '', 'has_media' => false];
                $groups[$key]['title'] = $groups[$key]['title'] ?: ($titleIndex === false ? '' : trim((string) ($row[$titleIndex] ?? '')));
                $groups[$key]['has_media'] = $groups[$key]['has_media'] || ($mediaIndex !== false && trim((string) ($row[$mediaIndex] ?? '')) !== '');
                $rowNumber++;
            }
            $missing = array_values(array_filter($groups, fn ($group) => ! $group['has_media']));

            return [
                'total_count' => count($missing),
                'entries' => array_map(fn ($group) => [
                    'row_number' => $group['row_number'],
                    'title' => $group['title'],
                    'field' => $primaryField,
                ], array_slice($missing, 0, $entryLimit)),
                'truncated' => count($missing) > $entryLimit,
            ];
        } finally {
            fclose($handle);
        }
    }

    public function mediaPreviewData(array $headers, array $rows, array $mediaFields, int $itemLimit = 100): array
    {
        $mediaFieldNames = array_map([$this, 'normalizeHeader'], $mediaFields);
        $columns = [];
        foreach ($headers as $columnIndex => $header) {
            if (in_array($this->normalizeHeader((string) $header), $mediaFieldNames, true)) {
                $columns[] = $columnIndex;
            }
        }

        $items = [];
        $validatedUrls = [];
        foreach ($rows as $rowIndex => $row) {
            foreach ($columns as $columnIndex) {
                $urls = array_values(array_filter(array_map('trim', explode('|', (string) ($row[$columnIndex] ?? '')))));
                foreach ($urls as $url) {
                    if (count($items) >= $itemLimit) {
                        break 2;
                    }
                    if (! array_key_exists($url, $validatedUrls)) {
                        $validatedUrls[$url] = $this->isPreviewableMediaUrl($url);
                    }
                    if (! $validatedUrls[$url]) {
                        continue;
                    }

                    $items[] = [
                        'row_index' => $rowIndex,
                        'column_index' => $columnIndex,
                        'url' => $url,
                    ];
                }
            }
        }

        return ['columns' => $columns, 'items' => $items];
    }

    public function countDataRows(string $disk, string $path): int
    {
        $handle = $this->open($disk, $path);
        if (! $handle) {
            return 0;
        }

        try {
            fgetcsv($handle);
            $count = 0;
            while (($row = fgetcsv($handle)) !== false) {
                if (! $this->isBlankRow($row)) {
                    $count++;
                }
            }

            return $count;
        } finally {
            fclose($handle);
        }
    }

    public function groupedCsvSummary(string $disk, string $path, string $groupHeader = 'handle'): array
    {
        $handle = $this->open($disk, $path);
        if (! $handle) {
            return ['total_records' => 0, 'variant_count' => 0, 'location_count' => 0, 'non_contiguous' => [], 'duplicate_skus' => [], 'duplicate_barcodes' => []];
        }

        try {
            $headers = $this->cleanHeaders(fgetcsv($handle) ?: []);
            $normalized = array_map([$this, 'normalizeHeader'], $headers);
            $groupIndex = array_search($this->normalizeHeader($groupHeader), $normalized, true);
            $skuIndex = array_search('variant_sku', $normalized, true);
            if ($skuIndex === false) {
                $skuIndex = array_search('sku', $normalized, true);
            }
            $barcodeIndex = array_search('barcode', $normalized, true);
            if ($barcodeIndex === false) {
                $barcodeIndex = array_search('variant_barcode', $normalized, true);
            }
            $locationIndex = array_search('store_location_id', $normalized, true);
            $records = [];
            $variants = [];
            $locations = [];
            $completed = [];
            $nonContiguous = [];
            $seenSkus = [];
            $seenBarcodes = [];
            $duplicateSkus = [];
            $duplicateBarcodes = [];
            $previous = null;
            $rowNumber = 2;
            while (($row = fgetcsv($handle)) !== false) {
                if ($this->isBlankRow($row)) {
                    $rowNumber++;

                    continue;
                }
                $group = $groupIndex === false ? '' : strtolower(trim((string) ($row[$groupIndex] ?? '')));
                if ($previous !== null && $group !== $previous) {
                    $completed[$previous] = true;
                }
                if ($group !== '' && isset($completed[$group]) && count($nonContiguous) < 50) {
                    $nonContiguous[] = ['row_number' => $rowNumber, 'handle' => $group];
                }
                if ($group !== '') {
                    $records[$group] = true;
                }
                $sku = $skuIndex === false ? '' : strtolower(trim((string) ($row[$skuIndex] ?? '')));
                if ($sku !== '') {
                    $variants[$group.'|'.$sku] = true;
                    if (isset($seenSkus[$sku]) && $seenSkus[$sku] !== $group && count($duplicateSkus) < 50) {
                        $duplicateSkus[] = ['row_number' => $rowNumber, 'value' => $sku];
                    }
                    $seenSkus[$sku] = $group;
                }
                $barcode = $barcodeIndex === false ? '' : strtolower(trim((string) ($row[$barcodeIndex] ?? '')));
                if ($barcode !== '') {
                    $variantKey = $group.'|'.$sku;
                    if (isset($seenBarcodes[$barcode]) && $seenBarcodes[$barcode] !== $variantKey && count($duplicateBarcodes) < 50) {
                        $duplicateBarcodes[] = ['row_number' => $rowNumber, 'value' => $barcode];
                    }
                    $seenBarcodes[$barcode] = $variantKey;
                }
                $location = $locationIndex === false ? '' : trim((string) ($row[$locationIndex] ?? ''));
                if ($location !== '') {
                    $locations[$group.'|'.$sku.'|'.$location] = true;
                }
                $previous = $group;
                $rowNumber++;
            }

            return [
                'total_records' => count($records),
                'variant_count' => count($variants),
                'location_count' => count($locations),
                'non_contiguous' => $nonContiguous,
                'duplicate_skus' => $duplicateSkus,
                'duplicate_barcodes' => $duplicateBarcodes,
            ];
        } finally {
            fclose($handle);
        }
    }

    public function findActiveJob(string $uploadableType, ?int $sellerId = null): ?BulkUploadJob
    {
        $query = BulkUploadJob::query()
            ->forType($uploadableType)
            ->runnable();

        if ($sellerId !== null) {
            $query->where('seller_id', $sellerId);
        }

        return $query
            ->latest('id')
            ->first();
    }

    public function createPendingJob(
        string $uploadableType,
        ?int $userId,
        string $disk,
        string $path,
        string $originalFilename,
        array $columnMapping,
        array $defaultValues,
        int $totalRows,
        string $operation = 'create',
        ?string $matchField = null,
        bool $notifyOnFinish = false,
        string $panel = 'admin',
        ?int $sellerId = null,
        ?int $totalRecords = null,
    ): BulkUploadJob {
        return BulkUploadJob::create([
            'uploadable_type' => $uploadableType,
            'user_id' => $userId,
            'panel' => $panel,
            'seller_id' => $sellerId,
            'file_disk' => $disk,
            'file_path' => $path,
            'original_filename' => $originalFilename,
            'column_mapping' => $columnMapping,
            'default_values' => $defaultValues,
            'total_rows' => $totalRows,
            'total_records' => $totalRecords ?? $totalRows,
            'status' => BulkUploadJobStatusEnum::PENDING(),
            'operation' => $operation,
            'match_field' => $matchField,
            'notify_on_finish' => $notifyOnFinish,
        ]);
    }

    public function markProcessing(BulkUploadJob $job): void
    {
        $job->update([
            'status' => BulkUploadJobStatusEnum::PROCESSING(),
            'started_at' => $job->started_at ?? now(),
        ]);
    }

    public function appendLog(BulkUploadJob $job, string $line): void
    {
        $stamped = '['.now()->format('Y-m-d H:i:s').'] '.$line;
        $job->error_log = $job->error_log ? $job->error_log.PHP_EOL.$stamped : $stamped;
        $job->save();
    }

    public function recordRowOutcome(BulkUploadJob $job, string $outcome, string $line): void
    {
        $job->processed_rows++;
        $job->processed_records++;

        match ($outcome) {
            BulkImportable::OUTCOME_CREATED => $job->success_count++,
            BulkImportable::OUTCOME_UPDATED => $job->updated_count++,
            BulkImportable::OUTCOME_SKIPPED => $job->skipped_count++,
            default => $job->failed_count++,
        };

        $stamped = '['.now()->format('Y-m-d H:i:s').'] '.$line;
        $job->error_log = $job->error_log ? $job->error_log.PHP_EOL.$stamped : $stamped;
        $job->save();
    }

    public function recordGroupOutcome(BulkUploadJob $job, string $outcome, int $rowCount, string $line): void
    {
        $job->processed_rows += $rowCount;
        $job->processed_records++;

        match ($outcome) {
            BulkImportable::OUTCOME_CREATED => $job->success_count++,
            BulkImportable::OUTCOME_UPDATED => $job->updated_count++,
            BulkImportable::OUTCOME_SKIPPED => $job->skipped_count++,
            default => $job->failed_count++,
        };

        $stamped = '['.now()->format('Y-m-d H:i:s').'] '.$line;
        $job->error_log = $job->error_log ? $job->error_log.PHP_EOL.$stamped : $stamped;
        $job->save();
    }

    public function markCompleted(BulkUploadJob $job): void
    {
        $job->update([
            'status' => BulkUploadJobStatusEnum::COMPLETED(),
            'finished_at' => now(),
        ]);
        $this->deleteInputArtifact($job);
        $this->dispatchFinishedNotification($job);
    }

    public function markFailed(BulkUploadJob $job, string $reason): void
    {
        $this->appendLog($job, $reason);
        $job->update([
            'status' => BulkUploadJobStatusEnum::FAILED(),
            'finished_at' => now(),
        ]);
        $this->deleteInputArtifact($job);
        $this->dispatchFinishedNotification($job);
    }

    public function markCancelled(BulkUploadJob $job): void
    {
        $job->update([
            'status' => BulkUploadJobStatusEnum::CANCELLED(),
            'finished_at' => now(),
        ]);
        $this->deleteInputArtifact($job);
    }

    public function deleteInputArtifact(BulkUploadJob $job): void
    {
        try {
            $storage = Storage::disk($job->file_disk ?: config('bulk_upload.file_disk', 'local'));
            if ($job->file_path && $storage->exists($job->file_path)) {
                $storage->delete($job->file_path);
            }
            $directory = $job->file_path ? dirname($job->file_path) : null;
            if ($directory && $directory !== '.' && $storage->exists($directory) && $storage->files($directory) === []) {
                $storage->deleteDirectory($directory);
            }
        } catch (\Throwable $exception) {
            Log::warning('Bulk upload input cleanup failed.', [
                'job_id' => $job->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function deleteAllArtifacts(BulkUploadJob $job): void
    {
        try {
            $storage = Storage::disk($job->file_disk ?: config('bulk_upload.file_disk', 'local'));
            $path = $job->file_path ?: $job->failed_rows_path;
            if ($path && dirname($path) !== '.') {
                $storage->deleteDirectory(dirname($path));
            }
        } catch (\Throwable $exception) {
            Log::warning('Bulk upload artifact cleanup failed.', [
                'job_id' => $job->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function appendFailedRows(BulkUploadJob $job, array $headers, array $entries): void
    {
        if ($entries === [] || ! $job->file_path) {
            return;
        }

        try {
            $storage = Storage::disk($job->file_disk ?: config('bulk_upload.file_disk', 'local'));
            $path = dirname($job->file_path).'/_errors.csv';
            $temporary = fopen('php://temp', 'w+');
            if ($temporary === false) {
                throw new \RuntimeException('Unable to create error report.');
            }

            try {
                if (! $storage->exists($path)) {
                    fputcsv($temporary, array_merge(
                        ['_row'],
                        array_map([$this, 'escapeSpreadsheetValue'], $headers),
                        ['_error'],
                    ));
                }
                foreach ($entries as $entry) {
                    $values = array_map([$this, 'escapeSpreadsheetValue'], array_values($entry['row'] ?? []));
                    fputcsv($temporary, array_merge([(string) ($entry['row_number'] ?? '')], $values, [$this->escapeSpreadsheetValue((string) ($entry['error'] ?? ''))]));
                }
                rewind($temporary);
                $payload = stream_get_contents($temporary);
            } finally {
                fclose($temporary);
            }

            $storage->append($path, rtrim((string) $payload, "\r\n"));
            if ($job->failed_rows_path !== $path) {
                $job->update(['failed_rows_path' => $path]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Bulk upload error report write failed.', [
                'job_id' => $job->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function openAtOffset(string $disk, string $path, int $offset): ?array
    {
        $handle = $this->open($disk, $path);
        if (! $handle) {
            return null;
        }

        fgetcsv($handle);
        $skipped = 0;
        $rowNumber = 2;
        while ($skipped < $offset && ($row = fgetcsv($handle)) !== false) {
            if (! $this->isBlankRow($row)) {
                $skipped++;
            }
            $rowNumber++;
        }

        return ['handle' => $handle, 'row_number' => $rowNumber];
    }

    public function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    public function readHeaders(string $disk, string $path): ?array
    {
        $handle = $this->open($disk, $path);
        if (! $handle) {
            return null;
        }

        try {
            $headers = fgetcsv($handle);

            return $headers ? $this->cleanHeaders($headers) : null;
        } finally {
            fclose($handle);
        }
    }

    public function escapeSpreadsheetValue(mixed $value): string
    {
        $string = is_scalar($value) ? (string) $value : '';

        return preg_match('/^[=+\-@]/', $string) ? "'".$string : $string;
    }

    public function recentLog(?string $log, int $lineLimit = 200): string
    {
        $lines = preg_split('/\R/', (string) $log) ?: [];

        return implode(PHP_EOL, array_slice($lines, -$lineLimit));
    }

    private function cleanHeaders(array $headers): array
    {
        return array_map(function ($header, int $index) {
            $value = trim((string) $header);

            return $index === 0 ? preg_replace('/^\xEF\xBB\xBF/', '', $value) : $value;
        }, $headers, array_keys($headers));
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $header), '_'));
    }

    private function isPreviewableMediaUrl(string $url): bool
    {
        if (strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return false;
        }

        try {
            OutboundHost::assertPublicHttpUrl($url, 'Image URL');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function open(string $disk, string $path)
    {
        try {
            $stream = Storage::disk($disk)->readStream($path);

            return is_resource($stream) ? $stream : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function dispatchFinishedNotification(BulkUploadJob $job): void
    {
        if (! $job->notify_on_finish || ! $job->user_id) {
            return;
        }

        try {
            User::query()->find($job->user_id)?->notify(new BulkUploadFinishedNotification($job));
        } catch (\Throwable $exception) {
            Log::warning('Bulk upload completion notification failed.', [
                'job_id' => $job->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
