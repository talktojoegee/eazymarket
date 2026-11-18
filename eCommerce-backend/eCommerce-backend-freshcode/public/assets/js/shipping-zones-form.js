/* global jQuery, axios, Toast, TomSelect */
/*
 * Shipping Zones — modal-driven CRUD inside the profile edit page.
 *
 * Coverage scope: country-level. Within a manual zone, a serviceability
 * mode (none / deny / allow) plus per-country rules (pincode exact/prefix/
 * range, or city/state) restrict where the zone delivers. Serviceability is
 * hidden for carrier (Shiprocket) zones — the courier decides.
 *
 * Endpoints exposed by the blade via `window.shippingZoneEndpoints`:
 *   base   — POST {profile}/zones (store)
 *   show   — base for GET/POST/DELETE {profile}/zones/{zone}
 */
(function ($) {
    'use strict';

    var endpoints = window.shippingZoneEndpoints || {};

    // Modal helpers use jQuery's .modal() plugin (Bootstrap 4 API still
    // shimmed by Tabler), matching store-locations-index.js. The Bootstrap 5
    // `window.bootstrap` global isn't exposed in this app.
    var $modalEl, $form, $submit, $title;
    var countryTS;
    var currencySymbol = '';

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content');
    }

    function showModal() { if ($modalEl) $modalEl.modal('show'); }
    function hideModal() { if ($modalEl) $modalEl.modal('hide'); }

    function bindModal() {
        $modalEl = $('#shippingZoneModal');
        if (!$modalEl.length) return;

        currencySymbol = $modalEl.data('currency-symbol') || '';

        $form = $('#shippingZoneForm');
        $submit = $('#shippingZoneSubmit');
        $title = $('#shippingZoneModalTitle');

        initCountryPicker();
        wireMethodToggle();
        wireRateTypeToggle();
        wireTierRepeaters();
        wireServiceability();
        wireSubmit();
    }

    /* ---------- Country picker ---------- */

    function initCountryPicker() {
        if (typeof TomSelect === 'undefined') return;

        var $sel = $('#zone-countries');
        var allowed = $sel.data('allowed-countries');
        if (!Array.isArray(allowed)) allowed = [];

        var config = {
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            dropdownParent: 'body',
            placeholder: $sel.data('placeholder') || 'Search countries…',
        };

        if (allowed.length) {
            config.options = allowed.map(function (c) {
                return { value: c.iso2, text: c.name + ' (' + c.iso2 + ')' };
            });
        } else {
            config.load = function (query, callback) {
                if (!query.length) return callback();
                fetch('/countries?value_field=iso2&search=' + encodeURIComponent(query))
                    .then(r => r.json())
                    .then(json => callback(json))
                    .catch(() => callback());
            };
        }

        countryTS = new TomSelect('#zone-countries', config);
        countryTS.on('change', function () { refreshServiceabilityCountries(); });
    }

    /* ---------- Rate type / method toggles ---------- */

    function wireMethodToggle() {
        var $method = $form.find('.js-zone-method');
        if (!$method.length) return;
        $method.on('change', function () {
            applyMethodFilter($(this).val());
        });
        applyMethodFilter($method.val());
    }

    function applyMethodFilter(method) {
        var $rate = $form.find('.js-zone-rate-type');
        var $rateCol = $('#zoneRateTypeCol');
        var $etaRow = $('#zoneEtaRow');
        var $note = $('#zoneShiprocketNote');

        $('#zoneServiceability').toggleClass('d-none', method === 'shiprocket');

        if (method === 'shiprocket') {
            $rate.find('option[value="carrier_quoted"]').prop('hidden', false).prop('disabled', false);
            $rate.val('carrier_quoted');
            $rateCol.prop('hidden', true);
            $etaRow.prop('hidden', true);
            $note.removeClass('d-none');
            $form.find('[data-show-when-rate]').each(function () { this.hidden = true; });
            return;
        }

        $rateCol.prop('hidden', false);
        $etaRow.prop('hidden', false);
        $note.addClass('d-none');

        var firstVisible = null;
        $rate.find('option').each(function () {
            var opt = $(this);
            var allowed = (opt.attr('data-methods') || '').toString();
            var show = allowed === method;
            opt.prop('hidden', !show).prop('disabled', !show);
            if (show && firstVisible === null) firstVisible = opt.val();
        });
        var current = $rate.val();
        var currentOpt = $rate.find('option[value="' + current + '"]');
        if (!current || currentOpt.prop('hidden')) {
            $rate.val(firstVisible);
        }
        $rate.trigger('change');
    }

    function wireRateTypeToggle() {
        var $select = $form.find('.js-zone-rate-type');
        $select.on('change', function () {
            applyRateTypeVisibility($(this).val());
        });
        applyRateTypeVisibility($select.val());
    }

    function applyRateTypeVisibility(rateType) {
        $form.find('[data-show-when-rate]').each(function () {
            var match = $(this).data('show-when-rate') === rateType;
            this.hidden = !match;
        });
    }

    /* ---------- Tier repeaters ---------- */

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // Plain numeric cell (e.g. weight in kg — not a money value).
    function numberCell(name) {
        return '<td><input type="number" step="0.01" min="0" name="' + name + '" class="form-control form-control-sm"></td>';
    }

    // Money cell — prefixes the input with the admin-configured currency symbol.
    function moneyCell(name) {
        var prefix = currencySymbol
            ? '<span class="input-group-text">' + escapeHtml(currencySymbol) + '</span>'
            : '';
        return '<td><div class="input-group input-group-sm">' + prefix +
            '<input type="number" step="0.01" min="0" name="' + name + '" class="form-control"></div></td>';
    }

    function tierRow(kind) {
        if (kind === 'weight') {
            return '<tr class="tier-row">' +
                numberCell('weight_rules[][min_kg]') +
                numberCell('weight_rules[][max_kg]') +
                moneyCell('weight_rules[][rate]') +
                '<td><button type="button" class="btn btn-sm btn-ghost-danger js-remove-tier"><i class="ti ti-x"></i></button></td>' +
                '</tr>';
        }
        return '<tr class="tier-row">' +
            moneyCell('price_rules[][min_amount]') +
            moneyCell('price_rules[][max_amount]') +
            moneyCell('price_rules[][rate]') +
            '<td><button type="button" class="btn btn-sm btn-ghost-danger js-remove-tier"><i class="ti ti-x"></i></button></td>' +
            '</tr>';
    }

    function wireTierRepeaters() {
        $form.on('click', '.js-add-weight-tier', function () {
            $('#weight-tier-rows').append(tierRow('weight'));
        });
        $form.on('click', '.js-add-price-tier', function () {
            $('#price-tier-rows').append(tierRow('price'));
        });
        $form.on('click', '.js-remove-tier', function () {
            $(this).closest('tr.tier-row').remove();
        });
    }

    /* ---------- Serviceability ---------- */

    var SERVICEABILITY_TYPES = [
        { value: 'pincode_exact', text: 'Pincode (exact)' },
        { value: 'pincode_prefix', text: 'Pincode (prefix)' },
        { value: 'pincode_range', text: 'Pincode (range)' },
        { value: 'city', text: 'City' },
        { value: 'state', text: 'State' },
    ];

    var SERVICEABILITY_MODE_HINTS = {
        none: 'No restriction — the zone serves every country it covers.',
        deny: 'Ship everywhere in the zone except destinations matching a rule.',
        allow: 'Ship only to destinations matching a rule.',
    };

    var SERVICEABILITY_TYPE_HINTS = {
        pincode_exact: 'Matches one exact pincode. e.g. 400001 → only 400001.',
        pincode_prefix: 'Matches every pincode starting with this. e.g. 19 → 190001, 194101, …',
        pincode_range: 'Matches pincodes between the two values (inclusive). e.g. 400001 to 400010.',
        city: 'Matches the destination city name. e.g. Al Ain. Use for countries without pincodes (UAE, Hong Kong).',
        state: 'Matches the destination state / province. e.g. Dubai.',
    };

    function selectedCountries() {
        if (!countryTS) return [];
        return countryTS.items.map(function (iso2) {
            var opt = countryTS.options[iso2] || {};
            return { iso2: iso2, name: opt.text || iso2 };
        });
    }

    function countryOptionsHtml(selected) {
        var list = selectedCountries();
        if (!list.length) {
            return '<option value="">' + '—' + '</option>';
        }
        return list.map(function (c) {
            var sel = c.iso2 === selected ? ' selected' : '';
            return '<option value="' + escapeHtml(c.iso2) + '"' + sel + '>' + escapeHtml(c.name) + '</option>';
        }).join('');
    }

    function typeOptionsHtml(selected) {
        return SERVICEABILITY_TYPES.map(function (t) {
            var sel = t.value === selected ? ' selected' : '';
            return '<option value="' + t.value + '"' + sel + '>' + t.text + '</option>';
        }).join('');
    }

    function serviceabilityRow(rule) {
        rule = rule || {};
        var type = rule.match_type || 'pincode_exact';
        var isRange = type === 'pincode_range';
        return '<div class="mb-2 serviceability-row">' +
            '<div class="row g-2 align-items-center">' +
            '<div class="col-3"><select class="form-select form-select-sm js-svc-country">' + countryOptionsHtml(rule.country_iso2) + '</select></div>' +
            '<div class="col-3"><select class="form-select form-select-sm js-svc-type">' + typeOptionsHtml(type) + '</select></div>' +
            '<div class="col js-svc-value-cell">' + valueCellHtml(type, rule) + '</div>' +
            '<div class="col-2' + (isRange ? '' : ' d-none') + ' js-svc-value-to-col"><input type="text" class="form-control form-control-sm js-svc-value-to" value="' + escapeHtml(rule.value_to || '') + '" placeholder="to"></div>' +
            '<div class="col-auto"><button type="button" class="btn btn-sm btn-ghost-danger js-remove-serviceability-rule"><i class="ti ti-x"></i></button></div>' +
            '</div>' +
            '<small class="form-hint mt-1 js-svc-hint"><i class="ti ti-info-circle me-1"></i>' + escapeHtml(hintFor(type)) + '</small>' +
            '</div>';
    }

    // Pincode types = free-text input; city/state = a TomSelect-backed select
    // (real data only). A saved value is pre-seeded as its own option so edit
    // mode shows it selected without a fetch.
    function valueCellHtml(type, rule) {
        rule = rule || {};
        if (type === 'city' || type === 'state') {
            var pre = rule.value
                ? ' data-preselect="' + escapeHtml(rule.value) + '"'
                : '';
            var opt = rule.value
                ? '<option value="' + escapeHtml(rule.value) + '" selected>' + escapeHtml(rule.value) + '</option>'
                : '';
            return '<select class="form-select form-select-sm js-svc-value-select"' + pre + '>' + opt + '</select>';
        }
        return '<input type="text" class="form-control form-control-sm js-svc-value" value="' + escapeHtml(rule.value || '') + '" placeholder="' + escapeHtml(placeholderFor(type)) + '">';
    }

    function isGeoType(type) {
        return type === 'city' || type === 'state';
    }

    function destroyValueSelect($row) {
        var el = $row.find('.js-svc-value-select')[0];
        if (el && el.tomselect) {
            el.tomselect.destroy();
        }
    }

    // Init/refresh the TomSelect on a row's value cell, scoped to the row's
    // country. States preload in full (few per country); cities load remotely
    // via prefix search (a country can have thousands).
    function initValueSelect($row) {
        var type = $row.find('.js-svc-type').val();
        if (!isGeoType(type) || typeof TomSelect === 'undefined') return;

        var $sel = $row.find('.js-svc-value-select');
        if (!$sel.length) return;
        destroyValueSelect($row);

        var country = $row.find('.js-svc-country').val();
        var preValue = $sel.data('preselect');
        var url = type === 'city' ? endpoints.geoCities : endpoints.geoStates;

        var config = {
            valueField: 'name',
            labelField: 'name',
            searchField: 'name',
            dropdownParent: 'body',
            maxOptions: 100,
            placeholder: type === 'city' ? 'Search city…' : 'Select state…',
            load: function (query, callback) {
                if (!country) return callback();
                if (type === 'city' && !query.length) return callback();
                var full = url + '?country=' + encodeURIComponent(country) +
                    (type === 'city' ? '&search=' + encodeURIComponent(query) : '');
                fetch(full, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.json(); })
                    .then(function (j) { callback((j.data && j.data.items) || []); })
                    .catch(function () { callback(); });
            },
        };
        if (type === 'state') config.preload = true;

        var ts = new TomSelect($sel[0], config);
        if (preValue) {
            ts.addOption({ name: preValue });
            ts.setValue(preValue, true);
        }
    }

    function addServiceabilityRule(rule) {
        var $row = $(serviceabilityRow(rule));
        $('#serviceability-rows').append($row);
        initValueSelect($row);
        return $row;
    }

    function hintFor(type) {
        return SERVICEABILITY_TYPE_HINTS[type] || SERVICEABILITY_TYPE_HINTS.pincode_exact;
    }

    function placeholderFor(type) {
        if (type === 'city') return 'City name';
        if (type === 'state') return 'State';
        if (type === 'pincode_prefix') return 'Prefix';
        return 'Pincode';
    }

    function wireServiceability() {
        $form.on('click', '.js-serviceability-mode', function () {
            setServiceabilityMode($(this).data('mode'));
        });

        $form.on('click', '.js-add-serviceability-rule', function () {
            addServiceabilityRule();
        });

        $form.on('click', '.js-remove-serviceability-rule', function () {
            var $row = $(this).closest('.serviceability-row');
            destroyValueSelect($row);
            $row.remove();
        });

        $form.on('change', '.js-svc-type', function () {
            var $row = $(this).closest('.serviceability-row');
            var type = $(this).val();
            destroyValueSelect($row);
            $row.find('.js-svc-value-cell').html(valueCellHtml(type, {}));
            $row.find('.js-svc-value-to-col').toggleClass('d-none', type !== 'pincode_range');
            $row.find('.js-svc-hint').html('<i class="ti ti-info-circle me-1"></i>' + escapeHtml(hintFor(type)));
            initValueSelect($row);
        });

        $form.on('change', '.js-svc-country', function () {
            var $row = $(this).closest('.serviceability-row');
            if (!isGeoType($row.find('.js-svc-type').val())) return;
            destroyValueSelect($row);
            $row.find('.js-svc-value-cell').html(valueCellHtml($row.find('.js-svc-type').val(), {}));
            initValueSelect($row);
        });
    }

    function setServiceabilityMode(mode) {
        if (['none', 'deny', 'allow'].indexOf(mode) === -1) mode = 'none';
        $('#serviceability-mode').val(mode);
        $form.find('.js-serviceability-mode').each(function () {
            $(this).toggleClass('active', $(this).data('mode') === mode);
        });
        $('#serviceability-mode-hint').text(SERVICEABILITY_MODE_HINTS[mode]);
        $('#serviceability-rules-wrap').toggleClass('d-none', mode === 'none');
    }

    function refreshServiceabilityCountries() {
        $('#serviceability-rows .serviceability-row').each(function () {
            var $sel = $(this).find('.js-svc-country');
            var current = $sel.val();
            $sel.html(countryOptionsHtml(current));
        });
    }

    function collectServiceability() {
        var out = [];
        $('#serviceability-rows .serviceability-row').each(function () {
            var $r = $(this);
            var type = $r.find('.js-svc-type').val();
            var $input = $r.find('.js-svc-value');
            var raw = $input.length ? $input.val() : $r.find('.js-svc-value-select').val();
            var value = (raw || '').trim();
            var country = $r.find('.js-svc-country').val();
            if (!country || !value) return;
            var rule = { country_iso2: country, match_type: type, value: value };
            if (type === 'pincode_range') {
                rule.value_to = ($r.find('.js-svc-value-to').val() || '').trim();
            }
            out.push(rule);
        });
        return out;
    }

    /* ---------- Open / populate ---------- */

    function openCreate() {
        resetForm();
        $form.attr('data-mode', 'create').attr('data-zone-id', '');
        $title.text('Add shipping zone');
        showModal();
    }

    function openEdit(zoneId) {
        resetForm();
        $form.attr('data-mode', 'edit').attr('data-zone-id', zoneId);
        $title.text('Edit shipping zone');

        fetch(endpoints.show + '/' + zoneId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(function (resp) {
                if (!resp || !resp.success) return;
                hydrateForm(resp.data.zone);
            })
            .catch(function () {});

        showModal();
    }

    function resetForm() {
        $form[0].reset();
        $('#weight-tier-rows').empty();
        $('#price-tier-rows').empty();
        $('#serviceability-rows').empty();
        setServiceabilityMode('none');
        if (countryTS) countryTS.clear(true);
        applyMethodFilter($form.find('.js-zone-method').val());
    }

    function hydrateForm(z) {
        $form.find('[name=name]').val(z.name);
        $form.find('[name=priority]').val(z.priority || 0);
        $form.find('[name=is_active]:checkbox').prop('checked', !!z.is_active);

        // Rate + ETA live on the rates child row now. `z.rate` is the
        // default rate the controller picks; fall back to a flat zero
        // shape if the API ever ships a zone without a rate.
        var r = z.rate || {};
        var method = z.method || 'manual';
        $form.find('.js-zone-method').val(method);
        applyMethodFilter(method);
        if (method !== 'shiprocket') {
            $form.find('.js-zone-rate-type').val(r.rate_type || 'flat').trigger('change');
        }

        $form.find('[name=flat_rate]').val(r.flat_rate || '');
        $form.find('[name=free_above]').val(r.free_above || '');
        $form.find('[name=free_above_weight]').val(r.free_above || '');
        $form.find('[name=free_above_price]').val(r.free_above || '');

        (r.weight_rules || []).forEach(function (t) {
            var $row = $(tierRow('weight'));
            $row.find('[name="weight_rules[][min_kg]"]').val(t.min_kg);
            $row.find('[name="weight_rules[][max_kg]"]').val(t.max_kg);
            $row.find('[name="weight_rules[][rate]"]').val(t.rate);
            $('#weight-tier-rows').append($row);
        });
        (r.price_rules || []).forEach(function (t) {
            var $row = $(tierRow('price'));
            $row.find('[name="price_rules[][min_amount]"]').val(t.min_amount);
            $row.find('[name="price_rules[][max_amount]"]').val(t.max_amount);
            $row.find('[name="price_rules[][rate]"]').val(t.rate);
            $('#price-tier-rows').append($row);
        });

        $form.find('[name=eta_min]').val(r.eta_min || '');
        $form.find('[name=eta_max]').val(r.eta_max || '');
        $form.find('[name=eta_unit]').val(r.eta_unit || 'days');

        // Countries — preload TomSelect with the existing selection.
        (z.coverage.countries || []).forEach(function (c) {
            countryTS.addOption({ value: c.iso2, text: c.name });
        });
        countryTS.setValue((z.coverage.countries || []).map(function (c) { return c.iso2; }), true);

        setServiceabilityMode(z.serviceability_mode || 'none');
        $('#serviceability-rows').empty();
        (z.serviceability || []).forEach(function (rule) {
            addServiceabilityRule(rule);
        });
    }

    /* ---------- Submit ---------- */

    function wireSubmit() {
        // Prevent accidental full-page submit if user hits Enter inside the
        // modal — the form has no action attribute, browsers would reload.
        $form.on('submit', function (e) { e.preventDefault(); $submit.trigger('click'); });

        $submit.on('click', function () {
            var payload = buildPayload();
            var mode = $form.attr('data-mode');
            var zoneId = $form.attr('data-zone-id');
            var url = mode === 'edit'
                ? endpoints.show + '/' + zoneId
                : endpoints.base;

            $submit.prop('disabled', true);

            axios.post(url, payload, {
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            }).then(function (resp) {
                $submit.prop('disabled', false);
                var data = resp.data || {};
                if (!data.success) {
                    Toast.fire({ icon: 'error', title: data.message || 'Save failed' });
                    return;
                }
                Toast.fire({ icon: 'success', title: data.message });
                hideModal();
                setTimeout(function () { window.location.reload(); }, 600);
            }).catch(function (err) {
                $submit.prop('disabled', false);
                var data = (err.response && err.response.data) || {};
                var firstError = data.data
                    ? Object.values(data.data).flat()[0]
                    : null;
                Toast.fire({ icon: 'error', title: firstError || data.message || 'Save failed' });
            });
        });
    }

    function buildPayload() {
        var method = $form.find('.js-zone-method').val();
        var svcMode = method === 'shiprocket' ? 'none' : ($('#serviceability-mode').val() || 'none');
        return {
            name: $form.find('[name=name]').val(),
            method: method,
            rate_type: $form.find('.js-zone-rate-type').val(),
            flat_rate: numericOrNull($form.find('[name=flat_rate]').val()),
            free_above: pickFreeAbove(),
            eta_min: numericOrNull($form.find('[name=eta_min]').val()),
            eta_max: numericOrNull($form.find('[name=eta_max]').val()),
            eta_unit: $form.find('[name=eta_unit]').val(),
            priority: numericOrNull($form.find('[name=priority]').val()) || 0,
            is_active: $form.find('[name=is_active]:checkbox').is(':checked'),
            weight_rules: collectTiers('weight'),
            price_rules: collectTiers('price'),
            country_iso2s: countryTS ? countryTS.items.slice() : [],
            serviceability_mode: svcMode,
            serviceability: svcMode === 'none' ? [] : collectServiceability(),
        };
    }

    function pickFreeAbove() {
        var rt = $form.find('.js-zone-rate-type').val();
        if (rt === 'weight_based')   return numericOrNull($form.find('[name=free_above_weight]').val());
        if (rt === 'price_based')    return numericOrNull($form.find('[name=free_above_price]').val());
        if (rt === 'carrier_quoted') return null;
        return numericOrNull($form.find('[name=free_above]').val());
    }

    function collectTiers(kind) {
        var rows = $form.find(kind === 'weight' ? '#weight-tier-rows tr.tier-row' : '#price-tier-rows tr.tier-row');
        var out = [];
        rows.each(function () {
            var $r = $(this);
            if (kind === 'weight') {
                out.push({
                    min_kg: numericOrNull($r.find('[name="weight_rules[][min_kg]"]').val()),
                    max_kg: numericOrNull($r.find('[name="weight_rules[][max_kg]"]').val()),
                    rate:   numericOrNull($r.find('[name="weight_rules[][rate]"]').val()),
                });
            } else {
                out.push({
                    min_amount: numericOrNull($r.find('[name="price_rules[][min_amount]"]').val()),
                    max_amount: numericOrNull($r.find('[name="price_rules[][max_amount]"]').val()),
                    rate:       numericOrNull($r.find('[name="price_rules[][rate]"]').val()),
                });
            }
        });
        return out;
    }

    function numericOrNull(v) {
        if (v === '' || v === null || typeof v === 'undefined') return null;
        return isNaN(parseFloat(v)) ? null : parseFloat(v);
    }

    /* ---------- Delete ---------- */

    function wireDelete() {
        var $deleteModal = $('#deleteShippingZoneModal');
        if (!$deleteModal.length) return;
        var pendingId = null;

        $(document).on('click', '.js-delete-zone', function () {
            pendingId = $(this).data('zone-id');
            var name = $(this).data('zone-name') || '';
            $('#deleteShippingZoneMessage').text('Delete "' + name + '"? This action cannot be undone.');
            $deleteModal.modal('show');
        });

        $('#deleteShippingZoneConfirm').on('click', function () {
            if (!pendingId) return;
            var $btn = $(this).prop('disabled', true);
            axios.delete(endpoints.show + '/' + pendingId, {
                headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            }).then(function (resp) {
                $btn.prop('disabled', false);
                var data = resp.data || {};
                Toast.fire({ icon: data.success ? 'success' : 'error', title: data.message });
                if (data.success) {
                    $deleteModal.modal('hide');
                    setTimeout(function () { window.location.reload(); }, 600);
                }
            }).catch(function (err) {
                $btn.prop('disabled', false);
                Toast.fire({ icon: 'error', title: (err.response && err.response.data && err.response.data.message) || 'Delete failed' });
            });
        });
    }

    /* ---------- Bootstrap ---------- */

    $(function () {
        bindModal();
        wireDelete();

        $(document).on('click', '.js-open-zone-modal', function () {
            var mode = $(this).data('mode');
            var zoneId = $(this).data('zone-id');
            if (mode === 'edit' && zoneId) openEdit(zoneId);
            else openCreate();
        });
    });
})(jQuery);
