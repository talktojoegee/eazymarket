<button type="button" class="d-none" id="datatableExportModalTrigger" data-bs-toggle="modal" data-bs-target="#datatableExportModal" aria-hidden="true" tabindex="-1"></button>
<div class="modal modal-blur fade" id="datatableExportModal" tabindex="-1" aria-labelledby="datatableExportTitle" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="datatableExportTitle">{{ __('labels.export_data') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary mb-4">{{ __('labels.export_help') }}</p>

                <div class="mb-3">
                    <label class="form-label" for="datatableExportScope">{{ __('labels.export_scope') }}</label>
                    <select class="form-select" id="datatableExportScope">
                        <option value="current_page">{{ __('labels.current_page') }}</option>
                        <option value="all_filtered" selected>{{ __('labels.all_filtered_results') }}</option>
                        <option value="all_records">{{ __('labels.all_records') }}</option>
                        <option value="selected">{{ __('labels.selected_rows') }}</option>
                    </select>
                </div>

                <div class="mb-3 d-none" id="datatableExportSelection">
                    <div class="d-flex align-items-center justify-content-between rounded border p-2">
                        <span class="text-secondary"><span data-selected-count>0</span> {{ __('labels.selected_rows') }}</span>
                        <button type="button" class="btn btn-sm btn-ghost-secondary" id="datatableExportClearSelection">
                            <i class="ti ti-square-x me-1"></i>{{ __('labels.clear_selection') }}
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="datatableExportDateRange" id="datatableExportDateLabel">{{ __('labels.date_range') }}</label>
                    <select class="form-select" id="datatableExportDateRange" data-today="{{ now()->toDateString() }}">
                        <option value="">{{ __('labels.all_dates') }}</option>
                        <option value="7">{{ __('labels.last_7_days') }}</option>
                        <option value="30">{{ __('labels.last_30_days') }}</option>
                        <option value="365">{{ __('labels.last_365_days') }}</option>
                        <option value="custom">{{ __('labels.custom_range') }}</option>
                    </select>
                </div>

                <div class="row g-2 mb-3 d-none" id="datatableExportCustomDates">
                    <div class="col-sm-6">
                        <label class="form-label" for="datatableExportDateFrom">{{ __('labels.from_date') }}</label>
                        <input class="form-control" id="datatableExportDateFrom" type="date" max="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="datatableExportDateTo">{{ __('labels.to_date') }}</label>
                        <input class="form-control" id="datatableExportDateTo" type="date" max="{{ now()->toDateString() }}">
                    </div>
                </div>

                <div>
                    <label class="form-label" for="datatableExportColumnMode">{{ __('labels.columns') }}</label>
                    <select class="form-select" id="datatableExportColumnMode">
                        <option value="visible">{{ __('labels.visible_columns') }}</option>
                        <option value="all">{{ __('labels.all_exportable_columns') }}</option>
                        <option value="custom">{{ __('labels.custom_columns') }}</option>
                    </select>
                    <div class="border rounded p-3 mt-2 d-none" id="datatableExportColumns">
                        <div class="d-flex justify-content-end gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-ghost-primary" id="datatableExportSelectAllColumns">{{ __('labels.select_all') }}</button>
                            <button type="button" class="btn btn-sm btn-ghost-secondary" id="datatableExportClearColumns">{{ __('labels.deselect_all') }}</button>
                        </div>
                        <div class="overflow-auto" data-export-column-list style="max-height: 180px"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="me-auto text-secondary small" role="status" id="datatableExportEstimate">{{ __('labels.calculating_export') }}</div>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="datatableExportSubmit">
                    <i class="ti ti-file-download me-1"></i>{{ __('labels.download_format', ['format' => 'CSV']) }}
                </button>
            </div>
        </div>
    </div>
</div>
