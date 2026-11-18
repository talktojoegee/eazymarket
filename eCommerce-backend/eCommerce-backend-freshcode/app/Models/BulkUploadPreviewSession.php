<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkUploadPreviewSession extends Model
{
    protected $fillable = [
        'token', 'user_id', 'seller_id', 'panel', 'module', 'file_disk', 'file_path', 'operation', 'match_field',
        'column_mapping', 'mapping_hash', 'status', 'byte_offset', 'next_row_number', 'total_rows', 'processed_rows',
        'product_count', 'variant_count', 'location_count', 'critical_count', 'warning_count', 'info_count',
        'failure_reason', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'byte_offset' => 'integer',
            'next_row_number' => 'integer',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'product_count' => 'integer',
            'variant_count' => 'integer',
            'location_count' => 'integer',
            'critical_count' => 'integer',
            'warning_count' => 'integer',
            'info_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(BulkUploadProductPreview::class, 'preview_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
