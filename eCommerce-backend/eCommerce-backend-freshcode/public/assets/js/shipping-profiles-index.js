/* global jQuery, Toast, axios */
/*
 * Shipping Profiles — list page interactions.
 *
 * Mirrors store-locations-index.js (same wireConfirmModal helper inlined for
 * symmetry; if a third caller appears we'll lift it into a shared file).
 * The delete action surfaces the undeletable-default error from the
 * ShippingProfileObserver via Toast.fire instead of a generic 500.
 */
(function ($) {
    'use strict';

    function wireConfirmModal(opts) {
        var modalEl = document.getElementById(opts.modalId);
        var messageEl = document.getElementById(opts.messageId);
        var confirmBtn = document.getElementById(opts.confirmId);
        if (!modalEl || !confirmBtn) return;

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
            trigger: '.js-delete-shipping-profile',
            modalId: 'deleteShippingProfileModal',
            messageId: 'deleteShippingProfileMessage',
            confirmId: 'deleteShippingProfileConfirm',
            method: 'DELETE',
            errorFallback: 'Could not delete shipping profile',
            buildMessage: function ($btn, fallback) {
                var name = $btn.data('profile-name') || '';
                return name
                    ? 'Delete "' + name + '"? Products attached to this profile will fall back to the store default. This action cannot be undone.'
                    : fallback;
            }
        });
    });
})(jQuery);
