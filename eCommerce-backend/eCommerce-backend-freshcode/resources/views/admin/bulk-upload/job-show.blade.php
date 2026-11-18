@extends('layouts.'.$panelName.'.app', ['page' => ($panelName === 'admin' ? $menuAdmin : $menuSeller)[$moduleConfig->menuKey]['active'] ?? '', 'sub_page' => ($panelName === 'admin' ? $menuAdmin : $menuSeller)[$moduleConfig->menuKey]['route']['bulk_upload']['sub_active'] ?? null])

@php
    $moduleDisplay = __($moduleConfig->displayNameKey);
    $moduleKey = $moduleConfig->key;
    $canCancel = auth()->user()?->can('create', $moduleConfig->modelClass) ?? false;
@endphp

@section('title', __('labels.bulk_upload_job') . ' #' . $job->id)

@section('header_data')
    @php
        $page_title = __('labels.bulk_upload_job') . ' #' . $job->id;
        $page_pretitle = $moduleDisplay;
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route($panelName.'.dashboard')],
        ['title' => $moduleDisplay, 'url' => route($panelName.'.'.$moduleKey.'.index')],
        ['title' => __('labels.bulk_upload_history'), 'url' => route("{$panelName}.{$moduleKey}.bulk-upload.jobs.index")],
        ['title' => '#' . $job->id, 'url' => null],
    ];
@endphp

@section($panelName.'-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            {{ __('labels.bulk_upload_job') }} #{{ $job->id }}
                            <span class="ms-2" id="bulk-status-badge">
                                <x-bulk-upload.status-badge :status="$job->status" id="bulk-status-text"/>
                            </span>
                        </h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                    <div class="card-actions d-flex gap-2">
                        <a id="bulk-download-errors-btn"
                           href="{{ route($panelName.'.'.$moduleKey.'.bulk-upload.jobs.errors', ['id' => $job->id]) }}"
                           class="btn btn-outline-warning @if(!($job->failed_count > 0 && $job->failed_rows_path)) d-none @endif"
                           data-bs-toggle="tooltip"
                           data-bs-placement="bottom"
                           data-bs-title="{{ $job->failedRowsExpiryLabel() ?? '' }}">
                            {{ __('labels.bulk_upload_download_errors') }}
                            <span class="badge bg-warning-lt ms-1" id="bulk-download-errors-count">{{ $job->failed_count }}</span>
                        </a>
                        @if($canCancel)
                            <button type="button"
                                    id="bulk-cancel-btn"
                                    class="btn btn-outline-danger @if($job->isTerminal()) d-none @endif">
                                {{ __('labels.bulk_upload_cancel') }}
                            </button>
                        @endif
                        <a href="{{ route($panelName.'.'.$moduleKey.'.bulk-upload.jobs.index') }}" class="btn btn-outline-secondary">
                            {{ __('labels.back') }}
                        </a>
                    </div>
                </div>
                <x-bulk-upload.job-detail :module-config="$moduleConfig" :job="$job" :recent-log="$recentLog" :panel-name="$panelName"/>
            </div>
        </div>
    </div>
@endsection
