<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Datatable extends Component
{
    public $id;
    public $route;
    public $columns;
    public $class;
    public $options;
    public $exportOptions;

    /**
     * Create a new component instance.
     *
     * @param string $id The HTML id attribute for the table.
     * @param string $route The URL route for the AJAX request.
     * @param array $columns An array of column definitions.
     * @param string $class CSS classes for styling the table.
     */
    public function __construct($id, $route, $columns, $class = 'table table-striped table-bordered table-vcenter text-nowrap w-100', $options = [], $exportOptions = [])
    {
        $this->id = $id;
        $this->route = $route;
        $this->columns = $columns;
        $this->class = $class;
        $this->options = $options;
        $this->exportOptions = $exportOptions;

        if (!empty($this->exportOptions['module']) && auth()->user()) {
            $panel = request()->routeIs('seller.*') ? 'seller' : 'admin';
            $registry = app(\App\Services\DatatableExportRegistry::class);
            $this->exportOptions = $registry->frontendOptions(
                auth()->user(),
                $this->exportOptions['module'],
                $panel,
                $this->exportOptions,
            );
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.datatable');
    }
}
