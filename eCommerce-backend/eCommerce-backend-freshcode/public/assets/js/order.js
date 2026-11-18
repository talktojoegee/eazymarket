$(document).ready(function () {

    // The orders index renders one grouped table per fulfillment method
    // (#orders-list-table-manual, #orders-list-table-shiprocket, ...) inside
    // tabs. #orders-table only survives on the seller dashboard widget, so
    // everything is looked up defensively and null-guarded.
    const ordersTable = $('#orders-table').length ? $('#orders-table').DataTable() : null;
    const $listTableEls = $('table[id^="orders-list-table"]');
    const ordersListTables = $listTableEls.toArray().map((el) => $(el).DataTable());
    let currentOrderId = null;
    let storeTomSelect = null;

    const updateOrderCount = () => {
        if (ordersListTables.length) {
            let total = 0;
            ordersListTables.forEach((table) => {
                const info = table.page.info();
                if (!info) return;
                total += info.recordsTotal;
                const method = $(table.table().node()).attr('id').replace('orders-list-table-', '');
                $('.order-tab-count[data-method="' + method + '"]').text(info.recordsTotal);
            });
            $('.order-count').html("(" + total + ")");
        } else if (ordersTable && ordersTable.page.info() !== undefined) {
            $('.order-count').html("(" + ordersTable.page.info().recordsTotal + ")");
        }
    };

    const reloadActiveTable = () => {
        ordersListTables.forEach((table) => table.ajax.reload(updateOrderCount, false));
        if (ordersTable) ordersTable.ajax.reload(updateOrderCount, false);
    };

    $listTableEls.on('draw.dt', updateOrderCount);

    $('#order-method-tabs a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        if ($.fn.dataTable) {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        }
    });

    // Pre-select order type filter from URL ?type= param
    const urlType = new URLSearchParams(window.location.search).get('type');
    if (urlType && $('#orderTypeFilter').length) {
        $('#orderTypeFilter').val(urlType);
    }

    // Pre-select fulfillment status filter from URL ?status= param
    const urlStatus = new URLSearchParams(window.location.search).get('status');
    if (urlStatus && $('#statusFilter').length) {
        $('#statusFilter').val(urlStatus);
        setTimeout(reloadActiveTable, 50);
    }

    // ── Market filter — rendered by <x-tom-select-market> (tom-select-pickers.js
    //    bootstraps the TomSelect). We just reload the active table on change.
    $('#marketFilter').on('change', function () {
        reloadActiveTable();
    });

    // ── Store TomSelect (server-side search, seller panel) ──
    const storeConfig = window.__ORDER_STORE_CONFIG__ || {};
    const storeSelectEl = document.getElementById('storeFilter');

    if (storeSelectEl && window.TomSelect && storeConfig.searchUrl) {
        storeTomSelect = new TomSelect(storeSelectEl, {
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            placeholder: storeSelectEl.getAttribute('placeholder') || '',
            preload: true,
            allowEmptyOption: true,
            load: function (query, callback) {
                var url = new URL(storeConfig.searchUrl, window.location.origin);
                if (query) url.searchParams.set('q', query);
                axios.get(url.toString())
                    .then(function (r) { callback(Array.isArray(r.data) ? r.data : []); })
                    .catch(function () { callback(); });
            },
            onChange: function () {
                reloadActiveTable();
            },
        });

        if (storeConfig.initialStoreId) {
            var stFindUrl = new URL(storeConfig.searchUrl, window.location.origin);
            stFindUrl.searchParams.set('find_id', storeConfig.initialStoreId);
            axios.get(stFindUrl.toString()).then(function (r) {
                if (Array.isArray(r.data) && r.data.length > 0) {
                    storeTomSelect.addOption(r.data[0]);
                    storeTomSelect.setValue(r.data[0].value, true);
                }
            });
        }
    }

    // Reload the table when any filter changes.
    $('#rangeFilter, #statusFilter, #paymentStatusFilter, #paymentMethodFilter, #lifecycleFilter')
        .on('change', reloadActiveTable);
    $('#refresh').on('click', reloadActiveTable);

    setTimeout(function () {
        updateOrderCount();
    }, 1000)

    // Attach the current filter values to the datatable AJAX request.
    $('#orders-table').add($listTableEls).on('preXhr.dt', function (e, settings, data) {
        data.range = $('#rangeFilter').val() || '';
        data.status = $('#statusFilter').val() || '';            // fulfillment axis
        data.payment_status = $('#paymentStatusFilter').val() || '';
        data.payment_method = $('#paymentMethodFilter').val() || '';
        data.lifecycle = $('#lifecycleFilter').val() || '';      // open / archived / cancelled
        data.market_id = $('#marketFilter').val() || '';
        data.store_id = storeTomSelect ? storeTomSelect.getValue() : '';
    });

    // ===== Orders tab: expand to show items =====
    (function initOrdersExpand() {
        if (!ordersListTables.length) return;
        const meta = document.getElementById('order-items-meta');
        if (!meta) return;

        let labels = {};
        try { labels = JSON.parse(meta.dataset.itemsLabels || '{}'); } catch (e) { labels = {}; }
        const urlTemplate = meta.dataset.itemsUrl || '';

        const setChevron = ($btn, open) => {
            const $icon = $btn.find('i');
            $icon.removeClass('ti-chevron-right ti-chevron-down')
                .addClass(open ? 'ti-chevron-down' : 'ti-chevron-right');
        };

        const wrap = (inner) => `<div class="p-3 border-top">${inner}</div>`;
        const escapeHtml = (val) => {
            if (val === null || val === undefined) return '';
            return String(val)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };

        $listTableEls.find('tbody').on('click', '.expand-order-items', function (e) {
            e.stopPropagation();
            const $btn = $(this);
            const $tr = $btn.closest('tr');
            const $table = $btn.closest('table[id^="orders-list-table"]');
            if (!$table.length) return;
            const row = $table.DataTable().row($tr);
            if (!row || !row.node()) return;

            if (row.child.isShown()) {
                row.child.hide();
                $tr.removeClass('shown');
                setChevron($btn, false);
                return;
            }

            const orderId = $btn.data('orderId');
            if (!orderId || !urlTemplate) return;

            row.child(wrap(`<div class="d-flex align-items-center justify-content-center py-4 text-secondary"><div class="spinner-border spinner-border-sm me-2" role="status"></div>${escapeHtml(labels.loading || 'Loading...')}</div>`)).show();
            $tr.addClass('shown');
            setChevron($btn, true);

            const url = urlTemplate.replace('__ID__', encodeURIComponent(orderId));
            axios.get(url)
                .then((response) => {
                    const html = response?.data?.data?.html;
                    if (html) {
                        row.child(html).show();
                    } else {
                        row.child(wrap(`<div class="text-secondary">${escapeHtml(labels.error || 'Failed to load items.')}</div>`)).show();
                    }
                    $tr.addClass('shown');
                    if (typeof refreshFsLightbox !== 'undefined') refreshFsLightbox();
                })
                .catch(() => {
                    row.child(wrap(`<div class="text-danger">${escapeHtml(labels.error || 'Failed to load items.')}</div>`)).show();
                    $tr.addClass('shown');
                });
        });
    })();

});
