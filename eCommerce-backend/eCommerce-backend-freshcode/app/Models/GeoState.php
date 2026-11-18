<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeoState extends Model
{
    protected $table = 'geo_states';

    protected $fillable = [
        'country_iso2',
        'state_code',
        'name',
    ];
}
