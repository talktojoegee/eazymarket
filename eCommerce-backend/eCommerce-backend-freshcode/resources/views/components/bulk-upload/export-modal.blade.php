@props([
    'module',
    'filters' => [],
    'panelName' => 'admin',
])

@php
    $modalId = $module . '-export-modal';
@endphp

<div class="modal modal-blur fade bulk-upload-export-modal"
     id="{{ $modalId }}"
     tabindex="-1"
     role="dialog"
     aria-hidden="true"
     data-count-url="{{ route($panelName.'.'.$module.'.bulk-upload.export.count') }}">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form class="bulk-upload-export-form" method="GET" action="{{ route($panelName.'.'.$module.'.bulk-upload.export') }}">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('labels.bulk_upload_export_for_edit') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">{{ __('labels.bulk_upload_export_hint') }}</p>
                    @foreach($filters as $filter)
                        @php
                            $dependency = $filter['depends_on'] ?? null;
                            $dependencyValue = $dependency
                                ? (is_object($dependency['value']) ? $dependency['value']->value : $dependency['value'])
                                : null;
                        @endphp
                        <div class="mb-3 export-filter-row"
                             @if($dependency)
                                 data-depends-on="{{ $dependency['key'] }}"
                                 data-depends-value="{{ $dependencyValue }}"
                             @endif>
                            <label class="form-label" for="{{ $modalId }}-{{ $filter['key'] }}">{{ __($filter['label']) }}</label>
                            @if($filter['type'] === 'select')
                                <select id="{{ $modalId }}-{{ $filter['key'] }}"
                                        name="filters[{{ $filter['key'] }}]"
                                        class="form-select">
                                    @foreach($filter['options'] as $option)
                                        @php
                                            $value = is_object($option['value']) ? $option['value']->value : $option['value'];
                                        @endphp
                                        <option value="{{ $value }}">{{ __($option['label']) }}</option>
                                    @endforeach
                                </select>
                            @elseif($filter['type'] === 'select_remote')
                                <select id="{{ $modalId }}-{{ $filter['key'] }}"
                                        name="filters[{{ $filter['key'] }}]"
                                        class="form-select export-remote-select"
                                        data-remote-url="{{ route($filter['route']) }}">
                                    <option value="">{{ __('labels.all') }}</option>
                                </select>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="modal-footer">
                    <div class="me-auto text-muted small">
                        <span class="spinner-border spinner-border-sm me-1 d-none export-count-spinner" role="status" aria-hidden="true"></span>
                        <span class="export-count-text">{{ __('labels.bulk_upload_export_count_loading') }}</span>
                    </div>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                    <button type="submit" class="btn btn-primary export-submit">
                        {{ __('labels.download_csv') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            window.CATALOG_BULK_EXPORT_I18N = {
                loading: @json(__('labels.bulk_upload_export_count_loading')),
                matches: @json(__('labels.bulk_upload_export_count_matches')),
                failed: @json(__('labels.bulk_upload_export_count_failed')),
                no_match: @json(__('labels.bulk_upload_export_count_zero')),
            };
        </script>
        <script src="{{ hyperAsset('assets/admin/js/catalog-bulk-upload-export.js') }}" defer></script>
    @endpush
@endonce
