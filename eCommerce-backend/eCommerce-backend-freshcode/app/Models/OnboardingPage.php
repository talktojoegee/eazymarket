<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingPage extends Model
{
    protected $fillable = [
        'surface',
        'title',
        'subtitle',
        'image_path',
        'font_color',
        'next_button_background_color',
        'next_button_text_color',
        'background_color',
        'background_type',
        'background_image_path',
    ];

}
