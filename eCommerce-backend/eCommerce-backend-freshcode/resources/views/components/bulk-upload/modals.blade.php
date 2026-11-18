@props([
    'module',
    'showExport' => true,
])

@php
    $exportFilters = [];
    if ($showExport) {
        try {
            $registry = app(App\Services\BulkUpload\BulkUploadModuleRegistry::class);
            $moduleConfig = $registry->tryGet($module);
            if ($moduleConfig) {
                $exportFilters = app($moduleConfig->importerClass)->exportFilters();
            } else {
                $showExport = false;
            }
        } catch (Throwable) {
            $showExport = false;
        }
    }
@endphp

@if($showExport)
    <x-bulk-upload.export-modal :module="$module" :filters="$exportFilters"/>
@endif
