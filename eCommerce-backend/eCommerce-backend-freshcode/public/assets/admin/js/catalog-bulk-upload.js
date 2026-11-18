'use strict';

(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const cfg = window.CATALOG_BULK_UPLOAD_CFG || {};
        const previewForm = document.getElementById('bulk-upload-preview-form');
        const confirmForm = document.getElementById('bulk-upload-confirm-form');
        if (!previewForm || !confirmForm || !cfg.previewUrl || !cfg.confirmUrl) return;

        const elements = {
            input: document.getElementById('catalog-bulk-csv-file'),
            previewSubmit: document.getElementById('bulk-upload-preview-submit'),
            previewTable: document.getElementById('bulk-upload-preview-table'),
            previewSummary: document.getElementById('bulk-upload-preview-summary'),
            previewToken: document.getElementById('preview-token'),
            originalFilename: document.getElementById('original-filename'),
            seller: document.getElementById('bulk-upload-seller'),
            confirmSeller: document.getElementById('bulk-confirm-seller-id'),
            mappingRows: document.getElementById('mapping-rows'),
            step1: document.getElementById('bulk-upload-step1'),
            step2: document.getElementById('bulk-upload-step2'),
            confirmSubmit: document.getElementById('bulk-upload-confirm-submit'),
            reset: document.getElementById('bulk-upload-reset'),
            operation: document.getElementById('bulk-operation-select'),
            matchRow: document.getElementById('bulk-match-by-row'),
            matchSelect: document.getElementById('bulk-match-by-select'),
            missingMedia: document.getElementById('bulk-upload-missing-media'),
            missingMediaTitle: document.getElementById('bulk-upload-missing-media-title'),
            missingMediaHeader: document.getElementById('bulk-upload-missing-media-header'),
            missingMediaIcon: document.getElementById('bulk-upload-missing-media-icon'),
            missingMediaCount: document.getElementById('bulk-upload-missing-media-count'),
            missingMediaMode: document.getElementById('bulk-upload-missing-media-mode'),
            missingMediaCopy: document.getElementById('bulk-upload-missing-media-copy'),
            missingMediaGuidance: document.getElementById('bulk-upload-missing-media-guidance'),
            missingMediaShowing: document.getElementById('bulk-upload-missing-media-showing'),
            missingMediaList: document.getElementById('bulk-upload-missing-media-list'),
            missingMediaMore: document.getElementById('bulk-upload-missing-media-more'),
        };
        const i18n = cfg.i18n || {};
        let csvHeaders = [];
        let suggestedMapping = {};
        let missingPrimaryMedia = null;
        let mediaPreviews = { columns: [], items: [] };
        let filePond = null;

        if (elements.input && window.FilePond) {
            const plugins = [window.FilePondPluginFileValidateType, window.FilePondPluginFileValidateSize].filter(Boolean);
            if (plugins.length) window.FilePond.registerPlugin(...plugins);
            filePond = window.FilePond.find(elements.input) || window.FilePond.create(elements.input, {
                allowImagePreview: false,
                credits: false,
                storeAsFile: true,
                maxFileSize: cfg.maxFileSize || '100MB',
                acceptedFileTypes: ['text/csv', 'text/plain', 'application/vnd.ms-excel'],
                labelIdle: elements.input.dataset.labelIdle,
            });
        }

        function refreshSellerLinks() {
            const sellerId = elements.seller ? elements.seller.value : (cfg.sellerId || '');
            document.querySelectorAll('.product-reference-link').forEach(function (link) {
                link.classList.toggle('disabled', !sellerId);
                const separator = String(link.dataset.baseUrl || '').includes('?') ? '&' : '?';
                link.href = sellerId ? link.dataset.baseUrl + separator + 'seller_id=' + encodeURIComponent(sellerId) : '#';
            });
        }

        if (elements.seller) elements.seller.addEventListener('change', refreshSellerLinks);
        refreshSellerLinks();

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function (character) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
            });
        }

        function normalize(value) {
            return String(value ?? '').toLowerCase().replace(/[^a-z0-9]+/g, '');
        }

        function messageFromError(error) {
            const response = error && error.response && error.response.data;
            if (response && response.message) return response.message;
            if (response && response.data && typeof response.data === 'object') {
                const first = Object.values(response.data).flat()[0];
                if (first) return first;
            }
            return i18n.unexpected_error || 'Unexpected error';
        }

        function toast(icon, message) {
            if (window.Toast && typeof window.Toast.fire === 'function') {
                window.Toast.fire({ icon: icon, title: message });
            }
        }

        function setBusy(button, busy) {
            if (!button) return;
            button.disabled = busy;
            const spinner = button.querySelector('.spinner-border');
            const label = button.querySelector('.preview-text, .confirm-text');
            if (spinner) spinner.classList.toggle('d-none', !busy);
            if (label) label.classList.toggle('d-none', busy);
        }

        function inferredHeader(field) {
            const serverSuggestion = suggestedMapping[field.key];
            if (serverSuggestion && csvHeaders.includes(serverSuggestion)) return serverSuggestion;
            const candidates = [field.key, field.label].concat(field.aliases || []).map(normalize);
            return csvHeaders.find(function (header) {
                return candidates.includes(normalize(header));
            }) || '';
        }

        function optionMarkup(selected) {
            const placeholder = '<option value="">' + escapeHtml(i18n.pick_column || '—') + '</option>';
            return placeholder + csvHeaders.map(function (header) {
                const isSelected = header === selected ? ' selected' : '';
                return '<option value="' + escapeHtml(header) + '"' + isSelected + '>' + escapeHtml(header) + '</option>';
            }).join('');
        }

        function renderPreview(data) {
            csvHeaders = Array.isArray(data.headers) ? data.headers.slice() : [];
            suggestedMapping = data.suggested_mapping || data.mapping || {};
            missingPrimaryMedia = data.missing_primary_media || null;
            mediaPreviews = data.media_previews || { columns: [], items: [] };
            const rows = Array.isArray(data.rows) ? data.rows : [];
            const totalRows = Number(data.total_rows || 0);
            const head = elements.previewTable.querySelector('thead');
            const body = elements.previewTable.querySelector('tbody');
            head.innerHTML = '<tr><th>#</th>' + csvHeaders.map(function (header) {
                return '<th>' + escapeHtml(header) + '</th>';
            }).join('') + '</tr>';
            body.innerHTML = rows.map(function (row, rowIndex) {
                let values;
                if (Array.isArray(row)) {
                    values = row;
                } else {
                    values = csvHeaders.map(function (header) { return row[header] ?? ''; });
                }
                const cells = csvHeaders.map(function (_, columnIndex) {
                    const value = values[columnIndex] ?? '';
                    const isMediaColumn = Array.isArray(mediaPreviews.columns) && mediaPreviews.columns.includes(columnIndex);
                    const previews = Array.isArray(mediaPreviews.items) ? mediaPreviews.items.filter(function (item) {
                        return Number(item.row_index) === rowIndex && Number(item.column_index) === columnIndex;
                    }) : [];
                    if (!isMediaColumn || !value) return '<td>' + escapeHtml(value) + '</td>';
                    if (!previews.length) {
                        return '<td><div class="text-break small">' + escapeHtml(value) + '</div><span class="badge bg-secondary-lt mt-1">'
                            + escapeHtml(i18n.preview_unavailable) + '</span></td>';
                    }
                    return '<td><div class="d-flex flex-wrap align-items-center gap-2">' + previews.map(function (preview) {
                        const url = escapeHtml(preview.url);
                        return '<a href="' + url + '" data-fslightbox="catalog-bulk-upload-preview" data-type="image" '
                            + 'class="d-inline-flex align-items-center text-decoration-none bulk-media-preview-link" '
                            + 'aria-label="' + escapeHtml(i18n.open_image) + '"><img src="' + url
                            + '" alt="' + escapeHtml(i18n.image_preview) + '" width="56" height="56" loading="lazy" '
                            + 'referrerpolicy="no-referrer" class="rounded border object-fit-cover bulk-media-preview-image"></a>';
                    }).join('') + '<div class="badge bg-secondary-lt d-none bulk-media-preview-error">'
                        + escapeHtml(i18n.preview_unavailable) + '</div></div></td>';
                }).join('');
                return '<tr><td class="text-muted">' + (rowIndex + 1) + '</td>' + cells + '</tr>';
            }).join('');
            body.querySelectorAll('.bulk-media-preview-image').forEach(function (image) {
                image.addEventListener('error', function () {
                    const link = image.closest('.bulk-media-preview-link');
                    if (link) link.classList.add('d-none');
                    const error = image.closest('td').querySelector('.bulk-media-preview-error');
                    if (error) error.classList.remove('d-none');
                });
            });
            if (typeof window.refreshFsLightbox === 'function') window.refreshFsLightbox();
            const template = rows.length < totalRows ? i18n.preview_showing : i18n.preview_total;
            const ignoredBlankRows = Number(data.ignored_blank_rows || 0);
            const blankRowsSummary = ignoredBlankRows > 0
                ? ' ' + String(i18n.blank_rows_ignored || '').replace(':count', ignoredBlankRows)
                : '';
            elements.previewSummary.textContent = String(template || '')
                .replace(':shown', rows.length)
                .replace(':total', totalRows) + blankRowsSummary + ' — ' + (data.original_filename || '');
            const grouped = data.grouped_summary || {};
            if (Object.keys(grouped).length) {
                elements.previewSummary.textContent += ' · ' + String(i18n.product_summary || '')
                    .replace(':products', Number(grouped.total_records || 0))
                    .replace(':variants', Number(grouped.variant_count || 0))
                    .replace(':locations', Number(grouped.location_count || 0));
                if (Array.isArray(grouped.non_contiguous) && grouped.non_contiguous.length) {
                    const warning = document.createElement('div');
                    warning.className = 'alert alert-danger mt-3 mb-0';
                    warning.textContent = String(i18n.non_contiguous || '').replace(':count', grouped.non_contiguous.length);
                    elements.previewSummary.appendChild(warning);
                }
                const duplicateCount = (Array.isArray(grouped.duplicate_skus) ? grouped.duplicate_skus.length : 0)
                    + (Array.isArray(grouped.duplicate_barcodes) ? grouped.duplicate_barcodes.length : 0);
                if (duplicateCount) {
                    const warning = document.createElement('div');
                    warning.className = 'alert alert-warning mt-3 mb-0';
                    warning.textContent = String(i18n.duplicate_identifiers || '').replace(':count', duplicateCount);
                    elements.previewSummary.appendChild(warning);
                }
            }
            elements.previewToken.value = data.preview_token || '';
            elements.originalFilename.value = data.original_filename || '';
        }

        function renderMappingRows() {
            const fields = Array.isArray(cfg.availableFields) ? cfg.availableFields : [];
            elements.mappingRows.innerHTML = fields.map(function (field) {
                const required = ' <span class="text-danger bulk-mapping-required' + (field.required ? '' : ' d-none') + '">*</span>';
                const selected = inferredHeader(field);
                return '<div class="mb-2 row align-items-center" data-field-key="' + escapeHtml(field.key) + '">'
                    + '<label class="col-sm-5 col-form-label">' + escapeHtml(field.label) + required + '</label>'
                    + '<div class="col-sm-7"><select class="form-select form-select-sm" name="mapping['
                    + escapeHtml(field.key) + ']" data-required="' + (field.required ? '1' : '0') + '">'
                    + optionMarkup(selected) + '</select></div></div>';
            }).join('');
        }

        function isUpdateMode() {
            return Boolean(elements.operation && elements.operation.value === 'update');
        }

        function refreshMissingMedia() {
            if (!elements.missingMedia) return;
            const total = Number(missingPrimaryMedia && missingPrimaryMedia.total_count || 0);
            elements.missingMedia.classList.toggle('d-none', total === 0);
            if (!total) return;
            const entries = Array.isArray(missingPrimaryMedia.entries) ? missingPrimaryMedia.entries : [];
            const updateMode = isUpdateMode();
            const primaryField = entries.length ? entries[0].field : '';
            const field = (cfg.availableFields || []).find(function (item) { return item.key === primaryField; });
            const fieldName = field ? field.label : primaryField;
            elements.missingMedia.classList.toggle('border-warning', !updateMode);
            elements.missingMedia.classList.toggle('border-info', updateMode);
            elements.missingMediaHeader.classList.toggle('bg-warning-lt', !updateMode);
            elements.missingMediaHeader.classList.toggle('bg-info-lt', updateMode);
            elements.missingMediaIcon.classList.toggle('bg-warning', !updateMode);
            elements.missingMediaIcon.classList.toggle('bg-info', updateMode);
            elements.missingMediaTitle.textContent = String(updateMode ? i18n.missing_media_update_title : i18n.missing_media_title)
                .replace(':count', total);
            elements.missingMediaCount.textContent = String(i18n.missing_media_rows_count || '').replace(':count', total);
            elements.missingMediaCount.className = 'badge ' + (updateMode ? 'bg-info text-info-fg' : 'bg-warning text-dark');
            elements.missingMediaMode.textContent = updateMode ? i18n.missing_media_update_mode : i18n.missing_media_create_mode;
            elements.missingMediaMode.className = 'badge bg-body border ' + (updateMode ? 'text-info border-info' : 'text-dark border-warning');
            elements.missingMediaCopy.textContent = String(updateMode ? i18n.missing_media_update : i18n.missing_media_create)
                .replace(':field', fieldName);
            elements.missingMediaGuidance.textContent = String(updateMode ? i18n.missing_media_update_guidance : i18n.missing_media_create_guidance)
                .replace(':field', fieldName);
            elements.missingMediaGuidance.className = 'alert py-2 mb-3 ' + (updateMode ? 'alert-info' : 'alert-warning text-dark');
            elements.missingMediaShowing.textContent = String(i18n.missing_media_showing || '')
                .replace(':shown', entries.length)
                .replace(':total', total);
            elements.missingMediaList.innerHTML = entries.map(function (entry) {
                const row = entry.row_number ?? entry.row ?? '';
                const title = entry.title || i18n.untitled_row;
                const metadata = (cfg.availableFields || []).find(function (item) { return item.key === entry.field; });
                const missingField = metadata ? metadata.label : entry.field || '';
                return '<tr><td class="text-muted">' + escapeHtml(row) + '</td><td class="fw-medium">'
                    + escapeHtml(title) + '</td><td><span class="badge bg-secondary-lt">'
                    + escapeHtml(missingField) + '</span></td></tr>';
            }).join('');
            const truncated = Boolean(missingPrimaryMedia.truncated);
            elements.missingMediaMore.classList.toggle('d-none', !truncated);
            elements.missingMediaMore.textContent = String(i18n.missing_media_more || '').replace(':count', Math.max(0, total - entries.length));
        }

        function refreshMatchFieldRow() {
            const existing = document.getElementById('mapping-match-field-row');
            if (existing) existing.remove();
            if (!isUpdateMode() || !elements.matchSelect) return;
            const matchKey = elements.matchSelect.value;
            if (!matchKey) return;
            const regularField = (cfg.availableFields || []).some(function (field) { return field.key === matchKey; });
            if (regularField) return;
            const metadata = (cfg.availableMatchFields || []).find(function (field) { return field.key === matchKey; });
            const selected = inferredHeader(metadata || { key: matchKey, label: matchKey });
            const row = document.createElement('div');
            row.id = 'mapping-match-field-row';
            row.className = 'mb-2 row align-items-center bg-info-lt rounded p-2';
            row.innerHTML = '<label class="col-sm-5 col-form-label">' + escapeHtml(metadata ? metadata.label : matchKey)
                + ' <span class="text-danger">*</span><div class="small text-muted">'
                + escapeHtml(i18n.match_key_hint || '') + '</div></label><div class="col-sm-7"><select '
                + 'class="form-select form-select-sm" name="mapping[' + escapeHtml(matchKey) + ']" data-match-field="1">'
                + optionMarkup(selected) + '</select></div>';
            elements.mappingRows.insertBefore(row, elements.mappingRows.firstChild);
        }

        function refreshMappingRequirements() {
            const matchKey = isUpdateMode() && elements.matchSelect ? elements.matchSelect.value : '';
            const fields = Array.isArray(cfg.availableFields) ? cfg.availableFields : [];
            fields.forEach(function (field) {
                const row = elements.mappingRows.querySelector('[data-field-key="' + CSS.escape(field.key) + '"]');
                const marker = row && row.querySelector('.bulk-mapping-required');
                if (marker) marker.classList.toggle('d-none', isUpdateMode() ? field.key !== matchKey : !field.required);
            });
        }

        function refreshMode() {
            if (elements.matchRow) elements.matchRow.classList.toggle('d-none', !isUpdateMode());
            refreshMatchFieldRow();
            refreshMappingRequirements();
            refreshMissingMedia();
        }

        previewForm.addEventListener('submit', function (event) {
            event.preventDefault();
            setBusy(elements.previewSubmit, true);
            axios.post(cfg.previewUrl, new FormData(previewForm), {
                headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' },
            }).then(function (response) {
                const json = response.data || {};
                if (!json.success || !json.data) {
                    toast('error', json.message || i18n.upload_failed || 'Upload failed');
                    return;
                }
                renderPreview(json.data);
                if (elements.confirmSeller) elements.confirmSeller.value = elements.seller ? elements.seller.value : (cfg.sellerId || '');
                if (elements.operation && json.data.suggested_operation) {
                    const suggestedOption = elements.operation.querySelector('option[value="' + CSS.escape(json.data.suggested_operation) + '"]');
                    if (suggestedOption) elements.operation.value = json.data.suggested_operation;
                }
                if (elements.matchSelect && json.data.suggested_match_field) {
                    const suggestedMatchOption = elements.matchSelect.querySelector('option[value="' + CSS.escape(json.data.suggested_match_field) + '"]');
                    if (suggestedMatchOption) elements.matchSelect.value = json.data.suggested_match_field;
                }
                renderMappingRows();
                refreshMode();
                elements.step2.classList.remove('d-none');
                document.dispatchEvent(new CustomEvent('catalog-bulk-preview:ready', { detail: json.data }));
                elements.step2.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }).catch(function (error) {
                toast('error', messageFromError(error));
            }).finally(function () {
                setBusy(elements.previewSubmit, false);
            });
        });

        if (elements.operation) elements.operation.addEventListener('change', refreshMode);
        if (elements.matchSelect) elements.matchSelect.addEventListener('change', function () {
            refreshMatchFieldRow();
            refreshMappingRequirements();
        });

        confirmForm.addEventListener('submit', function (event) {
            event.preventDefault();
            if (cfg.module === 'products' && window.CATALOG_PRODUCT_PREVIEW_CAN_QUEUE !== true) {
                toast('error', i18n.product_preview_stale);
                return;
            }
            const requiredKey = isUpdateMode() && elements.matchSelect ? elements.matchSelect.value : (cfg.requiredCreateMapping || 'title');
            const requiredSelect = confirmForm.querySelector('select[name="mapping[' + CSS.escape(requiredKey) + ']"]');
            if (requiredSelect && !requiredSelect.value) {
                toast('error', i18n.map_required || 'Choose a CSV column.');
                requiredSelect.focus();
                return;
            }
            setBusy(elements.confirmSubmit, true);
            axios.post(cfg.confirmUrl, new FormData(confirmForm), {
                headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' },
            }).then(function (response) {
                const json = response.data || {};
                if (!json.success) {
                    toast('error', json.message || i18n.upload_failed || 'Upload failed');
                    return;
                }
                toast('success', json.message || i18n.queued || 'Upload queued');
                window.setTimeout(function () {
                    window.location.href = json.data && json.data.redirect_url ? json.data.redirect_url : cfg.indexUrl;
                }, 900);
            }).catch(function (error) {
                toast('error', messageFromError(error));
            }).finally(function () {
                setBusy(elements.confirmSubmit, false);
            });
        });

        if (elements.reset) {
            elements.reset.addEventListener('click', function () {
                elements.step2.classList.add('d-none');
                confirmForm.reset();
                previewForm.reset();
                if (filePond) filePond.removeFiles();
                missingPrimaryMedia = null;
                mediaPreviews = { columns: [], items: [] };
                refreshMissingMedia();
                document.dispatchEvent(new CustomEvent('catalog-bulk-preview:reset'));
                elements.step1.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
    });
})();
