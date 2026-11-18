// Define a global namespace for datatable utilities
window.DatatableUtils = {
    // Public method to refresh a specific datatable by ID or all datatables
    refreshDatatable: function (tableId = null) {
        if (tableId && $.fn.DataTable.isDataTable('#' + tableId)) {
            // Refresh specific table if ID is provided and table exists
            $('#' + tableId).DataTable().ajax.reload(function () {
                console.log('Datatable ' + tableId + ' refreshed successfully');
                // Refresh lightbox for new content
                if (typeof refreshFsLightbox !== 'undefined') {
                    refreshFsLightbox();
                }
            }, false);
        } else {
            // Refresh all datatables if no ID provided or table not found
            const datatables = $.fn.dataTable.tables();
            if (datatables.length > 0) {
                $(datatables).each(function () {
                    $(this).DataTable().ajax.reload(function () {
                        console.log('Datatable refreshed successfully');
                        // Refresh lightbox for new content
                        if (typeof refreshFsLightbox !== 'undefined') {
                            refreshFsLightbox();
                        }
                    }, false);
                });
            }
        }
    }
};

function isDatabaseOrServerError(msg) {
    if (typeof msg !== 'string') return false;
    return /SQLSTATE|QueryException|PDOException|Connection:|SQL:|Unknown column|Reference '.*?not supported/i.test(msg);
}

function datatableErrorMessage(settings, techNote, message) {
    const xhr = settings?.jqXHR;
    const response = xhr?.responseJSON;
    const errors = response?.errors ? Object.values(response.errors).flat() : [];
    const validationError = errors.find(error => typeof error === 'string' && error.trim());

    if (validationError) return validationError;
    if (xhr?.status && xhr.status >= 500) return 'Failed to load';
    if (response?.message) {
        if (isDatabaseOrServerError(response.message)) return 'Failed to load';
        return response.message;
    }
    if (techNote === 1) return 'Failed to load';
    if (xhr?.status) {
        return 'Failed to load';
    }

    if (isDatabaseOrServerError(message)) return 'Failed to load';

    return 'Failed to load';
}

$.fn.dataTable.ext.errMode = function (settings, techNote, message) {
    const title = datatableErrorMessage(settings, techNote, message);

    if (typeof Toast !== 'undefined' && typeof Toast.fire === 'function') {
        Toast.fire({icon: 'error', title: title});
        return;
    }

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            icon: 'error',
            title: title,
        });
        return;
    }

    console.error(title);
};

const datatableExportStates = new Map();

function datatableExportToast(icon, title) {
    if (typeof Toast !== 'undefined' && typeof Toast.fire === 'function') {
        Toast.fire({icon: icon, title: title});
    }
}

function datatableExportDateState() {
    return {
        from: document.getElementById('datatableExportDateFrom')?.value || '',
        to: document.getElementById('datatableExportDateTo')?.value || '',
    };
}

function datatableExportPayload(state) {
    const dt = state.table;
    const params = dt ? (dt.ajax.params() || {}) : {};
    const order = Array.isArray(params.order) ? params.order[0] : null;
    const column = order && params.columns?.[order.column] ? params.columns[order.column].data : null;
    const ignored = new Set(['draw', 'start', 'length', 'search', 'order', 'columns', 'date_from', 'date_to']);
    const filters = {};
    Object.entries(params).forEach(([key, value]) => {
        if (!ignored.has(key) && value !== '' && value !== null && value !== undefined) filters[key] = value;
    });
    if (dt) {
        const ajaxUrl = new URL(dt.ajax.url(), window.location.origin);
        ajaxUrl.searchParams.forEach((value, key) => {
            if (!ignored.has(key) && value !== '') filters[key] = value;
        });
    }
    const dates = datatableExportDateState();
    const mode = document.getElementById('datatableExportColumnMode')?.value || 'visible';
    const allColumns = Object.keys(state.config.columns || {});
    let selectedColumns = allColumns;
    if (mode === 'visible') {
        const visible = state.config.visibleColumns || (dt ? dt.columns(':visible').dataSrc().toArray() : allColumns);
        selectedColumns = allColumns.filter(key => visible.includes(key));
        if (!selectedColumns.length) selectedColumns = allColumns;
    } else if (mode === 'custom') {
        selectedColumns = Array.from(document.querySelectorAll('#datatableExportColumns input:checked')).map(input => input.value);
    }
    return {
        module: state.config.module,
        scope: document.getElementById('datatableExportScope')?.value || 'all_filtered',
        format: 'csv',
        columns: selectedColumns,
        search: dt ? (params.search?.value || '') : (document.querySelector(state.config.searchSelector)?.value || ''),
        sort: dt ? column : state.config.sort,
        direction: dt ? (order?.dir || 'desc') : (state.config.direction || 'desc'),
        start: dt ? (params.start || 0) : 0,
        length: dt ? (params.length || 10) : (state.config.length || 50),
        date_from: dates.from || null,
        date_to: dates.to || null,
        selected_ids: Array.from(state.selected),
        filters: filters,
        context: state.config.context || {},
    };
}

function datatableExportError(error) {
    const errors = error?.response?.data?.errors;
    const fallback = window.activeDatatableExportState?.config?.labels?.error || 'Unable to prepare export.';
    return errors ? Object.values(errors).flat()[0] : error?.response?.data?.message || fallback;
}

function datatableExportRenderColumns(state) {
    const container = document.querySelector('#datatableExportColumns [data-export-column-list]');
    if (!container) return;
    container.innerHTML = '';
    Object.entries(state.config.columns || {}).forEach(([key, label]) => {
        const item = document.createElement('label');
        item.className = 'form-check mb-2';
        item.innerHTML = '<input class="form-check-input" type="checkbox" value="' + $('<div>').text(key).html() + '" checked><span class="form-check-label">' + $('<div>').text(label).html() + '</span>';
        container.appendChild(item);
    });
}

function datatableExportEstimate(state) {
    const estimate = document.getElementById('datatableExportEstimate');
    const submit = document.getElementById('datatableExportSubmit');
    if (!estimate || !submit) return;
    const payload = datatableExportPayload(state);
    if (!payload.columns.length || (payload.scope === 'selected' && !payload.selected_ids.length)) {
        estimate.className = 'me-auto text-warning small';
        estimate.textContent = payload.columns.length ? state.config.labels.selectRows : state.config.labels.selectColumns;
        submit.disabled = true;
        return;
    }
    estimate.className = 'me-auto text-secondary small';
    estimate.innerHTML = '<div class="d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span><span>' + $('<div>').text(state.config.labels.calculating).html() + '</span></div>';
    submit.disabled = true;
    axios.post(state.config.estimateUrl, payload).then(response => {
        const data = response.data.data;
        const mode = data.queued ? state.config.labels.queuedShort : state.config.labels.immediateShort;
        estimate.className = 'me-auto text-secondary small';
        estimate.textContent = state.config.labels.itemsMatch.replace(':count', data.count.toLocaleString()) + ' · ' + mode;
        submit.disabled = false;
    }).catch(error => {
        estimate.className = 'me-auto text-danger small';
        estimate.textContent = datatableExportError(error);
    });
}

function openDatatableExportState(state) {
    if (!state) return;
    const supportedScopes = state.config.supportedScopes || ['current_page', 'all_filtered', 'all_records', 'selected'];
    const scope = document.getElementById('datatableExportScope');
    Array.from(scope?.options || []).forEach(option => {
        const supported = supportedScopes.includes(option.value);
        option.hidden = !supported;
        option.disabled = !supported;
    });
    if (!supportedScopes.includes(scope?.value)) {
        scope.value = supportedScopes.includes('all_filtered') ? 'all_filtered' : supportedScopes[0];
    }
    const count = document.querySelector('[data-selected-count]');
    if (count) {
        count.textContent = state.selected.size;
    }
    document.getElementById('datatableExportSelection')?.classList.toggle(
        'd-none',
        state.config.selectionSupport === false || (state.selected.size === 0 && scope?.value !== 'selected'),
    );
    const clear = document.getElementById('datatableExportClearSelection');
    if (clear) {
        clear.disabled = state.selected.size === 0;
    }
    datatableExportRenderColumns(state);
    document.getElementById('datatableExportColumnMode').value = 'visible';
    document.getElementById('datatableExportColumns').classList.add('d-none');
    document.getElementById('datatableExportDateLabel').textContent = state.config.dateLabel;
    document.getElementById('datatableExportDateRange').value = '';
    document.getElementById('datatableExportDateFrom').value = '';
    document.getElementById('datatableExportDateTo').value = '';
    document.getElementById('datatableExportCustomDates').classList.add('d-none');
    window.activeDatatableExportState = state;
    document.getElementById('datatableExportModalTrigger')?.click();
    datatableExportEstimate(state);
}

function openDatatableExport(table) {
    openDatatableExportState(datatableExportStates.get(table.table().node().id));
}

$(document).ready(function () {

    const exportModal = document.getElementById('datatableExportModal');
    const exportModalTrigger = document.getElementById('datatableExportModalTrigger');
    if (exportModalTrigger && exportModalTrigger.parentElement !== document.body) document.body.appendChild(exportModalTrigger);
    if (exportModal && exportModal.parentElement !== document.body) document.body.appendChild(exportModal);


    // Show spinner on AJAX start, hide on complete
    $(document).on('ajaxStart', function () {
        $('#datatable-loading').removeClass('d-none');
    }).on('ajaxStop', function () {
        $('#datatable-loading').addClass('d-none');
    });
    // Reset filters
    $('#resetFilters').on('click', function () {
        $('#statusFilter, #brandFilter, #categoryFilter').val('');
        $('#reportrange').val('');
        // Trigger filter update if needed
    });


    const datatableElements = document.querySelectorAll('[data-datatable]');

    datatableElements.forEach((element) => {
        const route = element.getAttribute('data-route');
        const columns = JSON.parse(element.getAttribute('data-columns'));
        const exportOptions = JSON.parse(element.getAttribute('data-export-options') || '{}');
        const exportEnabled = exportOptions.module && exportOptions.canExport;
        const selectedRows = new Set();
        if (exportEnabled) datatableExportStates.set(element.id, {config: exportOptions, selected: selectedRows, table: null});
        // Read the custom options, if they exist.
        const rawOptions = element.getAttribute('data-options');
        let customOptions = rawOptions ? JSON.parse(rawOptions) : {};

        // Check if the DataTable is already initialized
        if ($.fn.DataTable.isDataTable(element)) {
            return; // Skip initialization if already initialized
        }

        const legacyExportButton = {
            extend: 'collection',
            text: '<i class="ti ti-download me-1"></i><span>Export</span>',
            className: 'btn',
            buttons: [{extend: 'csvHtml5', text: '<i class="ti ti-file-type-csv me-2 text-success"></i><span>CSV</span>', exportOptions: {columns: ':visible'}}],
            init: function (dt, node) {
                $(node).removeClass('btn-secondary').addClass('btn btn-sm ms-2 rounded-2 dt-toolbar-btn dropdown-toggle');
            }
        };
        const advancedExportButton = {
            text: '<i class="ti ti-download me-1"></i><span>Export</span>',
            action: function (event, dt) { openDatatableExport(dt); },
            init: function (dt, node) {
                $(node).removeClass('btn-secondary').addClass('btn btn-sm ms-2 rounded-2 dt-toolbar-btn');
            }
        };
        let defaultOptions = {
            language: {
                emptyTable: "<div class='text-center text-secondary p-4'><i class='ti ti-database fs-1'></i><br>No data available.</div>",
                lengthMenu: "Show _MENU_ per page",
                info: "_START_-_END_ of _TOTAL_",
                infoEmpty: "0-0 of 0",
                infoFiltered: "",
                paginate: {
                    previous: "<i class='ti ti-arrow-left'></i>",
                    next: "<i class='ti ti-arrow-right'></i>"
                }
            },
            layout: {
                topStart: {
                    buttons: [
                        {
                            extend: 'colvis',
                            text: '<i class="ti ti-columns-3 me-1"></i><span>Columns</span>',
                            columns: ':not(:first-child)',
                            columnText: function (dt, idx, title) {
                                return idx + ': ' + title;
                            },
                            init: function (dt, node) {
                                $(node)
                                    .removeClass('btn-secondary')
                                    .addClass('btn btn-sm rounded-2 dt-toolbar-btn dropdown-toggle');
                            }
                        },
                        ...(exportEnabled ? [advancedExportButton] : exportOptions.module ? [] : [legacyExportButton]),
                    ]
                },
                topEnd: 'search',
                bottomStart: 'pageLength',
                bottomEnd: {
                    info: {},
                    paging: {}
                }
            },
            initComplete: function () {
                const $search = $('.dt-search input');
                $search.removeClass('form-control-sm')
                    .addClass('ms-0')
                    .attr('placeholder', 'Search...');
                $('.dt-search label').contents().filter(function () {
                    return this.nodeType === 3;
                }).remove();

                $('.dt-length select').removeClass('form-select-sm');
            },
            select: false,
            responsive: true,
            processing: true,
            serverSide: true,
            scrollX: true,
            pagingType: 'simple_numbers',
            ajax: {
                url: route,
            },
            columns: columns,
            drawCallback: function() {
                const api = this.api();
                const info = api.page.info();

                if (info.page > 0 && info.pages > 0 && info.page >= info.pages) {
                    api.page(info.pages - 1).draw('page');
                    return;
                }

                if (typeof refreshFsLightbox !== 'undefined') {
                    refreshFsLightbox();
                }

                // Re-initialize Bootstrap tooltips for dynamically rendered rows
                if (typeof bootstrap !== 'undefined') {
                    api.table().container().querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                        if (!bootstrap.Tooltip.getInstance(el)) {
                            new bootstrap.Tooltip(el);
                        }
                    });
                }
                if (exportEnabled) {
                    const headerCell = api.column(0).header();
                    if (!headerCell) return;
                    let selectAll = headerCell.querySelector('[data-export-select-all]') || headerCell.querySelector('input[type="checkbox"]');
                    if (!selectAll) {
                        selectAll = document.createElement('input');
                        selectAll.type = 'checkbox';
                        selectAll.className = 'form-check-input me-2';
                        headerCell.prepend(selectAll);
                    }
                    selectAll.setAttribute('data-export-select-all', '');
                    selectAll.setAttribute('aria-label', exportOptions.labels.selectRows);
                    api.rows({page: 'current'}).every(function () {
                        const data = this.data();
                        const id = Number(data?.[exportOptions.rowId || 'id']);
                        if (!id) return;
                        const firstCell = this.node().cells[0];
                        if (!firstCell) return;
                        let checkbox = firstCell.querySelector('[data-export-select]');
                        if (!checkbox) {
                            checkbox = firstCell.querySelector('input[type="checkbox"]');
                        }
                        if (!checkbox) {
                            checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.className = 'form-check-input me-2';
                            checkbox.setAttribute('aria-label', exportOptions.labels.selectRows);
                            firstCell.prepend(checkbox);
                        }
                        checkbox.setAttribute('data-export-select', '');
                        checkbox.value = id;
                        checkbox.checked = selectedRows.has(id);
                    });
                    const pageIds = api.rows({page: 'current'}).data().toArray().map(row => Number(row?.[exportOptions.rowId || 'id'])).filter(Boolean);
                    selectAll.checked = pageIds.length > 0 && pageIds.every(id => selectedRows.has(id));
                    selectAll.indeterminate = pageIds.some(id => selectedRows.has(id)) && !selectAll.checked;
                }
            }
        };

        // Merge custom options with defaultOptions.
        // If customOptions is an array, merge each object into defaultOptions.
        if (Array.isArray(customOptions)) {
            customOptions.forEach(option => {
                defaultOptions = $.extend(true, {}, defaultOptions, option);
            });
        } else {
            defaultOptions = $.extend(true, {}, defaultOptions, customOptions);
        }
        const table = $(element).DataTable(defaultOptions);
        if (exportEnabled) {
            datatableExportStates.get(element.id).table = table;
            const tableContainer = table.table().container();
            $(tableContainer).on('change.datatableExport', '[data-export-select]', function () {
                const id = Number(this.value);
                this.checked ? selectedRows.add(id) : selectedRows.delete(id);
            });
            $(tableContainer).on('change.datatableExport', '[data-export-select-all]', function (event) {
                table.rows({page: 'current'}).every(function () {
                    const id = Number(this.data()?.[exportOptions.rowId || 'id']);
                    if (!id) return;
                    event.target.checked ? selectedRows.add(id) : selectedRows.delete(id);
                    const checkbox = this.node()?.querySelector('[data-export-select]');
                    if (checkbox) checkbox.checked = event.target.checked;
                });
            });
        }
    });

    document.querySelectorAll('[data-export-trigger]').forEach(trigger => {
        const config = JSON.parse(trigger.getAttribute('data-export-options') || '{}');
        const state = {config: config, selected: new Set(), table: null};
        trigger.addEventListener('click', () => openDatatableExportState(state));
    });

    $('#datatableExportColumnMode').on('change', function () {
        $('#datatableExportColumns').toggleClass('d-none', this.value !== 'custom');
        if (window.activeDatatableExportState) datatableExportEstimate(window.activeDatatableExportState);
    });
    $('#datatableExportSelectAllColumns, #datatableExportClearColumns').on('click', function () {
        const checked = this.id === 'datatableExportSelectAllColumns';
        $('#datatableExportColumns [data-export-column-list] input').prop('checked', checked);
        if (window.activeDatatableExportState) datatableExportEstimate(window.activeDatatableExportState);
    });
    $('#datatableExportModal').on('change', '#datatableExportScope, #datatableExportDateFrom, #datatableExportDateTo, #datatableExportColumns input', function () {
        const state = window.activeDatatableExportState;
        if (!state) return;
        if (this.id === 'datatableExportScope') {
            $('#datatableExportSelection').toggleClass('d-none', state.config.selectionSupport === false || (state.selected.size === 0 && this.value !== 'selected'));
        }
        datatableExportEstimate(state);
    });
    $('#datatableExportDateRange').on('change', function () {
        const custom = this.value === 'custom';
        $('#datatableExportCustomDates').toggleClass('d-none', !custom);
        if (!custom) {
            const today = this.dataset.today;
            $('#datatableExportDateTo').val(this.value ? today : '');
            if (this.value) {
                const date = new Date(today + 'T00:00:00Z');
                date.setUTCDate(date.getUTCDate() - Number(this.value) + 1);
                $('#datatableExportDateFrom').val(date.toISOString().slice(0, 10));
            } else {
                $('#datatableExportDateFrom').val('');
            }
            if (window.activeDatatableExportState) datatableExportEstimate(window.activeDatatableExportState);
        }
    });
    $('#datatableExportClearSelection').on('click', function () {
        const state = window.activeDatatableExportState;
        if (!state) return;
        state.selected.clear();
        state.table?.draw(false);
        const count = document.querySelector('[data-selected-count]');
        if (count) count.textContent = '0';
        this.disabled = true;
        datatableExportEstimate(state);
    });
    $('#datatableExportSubmit').on('click', function () {
        const state = window.activeDatatableExportState;
        if (!state) return;
        const button = this;
        button.disabled = true;
        axios.post(state.config.createUrl, datatableExportPayload(state)).then(response => {
            const data = response.data.data;
            document.querySelector('#datatableExportModal [data-bs-dismiss="modal"]')?.click();
            datatableExportToast('success', response.data.message);
            state.selected.clear();
            state.table?.draw(false);
            if (data?.download_url) window.location.assign(data.download_url);
        }).catch(error => {
            datatableExportToast('error', datatableExportError(error));
        }).finally(() => { button.disabled = false; });
    });
});
$(document).ready(function () {
    // Add click event listener for refresh button
    $('#refresh, .refresh-table').on('click', function () {
        // Try to find the closest datatable to the clicked button
        const closestTable = $(this).closest('.card').find('table.dataTable');

        if (closestTable.length > 0) {
            // If found, refresh only that table
            const tableId = closestTable.attr('id');
            if (tableId) {
                // Use the global method to refresh the specific table
                window.DatatableUtils.refreshDatatable(tableId);
                return;
            }
        }

        // If no specific table found, refresh all datatables
        window.DatatableUtils.refreshDatatable();
    });
});
