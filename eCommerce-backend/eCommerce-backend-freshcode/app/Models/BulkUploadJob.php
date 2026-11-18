<?php

namespace App\Models;

use App\Enums\BulkUpload\BulkUploadJobStatusEnum;
use App\Enums\BulkUpload\BulkUploadOperationEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BulkUploadJob extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uploadable_type',
        'panel',
        'operation',
        'match_field',
        'notify_on_finish',
        'user_id',
        'seller_id',
        'file_disk',
        'file_path',
        'failed_rows_path',
        'original_filename',
        'column_mapping',
        'default_values',
        'total_rows',
        'total_records',
        'processed_rows',
        'processed_records',
        'success_count',
        'updated_count',
        'skipped_count',
        'failed_count',
        'status',
        'error_log',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'default_values' => 'array',
            'status' => BulkUploadJobStatusEnum::class,
            'operation' => BulkUploadOperationEnum::class,
            'notify_on_finish' => 'boolean',
            'total_rows' => 'integer',
            'total_records' => 'integer',
            'processed_rows' => 'integer',
            'processed_records' => 'integer',
            'success_count' => 'integer',
            'updated_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('uploadable_type', $type);
    }

    public function scopeRunnable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            BulkUploadJobStatusEnum::PENDING(),
            BulkUploadJobStatusEnum::PROCESSING(),
        ]);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status?->value, [
            BulkUploadJobStatusEnum::COMPLETED(),
            BulkUploadJobStatusEnum::FAILED(),
            BulkUploadJobStatusEnum::CANCELLED(),
        ], true);
    }

    public function failedRowsExpiryLabel(): ?string
    {
        if (! $this->failed_rows_path) {
            return null;
        }

        if (! $this->isTerminal() || ! $this->finished_at) {
            return __('labels.bulk_upload_errors_expires_after_completion', [
                'days' => (int) config('bulk_upload.retention_days'),
                'failed_days' => (int) config('bulk_upload.retention_failed_days'),
            ]);
        }

        $days = $this->status?->value === BulkUploadJobStatusEnum::COMPLETED()
            ? (int) config('bulk_upload.retention_days')
            : (int) config('bulk_upload.retention_failed_days');

        return __('labels.bulk_upload_errors_expires_at', [
            'date' => $this->finished_at->copy()->addDays($days)->format('Y-m-d H:i'),
        ]);
    }
}
