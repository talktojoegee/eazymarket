'use strict';

/**
 * Admin Markets form + datatable wiring.
 *
 * Form ($('#market-form')):
 *   - serializes the form, posts to data-action with method POST
 *   - on success: toast + redirect to the markets index
 *   - on failure: surface validation errors per-field
 *
 * Datatable refresh:
 *   - the #refresh button on the index page reloads the DataTable
 */
$(function () {

    // -----------------------------------------------------------------
    // FX rates — manual refresh button on the markets index
    // -----------------------------------------------------------------
    const $fxCard = $('#fx-rates-card');
    if ($fxCard.length) {
        const $btn         = $('#fx-refresh-btn');
        const $btnLabel    = $btn.find('.fx-refresh-label');
        const $body        = $('#fx-rates-body');
        const $lastUpdated = $('#fx-last-updated');
        const refreshUrl   = $fxCard.attr('data-refresh-url');
        const defaultLabel = $btnLabel.text();
        const loadingLabel = $btn.attr('data-loading-label') || 'Refreshing…';

        $btn.on('click', function () {
            $btn.prop('disabled', true);
            $btnLabel.text(loadingLabel);

            $.ajax({
                url: refreshUrl,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':     $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                success: function (resp) {
                    if (resp.success && resp.data && resp.data.html) {
                        // Server renders the partial so i18n + markup stays in
                        // one place (resources/views/admin/markets/partials/fx-rates-table.blade.php).
                        $body.html(resp.data.html);
                        if ($lastUpdated.length && resp.data.last_updated_at) {
                            $lastUpdated.attr('data-iso', resp.data.last_updated_at);
                            $lastUpdated.text(' · ' + ($lastUpdated.attr('data-just-now') || 'just now'));
                        }
                        Toast.fire({ icon: 'success', title: resp.message });
                    } else {
                        Toast.fire({ icon: 'error', title: resp.message || 'Refresh failed' });
                    }
                },
                error: function (xhr) {
                    Toast.fire({
                        icon:  'error',
                        title: xhr.responseJSON?.message || 'Refresh failed',
                    });
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    $btnLabel.text(defaultLabel);
                },
            });
        });
    }

});

// Delete button — uses the project-wide handleDelete helper (custom.js), which
// confirms via SweetAlert, calls DELETE, refreshes any `.data-table` DataTable,
// and surfaces success / error messages including the server-side "last market"
// protection error.
document.addEventListener('click', function (event) {
    handleDelete(
        event,
        '.delete-market',
        `/${panel}/markets/`,
        'You are about to delete this Market.'
    );
});
