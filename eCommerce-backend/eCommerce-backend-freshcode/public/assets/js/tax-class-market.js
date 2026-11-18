'use strict';

/**
 * Admin tax-classes screen — market filter + modal market field hydration.
 *
 * Wires:
 *   1. #tax-class-market-filter (top-of-list dropdown) → re-loads the
 *      #tax-group-table DataTable with the chosen market_id as a query param.
 *   2. #tax-class-modal open → sets #class-market-id to the editing row's
 *      market_id (Edit) or clears it (Create).
 *
 * The base modal wiring (title field, tax_rate_ids TomSelect, form action swap)
 * is in custom.js — this file only adds the market-aware bits.
 */

(function () {
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    // Set/clear the market <select> value. When TomSelect has enhanced the
    // field (el.tomselect), drive it through the instance so the visible
    // control updates; otherwise fall back to the raw <select> value.
    function setMarketValue(el, val) {
        if (!el) return;
        const v = (val == null || val === '') ? '' : String(val);
        if (el.tomselect) {
            v ? el.tomselect.setValue(v, true) : el.tomselect.clear(true);
        } else {
            el.value = v;
        }
    }

    function applyMarketFilterToDatatable(marketId) {
        const tableEl = document.getElementById('tax-group-table');
        if (!tableEl || !window.jQuery || !jQuery.fn.DataTable.isDataTable(tableEl)) return;

        const dt = jQuery(tableEl).DataTable();
        // ajax.url() preserves all of DataTable's other request params (search/order/etc.);
        // we append market_id via a query string mutation that survives reloads.
        const baseUrl = dt.ajax.url().split('?')[0];
        const params  = new URLSearchParams(dt.ajax.url().split('?')[1] || '');
        if (marketId) {
            params.set('market_id', marketId);
        } else {
            params.delete('market_id');
        }
        const newUrl = baseUrl + (params.toString() ? '?' + params.toString() : '');
        dt.ajax.url(newUrl).load();
    }

    ready(function () {
        const filterEl = document.getElementById('tax-class-market-filter');
        const params   = new URLSearchParams(window.location.search);
        const initialMarket = params.get('market_id');

        if (filterEl) {
            // Honour ?market_id=X on load — set the dropdown and the DataTable filter.
            if (initialMarket) {
                filterEl.value = initialMarket;
                // DataTable initialises async; defer the URL mutation by one tick
                // so the DataTable instance exists before we touch ajax.url().
                setTimeout(() => applyMarketFilterToDatatable(initialMarket), 0);
            }

            filterEl.addEventListener('change', function () {
                applyMarketFilterToDatatable(this.value);
            });
        }

        // Honour ?open=create-tax-class on load — open the modal so the admin
        // can immediately create a tax class scoped to the pre-filtered market.
        if (params.get('open') === 'create-tax-class') {
            const modalEl = document.getElementById('tax-class-modal');
            if (modalEl && window.bootstrap?.Modal) {
                // Pre-select the market on the modal's market dropdown so the
                // form already has the correct value when it opens.
                const marketEl = document.getElementById('class-market-id');
                if (marketEl && initialMarket) setMarketValue(marketEl, initialMarket);

                // Strip the open=… param from the URL so refreshes don't reopen.
                params.delete('open');
                const cleanUrl = window.location.pathname
                    + (params.toString() ? '?' + params.toString() : '')
                    + window.location.hash;
                window.history.replaceState({}, document.title, cleanUrl);

                new window.bootstrap.Modal(modalEl).show();
            }
        }

        // When the tax-class modal opens, hydrate / clear the market <select>.
        // Edit flow: custom.js fires a fetch to /tax-classes/{id}/edit and waits
        // for its data — we hook the same event and read the market_id off the
        // triggering row's [data-market-id] attribute, which the datatable row
        // template should expose. If the attribute isn't present (older row),
        // we fall back to letting custom.js set it from the AJAX payload (handled
        // by the small mutation observer below).
        const modal = document.getElementById('tax-class-modal');
        if (!modal) return;

        modal.addEventListener('show.bs.modal', function (event) {
            const trigger     = event.relatedTarget;
            const taxClassId  = trigger ? trigger.getAttribute('data-id') : null;
            const marketEl    = document.getElementById('class-market-id');
            if (!marketEl) return;

            if (!taxClassId) {
                // Create flow — reset to placeholder.
                setMarketValue(marketEl, '');
                return;
            }

            // Edit flow — fetch the row's market and select it. We reuse the same
            // endpoint custom.js calls so this works regardless of which loaded first.
            const url = `${base_url}/${panel}/tax-classes/${taxClassId}/edit`;
            fetch(url, { method: 'GET' })
                .then(r => r.json())
                .then(resp => {
                    const marketId = resp?.data?.market_id;
                    if (marketId != null) {
                        setMarketValue(marketEl, marketId);
                    }
                })
                .catch(() => { /* surface failure left to custom.js */ });
        });
    });
})();
