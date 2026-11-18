<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SplashScreen extends Model
{
    protected $fillable = [
        'surface',
        'slot',
        'scheduled_date',
        'background_type',
        'background_color',
        'background_image_path',
        'background_video_path',
        'overlay_enabled',
        'overlay_type',
        'overlay_image_path',
        'overlay_lottie_path',
        'overlay_horizontal_position',
        'overlay_vertical_position',
        'overlay_size',
        'min_display_seconds',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'overlay_enabled' => 'boolean',
        ];
    }
}
