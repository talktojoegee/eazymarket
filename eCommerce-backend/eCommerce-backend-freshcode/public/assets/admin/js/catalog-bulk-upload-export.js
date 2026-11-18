'use strict';

(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const i18n = window.CATALOG_BULK_EXPORT_I18N || {};

        document.querySelectorAll('.bulk-upload-export-modal').forEach(function (modal) {
            const form = modal.querySelector('.bulk-upload-export-form');
            const countText = modal.querySelector('.export-count-text');
            const spinner = modal.querySelector('.export-count-spinner');
            const submit = modal.querySelector('.export-submit');
            const countUrl = modal.dataset.countUrl;
            if (!form || !countUrl) return;
            let timer = null;
            let requestSequence = 0;

            function refreshDependencies() {
                form.querySelectorAll('.export-filter-row[data-depends-on]').forEach(function (row) {
                    const parent = form.querySelector('[name="filters[' + row.dataset.dependsOn + ']"]');
                    const visible = Boolean(parent && parent.value === row.dataset.dependsValue);
                    row.classList.toggle('d-none', !visible);
                    const control = row.querySelector('select, input');
                    if (control && !visible) control.value = '';
                });
            }

            function filterParams() {
                const params = {};
                form.querySelectorAll('[name^="filters["]').forEach(function (control) {
                    if (control.value === '') return;
                    params[control.name] = control.value;
                });
                return params;
            }

            function refreshCount() {
                const sequence = ++requestSequence;
                if (spinner) spinner.classList.remove('d-none');
                if (countText) countText.textContent = i18n.loading || '';
                axios.get(countUrl, { params: filterParams(), headers: { 'Accept': 'application/json' } })
                    .then(function (response) {
                        if (sequence !== requestSequence) return;
                        const count = Number(response.data && response.data.data && response.data.data.count || 0);
                        const template = count === 0 ? i18n.no_match : i18n.matches;
                        if (countText) countText.textContent = String(template || '').replace(':count', count);
                        if (submit) submit.disabled = count === 0;
                    }).catch(function () {
                        if (sequence !== requestSequence) return;
                        if (countText) countText.textContent = i18n.failed || '';
                        if (submit) submit.disabled = true;
                    }).finally(function () {
                        if (sequence === requestSequence && spinner) spinner.classList.add('d-none');
                    });
            }

            function scheduleCount() {
                window.clearTimeout(timer);
                timer = window.setTimeout(refreshCount, 250);
            }

            form.querySelectorAll('.export-remote-select').forEach(function (select) {
                if (!window.TomSelect) return;
                new window.TomSelect(select, {
                    valueField: 'id',
                    labelField: 'text',
                    searchField: 'text',
                    load: function (query, callback) {
                        if (!query.length) {
                            callback();
                            return;
                        }
                        axios.get(select.dataset.remoteUrl, { params: { search: query } })
                            .then(function (response) { callback(response.data || []); })
                            .catch(function () { callback(); });
                    },
                });
            });

            form.addEventListener('change', function () {
                refreshDependencies();
                scheduleCount();
            });
            modal.addEventListener('shown.bs.modal', function () {
                refreshDependencies();
                refreshCount();
            });
            refreshDependencies();
        });
    });
})();
