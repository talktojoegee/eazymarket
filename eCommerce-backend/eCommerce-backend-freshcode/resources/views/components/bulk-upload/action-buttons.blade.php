@props([
    'module',
    'createPermission' => true,
    'showExport' => true,
    'layout' => 'buttons',
    'panelName' => 'admin',
])

@if($layout === 'dropdown-items')
    @if($createPermission)
        <li>
            <a class="dropdown-item" href="{{ route($panelName.'.'.$module.'.bulk-upload.page') }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-2 me-2" width="20" height="20"
                     viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2"/>
                    <path d="M7 9l5 -5l5 5"/>
                    <path d="M12 4l0 12"/>
                </svg>
                {{ __('labels.bulk_upload') }}
            </a>
        </li>
    @endif
    <li>
        <a class="dropdown-item" href="{{ route($panelName.'.'.$module.'.bulk-upload.jobs.index') }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-2 me-2" width="20" height="20"
                 viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                 stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M3 12a9 9 0 1 0 9 -9"/>
                <path d="M3 4.001v5h5"/>
                <path d="M12 7v5l3 3"/>
            </svg>
            {{ __('labels.bulk_upload_history') }}
        </a>
    </li>
    @if($showExport)
        <li>
            <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#{{ $module }}-export-modal">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-2 me-2" width="20" height="20"
                     viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M14 3v4a1 1 0 0 0 1 1h4"/>
                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/>
                    <path d="M12 17v-6"/>
                    <path d="M9.5 14.5l2.5 2.5l2.5 -2.5"/>
                </svg>
                {{ __('labels.bulk_upload_export_for_edit') }}
            </button>
        </li>
    @endif
@else
    @if($createPermission)
        <a href="{{ route($panelName.'.'.$module.'.bulk-upload.page') }}" class="btn btn-outline-success">
            {{ __('labels.bulk_upload') }}
        </a>
    @endif
    <a href="{{ route($panelName.'.'.$module.'.bulk-upload.jobs.index') }}" class="btn btn-outline-primary">
        {{ __('labels.bulk_upload_history') }}
    </a>
    @if($showExport)
        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#{{ $module }}-export-modal">
            {{ __('labels.bulk_upload_export_for_edit') }}
        </button>
    @endif
@endif
