<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeoPincode extends Model
{
    protected $table = 'geo_pincodes';

    protected $fillable = [
        'country_iso2',
        'postcode',
        'city',
        'state_code',
        'state_name',
    ];
}
