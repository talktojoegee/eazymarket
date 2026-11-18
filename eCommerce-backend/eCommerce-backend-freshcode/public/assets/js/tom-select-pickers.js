'use strict';

/**
 * Self-bootstrapping TomSelect pickers used by reusable Blade components:
 *   - <x-tom-select-country> renders <select class="js-tom-select-country" data-...>
 *   - <x-tom-select-currency> renders <select class="js-tom-select-currency" data-...>
 *
 * This file is loaded once from the admin layout (and seller/customer layouts that
 * use the components). It scans the DOM on DOMContentLoaded and initialises every
 * matching element, so any number of instances on a page work without ID collisions.
 *
 * Data attributes consumed:
 *   data-endpoint     — search URL (defaults differ per picker)
 *   data-value-field  — country picker only; 'iso2' | 'iso3' | 'name'
 *   data-only-active  — currency picker only; '1' | '0'
 *   data-placeholder  — visible placeholder text
 *   data-selected     — JSON array of pre-selected values
 */

(function () {
    function makeCountryRenderer(textField) {
        return function (data, escape) {
            const label = data[textField] != null && data[textField] !== '' ? data[textField] : (data.text || '');
            if (data.customProperties) {
                return '<div><span class="dropdown-item-indicator">' + data.customProperties + '</span>' + escape(label) + '</div>';
            }
            return '<div>' + escape(label) + '</div>';
        };
    }

    function renderCurrencyRow(data, escape) {
        return '<div><strong>' + escape(data.code) + '</strong> '
            + '<span class="text-muted ms-1">' + escape(data.symbol || '') + '</span> '
            + '<span class="text-muted ms-2">' + escape(data.name || '') + '</span></div>';
    }

    function appendQuery(url, params) {
        const sep = url.indexOf('?') === -1 ? '?' : '&';
        return url + sep + params;
    }

    function initCountryPickers() {
        if (typeof window.TomSelect === 'undefined') return;

        document.querySelectorAll('.js-tom-select-country').forEach(function (el) {
            if (el.tomselect) return;

            const endpoint   = el.dataset.endpoint || '/countries';
            const valueField = el.dataset.valueField || 'iso2';
            const textField  = el.dataset.textField || 'text';
            const noFilter   = el.dataset.noFilter === '1';
            let selected = [];
            try { selected = JSON.parse(el.dataset.selected || '[]'); } catch (e) { selected = []; }

            const ts = new TomSelect(el, {
                copyClassesToDropdown: false,
                dropdownParent: 'body',
                controlInput: '<input>',
                valueField: 'value',
                labelField: textField,
                searchField: textField,
                placeholder: el.dataset.placeholder || 'Search…',
                score: noFilter ? function () { return function () { return 1; }; } : undefined,
                render: {
                    item: makeCountryRenderer(textField),
                    option: makeCountryRenderer(textField),
                },
                load: function (query, callback) {
                    if (!query.length) return callback();
                    const url = appendQuery(endpoint,
                        'search=' + encodeURIComponent(query) +
                        '&value_field=' + encodeURIComponent(valueField));
                    fetch(url)
                        .then(r => r.json())
                        .then(json => callback(json))
                        .catch(() => callback());
                },
            });

            if (selected.length) {
                const url = appendQuery(endpoint,
                    'value_field=' + encodeURIComponent(valueField) +
                    selected.map(v => '&find_in[]=' + encodeURIComponent(v)).join(''));
                fetch(url)
                    .then(r => r.json())
                    .then(function (rows) {
                        (rows || []).forEach(opt => ts.addOption(opt));
                        ts.setValue(selected, /* silent */ true);
                    })
                    .catch(() => {});
            }
        });
    }

    function initCurrencyPickers() {
        if (typeof window.TomSelect === 'undefined') return;

        document.querySelectorAll('.js-tom-select-currency').forEach(function (el) {
            if (el.tomselect) return;

            const endpoint   = el.dataset.endpoint;
            const onlyActive = el.dataset.onlyActive === '1';
            let selected = [];
            try { selected = JSON.parse(el.dataset.selected || '[]'); } catch (e) { selected = []; }

            if (!endpoint) return; // mis-configured component; skip silently

            const ts = new TomSelect(el, {
                copyClassesToDropdown: false,
                dropdownParent: 'body',
                controlInput: '<input>',
                valueField: 'value',
                labelField: 'text',
                searchField: 'text',
                placeholder: el.dataset.placeholder || 'USD, EUR, INR…',
                render: {
                    item: renderCurrencyRow,
                    option: renderCurrencyRow,
                },
                load: function (query, callback) {
                    if (!query.length) return callback();
                    const url = appendQuery(endpoint,
                        'search=' + encodeURIComponent(query) +
                        '&only_active=' + (onlyActive ? '1' : '0'));
                    fetch(url)
                        .then(r => r.json())
                        .then(json => callback(json))
                        .catch(() => callback());
                },
            });

            if (selected.length) {
                const url = appendQuery(endpoint,
                    'only_active=' + (onlyActive ? '1' : '0') +
                    selected.map(v => '&find_in[]=' + encodeURIComponent(v)).join(''));
                fetch(url)
                    .then(r => r.json())
                    .then(function (rows) {
                        (rows || []).forEach(opt => ts.addOption(opt));
                        ts.setValue(selected, /* silent */ true);
                    })
                    .catch(() => {});
            }
        });
    }

    function renderMarketRow(data, escape) {
        // Static options (e.g. the "All markets" filter entry) carry only `text`,
        // not the AJAX market shape — fall back to the plain label so they don't
        // render blank.
        if (!data.code && !data.name) {
            return '<div>' + escape(data.text || '') + '</div>';
        }
        const code = data.code ? '<strong>' + escape(data.code) + '</strong>' : '';
        const name = data.name ? '<span class="text-muted ms-1">' + escape(data.name) + '</span>' : '';
        return '<div>' + code + ' ' + name + '</div>';
    }

    function initMarketPickers() {
        if (typeof window.TomSelect === 'undefined') return;

        document.querySelectorAll('.js-tom-select-market').forEach(function (el) {
            if (el.tomselect) return;

            const endpoint   = el.dataset.endpoint;
            const onlyActive = el.dataset.onlyActive === '1';
            const preloadLimit = parseInt(el.dataset.preloadLimit || '10', 10);
            let selected = [];
            try { selected = JSON.parse(el.dataset.selected || '[]'); } catch (e) { selected = []; }

            if (!endpoint) return; // mis-configured component; skip silently

            const includeAll = el.dataset.includeAll === '1';

            const ts = new TomSelect(el, {
                copyClassesToDropdown: false,
                dropdownParent: 'body',
                controlInput: '<input>',
                valueField: 'value',
                labelField: 'text',
                searchField: 'text',
                allowEmptyOption: includeAll,
                placeholder: el.dataset.placeholder || 'Pick a market…',
                preload: 'focus',
                // Keep the "All markets" empty option pinned to the TOP of the
                // dropdown. Without this, relevance scoring (and the loaded
                // markets) push it into the middle of the list. Falls back to
                // the default scorer for every real market.
                score: includeAll ? function (search) {
                    const base = this.sifter.getScoreFunction(search, this.getSearchOptions());
                    return function (item) {
                        return item.value === '' ? Infinity : base(item);
                    };
                } : undefined,
                render: {
                    item: renderMarketRow,
                    option: renderMarketRow,
                },
                load: function (query, callback) {
                    // Empty query → return the top N markets sorted by priority.
                    // This is the path that fires on first focus thanks to preload.
                    const params = 'search=' + encodeURIComponent(query || '') +
                        '&only_active=' + (onlyActive ? '1' : '0') +
                        '&limit=' + encodeURIComponent(preloadLimit);
                    fetch(appendQuery(endpoint, params))
                        .then(r => r.json())
                        .then(json => callback(extractRows(json)))
                        .catch(() => callback());
                },
            });

            // Pre-hydrate selected ids so labels render on edit pages without the
            // user opening the dropdown.
            if (selected.length) {
                const url = appendQuery(endpoint,
                    'only_active=' + (onlyActive ? '1' : '0') +
                    selected.map(v => '&find_in[]=' + encodeURIComponent(v)).join(''));
                fetch(url)
                    .then(r => r.json())
                    .then(function (resp) {
                        (extractRows(resp) || []).forEach(opt => ts.addOption(opt));
                        ts.setValue(selected, /* silent */ true);
                    })
                    .catch(() => {});
            }
        });
    }

    /**
     * The country/currency endpoints return a flat array, but Laravel API responses
     * via ApiResponseType wrap rows under `data`. Handle both transparently.
     */
    function extractRows(payload) {
        if (Array.isArray(payload)) return payload;
        if (payload && Array.isArray(payload.data)) return payload.data;
        if (payload && payload.data && Array.isArray(payload.data.rates)) return payload.data.rates;
        if (payload && payload.data && Array.isArray(payload.data.rows)) return payload.data.rows;
        return [];
    }

    /**
     * Generic enhancer for static-options selects — anything with a fixed
     * <option> list (statuses, enums, account types, etc.) Wrap the raw
     * <select> with `<select class="js-tom-select-static">` and this picks
     * it up. No AJAX, no preload — TomSelect just gives search + remove pills.
     */
    function initStaticPickers() {
        if (typeof window.TomSelect === 'undefined') return;

        document.querySelectorAll('.js-tom-select-static').forEach(function (el) {
            if (el.tomselect) return;

            new TomSelect(el, {
                copyClassesToDropdown: false,
                dropdownParent: 'body',
                controlInput: '<input>',
                placeholder: el.dataset.placeholder || el.getAttribute('placeholder') || 'Select…',
                allowEmptyOption: true,
            });
        });
    }

    /**
     * Remote-loading multi-select for products. Mirrors the country picker
     * but scoped per-store via `data-store-id` and pre-hydrated from
     * `data-selected` (JSON-encoded array of IDs) so chips render on edit.
     *
     * Wire up: `<select class="js-tom-select-product" data-endpoint="…"
     *           data-store-id="42" data-selected='[1,2,3]' multiple>`
     */
    function initProductPickers() {
        if (typeof window.TomSelect === 'undefined') return;

        document.querySelectorAll('.js-tom-select-product').forEach(function (el) {
            if (el.tomselect) return;

            const endpoint = el.dataset.endpoint;
            const storeId  = el.dataset.storeId || '';
            const preload = el.dataset.preload === 'focus';
            const preloadLimit = parseInt(el.dataset.preloadLimit || '15', 10);
            const maxItems = parseInt(el.dataset.maxItems || '0', 10) || null;
            const sourceElement = el.dataset.sourceSelector ? document.querySelector(el.dataset.sourceSelector) : null;
            const profileElement = el.dataset.profileSelector ? document.querySelector(el.dataset.profileSelector) : null;
            const primaryElement = el.dataset.primarySelect ? document.querySelector(el.dataset.primarySelect) : null;
            let selected = [];
            try { selected = JSON.parse(el.dataset.selected || '[]'); } catch (e) { selected = []; }

            if (!endpoint) return; // mis-configured; skip silently

            const requestParams = function (query) {
                const params = [
                    'store_id=' + encodeURIComponent(storeId),
                    'search=' + encodeURIComponent(query || ''),
                    'limit=' + encodeURIComponent(preloadLimit),
                ];
                if (sourceElement) params.push('source=' + encodeURIComponent(sourceElement.value));
                if (profileElement) params.push('profile_id=' + encodeURIComponent(profileElement.value));
                return params.join('&');
            };

            const ts = new TomSelect(el, {
                copyClassesToDropdown: false,
                dropdownParent: 'body',
                controlInput: '<input>',
                valueField: 'value',
                labelField: 'text',
                searchField: 'text',
                placeholder: el.dataset.placeholder || 'Search products…',
                preload: preload ? 'focus' : false,
                maxItems: maxItems,
                render: {
                    item: renderRemoteRow,
                    option: renderRemoteRow,
                },
                load: function (query, callback) {
                    if (!query.length && !preload) return callback();
                    const url = appendQuery(endpoint, requestParams(query));
                    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(r => r.json())
                        .then(json => callback(extractRows(json)))
                        .catch(() => callback());
                },
            });

            const syncPrimary = function () {
                if (!primaryElement) return;
                const values = Array.isArray(ts.getValue()) ? ts.getValue() : [ts.getValue()].filter(Boolean);
                const selectedPrimary = primaryElement.value || primaryElement.dataset.selected || '';
                primaryElement.replaceChildren(new Option(primaryElement.dataset.placeholder || '', ''));
                values.forEach(function (value) {
                    const option = ts.options[value];
                    primaryElement.add(new Option(option ? option.text : value, value));
                });
                if (values.includes(String(selectedPrimary))) {
                    primaryElement.value = String(selectedPrimary);
                } else if (values.length === 1) {
                    primaryElement.value = String(values[0]);
                }
                primaryElement.dataset.selected = primaryElement.value;
            };

            ts.on('item_add', syncPrimary);
            ts.on('item_remove', syncPrimary);

            [sourceElement, profileElement].filter(Boolean).forEach(function (contextElement) {
                contextElement.addEventListener('change', function () {
                    ts.clear(true);
                    ts.clearOptions();
                    ts.clearCache();
                    syncPrimary();
                    if (preload) ts.load('');
                });
            });

            // Pre-load chips for the existing selection so edit mode shows
            // titles, not bare IDs.
            if (selected.length) {
                const url = appendQuery(endpoint,
                    requestParams('') +
                    selected.map(v => '&find_in[]=' + encodeURIComponent(v)).join(''));
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json())
                    .then(function (rows) {
                        extractRows(rows).forEach(opt => ts.addOption(opt));
                        ts.setValue(selected.map(String), /* silent */ true);
                        syncPrimary();
                    })
                    .catch(() => {});
            } else {
                syncPrimary();
            }
        });
    }

    function renderRemoteRow(data, escape) {
        const image = data.image_url
            ? '<img src="' + escape(data.image_url) + '" class="avatar avatar-sm rounded object-contain me-2" alt="">'
            : '';
        const subtitle = data.subtitle
            ? '<div class="small text-secondary">' + escape(data.subtitle) + '</div>'
            : '';
        const stockStatus = data.stock_status_label
            ? '<span class="badge bg-danger-lt text-danger ms-2">' + escape(data.stock_status_label) + '</span>'
            : '';
        return '<div class="d-flex align-items-center">' + image + '<div><div class="d-flex align-items-center">' + escape(data.text || '') + stockStatus + '</div>' + subtitle + '</div></div>';
    }

    function initRemotePickers() {
        if (typeof window.TomSelect === 'undefined') return;

        document.querySelectorAll('.js-tom-select-remote').forEach(function (el) {
            if (el.tomselect) return;

            const endpoint = el.dataset.endpoint;
            const preload = el.dataset.preload === 'focus';
            const preloadLimit = parseInt(el.dataset.preloadLimit || '15', 10);
            let selected = [];
            try { selected = JSON.parse(el.dataset.selected || '[]'); } catch (e) { selected = []; }
            if (!endpoint) return;

            const ts = new TomSelect(el, {
                copyClassesToDropdown: false,
                dropdownParent: 'body',
                controlInput: '<input>',
                valueField: 'value',
                labelField: 'text',
                searchField: 'text',
                placeholder: el.dataset.placeholder || 'Search…',
                preload: preload ? 'focus' : false,
                render: {
                    item: renderRemoteRow,
                    option: renderRemoteRow,
                },
                load: function (query, callback) {
                    if (!query.length && !preload) return callback();
                    const params = 'search=' + encodeURIComponent(query || '') + '&limit=' + encodeURIComponent(preloadLimit);
                    fetch(appendQuery(endpoint, params), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                        .then(r => r.json())
                        .then(json => callback(extractRows(json)))
                        .catch(() => callback());
                },
            });

            if (selected.length) {
                const params = selected.map(v => 'find_in[]=' + encodeURIComponent(v)).join('&');
                fetch(appendQuery(endpoint, params), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(r => r.json())
                    .then(function (json) {
                        extractRows(json).forEach(opt => ts.addOption(opt));
                        ts.setValue(selected.map(String), true);
                    })
                    .catch(() => {});
            }
        });
    }

    function bootstrap() {
        // TomSelect is loaded with `defer`; if it isn't on `window` yet, queue a microtask.
        if (typeof window.TomSelect === 'undefined') {
            return window.setTimeout(bootstrap, 50);
        }
        initCountryPickers();
        initCurrencyPickers();
        initMarketPickers();
        initStaticPickers();
        initRemotePickers();
        initProductPickers();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
    } else {
        bootstrap();
    }
})();
