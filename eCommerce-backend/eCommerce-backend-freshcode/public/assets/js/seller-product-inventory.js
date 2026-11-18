/* global jQuery, axios, Toast, productData */
/*
 * Seller Product form — Pricing & Inventory (step 7).
 *
 * Owns the new variant-pricing + per-(variant, location) inventory UI in
 * #variantInventoryAccordion. Renders one card per variant (or a single card
 * for simple products), each card carrying:
 *   - price / special_price / cost / sku inputs (variant-level)
 *   - inventory grid grouped Store → Locations:
 *       [ Sell from here ]  [ Track inventory ]  [ Qty ]
 *
 * On form submit, takes over from product.js: builds variants_json (or the
 * simple-product payload) with the new shape and POSTs via axios. The same
 * payload shape is consumed by the seller mobile API
 * (SellerProductApiController → StoreUpdateProductRequest → ProductService),
 * so backend and panel share validation + persistence without duplication.
 *
 * Old store-pricing renderers in product.js auto-no-op (their guards check
 * for #storePricingSection which is no longer in the DOM).
 */
(function ($) {
    'use strict';

    const sectionEl = () => document.getElementById('variantInventorySection');
    const accordionEl = () => document.getElementById('variantInventoryAccordion');
    const emptyEl = () => document.getElementById('variantInventoryEmpty');
    const formEl = () => document.getElementById('product-form-submit');

    /** In-memory state — single source of truth driven by user edits. */
    const state = {
        stores: [],
        rows: new Map(),
        productType: 'simple',
        isInitialized: false,
        activeKey: null,
        currentKeys: [],
    };

    const SIMPLE_KEY = '__simple__';

    /** ──────────────────────────────────────────────────────────────────
     * Data loading
     * ─────────────────────────────────────────────────────────────────── */

    function getBaseUrl() {
        return document.getElementById('base_url')?.value || '';
    }
    function getPanel() {
        return document.getElementById('panel')?.getAttribute('data-panel') || 'seller';
    }

    function loadStoresWithLocations() {
        const section = sectionEl();
        if (!section) return Promise.resolve([]);
        const path = section.dataset.storesEndpointPath || 'stores/with-locations';
        const base = getBaseUrl();
        const p = getPanel();
        let url = `${base}/${p}/${path}`;

        if (p === 'admin') {
            const sellerIdEl = document.getElementById('adminSellerPicker') || document.querySelector('[name="seller_id"]');
            // TomSelect doesn't always sync the underlying <select>.value
            // synchronously; prefer its API when the picker is wrapped.
            const sellerId = (sellerIdEl && sellerIdEl.tomselect && sellerIdEl.tomselect.getValue())
                || sellerIdEl?.value
                || new URL(window.location.href).searchParams.get('seller_id')
                || '';
            if (sellerId) url += `?seller_id=${encodeURIComponent(sellerId)}`;
        }

        return axios.get(url)
            .then(r => Array.isArray(r.data?.data) ? r.data.data : (r.data?.data ? Object.values(r.data.data) : []))
            .catch(() => []);
    }

    function loadExistingPricing(productId) {
        if (!productId) return Promise.resolve(null);
        return axios.get(`${getBaseUrl()}/${getPanel()}/products/${productId}/pricing`)
            .then(r => r.data?.data || r.data || null)
            .catch(() => null);
    }

    /** ──────────────────────────────────────────────────────────────────
     * Variant discovery — scrape from product.js's variants tab.
     * Returns: [{ key, id, title, isDefault }]
     * ─────────────────────────────────────────────────────────────────── */

    function collectVariantsFromDom() {
        if (window.productVariantBuilder) {
            return window.productVariantBuilder.getVariants().map((variant, index) => ({
                key: String(variant.key || variant.id || index + 1),
                id: String(variant.id || index + 1),
                dbId: variant.db_id ? String(variant.db_id) : null,
                title: variant.title || `Variant ${index + 1}`,
                inherited: variant,
            }));
        }
        const list = document.getElementById('variantsList');
        if (!list) return [];

        const items = list.querySelectorAll('[data-id]');
        const out = [];
        items.forEach((el, i) => {
            const id = el.dataset.id || String(i + 1);
            const dbId = el.dataset.dbId || null;
            const titleInput = el.querySelector('.card-body input[type="text"]');
            const badgeLabel = Array.from(el.querySelectorAll('.card-title .badge'))
                .map(b => b.textContent.trim())
                .join(' · ');
            const title = (titleInput?.value || badgeLabel || `Variant ${i + 1}`).trim();
            out.push({ key: String(id), id: String(id), dbId, title });
        });

        if (out.length === 0 && window.productData?.variants?.length) {
            window.productData.variants.forEach((v, i) => out.push({
                key: String(v.id ?? i + 1),
                id: String(v.id ?? i + 1),
                dbId: String(v.id ?? ''),
                title: v.title || `Variant ${i + 1}`,
            }));
        }
        return out;
    }

    function productType() {
        const sel = document.getElementById('productType');
        return (sel?.value || 'simple').toLowerCase();
    }

    function eligibleKitStoreIds() {
        const selectedOptions = Array.from(document.querySelectorAll('#kit-components .kit-component-select'))
            .filter(select => select.value)
            .map(select => select.tomselect?.options?.[select.value]?.store_ids || []);
        if (selectedOptions.length === 0) return [];
        return selectedOptions[0].map(Number).filter(storeId =>
            selectedOptions.every(storeIds => storeIds.map(Number).includes(storeId))
        );
    }

    /** ──────────────────────────────────────────────────────────────────
     * Render
     * ─────────────────────────────────────────────────────────────────── */

    function ensureRowForKey(key, meta) {
        if (state.rows.has(key)) return state.rows.get(key);
        const row = {
            id: meta?.id ?? key,
            title: meta?.title ?? '',
            image: meta?.inherited?.image ?? '',
            price: meta?.inherited?.price ?? '',
            special_price: meta?.inherited?.special_price ?? '',
            cost: meta?.inherited?.cost ?? '',
            sku: meta?.inherited?.sku ?? '',
            inventory: new Map(),
        };
        state.rows.set(key, row);
        return row;
    }

    function pruneRowsToKeys(keys) {
        const keep = new Set(keys);
        for (const k of state.rows.keys()) {
            if (!keep.has(k)) state.rows.delete(k);
        }
    }

    function render() {
        const section = sectionEl();
        const accordion = accordionEl();
        const empty = emptyEl();
        if (!section || !accordion) return;

        const labels = section.dataset;
        const type = state.productType = productType();

        let keys = [];
        if (type === 'variant') {
            const variants = collectVariantsFromDom();
            variants.forEach(v => {
                const row = ensureRowForKey(v.key, v);
                row.id = v.id;
                row.title = v.title;
                if (v.inherited && Object.prototype.hasOwnProperty.call(v.inherited, 'image')) {
                    row.image = v.inherited.image || '';
                }
                hydrateRowFromServerVariant(row, v.dbId);
            });
            keys = variants.map(v => v.key);
            pruneRowsToKeys(keys);
        } else {
            const row = ensureRowForKey(SIMPLE_KEY, { id: SIMPLE_KEY, title: '' });
            hydrateRowFromSimpleProduct(row);
            keys = [SIMPLE_KEY];
            pruneRowsToKeys(keys);
        }
        state.currentKeys = keys;
        if (!keys.includes(state.activeKey)) state.activeKey = keys[0] || null;
        renderTaxPreview();

        if (state.stores.length === 0) {
            accordion.innerHTML = '';
            empty.textContent = labels.lblNoLocations || 'Add at least one location under a store before listing this product.';
            empty.classList.remove('d-none');
            return;
        }
        if (keys.length === 0) {
            accordion.innerHTML = '';
            empty.textContent = labels.lblLoading || 'Loading…';
            empty.classList.remove('d-none');
            return;
        }
        empty.classList.add('d-none');

        accordion.innerHTML = renderInventoryWorkspace(keys, type === 'variant');

        keys.forEach(key => syncStateToDom(key));
    }

    // Money field with the base-currency symbol prefix. Sellers always enter
    // prices in the platform's base currency — conversion to the customer's
    // market currency happens at read time (see App\Support\PricePresenter).
    function moneyInput(field, value, labels) {
        const symbol = (labels.baseCurrencySymbol || '').trim();
        if (!symbol) {
            return `<input type="number" step="any" min="0" class="form-control vinv-field" data-field="${field}" value="${escapeAttr(value)}">`;
        }
        return `
            <div class="input-group">
                <span class="input-group-text">${escapeHtml(symbol)}</span>
                <input type="number" step="any" min="0" class="form-control vinv-field" data-field="${field}" value="${escapeAttr(value)}">
            </div>`;
    }

    function formatSummaryPrice(value, labels) {
        if (value === '' || value === null || value === undefined) return labels.lblPriceMissing || 'Price missing';
        const number = Number(value);
        const formatted = Number.isFinite(number) ? new Intl.NumberFormat().format(number) : value;
        return `${labels.baseCurrencySymbol || ''}${formatted}`;
    }

    function renderSummaryPrices(row, labels) {
        const special = Number(row.special_price);
        const hasSpecial = row.special_price !== '' && Number.isFinite(special) && special > 0;
        if (!hasSpecial) return `<span data-summary="price">${escapeHtml(formatSummaryPrice(row.price, labels))}</span>`;
        return `<span class="vinv-sale-price" data-summary="special-price">${escapeHtml(formatSummaryPrice(row.special_price, labels))}</span><del data-summary="price">${escapeHtml(formatSummaryPrice(row.price, labels))}</del>`;
    }

    function renderInventoryWorkspace(keys, isVariantProduct) {
        const activeKey = state.activeKey || keys[0];
        const activeIndex = keys.indexOf(activeKey);
        const labels = sectionEl().dataset;
        const picker = isVariantProduct
            ? `<div class="vinv-variant-picker" role="tablist" aria-label="${escapeAttr(labels.lblProductVariants || '')}">${keys.map((key, index) => renderVariantOption(key, index, key === activeKey)).join('')}</div>`
            : '';

        return `${picker}${renderVariantEditor(activeKey, Math.max(activeIndex, 0))}`;
    }

    function renderVariantOption(key, idx, active) {
        const row = state.rows.get(key);
        const labels = sectionEl().dataset;
        const image = row.image
            ? `<img class="vinv-variant-image" src="${escapeAttr(row.image)}" alt="">`
            : '<span class="vinv-variant-image vinv-variant-image-empty"><i class="ti ti-photo"></i></span>';

        return `
        <button type="button" class="vinv-variant-option ${active ? 'is-active' : ''}"
                data-select-variant="${escapeAttr(key)}" role="tab" aria-selected="${active ? 'true' : 'false'}">
            ${image}
            <span class="vinv-variant-copy">
                <strong class="vinv-variant-title">${escapeHtml(row.title || `${labels.lblVariant || 'Variant'} ${idx + 1}`)}</strong>
                <span class="vinv-variant-summary">
                    <span class="vinv-price-summary">${renderSummaryPrices(row, labels)}</span>
                    <span aria-hidden="true">·</span>
                    <span data-summary="sku">${escapeHtml(row.sku ? `SKU ${row.sku}` : (labels.lblSkuMissing || 'SKU missing'))}</span>
                </span>
            </span>
        </button>`;
    }

    function renderVariantEditor(key, idx) {
        const row = state.rows.get(key);
        const labels = sectionEl().dataset;
        const kitStoreId = document.getElementById('kit-store')?.value || '';
        const kitEligibleStoreIds = eligibleKitStoreIds();
        const visibleStores = state.productType === 'kit'
            ? state.stores.filter(store => kitEligibleStoreIds.includes(Number(store.id)))
            : state.stores;
        const inventoryRows = visibleStores.map(store => renderStoreRows(key, store)).join('');
        const availabilityMarkup = state.productType === 'kit'
            ? `<section class="vinv-panel vinv-availability-panel">
                <div class="vinv-availability-header"><div><h4>${escapeHtml(labels.lblAvailabilityTitle || 'Availability by location')} <span class="text-danger">*</span></h4><p>${escapeHtml(labels.lblKitInventoryHint || '')}</p></div></div>
                ${kitStoreId && kitEligibleStoreIds.length ? `<div class="table-responsive vinv-table-wrap"><table class="table table-vcenter mb-0 vinv-grid"><thead><tr><th>${escapeHtml(labels.lblLocation || 'Location')}</th><th class="text-center">${escapeHtml(labels.lblAvailableSale || 'Available for sale')}</th></tr></thead><tbody>${inventoryRows}</tbody></table></div>` : `<div class="alert alert-info mb-0">${escapeHtml(kitStoreId ? (labels.lblKitNoEligibleStores || 'No stores carry every selected kit product.') : labels.lblKitSelectStoreHint || '')}</div>`}
            </section>`
            : `<section class="vinv-panel vinv-availability-panel">
                <div class="vinv-availability-header">
                    <div>
                        <h4>${escapeHtml(labels.lblAvailabilityTitle || 'Availability by location')} <span class="text-danger">*</span></h4>
                        <p>${escapeHtml(labels.lblAvailabilityHint || '')}</p>
                    </div>
                    <div class="vinv-bulk-actions">
                        <button type="button" class="btn btn-outline-primary vinv-bulk" data-action="sell-all">${escapeHtml(labels.lblEnableAll || 'Enable all')}</button>
                        <button type="button" class="btn vinv-bulk" data-action="stop-all">${escapeHtml(labels.lblDisableAll || 'Disable all')}</button>
                        <span class="vinv-set-quantity">${escapeHtml(labels.lblSetQuantity || 'Set quantity')}</span>
                        <input type="number" min="0" class="form-control vinv-bulk-qty" placeholder="${labels.lblQty || 'Qty'}">
                        <button type="button" class="btn btn-primary-lt vinv-bulk" data-action="apply-qty">${escapeHtml(labels.lblApplyAll || 'Apply to all')}</button>
                    </div>
                </div>
                <div class="table-responsive vinv-table-wrap">
                    <table class="table table-vcenter mb-0 vinv-grid">
                        <thead><tr>
                            <th>${escapeHtml(labels.lblLocation || 'Location')}</th>
                            <th class="text-center">${escapeHtml(labels.lblAvailableSale || 'Available for sale')}</th>
                            <th class="text-center">${escapeHtml(labels.lblTrackStock || 'Track stock')}</th>
                            <th>${escapeHtml(labels.lblQty || '')}</th>
                        </tr></thead>
                        <tbody>${inventoryRows}</tbody>
                    </table>
                </div>
            </section>`;

        return `
        <div class="vinv-variant-editor" data-vinv-key="${escapeAttr(key)}" data-variant-index="${idx}">
            <section class="vinv-panel vinv-pricing-panel">
                <h4>${escapeHtml(labels.lblPricingTitle || 'Pricing')}</h4>
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label required">${labels.lblPrice || 'Price'}</label>
                        ${moneyInput('price', row.price, labels)}
                        <small class="form-hint text-muted">${labels.lblBaseCurrencyHint || ''}</small>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label">${labels.lblSpecialPrice || 'Special price'}</label>
                        ${moneyInput('special_price', row.special_price, labels)}
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label">${labels.lblCost || 'Cost'}</label>
                        ${moneyInput('cost', row.cost, labels)}
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label required">${labels.lblSku || 'SKU'}</label>
                        <input type="text" class="form-control vinv-field" data-field="sku" value="${escapeAttr(row.sku)}">
                    </div>
                </div>
            </section>
            ${availabilityMarkup}
        </div>`;
    }

    function renderStoreRows(variantKey, store) {
        const labels = sectionEl().dataset;
        const row = state.rows.get(variantKey);

        if (!store.locations || store.locations.length === 0) {
            return `
            <tr class="vinv-store-row"><th colspan="${state.productType === 'kit' ? 2 : 4}"><i class="ti ti-building-store"></i>${escapeHtml(store.name)}</th></tr>
            <tr><td colspan="${state.productType === 'kit' ? 2 : 4}" class="text-muted">${labels.lblNoLocations || 'No locations'}</td></tr>`;
        }

        if (state.productType === 'kit') {
            const kitRows = store.locations.map(loc => {
                const inv = row.inventory.get(loc.id) || { sell: false, tracked: false, qty: 0 };
                return `<tr data-loc-id="${loc.id}" data-location-name="${escapeAttr(loc.name)}">
                    <td><span class="vinv-location-name"><i class="ti ti-map-pin"></i>${escapeHtml(loc.name)} ${loc.is_default ? '<span class="badge bg-blue-lt">Default</span>' : ''}</span></td>
                    <td class="text-center"><div class="form-check form-switch d-inline-flex mb-0 vinv-switch"><input type="checkbox" class="form-check-input vinv-cell" data-field="sell" ${inv.sell ? 'checked' : ''} aria-label="${escapeAttr(labels.lblSellFrom || 'Sell from this location')}"></div></td>
                </tr>`;
            }).join('');
            return `<tr class="vinv-store-row"><th colspan="2"><i class="ti ti-building-store"></i>${escapeHtml(store.name)}</th></tr>${kitRows}`;
        }

        const rowsHtml = store.locations.map(loc => {
            const inv = row.inventory.get(loc.id) || { sell: false, tracked: true, qty: 0 };
            return `
            <tr data-loc-id="${loc.id}" data-location-name="${escapeAttr(loc.name)}">
                <td>
                    <span class="vinv-location-name">
                        <i class="ti ti-map-pin"></i>${escapeHtml(loc.name)}
                        ${loc.is_default ? '<span class="badge bg-blue-lt">Default</span>' : ''}
                    </span>
                </td>
                <td class="text-center">
                    <div class="form-check form-switch d-inline-flex mb-0 vinv-switch">
                        <input type="checkbox" class="form-check-input vinv-cell" data-field="sell" ${inv.sell ? 'checked' : ''} aria-label="${escapeAttr(labels.lblSellFrom || 'Sell from this location')}">
                    </div>
                </td>
                <td class="text-center">
                    <div class="form-check form-switch d-inline-flex mb-0 vinv-switch">
                        <input type="checkbox" class="form-check-input vinv-cell" data-field="tracked" ${inv.tracked ? 'checked' : ''} aria-label="${escapeAttr(labels.lblTrack || 'Track inventory')}">
                    </div>
                </td>
                <td>
                    <div class="vinv-qty-wrap ${inv.tracked ? '' : 'is-untracked'}">
                        <input type="number" min="0" class="form-control vinv-cell" data-field="qty" value="${escapeAttr(inv.qty)}" placeholder="${inv.tracked ? '' : escapeAttr(labels.lblUnlimited || 'Unlimited')}" ${inv.tracked ? '' : 'disabled'}>
                        <span class="vinv-unlimited-label" aria-hidden="true">∞</span>
                    </div>
                </td>
            </tr>`;
        }).join('');

        return `
        <tr class="vinv-store-row"><th colspan="4"><i class="ti ti-building-store"></i>${escapeHtml(store.name)}</th></tr>
        ${rowsHtml}`;
    }

    function syncStateToDom(key) {
    }

    /** ──────────────────────────────────────────────────────────────────
     * Event wiring (event delegation off the accordion root)
     * ─────────────────────────────────────────────────────────────────── */

    function attachDelegatedHandlers() {
        const root = accordionEl();
        if (!root || root.dataset.bound === '1') return;
        root.dataset.bound = '1';

        root.addEventListener('input', (e) => {
            const t = e.target;
            const item = t.closest('[data-vinv-key]');
            if (!item) return;
            const key = item.dataset.vinvKey;
            const row = state.rows.get(key);
            if (!row) return;

            if (t.classList.contains('vinv-field')) {
                row[t.dataset.field] = t.value;
                updateCardSummary(item, row);
                return;
            }
            if (t.classList.contains('vinv-cell')) {
                const tr = t.closest('tr[data-loc-id]');
                const locId = parseInt(tr.dataset.locId, 10);
                const inv = row.inventory.get(locId) || { sell: false, tracked: true, qty: 0 };
                if (t.dataset.field === 'sell') inv.sell = t.checked;
                if (t.dataset.field === 'tracked') {
                    inv.tracked = t.checked;
                    const qtyInput = tr.querySelector('input[data-field="qty"]');
                    if (qtyInput) {
                        qtyInput.disabled = !t.checked;
                        qtyInput.value = inv.qty;
                        qtyInput.placeholder = t.checked ? '' : (sectionEl()?.dataset.lblUnlimited || 'Unlimited');
                    }
                    tr.querySelector('.vinv-qty-wrap')?.classList.toggle('is-untracked', !t.checked);
                }
                if (t.dataset.field === 'qty') inv.qty = t.value;
                row.inventory.set(locId, inv);
                updateCardSummary(item, row);
            }
        });

        root.addEventListener('click', (e) => {
            const variantButton = e.target.closest('[data-select-variant]');
            if (variantButton) {
                state.activeKey = variantButton.dataset.selectVariant;
                render();
                return;
            }
            const btn = e.target.closest('.vinv-bulk');
            if (!btn) return;
            const item = btn.closest('[data-vinv-key]');
            const key = item.dataset.vinvKey;
            const row = state.rows.get(key);
            if (!row) return;
            const action = btn.dataset.action;

            if (action === 'sell-all' || action === 'stop-all') {
                const want = action === 'sell-all';
                state.stores.forEach(store => store.locations.forEach(loc => {
                    const inv = row.inventory.get(loc.id) || { sell: false, tracked: true, qty: 0 };
                    inv.sell = want;
                    row.inventory.set(loc.id, inv);
                }));
                render();
            }
            if (action === 'apply-qty') {
                const qtyInput = item.querySelector('.vinv-bulk-qty');
                const qty = parseInt(qtyInput?.value || '0', 10) || 0;
                state.stores.forEach(store => store.locations.forEach(loc => {
                    const inv = row.inventory.get(loc.id) || { sell: false, tracked: true, qty: 0 };
                    if (inv.tracked) inv.qty = qty;
                    row.inventory.set(loc.id, inv);
                }));
                render();
            }
        });
    }

    function updateCardSummary(item, row) {
        const labels = sectionEl()?.dataset || {};
        const option = Array.from(accordionEl()?.querySelectorAll('[data-select-variant]') || [])
            .find(element => element.dataset.selectVariant === item.dataset.vinvKey);
        const priceSummary = option?.querySelector('.vinv-price-summary');
        const sku = option?.querySelector('[data-summary="sku"]');
        if (priceSummary) priceSummary.innerHTML = renderSummaryPrices(row, labels);
        if (sku) sku.textContent = row.sku ? `SKU ${row.sku}` : (labels.lblSkuMissing || 'SKU missing');
        renderTaxPreview();
    }

    function selectedTaxOptions() {
        const select = document.getElementById('select-tax-group');
        if (!select) return [];
        const value = select.tomselect?.getValue() ?? Array.from(select.selectedOptions).map(option => option.value);
        const ids = Array.isArray(value) ? value : String(value || '').split(',').filter(Boolean);
        return ids.map(id => {
            const nativeOption = Array.from(select.options).find(option => String(option.value) === String(id));
            let nativeData = {};
            try { nativeData = JSON.parse(nativeOption?.dataset.data || '{}'); } catch (error) { nativeData = {}; }
            const option = select.tomselect?.options?.[id] || {};
            const data = {...nativeData, ...option};
            let currencyFormat = data.currency_format || {};
            if (typeof currencyFormat === 'string') {
                try { currencyFormat = JSON.parse(currencyFormat); } catch (error) { currencyFormat = {}; }
            }
            return {
                text: data.text || nativeOption?.textContent || `#${id}`,
                market: data.market || nativeOption?.dataset.market || 'Market',
                rate: Number(data.rate ?? nativeOption?.dataset.rate ?? 0),
                currencyCode: data.currency_code || '',
                currencySymbol: data.currency_symbol || data.currency_code || '',
                fxRate: data.fx_rate === null || data.fx_rate === undefined ? null : Number(data.fx_rate),
                fxFallback: data.fx_fallback === true || data.fx_fallback === 1 || data.fx_fallback === '1',
                currencyFormat,
            };
        });
    }

    function groupMarketInteger(digits, separator, style) {
        if (!separator || digits.length <= 3) return digits;
        if (style === 'indian') {
            const last = digits.slice(-3);
            const lead = digits.slice(0, -3);
            return lead ? `${lead.replace(/\B(?=(\d{2})+(?!\d))/g, separator)}${separator}${last}` : last;
        }
        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, separator);
    }

    function formatMarketMoney(baseAmount, market) {
        const rules = market.currencyFormat || {};
        const decimals = Number(rules.decimal_places ?? 2);
        const converted = Number(baseAmount || 0) * (market.fxRate ?? 1);
        const negative = converted < 0;
        const fixed = Math.abs(converted).toFixed(decimals);
        const [integer, fraction = ''] = fixed.split('.');
        const grouped = groupMarketInteger(integer, rules.thousand_separator ?? ',', rules.grouping_style ?? 'western');
        const number = decimals > 0 ? `${grouped}${rules.decimal_separator ?? '.'}${fraction}` : grouped;
        const space = rules.space_between_symbol ? ' ' : '';
        const value = rules.symbol_position === 'after'
            ? `${number}${space}${market.currencySymbol}`
            : `${market.currencySymbol}${space}${number}`;
        if (!negative) return value;
        const template = rules.negative_format || '-{n}';
        return template.includes('{n}') ? template.replace('{n}', value) : `-${value}`;
    }

    function renderTaxPreview() {
        const preview = document.getElementById('productTaxPreview');
        if (!preview) return;
        const row = state.rows.get(state.activeKey);
        const options = selectedTaxOptions();
        const labels = sectionEl()?.dataset || {};
        const previewLabels = preview.dataset;
        if (!row || options.length === 0) {
            preview.innerHTML = `<div class="product-tax-preview-empty"><i class="ti ti-calculator"></i><span>${escapeHtml(previewLabels.lblEmpty || '')}</span></div>`;
            return;
        }

        const price = Number(row.price || 0);
        const special = Number(row.special_price || 0);
        const sellingPrice = special > 0 ? special : price;
        const inclusive = document.getElementById('isInclusiveTax')?.checked === true;
        const markets = new Map();
        options.forEach(option => {
            const key = `${option.market}:${option.currencyCode}`;
            const market = markets.get(key) || {...option, rate: 0, classes: []};
            market.rate += Number.isFinite(option.rate) ? option.rate : 0;
            market.classes.push(option.text.replace(/\s*\([^)]*\)\s*$/, ''));
            markets.set(key, market);
        });

        const cards = Array.from(markets.values()).map(market => {
            const tax = inclusive
                ? sellingPrice * (market.rate / 100) / (1 + market.rate / 100)
                : sellingPrice * market.rate / 100;
            const customerPrice = inclusive ? sellingPrice : sellingPrice + tax;
            const marketSellingPrice = special > 0
                ? `<strong>${escapeHtml(formatMarketMoney(special, market))}</strong><del>${escapeHtml(formatMarketMoney(price, market))}</del>`
                : `<strong>${escapeHtml(formatMarketMoney(price, market))}</strong>`;
            const rateTemplate = previewLabels.lblOneBaseEqualsMarket || '1 :base = :market';
            const formattedRate = rateTemplate
                .replace(':base', previewLabels.baseCurrencyCode || '')
                .replace(':market', formatMarketMoney(1, market));
            const fxText = market.fxFallback
                ? `<span class="product-tax-fx is-warning"><i class="ti ti-alert-triangle"></i>${escapeHtml(previewLabels.lblFxFallback || '')}</span>`
                : `<span class="product-tax-fx">${escapeHtml(previewLabels.lblFxRate || 'Exchange rate')}: ${escapeHtml(formattedRate)}</span>`;
            return `<article class="product-tax-market-card">
                <header><div><strong>${escapeHtml(market.market)}</strong><span>${escapeHtml(market.classes.join(', '))}</span></div><span class="badge">${escapeHtml(market.currencyCode)} · ${escapeHtml(`${market.rate.toFixed(2).replace(/\.00$/, '')}%`)}</span></header>
                <div class="product-tax-metrics">
                    <div><span>${escapeHtml(previewLabels.lblSellingPrice || '')}</span><div class="product-tax-sale">${marketSellingPrice}</div></div>
                    <div><span>${escapeHtml(previewLabels.lblTaxAmount || '')}</span><strong>${escapeHtml(formatMarketMoney(tax, market))}</strong></div>
                    <div class="is-total"><span>${escapeHtml(previewLabels.lblCustomerPrice || '')}</span><strong>${escapeHtml(formatMarketMoney(customerPrice, market))}</strong></div>
                </div>
                ${fxText}
            </article>`;
        }).join('');

        preview.innerHTML = `
            <div class="product-tax-preview-head">
                <div><span>${escapeHtml(previewLabels.lblTitle || '')}</span><small>${escapeHtml(row.title || labels.lblVariant || '')}</small></div>
                <span class="badge bg-blue-lt">${escapeHtml(inclusive ? previewLabels.lblTaxIncluded : previewLabels.lblTaxAdded)}</span>
            </div>
            <div class="product-tax-market-grid">${cards}</div>`;
    }

    /** ──────────────────────────────────────────────────────────────────
     * Variant-list watcher — re-render whenever the variant tab mutates.
     * ─────────────────────────────────────────────────────────────────── */

    function watchVariantList() {
        const list = document.getElementById('variantsList');
        const typeSel = document.getElementById('productType');
        if (typeSel) typeSel.addEventListener('change', render);
        document.getElementById('kit-store')?.addEventListener('change', () => {
            render();
        });
        document.addEventListener('kit:components-changed', render);
        document.addEventListener('kit:store-options-loaded', render);
        document.addEventListener('product:variants-changed', render);
        document.addEventListener('product:tax-options-changed', renderTaxPreview);
        document.getElementById('select-tax-group')?.addEventListener('change', renderTaxPreview);
        document.getElementById('isInclusiveTax')?.addEventListener('change', renderTaxPreview);

        if (!list) return;
        const mo = new MutationObserver(() => {
            if (productType() === 'variant') render();
        });
        mo.observe(list, { childList: true, subtree: true });
    }

    /** ──────────────────────────────────────────────────────────────────
     * Form submit takeover — capture-phase listener runs before product.js,
     * builds the new payload shape, and posts via axios.
     * ─────────────────────────────────────────────────────────────────── */

    function attachSubmitInterceptor() {
        const form = formEl();
        if (!form || form.dataset.vinvSubmitBound === '1') return;
        form.dataset.vinvSubmitBound = '1';

        form.addEventListener('submit', (e) => {
            if (e.defaultPrevented) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            submitForm(form);
        }, true);
    }

    function buildPayload() {
        const type = state.productType;
        const variants = [];
        const eligibleLocationIds = type === 'kit'
            ? new Set(state.stores
                .filter(store => eligibleKitStoreIds().includes(Number(store.id)))
                .flatMap(store => store.locations.map(location => String(location.id))))
            : null;

        for (const [key, row] of state.rows) {
            const inventory = [];
            for (const [locId, inv] of row.inventory) {
                if (!inv.sell || (eligibleLocationIds && !eligibleLocationIds.has(String(locId)))) continue;
                inventory.push({
                    store_location_id: locId,
                    sell_from: true,
                    tracked: type === 'kit' ? false : !!inv.tracked,
                    qty: type === 'kit' ? 0 : (parseInt(inv.qty, 10) || 0),
                });
            }
            variants.push({
                id: row.id,
                title: row.title,
                price: row.price === '' ? null : Number(row.price),
                special_price: row.special_price === '' ? null : Number(row.special_price),
                cost: row.cost === '' ? null : Number(row.cost),
                sku: row.sku || '',
                inventory,
            });
        }
        return { type, variants };
    }

    function submitForm(form) {
        if (form.dataset.productSubmitting === '1') return;
        form.dataset.productSubmitting = '1';
        const submitBtn = form.querySelector('button[type="submit"]');
        const original = submitBtn?.innerHTML;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${original || 'Saving…'}`;
        }
        clearFieldErrors();

        try { window.hugerte?.triggerSave?.(); } catch (e) { /* noop */ }
        try { window.tinymce?.triggerSave?.(); } catch (e) { /* noop */ }

        try { if (typeof addVariantInputsToForm === 'function') addVariantInputsToForm(); } catch (e) { /* noop */ }

        const fd = new FormData(form);
        const { type, variants } = buildPayload();

        if (type === 'variant') {
            const builderVariants = window.productVariantBuilder?.serialize?.();
            const existing = Array.isArray(builderVariants) && builderVariants.length
                ? builderVariants
                : JSON.parse(fd.get('variants_json') || '[]');
            const enriched = mergeVariantPayload(existing, variants);
            fd.set('variants_json', JSON.stringify(enriched));
            fd.set('pricing', JSON.stringify({}));
        } else {
            const row = variants[0] || {};
            fd.set('price', row.price ?? '');
            fd.set('special_price', row.special_price ?? '');
            fd.set('cost', row.cost ?? '');
            fd.set('sku', row.sku ?? '');
            fd.delete('inventory');
            (row.inventory || []).forEach((inv, i) => {
                fd.set(`inventory[${i}][store_location_id]`, inv.store_location_id);
                fd.set(`inventory[${i}][sell_from]`, inv.sell_from ? 1 : 0);
                fd.set(`inventory[${i}][tracked]`, inv.tracked ? 1 : 0);
                if (inv.qty !== null) fd.set(`inventory[${i}][qty]`, inv.qty);
            });
            fd.set('pricing', JSON.stringify({}));
        }

        axios.post(form.getAttribute('action'), fd, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        }).then(r => {
            const data = r.data || {};
            if (data.success) {
                Toast.fire({ icon: 'success', title: data.message || 'Saved' });
                const redirect = form.dataset.redirect || `${getBaseUrl()}/${getPanel()}/products`;
                setTimeout(() => window.location.href = redirect, 600);
            } else {
                Toast.fire({ icon: 'error', title: data.message || 'Save failed' });
            }
        }).catch(err => {
            const msg = err?.response?.data?.message || 'Server error';
            Toast.fire({ icon: 'error', title: msg });
            const validationErrors = err?.response?.data?.errors || err?.response?.data?.data;
            if (err?.response?.status === 422 && validationErrors) {
                renderFieldErrors(validationErrors);
            }
        }).finally(() => {
            delete form.dataset.productSubmitting;
            if (submitBtn) {
                submitBtn.disabled = false;
                if (original) submitBtn.innerHTML = original;
            }
        });
    }

    function mergeVariantPayload(legacyVariants, newVariants) {
        const byId = new Map();
        legacyVariants.forEach(v => byId.set(String(v.id), v));
        newVariants.forEach(nv => {
            const base = byId.get(String(nv.id));
            if (!base) return;
            base.price = nv.price;
            base.special_price = nv.special_price;
            base.cost = nv.cost;
            base.sku = nv.sku;
            base.inventory = nv.inventory;
        });
        return Array.from(byId.values());
    }

    function renderFieldErrors(errors) {
        const summary = document.getElementById('variantInventoryErrors');
        const items = [];
        let firstTarget = null;
        const errorSections = new Set();
        const firstVariantField = Object.keys(errors).find(field => /^variants_json\.\d+/.test(field));
        const firstVariantIndex = firstVariantField?.match(/^variants_json\.(\d+)/)?.[1];
        if (firstVariantIndex !== undefined) {
            const key = state.currentKeys[Number(firstVariantIndex)];
            if (key && key !== state.activeKey) {
                state.activeKey = key;
                render();
            }
        }

        Object.entries(errors).forEach(([field, msgs]) => {
            const msg = Array.isArray(msgs) ? msgs[0] : msgs;
            let target = document.querySelector(`[name="${field}"]`);
            let context = '';
            const variantMatch = field.match(/^variants_json\.(\d+)(?:\.([a-z_]+))?/);
            const inventoryMatch = field.match(/^variants_json\.(\d+)\.inventory\.(\d+)(?:\.([a-z_]+))?/);
            const simplePricingMatch = field.match(/^pricing\.([a-z_]+)$/);
            const simpleInventoryMatch = field.match(/^inventory\.(\d+)(?:\.([a-z_]+))?/);

            if (variantMatch) {
                const variantKey = state.currentKeys[Number(variantMatch[1])];
                const variantRow = state.rows.get(variantKey);
                const card = accordionEl()?.querySelector(`[data-variant-index="${variantMatch[1]}"]`);
                const title = variantRow?.title || `Variant ${Number(variantMatch[1]) + 1}`;
                context = title;
                if (inventoryMatch && card) {
                    const soldRows = Array.from(card.querySelectorAll('tr[data-loc-id]')).filter(row => row.querySelector('[data-field="sell"]')?.checked);
                    const locationRow = soldRows[Number(inventoryMatch[2])];
                    if (locationRow) {
                        context += ` · ${locationRow.dataset.locationName || sectionEl()?.dataset.lblInventoryLocation || ''}`;
                        target = locationRow.querySelector(`[data-field="${inventoryMatch[3] || 'qty'}"]`) || locationRow;
                    }
                } else if (card && variantMatch[2]) {
                    target = card.querySelector(`[data-field="${variantMatch[2]}"]`) || card;
                }
            } else if (simplePricingMatch || simpleInventoryMatch) {
                const card = accordionEl()?.querySelector('[data-variant-index="0"]');
                context = 'Pricing & Inventory';
                if (simpleInventoryMatch && card) {
                    const soldRows = Array.from(card.querySelectorAll('tr[data-loc-id]')).filter(row => row.querySelector('[data-field="sell"]')?.checked);
                    const locationRow = soldRows[Number(simpleInventoryMatch[1])];
                    if (locationRow) {
                        context += ` · ${locationRow.dataset.locationName || sectionEl()?.dataset.lblInventoryLocation || ''}`;
                        target = locationRow.querySelector(`[data-field="${simpleInventoryMatch[2] || 'qty'}"]`) || locationRow;
                    }
                } else if (card) {
                    target = card.querySelector(`[data-field="${simplePricingMatch[1]}"]`) || card;
                }
            }

            if (target) {
                target.classList.add('is-invalid');
                let fb = target.parentNode.querySelector('.invalid-feedback');
                if (!fb) {
                    fb = document.createElement('div');
                    fb.className = 'invalid-feedback d-block';
                    target.parentNode.appendChild(fb);
                }
                fb.textContent = msg;
                firstTarget ||= target;
                const sectionId = target.closest('.form-section')?.id;
                if (sectionId) errorSections.add(sectionId);
            } else {
                const sectionId = sectionForValidationField(field);
                if (sectionId) errorSections.add(sectionId);
            }
            items.push(`<li>${context ? `<strong>${escapeHtml(context)}:</strong> ` : ''}${escapeHtml(msg)}</li>`);
        });

        if (summary && items.length) {
            summary.innerHTML = `<div class="fw-semibold mb-1">${escapeHtml(sectionEl()?.dataset.lblProductIssuesHeading || '')}</div><ul class="mb-0 ps-3">${items.join('')}</ul>`;
            summary.classList.remove('d-none');
        }
        errorSections.forEach(sectionId => {
            document.querySelector(`#productFormRail [data-target="${sectionId}"]`)?.classList.add('has-error');
        });
        firstTarget?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstTarget?.focus?.({ preventScroll: true });
    }

    function clearFieldErrors() {
        const section = sectionEl();
        if (!section) return;
        section.querySelectorAll('.is-invalid').forEach(element => element.classList.remove('is-invalid'));
        section.querySelectorAll('.invalid-feedback').forEach(element => element.remove());
        const summary = document.getElementById('variantInventoryErrors');
        if (summary) {
            summary.replaceChildren();
            summary.classList.add('d-none');
        }
        document.querySelectorAll('#productFormRail .nav-link.has-error').forEach(link => link.classList.remove('has-error'));
    }

    function sectionForValidationField(field) {
        if (/^(category_id)$/.test(field)) return 'section-general';
        if (/^(title|brand_id|made_in|hsn_code|indicator|custom_fields)/.test(field)) return 'section-info';
        if (/^(minimum_order_quantity|quantity_step_size|total_allowed_quantity|is_returnable|returnable_days|is_cancelable|cancelable_till|allow_cod|is_attachment_required|attachment_mode|featured|warranty_period|guarantee_period)/.test(field)) return 'section-policies';
        if (/^(main_image|additional_images|product_video|video_type|video_link|image_fit)/.test(field)) return 'section-media';
        if (/^(short_description|description|custom_sections)/.test(field)) return 'section-description';
        if (/^(tax_groups|is_inclusive_tax)/.test(field)) return 'section-tax';
        if (/^(pricing|inventory)/.test(field)) return 'section-pricing';
        if (/^(type|barcode|weight|height|length|breadth|variants_json)/.test(field)) {
            return /\.(price|special_price|cost|sku|inventory)/.test(field) ? 'section-pricing' : 'section-variations';
        }
        return null;
    }

    /** ──────────────────────────────────────────────────────────────────
     * Boot
     * ─────────────────────────────────────────────────────────────────── */

    function init() {
        if (!sectionEl() || state.isInitialized) return;
        state.isInitialized = true;

        loadStoresWithLocations().then(stores => {
            state.stores = stores || [];

            render();
            attachDelegatedHandlers();
            attachSubmitInterceptor();
            attachLeftRailScrollSpy();
            watchVariantList();
        });
    }

    /**
     * Public hook for the admin seller picker — refetches stores+locations for
     * the currently picked seller, drops any previously selected inventory
     * (so the prior seller's locations don't bleed into the new payload), and
     * re-renders the pricing accordion.
     */
    function reloadStores() {
        if (!sectionEl()) return Promise.resolve();
        return loadStoresWithLocations().then(stores => {
            state.stores = stores || [];
            // Previous seller's per-location inventory selections no longer apply.
            state.rows.forEach(row => {
                row.inventory.clear();
                row._hydrated = false;
            });
            render();
        });
    }
    window.vinvReloadStores = reloadStores;

    /**
     * Left-rail anchor nav: highlight the section currently in view.
     * Plain IntersectionObserver — no scroll listeners, no jank.
     */
    function attachLeftRailScrollSpy() {
        const rail = document.getElementById('productFormRail');
        const main = document.getElementById('productFormMain');
        if (!rail || !main) return;

        const links = Array.from(rail.querySelectorAll('a[data-target]'));
        const sections = links
            .map(l => document.getElementById(l.dataset.target))
            .filter(Boolean);

        if (sections.length === 0) return;

        const setActive = (id) => {
            links.forEach(l => l.classList.toggle('is-active', l.dataset.target === id));
        };

        const scrollToSection = (sectionId, behavior = 'smooth') => {
            const section = document.getElementById(sectionId);
            if (!section) return;
            section.scrollIntoView({ behavior, block: 'start' });
            setActive(sectionId);
        };

        links.forEach(link => {
            link.addEventListener('click', event => {
                event.preventDefault();
                const sectionId = link.dataset.target;
                window.history.replaceState(null, '', `#${sectionId}`);
                scrollToSection(sectionId);
            });
        });

        const requestedSection = window.location.hash.replace(/^#/, '');
        if (requestedSection && sections.some(section => section.id === requestedSection)) {
            requestAnimationFrame(() => requestAnimationFrame(() => scrollToSection(requestedSection, 'auto')));
            window.setTimeout(() => scrollToSection(requestedSection, 'auto'), 250);
        }

        const observer = new IntersectionObserver((entries) => {
            const visible = entries
                .filter(e => e.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
            if (visible) setActive(visible.target.id);
        }, { rootMargin: '-30% 0px -55% 0px', threshold: [0.05, 0.5, 0.9] });

        sections.forEach(s => observer.observe(s));
    }

    const DEBUG = typeof window !== 'undefined' && /[?&]vinv-debug=1/.test(window.location.search);
    function dlog(...args) { if (DEBUG) console.log('[vinv]', ...args); }

    function hydrateRowFromServerVariant(row, dbId) {
        if (row._hydrated) { dlog('skip hydrate, already hydrated', dbId); return; }
        if (!dbId) { dlog('skip hydrate, no dbId on card'); return; }
        const serverVariants = window.productData?.variants;
        if (!Array.isArray(serverVariants) || serverVariants.length === 0) {
            dlog('skip hydrate, no productData.variants'); return;
        }

        const sv = serverVariants.find(v => String(v.id) === String(dbId));
        if (!sv) { dlog('no server variant matches dbId', dbId, 'ids=', serverVariants.map(v => v.id)); return; }

        dlog('hydrating variant row', dbId, sv);
        applyServerVariantToRow(row, sv);
        row._hydrated = true;
    }

    function hydrateRowFromSimpleProduct(row) {
        if (row._hydrated) { dlog('skip simple hydrate, already done'); return; }
        const pd = window.productData;
        if (!pd) { dlog('skip simple hydrate, no productData'); return; }
        const sv = pd.variant || (Array.isArray(pd.variants) ? pd.variants[0] : null);
        if (!sv) { dlog('skip simple hydrate, no variant on productData', pd); return; }

        dlog('hydrating simple row from', sv);
        applyServerVariantToRow(row, sv);
        row._hydrated = true;
    }

    function applyServerVariantToRow(row, sv) {
        row.image = sv.image || row.image || window.productData?.product?.main_image || '';
        row.price = sv.price_exclude_tax ?? '';
        row.special_price = sv.special_price_exclude_tax  ?? '';
        row.cost = sv.cost ?? '';
        row.sku = sv.sku ?? '';

        const inv = sv.location_inventories || sv.locationInventories || [];
        inv.forEach(i => {
            row.inventory.set(parseInt(i.store_location_id ?? i.storeLocationId, 10), {
                sell: !!(i.is_fulfillment_location ?? i.isFulfillmentLocation),
                tracked: !!i.tracked,
                qty: (i.stock ?? i.quantity ?? i.qty) ?? 0,
            });
        });
    }

    /* ──────── Small html-escape helpers ──────── */
    function escapeHtml(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function escapeAttr(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})(jQuery);
