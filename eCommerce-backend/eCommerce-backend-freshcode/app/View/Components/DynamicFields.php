<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class DynamicFields extends Component
{
    public function __construct(
        public iterable $fields,
        public array $values = [],
        public string $prefix = 'dynamic_fields',
    ) {
    }

    public function render(): View|Closure|string
    {
        return view('components.dynamic-fields');
    }
}
