'use strict';

/**
 * Admin order detail — Force Cancel + Reassign Rider modals.
 * Endpoints, current rider, items list, etc. are all seeded on the page via
 * window.adminOrderEndpoints / window.adminOrderContext / window.adminOrderItems
 * so this file is order-id agnostic.
 */
document.addEventListener('DOMContentLoaded', function () {
    const endpoints = window.adminOrderEndpoints || {};
    const context = window.adminOrderContext || {};
    const items = Array.isArray(window.adminOrderItems) ? window.adminOrderItems : [];

    initReassignRider();
    initMarkPaymentReceived();
    initSettleRiderEarnings();

    /* ────────────────────────────────────────────────────────────────── */
    /*  Settle Rider Earnings (CANCELLED_BY_ADMIN assignment)              */
    /* ────────────────────────────────────────────────────────────────── */

    function initSettleRiderEarnings() {
        const modalEl = document.getElementById('adminSettleRiderEarningsModal');
        if (!modalEl) return;

        const form           = modalEl.querySelector('#adminSettleRiderEarningsForm');
        const statusStrip    = modalEl.querySelector('#adminSettleStatusStrip');
        const heading        = modalEl.querySelector('#adminSettleHeading');
        const subtext        = modalEl.querySelector('#adminSettleSubtext');
        const amountEl       = modalEl.querySelector('#adminSettleAmount');
        const amountAlert    = modalEl.querySelector('#adminSettleAmountAlert');
        const decisionInput  = modalEl.querySelector('#adminSettleDecision');
        const assignmentInput = modalEl.querySelector('#adminSettleAssignmentId');
        const reasonField    = modalEl.querySelector('#adminSettleReason');
        const submitBtn      = modalEl.querySelector('#adminSettleSubmit');
        const submitLabel    = modalEl.querySelector('#adminSettleSubmitLabel');

        if (!form || !decisionInput || !assignmentInput || !reasonField || !submitBtn) return;

        // The button that triggers the modal seeds decision/assignment/amount.
        // Use show.bs.modal so we re-read attributes on every open (Approve and
        // Reject share the modal; whichever button was clicked wins).
        modalEl.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            const decision = trigger.getAttribute('data-decision') || 'approve';
            const assignmentId = trigger.getAttribute('data-assignment-id') || '';
            const amount = trigger.getAttribute('data-amount') || '0.00';
            const currency = trigger.getAttribute('data-currency') || '';

            decisionInput.value = decision;
            assignmentInput.value = assignmentId;
            reasonField.value = '';
            reasonField.classList.remove('is-invalid');
            clearFieldErrors(form);

            // Switch the modal's appearance based on the decision so admin
            // can tell at a glance whether they're about to pay or refuse.
            const isApprove = decision === 'approve';
            if (statusStrip) {
                statusStrip.className = 'modal-status ' + (isApprove ? 'bg-success' : 'bg-danger');
            }
            if (heading) {
                heading.textContent = isApprove
                    ? (window.adminOrderLabels?.settleApprove || 'Approve & Pay')
                    : (window.adminOrderLabels?.settleReject || 'Reject Payment');
            }
            if (subtext) {
                subtext.textContent = isApprove
                    ? 'Confirm the rider should be paid for this cancelled order. Wallet credit fires immediately.'
                    : 'Confirm the rider should NOT be paid for this cancelled order. Earnings will be zeroed out.';
            }
            if (amountAlert) {
                amountAlert.className = 'alert mb-3 ' + (isApprove ? 'alert-success' : 'alert-danger');
            }
            if (amountEl) {
                amountEl.textContent = currency + amount;
            }
            submitBtn.className = 'btn ' + (isApprove ? 'btn-success' : 'btn-danger');
            if (submitLabel) {
                submitLabel.textContent = isApprove
                    ? (window.adminOrderLabels?.settleApprove || 'Approve & Pay')
                    : (window.adminOrderLabels?.settleReject || 'Reject Payment');
            }
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const urlTpl = endpoints.settleRiderEarningsTpl;
            if (!urlTpl) return;

            const assignmentId = assignmentInput.value;
            const decision = decisionInput.value;
            const reason = (reasonField.value || '').trim();
            if (!assignmentId || !decision) return;

            if (!reason || reason.length < 3) {
                markInvalid(form, 'reason', 'Reason is required (min 3 chars).');
                return;
            }

            const url = urlTpl.replace('__ID__', assignmentId);
            postWithAxios({
                form,
                submitBtn,
                modalEl,
                url,
                payload: { decision, reason },
            });
        });
    }

    /* ────────────────────────────────────────────────────────────────── */
    /*  Reassign Rider                                                     */
    /* ────────────────────────────────────────────────────────────────── */

    function initReassignRider() {
        const modalEl = document.getElementById('adminReassignRiderModal');
        if (!modalEl || !endpoints.reassignRider) return;

        const selectEl = modalEl.querySelector('#adminReassignDeliveryBoyId');
        const reasonField = modalEl.querySelector('#adminReassignReason');
        const submitBtn = modalEl.querySelector('#adminReassignRiderSubmit');
        const form = modalEl.querySelector('#adminReassignRiderForm');
        if (!selectEl || !form || !window.TomSelect) return;

        // Lazy-init Tom Select on first open. Reusing the same instance keeps
        // typed search history; we clear the selection each open.
        let ts = null;
        const getTomSelect = () => {
            if (selectEl.tomselect) return selectEl.tomselect;
            return new TomSelect(selectEl, {
                copyClassesToDropdown: false,
                dropdownParent: 'body',
                valueField: 'value',
                labelField: 'text',
                searchField: 'text',
                placeholder: selectEl.getAttribute('placeholder') || 'Search rider…',
                preload: false,
                load: function (query, callback) {
                    if (!endpoints.riderSearch) return callback();
                    const url = new URL(endpoints.riderSearch, window.location.origin);
                    if (query) url.searchParams.set('search', query);
                    url.searchParams.set('available', '1');
                    if (context.deliveryZoneId) {
                        url.searchParams.set('delivery_zone_id', context.deliveryZoneId);
                    }
                    fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
                        .then((r) => r.json())
                        .then((rows) => callback(Array.isArray(rows) ? rows : []))
                        .catch(() => callback());
                },
                render: {
                    no_results: (data, escape) =>
                        `<div class="no-results p-2 text-muted">No matching riders for "${escape(data.input)}"</div>`,
                },
            });
        };

        modalEl.addEventListener('show.bs.modal', function () {
            ts = getTomSelect();
            ts.clearOptions();
            ts.clear();
            // Preselect the currently assigned rider so admin can confirm or swap.
            if (context.currentRider && context.currentRider.value) {
                ts.addOption({ value: context.currentRider.value, text: context.currentRider.text });
                ts.addItem(context.currentRider.value, true); // silent
            }
            if (reasonField) {
                reasonField.value = '';
                reasonField.classList.remove('is-invalid');
            }
            clearFieldErrors(form);
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const raw = selectEl.value.trim();
            const payload = {
                delivery_boy_id: raw === '' ? null : parseInt(raw, 10),
                reason: (reasonField?.value || '').trim(),
            };
            postWithAxios({ form, submitBtn, modalEl, url: endpoints.reassignRider, payload });
        });
    }

    /* ────────────────────────────────────────────────────────────────── */
    /*  Mark Payment Received                                              */
    /* ────────────────────────────────────────────────────────────────── */

    function initMarkPaymentReceived() {
        const triggerBtn = document.getElementById('btnMarkPaymentReceived');
        const modalEl = document.getElementById('adminMarkPaymentModal');
        if (!triggerBtn || !modalEl) return;

        const confirmBtn = modalEl.querySelector('#adminPaymentReceivedConfirm');
        const remarkField = modalEl.querySelector('#adminPaymentRemark');

        triggerBtn.addEventListener('click', function () {
            if (remarkField) remarkField.value = '';
            $('#adminMarkPaymentModal').modal('show');
        });

        confirmBtn.addEventListener('click', function () {
            if (!endpoints.markPaymentReceived) return;

            confirmBtn.disabled = true;
            const payload = {
                remark: (remarkField?.value || '').trim() || null,
            };

            window.axios.post(endpoints.markPaymentReceived, payload)
                .then((response) => {
                    const data = response.data || {};
                    if (data.success) {
                        Toast.fire({ icon: 'success', title: data.message });
                        $(`#${modalEl.id}`).modal('hide');
                        setTimeout(() => window.location.reload(), 600);
                    } else {
                        Toast.fire({ icon: 'error', title: data.message || 'Failed' });
                    }
                })
                .catch((err) => {
                    Toast.fire({ icon: 'error', title: err.response?.data?.message || 'Something went wrong' });
                })
                .finally(() => {
                    confirmBtn.disabled = false;
                });
        });
    }

    /* ────────────────────────────────────────────────────────────────── */
    /*  Shared helpers                                                     */
    /* ────────────────────────────────────────────────────────────────── */

    function postWithAxios({ form, submitBtn, modalEl, url, payload }) {
        if (submitBtn) submitBtn.disabled = true;
        clearFieldErrors(form);

        window.axios.post(url, payload)
            .then((response) => {
                const data = response.data || {};
                if (data.success) {
                    Toast.fire({ icon: 'success', title: data.message });
                    $(`#${modalEl.id}`).modal('hide');
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    // Server returned 200 but success=false (e.g. blocked by
                    // policy: reassign_blocked_post_collect, or the BUG-1
                    // already-cancelled guard). Show as a toast.
                    Toast.fire({ icon: 'error', title: data.message || 'Failed' });
                }
            })
            .catch((err) => {
                const errors = err.response?.data?.errors;
                if (errors) {
                    Object.entries(errors).forEach(([field, msgs]) => {
                        markInvalid(form, field, msgs?.[0] || 'Invalid');
                    });
                } else {
                    Toast.fire({ icon: 'error', title: err.response?.data?.message || 'Something went wrong' });
                }
            })
            .finally(() => {
                if (submitBtn) submitBtn.disabled = false;
            });
    }

    function markInvalid(form, field, message) {
        if (!form) return;
        const input = form.querySelector(`[name="${field}"]`);
        if (input) input.classList.add('is-invalid');
        const fb = form.querySelector(`.invalid-feedback[data-field="${field}"]`);
        if (fb) fb.textContent = message;
    }

    function clearFieldErrors(form) {
        if (!form) return;
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach((el) => (el.textContent = ''));
    }
});
