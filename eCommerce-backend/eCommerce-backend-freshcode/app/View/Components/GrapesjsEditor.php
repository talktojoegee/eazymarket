<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

class GrapesjsEditor extends Component
{
    public string $id;

    public string $projectDataJson;

    public string $assetToken;

    public function __construct(
        public string $name = 'page',
        public string $html = '',
        public string $css = '',
        array|string|null $projectData = null,
        public array $options = [],
        public string $height = '700px',
        public bool $readOnly = false,
        public ?int $documentId = null,
        ?string $assetToken = null,
        ?string $id = null,
    ) {
        $this->id = $id ?: 'grapesjs-editor-'.Str::lower(Str::random(12));
        $project = is_string($projectData) ? json_decode($projectData, true) : $projectData;
        $this->projectDataJson = is_array($project) ? json_encode($project) : '';
        $this->assetToken = $assetToken ?: Str::uuid()->toString();
    }

    public function render(): View|Closure|string
    {
        return view('components.grapesjs-editor');
    }
}
