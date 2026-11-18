'use strict';

/**
 * Phase 1D — wires up the seller-side post-accept Cancel action. The modal is
 * populated from the listing dropdown's data-id and submitted through axios.
 * On success we trigger the same datatable reload that order.js uses, so the
 * row's status/badge updates without a full reload.
 */
document.addEventListener('DOMContentLoaded', function () {
    const cancelModalEl = document.getElementById('cancelItemModal');
    if (!cancelModalEl) return;

    // Reload the datatable that's currently visible on the orders index. Falls
    // back to no-op when invoked from the order detail page.
    const reloadActiveTable = function () {
        if (typeof $ === 'undefined') return;
        $('table[id^="orders-list-table"]').each(function () {
            $(this).DataTable().ajax.reload(null, false);
        });
        const ordersTable = $('#orders-table').length ? $('#orders-table').DataTable() : null;
        if (ordersTable) ordersTable.ajax.reload(null, false);
    };

    // Bootstrap dispatches show.bs.modal with relatedTarget = the trigger
    // element. We pull data-id off the dropdown item and seed the hidden input.
    cancelModalEl.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        const id = trigger && trigger.getAttribute('data-id');
        if (id) {
            cancelModalEl.querySelector('#cancelItemId').value = id;
        }
        // Reset previous state
        const reasonField = cancelModalEl.querySelector('#cancelItemReason');
        if (reasonField) {
            reasonField.value = '';
            reasonField.classList.remove('is-invalid');
        }
        const fb = cancelModalEl.querySelector('.invalid-feedback');
        if (fb) fb.textContent = '';
    });

    const cancelForm = cancelModalEl.querySelector('#cancelItemForm');
    if (cancelForm) {
        cancelForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const id = cancelModalEl.querySelector('#cancelItemId').value;
            const reason = cancelModalEl.querySelector('#cancelItemReason').value.trim();
            if (!id) return;

            const submitBtn = cancelForm.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            window.axios.post(`${base_url}/${panel}/orders/${id}/cancel-item`, { reason })
                .then((response) => {
                    const data = response.data || {};
                    if (data.success) {
                        Toast.fire({ icon: 'success', title: data.message });
                        $('#cancelItemModal').modal('hide');
                        reloadActiveTable();
                    } else {
                        Toast.fire({ icon: 'error', title: data.message || 'Failed' });
                    }
                })
                .catch((err) => {
                    const errors = err.response?.data?.errors;
                    if (errors && errors.reason) {
                        const reasonField = cancelModalEl.querySelector('#cancelItemReason');
                        const fb = cancelModalEl.querySelector('.invalid-feedback');
                        reasonField.classList.add('is-invalid');
                        if (fb) fb.textContent = errors.reason[0];
                    } else {
                        Toast.fire({ icon: 'error', title: err.response?.data?.message || 'Something went wrong' });
                    }
                })
                .finally(() => {
                    if (submitBtn) submitBtn.disabled = false;
                });
        });
    }
});
