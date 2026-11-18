document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('dynamic-seller-field-country-filter');
    const filterSelect = document.getElementById('field-country-filter');
    const clearFilter = document.getElementById('clear-field-country-filter');
    const tableElement = document.getElementById('dynamic-seller-fields-table');
    const removeCountryParams = (url) => {
        [...url.searchParams.keys()]
            .filter((key) => key === 'country_codes' || key.startsWith('country_codes['))
            .forEach((key) => url.searchParams.delete(key));
    };

    const applyCountryFilter = () => {
        const section = filterForm?.querySelector('[name="section"]')?.value || 'bank_details';
        const selectedCountries = filterSelect?.tomselect?.items || [...(filterSelect?.selectedOptions || [])].map((option) => option.value);
        const dataTableUrl = new URL(tableElement?.dataset.route || window.location.href, window.location.origin);
        dataTableUrl.searchParams.set('section', section);
        removeCountryParams(dataTableUrl);
        selectedCountries.forEach((country) => dataTableUrl.searchParams.append('country_codes[]', country));

        if (tableElement && window.jQuery?.fn?.DataTable?.isDataTable(tableElement)) {
            window.jQuery(tableElement).DataTable().ajax.url(dataTableUrl.toString()).load();
        }

        const targetSelect = document.getElementById('field-country-target');
        if (targetSelect?.tomselect && targetSelect.dataset.editing !== '1' && filterSelect?.tomselect) {
            selectedCountries.forEach((country) => {
                const option = filterSelect.tomselect.options[country];
                if (option) targetSelect.tomselect.addOption(option);
            });
            targetSelect.tomselect.setValue(selectedCountries, true);
        }

        const pageUrl = new URL(window.location.href);
        pageUrl.searchParams.set('section', section);
        removeCountryParams(pageUrl);
        selectedCountries.forEach((country) => pageUrl.searchParams.append('country_codes[]', country));
        window.history.replaceState({}, '', pageUrl.toString());
        document.querySelectorAll('.nav-tabs a[href*="dynamic-fields"]').forEach((link) => {
            const targetUrl = new URL(link.href, window.location.origin);
            const targetSection = targetUrl.searchParams.get('section') || section;
            removeCountryParams(targetUrl);
            selectedCountries.forEach((country) => targetUrl.searchParams.append('country_codes[]', country));
            targetUrl.searchParams.set('section', targetSection);
            link.href = targetUrl.toString();
        });
    };

    filterForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        applyCountryFilter();
    });
    clearFilter?.addEventListener('click', () => {
        filterSelect?.tomselect?.clear(true);
        applyCountryFilter();
    });

    const type = document.getElementById('field-type');
    const options = document.getElementById('field-options-wrap');
    const defaultValueWrap = document.getElementById('field-default-wrap');
    const defaultValue = document.getElementById('field-default-value');

    const syncOptions = () => {
        if (type && options) options.hidden = type.value !== 'select';
        if (type && defaultValueWrap) {
            const supportsDefault = type.value !== 'image';
            defaultValueWrap.hidden = !supportsDefault;
            if (defaultValue) defaultValue.disabled = !supportsDefault;
        }
    };

    type?.addEventListener('change', syncOptions);
    syncOptions();

    const sendFieldAction = async (url, operation) => {
        try {
            const target = new URL(url, window.location.origin);
            target.searchParams.set('operation', operation);
            const response = await fetch(target.toString(), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            const data = await response.json();
            if (!response.ok) {
                window.Toast?.fire({ icon: 'error', title: data.message || 'Unable to update this field.' });
                return;
            }
            window.Toast?.fire({ icon: 'success', title: data.message });
            if (tableElement && window.jQuery?.fn?.DataTable?.isDataTable(tableElement)) {
                window.jQuery(tableElement).DataTable().ajax.reload(null, false);
            }
        } catch (error) {
            window.Toast?.fire({ icon: 'error', title: 'Unable to update this field.' });
        }
    };

    document.addEventListener('click', async (event) => {
        const removeButton = event.target.closest('[data-remove-field-url]');
        const restoreButton = event.target.closest('[data-restore-url]');

        if (restoreButton) {
            await sendFieldAction(restoreButton.dataset.restoreUrl, 'restore');
            return;
        }

        if (!removeButton) return;
        if (!window.Swal) {
            window.Toast?.fire({ icon: 'error', title: 'Confirmation dialog is unavailable.' });
            return;
        }

        const confirmation = await Swal.fire({
            title: 'Remove this field?',
            text: `${removeButton.dataset.fieldLabel} can be archived with its saved values kept, or permanently removed with seller values deleted for the selected countries.`,
            icon: 'warning',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'Permanently remove',
            denyButtonText: 'Archive',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d63939',
            denyButtonColor: '#f59f00',
        });

        if (confirmation.isConfirmed) {
            await sendFieldAction(removeButton.dataset.removeFieldUrl, 'permanent');
        } else if (confirmation.isDenied) {
            await sendFieldAction(removeButton.dataset.removeFieldUrl, 'archive');
        }
    });
});
