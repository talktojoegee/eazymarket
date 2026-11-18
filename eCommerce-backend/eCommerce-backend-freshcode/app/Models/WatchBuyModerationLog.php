<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchBuyModerationLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'target_type',
        'target_id',
        'action',
        'actor_id',
        'previous_status',
        'new_status',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
