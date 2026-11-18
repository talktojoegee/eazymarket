<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatatableExport extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'panel',
        'module',
        'format',
        'scope',
        'columns',
        'parameters',
        'status',
        'total_rows',
        'processed_rows',
        'disk',
        'path',
        'filename',
        'failure_reason',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'parameters' => 'array',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
