function reloadWatchBuyTables() {
    if (!window.jQuery || !jQuery.fn.DataTable) {
        return;
    }

    jQuery('.data-table').each(function () {
        if (jQuery.fn.DataTable.isDataTable(this)) {
            jQuery(this).DataTable().ajax.reload(null, false);
        }
    });
}

function initializeWatchBuyFilters() {
    if (!window.jQuery || !jQuery.fn.DataTable) {
        return;
    }

    document.querySelectorAll('[data-watch-buy-filters]').forEach(function (filters) {
        const tableId = filters.dataset.tableId;
        const table = document.getElementById(tableId);
        if (!table) {
            return;
        }

        const fields = Array.from(filters.querySelectorAll('[data-watch-buy-filter]'));
        jQuery(table).on('preXhr.dt', function (event, settings, data) {
            fields.forEach(function (field) {
                data[field.dataset.watchBuyFilter] = field.tomselect ? field.tomselect.getValue() : field.value;
            });
        });

        const reload = function () {
            if (jQuery.fn.DataTable.isDataTable(table)) {
                jQuery(table).DataTable().ajax.reload(null, false);
            }
        };

        fields.forEach(function (field) {
            field.addEventListener('change', reload);
        });

        filters.querySelector('[data-watch-buy-filter-reset]')?.addEventListener('click', function () {
            fields.forEach(function (field) {
                if (field.tomselect) {
                    field.tomselect.clear(true);
                } else {
                    field.value = '';
                }
            });
            reload();
        });
    });
}

function submitWatchBuyAction(form) {
    if (form.dataset.submitting === '1') {
        return;
    }

    const button = form.querySelector('button');
    form.dataset.submitting = '1';
    if (button) {
        button.disabled = true;
    }

    axios({
        method: (form.getAttribute('method') || 'POST').toUpperCase(),
        url: form.getAttribute('action'),
        data: new FormData(form),
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    }).then(function (response) {
        const data = response.data;
        Toast.fire({
            icon: data.success === false ? 'error' : 'success',
            title: data.message,
        });
        if (data.success !== false) {
            reloadWatchBuyTables();
        }
    }).catch(function (error) {
        Toast.fire({
            icon: 'error',
            title: error.response?.data?.message || form.dataset.errorMessage,
        });
    }).finally(function () {
        delete form.dataset.submitting;
        if (button) {
            button.disabled = false;
        }
    });
}

document.addEventListener('submit', function (event) {
    const form = event.target.closest('.watch-buy-action-form');
    if (!form) {
        return;
    }

    event.preventDefault();
    submitWatchBuyAction(form);
});

document.addEventListener('DOMContentLoaded', initializeWatchBuyFilters);

document.addEventListener('click', function (event) {
    const deleteButton = event.target.closest('.watch-buy-delete');
    if (deleteButton) {
        Swal.fire({
            title: deleteButton.dataset.title,
            text: deleteButton.dataset.text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: deleteButton.dataset.confirmText,
            cancelButtonText: deleteButton.dataset.cancelText,
            confirmButtonColor: '#d63939',
        }).then(function (result) {
            if (result.isConfirmed) {
                submitWatchBuyAction(deleteButton.closest('form'));
            }
        });
        return;
    }

    const reasonButton = event.target.closest('.watch-buy-reason-action');
    if (!reasonButton) {
        return;
    }

    Swal.fire({
        title: reasonButton.dataset.title,
        input: 'textarea',
        inputPlaceholder: reasonButton.dataset.placeholder,
        inputAttributes: {maxlength: 2000},
        showCancelButton: true,
        confirmButtonText: reasonButton.dataset.confirmText,
        cancelButtonText: reasonButton.dataset.cancelText,
        inputValidator: function (value) {
            return value && value.trim() ? null : reasonButton.dataset.reasonRequired;
        },
    }).then(function (result) {
        if (result.isConfirmed) {
            const form = reasonButton.closest('form');
            form.querySelector('[name="reason"]').value = result.value.trim();
            submitWatchBuyAction(form);
        }
    });
});
