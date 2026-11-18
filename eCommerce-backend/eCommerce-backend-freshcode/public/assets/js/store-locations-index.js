/* global jQuery, Toast, axios */
/*
 * Store Locations — list page interactions.
 *
 * Promote-to-default and delete both open shared Bootstrap modals
 * (#promoteDefaultModal / #deleteLocationModal) — same pattern as
 * admin/orders/partials/admin-action-modals. Confirming fires the
 * appropriate POST / DELETE against the controller. Delete surfaces the
 * undeletable-default error from StoreLocationObserver via Toast.fire.
 */
(function ($) {
    'use strict';

    /**
     * Generic confirmation-modal wirer.
     *
     *   wireConfirmModal({
     *     trigger: '.js-promote-default',
     *     modalId: 'promoteDefaultModal',
     *     messageId: 'promoteDefaultMessage',
     *     confirmId: 'promoteDefaultConfirm',
     *     method: 'POST',
     *     buildMessage: ($btn, defaultMsg) => '…',
     *     errorFallback: 'Could not promote',
     *   });
     */
    function wireConfirmModal(opts) {
        var modalEl = document.getElementById(opts.modalId);
        var messageEl = document.getElementById(opts.messageId);
        var confirmBtn = document.getElementById(opts.confirmId);
        if (!modalEl || !confirmBtn) return; // partial not on this page

        var defaultMessage = messageEl ? messageEl.textContent : '';
        var pendingUrl = null;

        $(document).on('click', opts.trigger, function () {
            pendingUrl = this.dataset.action || null;
            if (messageEl && typeof opts.buildMessage === 'function') {
                messageEl.textContent = opts.buildMessage($(this), defaultMessage);
            }
            $(modalEl).modal('show');
        });

        confirmBtn.addEventListener('click', function () {
            if (!pendingUrl) return;
            confirmBtn.disabled = true;

            var token = $('meta[name="csrf-token"]').attr('content');
            var done = function (success, message) {
                Toast.fire({ icon: success ? 'success' : 'error', title: message || opts.errorFallback });
                if (success) {
                    $(modalEl).modal('hide');
                    setTimeout(function () { window.location.reload(); }, 600);
                } else {
                    confirmBtn.disabled = false;
                }
            };

            var headers = { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' };

            if (window.axios) {
                window.axios.request({
                    url: pendingUrl,
                    method: opts.method,
                    headers: headers
                }).then(function (response) {
                    var data = response.data || {};
                    done(!!data.success, data.message);
                }).catch(function (err) {
                    var msg = (err && err.response && err.response.data && err.response.data.message);
                    done(false, msg);
                });
            } else {
                $.ajax({
                    url: pendingUrl,
                    type: opts.method,
                    headers: headers,
                    success: function (resp) { done(!!(resp && resp.success), resp && resp.message); },
                    error: function (xhr) { done(false, xhr.responseJSON && xhr.responseJSON.message); }
                });
            }
        });
    }

    $(function () {
        wireConfirmModal({
            trigger: '.js-promote-default',
            modalId: 'promoteDefaultModal',
            messageId: 'promoteDefaultMessage',
            confirmId: 'promoteDefaultConfirm',
            method: 'POST',
            errorFallback: 'Could not promote',
            buildMessage: function ($btn, fallback) {
                var name = $btn.data('location-name') || '';
                return name
                    ? 'Promote "' + name + '" to the store default? The current default will be demoted.'
                    : fallback;
            }
        });

        wireConfirmModal({
            trigger: '.js-delete-location',
            modalId: 'deleteLocationModal',
            messageId: 'deleteLocationMessage',
            confirmId: 'deleteLocationConfirm',
            method: 'DELETE',
            errorFallback: 'Could not delete',
            buildMessage: function ($btn, fallback) {
                var name = $btn.data('location-name') || '';
                return name
                    ? 'Delete "' + name + '"? This action cannot be undone.'
                    : fallback;
            }
        });
    });
})(jQuery);
