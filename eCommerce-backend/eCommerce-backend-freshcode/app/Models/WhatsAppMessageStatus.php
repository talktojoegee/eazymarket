<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessageStatus extends Model
{
    protected $table = 'whatsapp_message_statuses';
    
    protected $fillable = [
        'message_id',
        'recipient_id',
        'module',
        'template_name',
        'status',
        'status_at',
        'error_code',
        'error_details',
    ];

    protected function casts(): array
    {
        return [
            'status_at' => 'datetime',
        ];
    }
}
