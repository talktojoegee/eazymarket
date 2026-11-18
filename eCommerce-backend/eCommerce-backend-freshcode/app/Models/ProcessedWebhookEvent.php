<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProcessedWebhookEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['provider', 'event_id'];

    public static function firstDelivery(string $provider, string $eventId): bool
    {
        if ($eventId === '') {
            return true;
        }

        return DB::table('processed_webhook_events')->insertOrIgnore([
            'provider' => $provider,
            'event_id' => $eventId,
            'created_at' => now(),
        ]) > 0;
    }
}
