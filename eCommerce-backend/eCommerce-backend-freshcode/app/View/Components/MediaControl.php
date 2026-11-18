<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

class MediaControl extends Component
{
    public array $mediaTypeOptions = [
        'image' => 'Image',
        'lottie' => 'Lottie',
        'other' => 'Other',
    ];

    public array $aspectRatioOptions = [
        'original' => 'Original',
        '1:1' => 'Square (1:1)',
        '16:9' => 'Widescreen (16:9)',
        '4:3' => 'Standard (4:3)',
        '3:2' => 'Classic (3:2)',
        '2:3' => 'Portrait (2:3)',
        'custom' => 'Custom width / height',
    ];

    public array $dimensionUnitOptions = [
        'px' => 'px',
        '%' => '%',
        'rem' => 'rem',
        'em' => 'em',
        'vw' => 'vw',
        'vh' => 'vh',
    ];

    public array $imageFitOptions = [
        'cover' => 'Cover frame',
        'contain' => 'Contain image',
        'fill' => 'Stretch to fill',
        'scale-down' => 'Scale down only',
        'none' => 'Original size',
    ];

    public array $imageFocusOptions = [
        'center' => 'Center',
        'top' => 'Top',
        'right' => 'Right',
        'bottom' => 'Bottom',
        'left' => 'Left',
    ];

    public string $id;

    public string $mediaType;

    public string $aspectRatio;

    public string $aspectWidthUnit;

    public string $aspectHeightUnit;

    public string $imageFit;

    public string $imageFocus;

    public function __construct(
        public string $name = 'media',
        public string $configName = 'media_config',
        public string $label = 'Choose media',
        ?string $id = null,
        string $mediaType = 'image',
        public ?string $previewUrl = null,
        string $aspectRatio = 'original',
        public float $aspectWidth = 100,
        string $aspectWidthUnit = '%',
        public float $aspectHeight = 300,
        string $aspectHeightUnit = 'px',
        public string $seoAlt = '',
        string $imageFit = 'cover',
        string $imageFocus = 'center',
        public ?string $removeName = null,
        public bool $required = false,
        public bool $disabled = false,
        public bool $showConfig = true,
    ) {
        $this->id = $id ?: 'media-control-'.Str::lower(Str::random(8));
        $this->mediaType = array_key_exists($mediaType, $this->mediaTypeOptions) ? $mediaType : 'image';
        $this->aspectRatio = array_key_exists($aspectRatio, $this->aspectRatioOptions) ? $aspectRatio : 'original';
        $this->aspectWidth = $this->normalizeDimension($aspectWidth, 100);
        $this->aspectHeight = $this->normalizeDimension($aspectHeight, 300);
        $this->aspectWidthUnit = array_key_exists($aspectWidthUnit, $this->dimensionUnitOptions) ? $aspectWidthUnit : '%';
        $this->aspectHeightUnit = array_key_exists($aspectHeightUnit, $this->dimensionUnitOptions) ? $aspectHeightUnit : 'px';
        $this->imageFit = array_key_exists($imageFit, $this->imageFitOptions) ? $imageFit : 'cover';
        $this->imageFocus = array_key_exists($imageFocus, $this->imageFocusOptions) ? $imageFocus : 'center';
        $this->removeName ??= $this->name.'_remove';
    }

    public function render(): View|Closure|string
    {
        return view('components.media-control');
    }

    private function normalizeDimension(float $value, float $fallback): float
    {
        return $value > 0 ? min(10000, $value) : $fallback;
    }
}
