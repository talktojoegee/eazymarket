<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Replaces AppNotificationZoneMap. Mirrors that model's shape so the dispatch
 * code can be swapped in place once we cut over.
 *
 * @method static create(array $data)
 * @method static where(string $column, mixed $value)
 */
class AppNotificationMarketMap extends Model
{
    use HasFactory;

    protected $table = 'app_notification_market_map';

    protected $fillable = [
        'notification_id',
        'market_id',
    ];

    protected function casts(): array
    {
        return [
            'notification_id' => 'integer',
            'market_id'       => 'integer',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(AppNotification::class, 'notification_id');
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
