'use strict';

(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const cfg = window.CATALOG_BULK_UPLOAD_STATUS_CFG || {};
        if (!cfg.statusUrl) return;

        const elements = {
            statusText: document.getElementById('bulk-status-text'),
            startedAt: document.getElementById('bulk-started-at'),
            finishedAt: document.getElementById('bulk-finished-at'),
            processedRows: document.getElementById('bulk-processed-rows'),
            totalRows: document.getElementById('bulk-total-rows'),
            processedRecords: document.getElementById('bulk-processed-records'),
            totalRecords: document.getElementById('bulk-total-records'),
            progressPercent: document.getElementById('bulk-progress-percent'),
            progressBar: document.getElementById('bulk-progress-bar'),
            statTotal: document.getElementById('bulk-stat-total'),
            statSuccess: document.getElementById('bulk-stat-success'),
            statUpdated: document.getElementById('bulk-stat-updated'),
            statSkipped: document.getElementById('bulk-stat-skipped'),
            statFailed: document.getElementById('bulk-stat-failed'),
            log: document.getElementById('bulk-log'),
            logCopy: document.getElementById('bulk-log-copy'),
            cancel: document.getElementById('bulk-cancel-btn'),
            errors: document.getElementById('bulk-download-errors-btn'),
            errorsCount: document.getElementById('bulk-download-errors-count'),
        };
        const i18n = cfg.i18n || {};
        const badgeClasses = {
            pending: 'bg-secondary-lt',
            processing: 'bg-info-lt',
            completed: 'bg-success-lt',
            failed: 'bg-danger-lt',
            cancelled: 'bg-warning-lt',
        };
        let timer = null;
        let stopped = Boolean(cfg.initialIsTerminal);

        function toast(icon, message) {
            if (window.Toast && typeof window.Toast.fire === 'function') {
                window.Toast.fire({ icon: icon, title: message });
            }
        }

        function setText(element, value) {
            if (element && value !== null && value !== undefined) element.textContent = value;
        }

        function updateBadge(status, label) {
            if (!elements.statusText) return;
            elements.statusText.className = 'badge ' + (badgeClasses[status] || badgeClasses.pending);
            elements.statusText.textContent = label || i18n['status_' + status] || status || '';
        }

        function applyData(data) {
            if (!data) return;
            updateBadge(data.status, data.status_label);
            setText(elements.startedAt, data.started_at || '—');
            setText(elements.finishedAt, data.finished_at || '—');
            setText(elements.processedRows, data.processed_rows);
            setText(elements.totalRows, data.total_rows);
            setText(elements.processedRecords, data.processed_records);
            setText(elements.totalRecords, data.total_records);
            setText(elements.progressPercent, data.progress_percent);
            setText(elements.statTotal, data.total_rows);
            setText(elements.statSuccess, data.success_count);
            setText(elements.statUpdated, data.updated_count);
            setText(elements.statSkipped, data.skipped_count);
            setText(elements.statFailed, data.failed_count);
            if (elements.progressBar) {
                const percent = Math.max(0, Math.min(100, Number.parseInt(data.progress_percent || 0, 10)));
                elements.progressBar.style.width = percent + '%';
                elements.progressBar.setAttribute('aria-valuenow', String(percent));
            }
            if (elements.log) {
                const atBottom = elements.log.scrollHeight - elements.log.clientHeight - elements.log.scrollTop < 40;
                elements.log.textContent = data.error_log || data.recent_log || '';
                if (atBottom) elements.log.scrollTop = elements.log.scrollHeight;
            }
            if (elements.cancel) elements.cancel.classList.toggle('d-none', Boolean(data.is_terminal));
            if (elements.errors) {
                const failed = Number.parseInt(data.failed_count || 0, 10);
                const available = Boolean(data.has_failed_rows_artifact);
                elements.errors.classList.toggle('d-none', failed === 0 || !available);
                setText(elements.errorsCount, failed);
                const expiry = data.failed_rows_expires_label || '';
                elements.errors.setAttribute('data-bs-title', expiry);
                elements.errors.setAttribute('data-bs-original-title', expiry);
                if (window.bootstrap && window.bootstrap.Tooltip) {
                    const tooltip = window.bootstrap.Tooltip.getInstance(elements.errors);
                    if (tooltip && typeof tooltip.setContent === 'function') tooltip.setContent({ '.tooltip-inner': expiry });
                }
            }
        }

        function stopPolling() {
            stopped = true;
            if (timer) window.clearInterval(timer);
            timer = null;
        }

        function poll(force) {
            if (stopped && !force) return;
            axios.get(cfg.statusUrl, { headers: { 'Accept': 'application/json' } }).then(function (response) {
                const json = response.data || {};
                if (!json.success || !json.data) return;
                applyData(json.data);
                if (json.data.is_terminal) stopPolling();
            }).catch(function () {});
        }

        poll(true);
        if (!stopped) timer = window.setInterval(poll, Number.parseInt(cfg.pollInterval, 10) || 2000);

        if (elements.logCopy) {
            elements.logCopy.addEventListener('click', function () {
                if (!navigator.clipboard || !elements.log) {
                    toast('error', i18n.clipboard_failed || i18n.unexpected_error || 'Unable to copy');
                    return;
                }
                navigator.clipboard.writeText(elements.log.textContent || '').then(function () {
                    toast('success', i18n.copied || 'Copied');
                }).catch(function () {
                    toast('error', i18n.clipboard_failed || i18n.unexpected_error || 'Unable to copy');
                });
            });
        }

        if (elements.cancel && cfg.cancelUrl) {
            elements.cancel.addEventListener('click', function () {
                if (elements.cancel.disabled) return;
                if (!window.Swal || typeof window.Swal.fire !== 'function') {
                    toast('error', i18n.unexpected_error || 'Unable to continue');
                    return;
                }
                window.Swal.fire({
                    title: i18n.cancel_title || 'Cancel job',
                    text: i18n.cancel_confirm || 'Are you sure?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d63939',
                    confirmButtonText: i18n.cancel_yes || 'Yes, cancel it',
                    cancelButtonText: i18n.cancel_no || 'No',
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    elements.cancel.disabled = true;
                    axios.post(cfg.cancelUrl, {}, {
                        headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' },
                    }).then(function (response) {
                        const json = response.data || {};
                        if (!json.success) {
                            toast('error', json.message || i18n.unexpected_error || 'Error');
                            elements.cancel.disabled = false;
                            return;
                        }
                        toast('success', json.message || '');
                        poll(true);
                    }).catch(function (error) {
                        const message = error && error.response && error.response.data && error.response.data.message;
                        toast('error', message || i18n.unexpected_error || 'Error');
                        elements.cancel.disabled = false;
                    });
                });
            });
        }

        if (elements.cancel && cfg.initialIsTerminal) elements.cancel.classList.add('d-none');
    });
})();
