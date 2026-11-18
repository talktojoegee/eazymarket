'use strict';

/**
 * Hypercommerce fulfillment — Shopify-style. Shared by the seller panel (one
 * card) and the admin panel (one card per seller order). Entirely
 * data-attribute driven, so it supports any number of cards on a page:
 *
 *   .js-fulfill-now      → POST data-fulfill-url                 (local_pickup, one-click)
 *   .js-fulfill-open     → opens #fulfillShipmentModal, passing data-fulfill-url
 *   #fulfillShipmentForm → POST its data-fulfill-url {carrier, tracking, items[]}
 *   .js-set-fstatus      → POST data-status-url {status}         (in_progress / on_hold / unfulfilled)
 *   .js-shipment-action  → POST data-transition-url (SHIPMENT_ID replaced) {status} (deliver / cancel)
 *   .js-shipment-edit    → opens #editFulfillmentModal, prefilled from data-*
 *   #editFulfillmentForm → POST its data-edit-url {carrier, tracking, tracking_url}
 *   (Print packing slip is a plain <a target="_blank"> — no JS needed.)
 *
 * Uses the same globals as the rest of the panel: window.axios + SweetAlert2 Toast.
 */
document.addEventListener('DOMContentLoaded', function () {
    if (!document.querySelector('.js-fulfillment-card')) return;

    function notifyOk(msg) {
        if (typeof Toast !== 'undefined') Toast.fire({ icon: 'success', title: msg || 'Done' });
    }
    function notifyErr(msg) {
        if (typeof Toast !== 'undefined') Toast.fire({ icon: 'error', title: msg || 'Something went wrong' });
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function post(url, payload, btn) {
        if (!url) return Promise.resolve();
        if (btn) btn.disabled = true;
        return window.axios.post(url, payload || {})
            .then(function (res) {
                var data = (res && res.data) || {};
                if (data.success) {
                    var warn = !!(data.data && data.data.warning);
                    if (typeof Toast !== 'undefined') {
                        Toast.fire({ icon: warn ? 'warning' : 'success', title: data.message || 'Done' });
                    }
                    setTimeout(function () { window.location.reload(); }, warn ? 1400 : 400);
                } else {
                    notifyErr(data.message);
                    if (btn) btn.disabled = false;
                }
            })
            .catch(function (err) {
                var msg = (err && err.response && err.response.data && err.response.data.message) || null;
                notifyErr(msg);
                if (btn) btn.disabled = false;
            });
    }

    // local_pickup — one-click "Mark as fulfilled" (no tracking)
    document.querySelectorAll('.js-fulfill-now').forEach(function (btn) {
        btn.addEventListener('click', function () {
            post(btn.getAttribute('data-fulfill-url'), {}, btn);
        });
    });

    // Manual status flips (in progress / on hold / unfulfilled)
    document.querySelectorAll('.js-set-fstatus').forEach(function (btn) {
        btn.addEventListener('click', function () {
            post(btn.getAttribute('data-status-url'), { status: btn.getAttribute('data-status') }, btn);
        });
    });

    document.querySelectorAll('.js-shipment-retry').forEach(function (btn) {
        btn.addEventListener('click', function () {
            post(btn.getAttribute('data-retry-url'), {}, btn);
        });
    });

    document.querySelectorAll('.js-shipment-sync').forEach(function (btn) {
        btn.addEventListener('click', function () {
            post(btn.getAttribute('data-sync-url'), {}, btn);
        });
    });

    document.querySelectorAll('.js-shipment-ndr').forEach(function (btn) {
        btn.addEventListener('click', function () {
            Swal.fire({
                title: btn.getAttribute('data-ndr-title') || 'Confirm',
                input: 'textarea',
                inputPlaceholder: btn.getAttribute('data-ndr-placeholder') || 'Comments for the courier (required)',
                showCancelButton: true,
                inputValidator: function (v) {
                    if (!v || !v.trim()) return btn.getAttribute('data-ndr-required') || 'Comments are required.';
                },
            }).then(function (r) {
                if (!r.isConfirmed) return;
                post(btn.getAttribute('data-ndr-url'), {
                    action: btn.getAttribute('data-ndr-action'),
                    comments: r.value.trim(),
                }, btn);
            });
        });
    });

    // "Mark as fulfilled" modal — carries the URL of whichever card opened it
    var modalEl = document.getElementById('fulfillShipmentModal');
    var form = document.getElementById('fulfillShipmentForm');
    if (modalEl && form) {
        var itemsSection = document.getElementById('fulfillItemsSection');
        var itemsList = document.getElementById('fulfillItemsList');
        var manualBlock = document.getElementById('fulfillManualBlock');
        var srBlock = document.getElementById('fulfillShiprocketBlock');
        var srNote = document.getElementById('fulfillShiprocketNote');
        var srNoteText = document.getElementById('fulfillShiprocketNoteText');
        var pkgSelect = document.getElementById('fulfillPackage');

        function setSrDims(d) {
            var map = { length_cm: 'l', breadth_cm: 'b', height_cm: 'h', weight_kg: 'w' };
            Object.keys(map).forEach(function (name) {
                var inp = form.querySelector('[name="' + name + '"]');
                if (inp) inp.value = (d[name] != null && d[name] !== '') ? d[name] : '';
            });
        }

        function round3(n) {
            return Math.round(n * 1000) / 1000;
        }

        function srField(name) {
            var v = (form.querySelector('[name="' + name + '"]') || {}).value;
            return parseFloat(v) || 0;
        }

        function itemsWeightKg() {
            var total = 0;
            form.querySelectorAll('.js-fulfill-qty').forEach(function (inp) {
                var qty = parseInt(inp.value, 10) || 0;
                var w = parseFloat(inp.getAttribute('data-weight')) || 0;
                if (qty > 0) total += qty * w;
            });
            return total;
        }

        function applyPackageWeight() {
            if (!pkgSelect) return;
            var opt = pkgSelect.options[pkgSelect.selectedIndex];
            if (!opt || opt.value === 'custom') return;
            var empty = parseFloat(opt.getAttribute('data-w')) || 0;
            var inp = form.querySelector('[name="weight_kg"]');
            if (inp) inp.value = round3(empty + itemsWeightKg());
        }

        function updateWeightBreakdown() {
            var itemsEl = document.getElementById('fulfillItemsWeightVal');
            var pkgEl = document.getElementById('fulfillPkgWeightVal');
            if (itemsEl) itemsEl.textContent = round3(itemsWeightKg());
            if (pkgEl) {
                var opt = pkgSelect ? pkgSelect.options[pkgSelect.selectedIndex] : null;
                pkgEl.textContent = (opt && opt.value !== 'custom')
                    ? round3(parseFloat(opt.getAttribute('data-w')) || 0)
                    : '—';
            }
        }

        function updateBilled() {
            updateWeightBreakdown();
            var line = document.getElementById('fulfillBilledLine');
            if (!line) return;
            var divisor = parseFloat(line.getAttribute('data-divisor')) || 5000;
            var dead = srField('weight_kg');
            var vol = round3((srField('length_cm') * srField('breadth_cm') * srField('height_cm')) / divisor);
            var deadEl = document.getElementById('fulfillDeadWeight');
            var volEl = document.getElementById('fulfillVolWeight');
            var billedEl = document.getElementById('fulfillBilledWeight');
            if (deadEl) deadEl.textContent = round3(dead);
            if (volEl) volEl.textContent = vol;
            if (billedEl) billedEl.textContent = round3(Math.max(dead, vol));
        }

        if (pkgSelect) {
            pkgSelect.addEventListener('change', function () {
                var opt = pkgSelect.options[pkgSelect.selectedIndex];
                if (!opt || opt.value === 'custom') { updateBilled(); return; }
                setSrDims({
                    length_cm: opt.getAttribute('data-l'),
                    breadth_cm: opt.getAttribute('data-b'),
                    height_cm: opt.getAttribute('data-h'),
                    weight_kg: opt.getAttribute('data-w'),
                });
                applyPackageWeight();
                updateBilled();
            });
        }

        ['length_cm', 'breadth_cm', 'height_cm', 'weight_kg'].forEach(function (name) {
            var inp = form.querySelector('[name="' + name + '"]');
            if (inp) inp.addEventListener('input', updateBilled);
        });

        if (itemsList) {
            itemsList.addEventListener('input', function (e) {
                if (!e.target.classList.contains('js-fulfill-qty')) return;
                if (form.getAttribute('data-shiprocket') !== '1') return;
                applyPackageWeight();
                updateBilled();
            });
        }

        modalEl.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            var url = trigger && trigger.getAttribute('data-fulfill-url');
            form.setAttribute('data-fulfill-url', url || '');
            form.reset();

            // Build per-line qty inputs from the card's data-lines, defaulting to
            // the remaining quantity. Only lines with units still to ship appear.
            var lines = [];
            try { lines = JSON.parse((trigger && trigger.getAttribute('data-lines')) || '[]'); } catch (e) { lines = []; }
            if (itemsList) itemsList.innerHTML = '';
            var shown = 0;
            (lines || []).forEach(function (ln) {
                var remaining = parseInt(ln.remaining, 10) || 0;
                if (remaining <= 0 || !itemsList) return;
                shown++;
                var preset = (ln.preset != null && ln.preset !== '') ? (parseInt(ln.preset, 10) || 0) : remaining;
                var variant = ln.variant ? ' · ' + ln.variant : '';
                var wEach = parseFloat(ln.weight_kg) || 0;
                var row = document.createElement('div');
                row.className = 'd-flex align-items-center justify-content-between gap-2';
                row.innerHTML =
                    '<div class="small">' +
                        '<div class="fw-medium">' + escapeHtml(ln.title || '') + '<span class="text-muted">' + escapeHtml(variant) + '</span></div>' +
                        '<div class="text-secondary">' + remaining + ' of ' + (parseInt(ln.ordered, 10) || 0) + ' remaining' +
                            (wEach > 0 ? ' · ' + wEach + ' kg each' : '') + '</div>' +
                    '</div>' +
                    '<input type="number" class="form-control text-center js-fulfill-qty" style="width:80px" ' +
                        'data-order-item-id="' + (parseInt(ln.order_item_id, 10) || 0) + '" ' +
                        'data-weight="' + (parseFloat(ln.weight_kg) || 0) + '" ' +
                        'min="0" max="' + remaining + '" value="' + preset + '">';
                itemsList.appendChild(row);
            });
            if (itemsSection) itemsSection.classList.toggle('d-none', shown === 0);

            var isSr = (trigger && trigger.getAttribute('data-shiprocket')) === '1';
            form.setAttribute('data-shiprocket', isSr ? '1' : '0');
            if (manualBlock) manualBlock.classList.toggle('d-none', isSr);
            if (srBlock) srBlock.classList.toggle('d-none', !isSr);
            if (srNote) srNote.classList.toggle('d-none', !isSr);

            if (isSr) {
                var isEdit = (trigger && trigger.getAttribute('data-edit')) === '1';
                var title = (trigger && trigger.getAttribute('data-title')) || 'Shiprocket';
                if (srNoteText) {
                    srNoteText.textContent = 'Booked automatically through ' + title +
                        '. Courier, AWB and label are assigned by Shiprocket — no manual tracking to enter.';
                }
                var packages = [];
                var defaults = {};
                try { packages = JSON.parse((trigger && trigger.getAttribute('data-packages')) || '[]'); } catch (e) { packages = []; }
                try { defaults = JSON.parse((trigger && trigger.getAttribute('data-defaults')) || '{}'); } catch (e) { defaults = {}; }

                if (pkgSelect) {
                    pkgSelect.innerHTML = '';
                    var defPkg = null;
                    (packages || []).forEach(function (p) {
                        var opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.name + ' — ' + p.length_cm + ' × ' + p.breadth_cm + ' × ' + p.height_cm + ' cm';
                        opt.setAttribute('data-l', p.length_cm);
                        opt.setAttribute('data-b', p.breadth_cm);
                        opt.setAttribute('data-h', p.height_cm);
                        opt.setAttribute('data-w', p.weight_kg);
                        pkgSelect.appendChild(opt);
                        if (!isEdit && p.is_default) { pkgSelect.value = String(p.id); defPkg = p; }
                    });
                    var custom = document.createElement('option');
                    custom.value = 'custom';
                    custom.textContent = 'Custom…';
                    pkgSelect.appendChild(custom);
                    if (isEdit) pkgSelect.value = 'custom';

                    setSrDims(defPkg || {
                        length_cm: defaults.length_cm,
                        breadth_cm: defaults.breadth_cm,
                        height_cm: defaults.height_cm,
                        weight_kg: defaults.weight_kg,
                    });
                    if (defPkg) {
                        applyPackageWeight();
                    } else if (!isEdit && itemsWeightKg() > 0) {
                        var wInp = form.querySelector('[name="weight_kg"]');
                        if (wInp) wInp.value = round3(itemsWeightKg());
                    }
                }
                updateBilled();
            }
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var submitBtn = form.querySelector('button[type="submit"]');

            // Per-line quantities (clamped).
            var qtyInputs = form.querySelectorAll('.js-fulfill-qty');
            var hasLineInputs = qtyInputs.length > 0;
            var items = [];
            qtyInputs.forEach(function (inp) {
                var id = parseInt(inp.getAttribute('data-order-item-id'), 10) || 0;
                var max = parseInt(inp.getAttribute('max'), 10) || 0;
                var qty = parseInt(inp.value, 10) || 0;
                if (qty < 0) qty = 0;
                if (qty > max) qty = max;
                if (id > 0 && qty > 0) items.push({ order_item_id: id, quantity: qty });
            });

            if (hasLineInputs && items.length === 0) {
                notifyErr(form.getAttribute('data-error-no-items') || 'Select a quantity for at least one item to fulfill.');
                return;
            }

            var payload;
            if (form.getAttribute('data-shiprocket') === '1') {
                var num = function (name) {
                    var v = (form.querySelector('[name="' + name + '"]') || {}).value;
                    return (v === '' || v == null) ? null : parseFloat(v);
                };
                payload = {
                    length_cm: num('length_cm'),
                    breadth_cm: num('breadth_cm'),
                    height_cm: num('height_cm'),
                    weight_kg: num('weight_kg'),
                };
            } else {
                payload = {
                    carrier_name: (form.querySelector('[name="carrier_name"]') || {}).value || null,
                    tracking_number: (form.querySelector('[name="tracking_number"]') || {}).value || null,
                    tracking_url: (form.querySelector('[name="tracking_url"]') || {}).value || null,
                };
            }
            if (hasLineInputs) payload.items = items;

            post(form.getAttribute('data-fulfill-url'), payload, submitBtn);
        });
    }

    // "Edit fulfillment" modal — prefilled from the triggering button's data-*
    var editModalEl = document.getElementById('editFulfillmentModal');
    var editForm = document.getElementById('editFulfillmentForm');
    if (editModalEl && editForm) {
        editModalEl.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            editForm.setAttribute('data-edit-url', (trigger && trigger.getAttribute('data-edit-url')) || '');
            var c = editForm.querySelector('[name="carrier_name"]');
            var t = editForm.querySelector('[name="tracking_number"]');
            var u = editForm.querySelector('[name="tracking_url"]');
            if (c) c.value = (trigger && trigger.getAttribute('data-carrier')) || '';
            if (t) t.value = (trigger && trigger.getAttribute('data-tracking')) || '';
            if (u) u.value = (trigger && trigger.getAttribute('data-tracking-url')) || '';
        });

        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var submitBtn = editForm.querySelector('button[type="submit"]');
            // Send null (not '') for cleared fields so the hardened url rule isn't
            // tripped by an empty string; the backend treats null as "clear it".
            var payload = {
                carrier_name: (editForm.querySelector('[name="carrier_name"]') || {}).value || null,
                tracking_number: (editForm.querySelector('[name="tracking_number"]') || {}).value || null,
                tracking_url: (editForm.querySelector('[name="tracking_url"]') || {}).value || null,
            };
            post(editForm.getAttribute('data-edit-url'), payload, submitBtn);
        });
    }

    // Shipment transitions with no confirmation: Mark as delivered
    document.querySelectorAll('.js-shipment-action').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var template = btn.getAttribute('data-transition-url');
            var shipmentId = btn.getAttribute('data-shipment-id');
            var to = btn.getAttribute('data-to');
            if (!template || !shipmentId || !to) return;
            post(template.replace('SHIPMENT_ID', shipmentId), { status: to }, btn);
        });
    });

    // Confirmation modals (x-confirm-modal component). A .js-confirm-open trigger
    // carries data-action-url + data-action-payload (JSON) and opens the modal in
    // its data-bs-target; the modal's .js-confirm-action button POSTs on confirm.
    document.querySelectorAll('.js-confirm-action').forEach(function (confirmBtn) {
        var modal = confirmBtn.closest('.modal');
        if (!modal) return;

        modal.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            confirmBtn.setAttribute('data-action-url', (trigger && trigger.getAttribute('data-action-url')) || '');
            confirmBtn.setAttribute('data-action-payload', (trigger && trigger.getAttribute('data-action-payload')) || '{}');
        });

        confirmBtn.addEventListener('click', function () {
            var url = confirmBtn.getAttribute('data-action-url');
            var payload = {};
            try { payload = JSON.parse(confirmBtn.getAttribute('data-action-payload') || '{}'); } catch (e) { payload = {}; }
            post(url, payload, confirmBtn);
        });
    });
});
