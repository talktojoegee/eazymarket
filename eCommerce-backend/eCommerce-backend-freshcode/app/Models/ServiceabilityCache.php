<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceabilityCache extends Model
{
    protected $table = 'serviceability_cache';

    protected $fillable = [
        'provider_code',
        'pickup_pin',
        'delivery_pin',
        'weight_kg',
        'payment_method',
        'response_payload',
        'is_serviceable',
        'cheapest_rate',
        'fastest_eta_days',
        'expires_at',
    ];

    protected $casts = [
        'response_payload' => 'array',
        'is_serviceable'   => 'boolean',
        'weight_kg'        => 'float',
        'cheapest_rate'    => 'float',
        'expires_at'       => 'datetime',
    ];
}
