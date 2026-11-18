<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkUploadProductPreview extends Model
{
    protected $fillable = [
        'preview_session_id', 'sequence', 'first_row_number', 'last_row_number', 'product_id', 'handle', 'title',
        'type', 'status', 'category', 'brand', 'main_image_url', 'variant_count', 'location_count', 'minimum_price',
        'maximum_price', 'inventory_total', 'critical_count', 'warning_count', 'info_count', 'search_text', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'minimum_price' => 'decimal:2',
            'maximum_price' => 'decimal:2',
            'inventory_total' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BulkUploadPreviewSession::class, 'preview_session_id');
    }
}
