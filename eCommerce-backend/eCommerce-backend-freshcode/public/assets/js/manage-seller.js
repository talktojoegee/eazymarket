$(document).ready(function() {
    // Initialize variables
    let currentCommissionId = null;
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    // Initialize Tom Select for Store filter (server-side)
    try {
        const storeEl = document.getElementById('storeFilter');
        if (storeEl && window.TomSelect) {
            new TomSelect(storeEl, {
                copyClassesToDropdown: false,
                dropdownParent: 'body',
                controlInput: '<input>',
                valueField: 'value',
                labelField: 'text',
                searchField: 'text',
                placeholder: (typeof labels !== 'undefined' && labels.store) ? labels.store : 'Store',
                load: function (query, callback) {
                    if (!query.length) return callback();
                    const url = (typeof panel !== 'undefined' && panel === 'admin')
                        ? `${base_url}/admin/sellers/store/search?search=${encodeURIComponent(query)}`
                        : `${base_url}/seller/stores/search?search=${encodeURIComponent(query)}`;
                    fetch(url)
                        .then(response => response.json())
                        .then(json => callback(json))
                        .catch(() => callback());
                }
            });

            // Reload tables on change
            storeEl.addEventListener('change', function () {
                if ($('#commissions-table').length) {
                    $('#commissions-table').DataTable().ajax.reload();
                }
                if ($('#debits-table').length) {
                    $('#debits-table').DataTable().ajax.reload();
                }
                if ($('#sellers-rollup-table').length) {
                    $('#sellers-rollup-table').DataTable().ajax.reload();
                }
                if ($('#commissions-history-table').length) {
                    $('#commissions-history-table').DataTable().ajax.reload();
                }
            });
        }
    } catch (e) { console.error(e); }

    // Inject store_id into DataTables requests where applicable
    try {
        $('#commissions-table').on('preXhr.dt', function (e, settings, data) {
            data.store_id = $('#storeFilter').val();
            data.date_range = $('#dateRangeFilter').val() || '';
        });
    } catch (e) {}
    try {
        $('#debits-table').on('preXhr.dt', function (e, settings, data) {
            data.store_id = $('#storeFilter').val();
            data.date_range = $('#dateRangeFilter').val() || '';
        });
    } catch (e) {}
    try {
        $('#sellers-rollup-table').on('preXhr.dt', function (e, settings, data) {
            data.store_id = $('#storeFilter').val();
            data.date_range = $('#dateRangeFilter').val() || '';
            data.scope = $('#rollupScope').val() || 'pending';
        });
    } catch (e) {}

    // Reload the rollup when its scope (pending only / all sellers) changes
    $('#rollupScope').on('change', function () {
        if ($('#sellers-rollup-table').length) $('#sellers-rollup-table').DataTable().ajax.reload();
    });
    try {
        $('#commissions-history-table').on('preXhr.dt', function (e, settings, data) {
            data.store_id = $('#storeFilter').val();
        });
    } catch (e) {}

    // --- Phase 1: Earnings & Deductions summary cards --------------------
    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function loadEarningsSummary() {
        if (!document.getElementById('earnings-summary')) return;
        const summaryUrl = `${base_url}/${(typeof panel !== 'undefined' ? panel : 'admin')}/commissions/summary`;
        axios.get(summaryUrl, {
            params: {
                store_id: $('#storeFilter').val() || '',
                date_range: $('#dateRangeFilter').val() || ''
            }
        }).then(function (response) {
            const r = response.data;
            if (!r || !r.success || !r.data) return;
            const d = r.data;
            setText('sum-credits', d.credits_total_formatted);
            setText('sum-credits-count', d.credits_count);
            setText('sum-debits', d.debits_total_formatted);
            setText('sum-debits-count', d.debits_count);
            setText('sum-net', d.net_total_formatted);
            setText('sum-pending-count', d.pending_count);
            setText('sum-matured', d.matured_total_formatted);
            setText('sum-matured-count', d.matured_count);
            if (d.sellers_awaiting !== null && d.sellers_awaiting !== undefined) {
                setText('sum-sellers', d.sellers_awaiting);
            }
            const aging = d.aging || {};
            ['0_7', '8_15', '16_30', '31_plus'].forEach(function (k) {
                if (aging[k]) {
                    setText('aging-' + k + '-amount', aging[k].amount_formatted);
                    setText('aging-' + k + '-count', aging[k].count);
                }
            });
        }).catch(function (error) {
            console.error('Failed to load earnings summary:', error);
        });
    }

    // Reload summary when the date-range filter changes (also reload the tables).
    $('#dateRangeFilter').on('change', function () {
        if ($('#commissions-table').length) $('#commissions-table').DataTable().ajax.reload();
        if ($('#debits-table').length) $('#debits-table').DataTable().ajax.reload();
        if ($('#sellers-rollup-table').length) $('#sellers-rollup-table').DataTable().ajax.reload();
        loadEarningsSummary();
    });

    // Store filter changes already reload the tables; also refresh the summary.
    $('#storeFilter').on('change', loadEarningsSummary);

    // Initial load.
    loadEarningsSummary();

    // Refresh button
    $('#refresh').on('click', function() {
        // Refresh any present tables on the page
        if ($('#commissions-table').length) {
            $('#commissions-table').DataTable().ajax.reload();
        }
        if ($('#debits-table').length) {
            $('#debits-table').DataTable().ajax.reload();
        }
        if ($('#sellers-rollup-table').length) {
            $('#sellers-rollup-table').DataTable().ajax.reload();
        }
        if ($('#commissions-history-table').length) {
            $('#commissions-history-table').DataTable().ajax.reload();
        }
        loadEarningsSummary();
    });

    // Export the current tab (credits / debits / history) with the active filters
    $('#exportBtn').on('click', function () {
        const panelName = (typeof panel !== 'undefined' ? panel : 'admin');
        let type = 'credits';
        if ($('#commissions-history-table').length) {
            type = 'history';
        } else if ($('#tab-debits').hasClass('active')) {
            type = 'debits';
        }
        const params = new URLSearchParams({
            type: type,
            store_id: $('#storeFilter').val() || '',
            date_range: $('#dateRangeFilter').val() || ''
        });
        window.location = `${base_url}/${panelName}/commissions/export?` + params.toString();
    });

    // Open the print-optimised payout statement in a new tab (seller panel)
    $('#statementBtn').on('click', function () {
        const panelName = (typeof panel !== 'undefined' ? panel : 'seller');
        const params = new URLSearchParams({
            date_range: $('#dateRangeFilter').val() || ''
        });
        window.open(`${base_url}/${panelName}/commissions/statement?` + params.toString(), '_blank');
    });

    // Per-seller settle (Phase 3 rollup)
    let currentSellerId = null;
    $(document).on('click', '.settle-seller-btn', function () {
        currentSellerId = $(this).data('seller-id');
        $('#settle-seller-name').text($(this).data('seller'));
        $('#settle-seller-net').text($(this).data('net'));
        $('#settleSellerModal').modal('show');
    });

    $('#confirmSettleSeller').on('click', function () {
        if (!currentSellerId) return;
        const btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
        axios.post(`${base_url}/admin/commissions/sellers/${currentSellerId}/settle`, { _token: csrfToken }, {
            headers: { 'X-CSRF-TOKEN': csrfToken }
        })
        .then(function (response) {
            const data = response.data;
            Toast.fire({ icon: data.success ? 'success' : 'error', title: data.message });
            ['#commissions-table', '#debits-table', '#sellers-rollup-table'].forEach(function (sel) {
                if ($(sel).length) $(sel).DataTable().ajax.reload();
            });
            loadEarningsSummary();
        })
        .catch(function (error) {
            Toast.fire({ icon: 'error', title: error.response?.data?.message || 'An error occurred' });
        })
        .finally(function () {
            btn.prop('disabled', false).html('Confirm');
            $('#settleSellerModal').modal('hide');
        });
    });

    // Per-seller statement (admin rollup row)
    $(document).on('click', '.seller-statement-btn', function () {
        const sellerId = $(this).data('seller-id');
        const panelName = (typeof panel !== 'undefined' ? panel : 'admin');
        const params = new URLSearchParams({
            seller_id: sellerId,
            date_range: $('#dateRangeFilter').val() || ''
        });
        window.open(`${base_url}/${panelName}/commissions/statement?` + params.toString(), '_blank');
    });

    // Hold / release a pending entry (Phase 3b)
    function reloadEarningTables() {
        ['#commissions-table', '#debits-table', '#sellers-rollup-table'].forEach(function (sel) {
            if ($(sel).length) $(sel).DataTable().ajax.reload();
        });
        loadEarningsSummary();
    }

    let currentHoldUrl = null;
    $(document).on('click', '.hold-commission-btn', function () {
        currentHoldUrl = $(this).data('hold-url');
        if (!currentHoldUrl) return;
        $('#holdReasonInput').val('');
        $('#holdModal').modal('show');
    });

    $('#confirmHold').on('click', function () {
        if (!currentHoldUrl) return;
        const btn = $(this);
        const originalHtml = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
        axios.post(currentHoldUrl, { _token: csrfToken, reason: $('#holdReasonInput').val() }, {
            headers: { 'X-CSRF-TOKEN': csrfToken }
        })
        .then(function (r) {
            Toast.fire({ icon: r.data.success ? 'success' : 'error', title: r.data.message });
            reloadEarningTables();
        })
        .catch(function (e) { Toast.fire({ icon: 'error', title: e.response?.data?.message || 'An error occurred' }); })
        .finally(function () {
            btn.prop('disabled', false).html(originalHtml);
            $('#holdModal').modal('hide');
        });
    });

    $(document).on('click', '.unhold-commission-btn', function () {
        const url = $(this).data('unhold-url');
        if (!url) return;
        axios.post(url, { _token: csrfToken }, { headers: { 'X-CSRF-TOKEN': csrfToken } })
            .then(function (r) {
                Toast.fire({ icon: r.data.success ? 'success' : 'error', title: r.data.message });
                reloadEarningTables();
            })
            .catch(function (e) { Toast.fire({ icon: 'error', title: e.response?.data?.message || 'An error occurred' }); });
    });

    // Settle single commission
    $(document).on('click', '.settle-commission-btn', function(e) {
        e.preventDefault();
        currentCommissionId = $(this).data('id');
        const orderId = $(this).data('order-id');
        const store = $(this).data('store');
        const product = $(this).data('product');
        const commission = $(this).data('commission');
        const amount = $(this).data('amount');

        // Set values in the modal
        $('#settlement-order-id').text(orderId);
        $('#settlement-product').text(product);
        $('#settlement-commission').text(commission);
        $('#settlement-amount').text(amount);

        // Show the modal
        $('#settlementModal').modal('show');
    });

    // Confirm single settlement
    $('#confirmSettlement').on('click', function() {
        if (!currentCommissionId) return;

        // Disable the button and show loading state
        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');

        // Process the settlement using axios
        const settleUrl = $(this).data('settle-url') || $('.settle-commission-btn[data-id="'+currentCommissionId+'"]').data('settle-url') || `${base_url}/admin/commissions/${currentCommissionId}/settle`;
        axios.post(settleUrl, {
            _token: $('meta[name="csrf-token"]').attr('content'),
        })
        .then(function(response) {
            const data = response.data;
            if (data.success) {
                Toast.fire({
                    icon: 'success',
                    title: data.message
                });
                if ($('#commissions-table').length) {
                    $('#commissions-table').DataTable().ajax.reload();
                }
                if ($('#debits-table').length) {
                    $('#debits-table').DataTable().ajax.reload();
                }
                if ($('#commissions-history-table').length) {
                    $('#commissions-history-table').DataTable().ajax.reload();
                }
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data.message
                });
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            Toast.fire({
                icon: 'error',
                title: error.response?.data?.message || 'An error occurred while processing the settlement'
            });
        })
        .finally(function() {
            // Reset the button state
            $('#confirmSettlement').prop('disabled', false).html('Confirm');

            // Hide the modal
            $('#settlementModal').modal('hide');
        });
    });

    // View settlement detail (both panels, credits/debits/history)
    $(document).on('click', '.view-commission-btn', function (e) {
        e.preventDefault();
        const url = $(this).data('detail-url');
        if (!url) return;

        const $body = $('#commissionDetailBody');
        const loader = '<div class="text-center text-secondary py-5"><div class="spinner-border" role="status"></div></div>';
        $body.html(loader);
        $('#commissionDetailModal').modal('show');

        axios.get(url)
            .then(function (response) {
                const data = response.data;
                if (data && data.success && data.data) {
                    if (data.data.title) {
                        $('#commissionDetailTitle').text(data.data.title);
                    }
                    $body.html(data.data.html || '');
                } else {
                    $body.html('<div class="text-center text-danger py-5">' + ((data && data.message) || 'Failed to load details') + '</div>');
                }
            })
            .catch(function (error) {
                console.error('Error:', error);
                $body.html('<div class="text-center text-danger py-5">' + (error.response?.data?.message || 'Failed to load details') + '</div>');
            });
    });

    // Settle all commissions (credits)
    let settleAllUrl = `${base_url}/admin/commissions/settle-all`;
    $('#settle-all-btn').on('click', function() {
        settleAllUrl = `${base_url}/admin/commissions/settle-all`;
        $('#settleAllModal').modal('show');
    });
    // Settle all debit settlements
    $('#settle-all-debits-btn').on('click', function() {
        settleAllUrl = `${base_url}/admin/commissions/debits/settle-all`;
        $('#settleAllModal').modal('show');
    });

    // Run the auto-settle cron on demand (settle matured credits now).
    $('#run-auto-settle-btn').on('click', function () {
        const btn = $(this);
        const url = btn.data('url');
        if (!url) return;
        Swal.fire({
            title: (typeof trans !== 'undefined' && trans.run_auto_settle_now) || 'Run auto-settle now?',
            text: (typeof trans !== 'undefined' && trans.run_auto_settle_confirm) || 'Settle all matured payouts into seller wallets now?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: (typeof trans !== 'undefined' && trans.confirm) || 'Confirm'
        }).then(function (res) {
            if (!res.isConfirmed) return;
            const originalHtml = btn.html();
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
            axios.post(url, {}, { headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken } })
                .then(function (response) {
                    const data = response.data;
                    Toast.fire({ icon: data.success ? 'success' : 'error', title: data.message });
                    reloadEarningTables();
                })
                .catch(function (e) {
                    Toast.fire({ icon: 'error', title: e.response?.data?.message || 'An error occurred' });
                })
                .finally(function () {
                    btn.prop('disabled', false).html(originalHtml);
                });
        });
    });

    // Confirm settle all
    $('#confirmSettleAll').on('click', function() {
        // Disable the button and show loading state
        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');

        // Process all settlements using axios
        axios.post(settleAllUrl, {}, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(function(response) {
            const data = response.data;
            if (data.success) {
                Toast.fire({
                    icon: 'success',
                    title: data.message
                });
                if ($('#commissions-table').length) {
                    $('#commissions-table').DataTable().ajax.reload();
                }
                if ($('#debits-table').length) {
                    $('#debits-table').DataTable().ajax.reload();
                }
                if ($('#commissions-history-table').length) {
                    $('#commissions-history-table').DataTable().ajax.reload();
                }
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data.message
                });
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            Toast.fire({
                icon: 'error',
                title: error.response?.data?.message || 'An error occurred while processing the settlements'
            });
        })
        .finally(function() {
            // Reset the button state
            $('#confirmSettleAll').prop('disabled', false).html('Confirm');

            // Hide the modal
            $('#settleAllModal').modal('hide');
        });
    });
});
