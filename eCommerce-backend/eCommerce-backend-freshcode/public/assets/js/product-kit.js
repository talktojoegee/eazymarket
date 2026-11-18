(function () {
    const section = document.querySelector('[data-kit-section]');
    if (!section) return;

    const config = section.dataset;
    const typeSelect = document.getElementById('productType');
    const navLink = document.getElementById('kitSectionNav');
    const storeSelect = document.getElementById('kit-store');
    const components = document.getElementById('kit-components');
    const template = document.getElementById('kit-component-template');
    const addButton = document.getElementById('kit-add-component');
    const recalculateButton = document.getElementById('kit-recalculate-price');
    const priceStatus = document.getElementById('kit-price-status');
    const calculatedPrice = document.getElementById('kit-calculated-price');
    const adminSellerSelect = document.getElementById('adminSellerPicker');
    const inventoryAccordion = document.getElementById('variantInventoryAccordion');
    let automaticPrice = true;
    let settingPrice = false;
    let pendingPrice = null;

    function toast(icon, title) {
        if (typeof Toast !== 'undefined' && Toast.fire) Toast.fire({ icon, title });
    }

    function sellerId() {
        return adminSellerSelect?.tomselect?.getValue() || adminSellerSelect?.value || config.sellerId || '';
    }

    function isKit() {
        return typeSelect?.value === 'kit';
    }

    function componentPrice(row) {
        const select = row.querySelector('.kit-component-select');
        const option = select?.tomselect?.options?.[select.value];
        const value = option?.price ?? row.dataset.componentPrice;
        return value !== undefined && value !== '' && Number.isFinite(Number(value)) ? Number(value) : null;
    }

    function kitTotal() {
        const rows = [...(components?.querySelectorAll('.kit-component-row') || [])];
        const selectedRows = rows.filter(row => row.querySelector('.kit-component-select')?.value);
        if (!selectedRows.length) return null;
        let total = 0;
        for (const row of selectedRows) {
            const price = componentPrice(row);
            if (price === null) return null;
            const quantity = Math.max(1, parseInt(row.querySelector('input[type="number"]')?.value || '1', 10));
            total += price * quantity;
        }
        return Math.round((total + Number.EPSILON) * 100) / 100;
    }

    function productPriceInput() {
        return inventoryAccordion?.querySelector('[data-vinv-key="__simple__"] .vinv-field[data-field="price"]');
    }

    function updatePriceStatus() {
        priceStatus.textContent = automaticPrice ? config.labelPriceAuto : config.labelPriceManual;
    }

    function applyKitPrice(value) {
        const input = productPriceInput();
        if (!input) {
            pendingPrice = value;
            return;
        }
        pendingPrice = null;
        input.value = Number(value).toFixed(2);
        settingPrice = true;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        settingPrice = false;
    }

    function calculateKitPrice(force = false) {
        const total = kitTotal();
        recalculateButton.disabled = !isKit() || total === null;
        calculatedPrice.textContent = total === null ? '' : `${document.getElementById('variantInventorySection')?.dataset.baseCurrencySymbol || ''}${total.toFixed(2)}`;
        if (total !== null && (automaticPrice || force)) applyKitPrice(total);
        updatePriceStatus();
    }

    function reindex() {
        components?.querySelectorAll('.kit-component-row').forEach((row, index) => {
            const select = row.querySelector('.kit-component-select');
            const quantity = row.querySelector('input[type="number"]');
            const remove = row.querySelector('.kit-remove-component');
            if (select) select.name = `kit_components[${index}][product_variant_id]`;
            if (quantity) quantity.name = `kit_components[${index}][quantity]`;
            if (select?.tomselect) select.tomselect.input.setAttribute('aria-label', `Product and variant ${index + 1}`);
            [select, quantity, remove].forEach(control => {
                if (control) control.disabled = !isKit() || !storeSelect.value;
            });
            if (select?.tomselect) {
                if (isKit() && storeSelect.value) select.tomselect.enable();
                else select.tomselect.disable();
            }
        });
    }

    function initializeComponentSelect(select) {
        if (!window.TomSelect || !select || select.tomselect) return;
        new window.TomSelect(select, {
            valueField: 'id',
            labelField: 'text',
            searchField: 'text',
            maxOptions: 20,
            loadThrottle: 300,
            dropdownParent: 'body',
            placeholder: select.dataset.placeholder || 'Search products and variants',
            create: false,
            shouldLoad(query) {
                return Boolean(storeSelect.value && query.trim());
            },
            load(query, callback) {
                if (!storeSelect.value || !query.trim()) return callback();
                const url = new URL(config.lookupUrl, window.location.origin);
                url.searchParams.set('seller_id', sellerId());
                url.searchParams.set('store_id', storeSelect.value);
                url.searchParams.set('q', query.trim());
                components.querySelectorAll('.kit-component-select').forEach(otherSelect => {
                    if (otherSelect === select || !otherSelect.value) return;
                    url.searchParams.append('exclude_variant_ids[]', otherSelect.value);
                });
                fetch(url, { headers: { Accept: 'application/json' } })
                    .then(response => response.ok ? response.json() : Promise.reject(new Error(config.labelSearchError)))
                    .then(payload => callback(payload.results || []))
                    .catch(error => {
                        callback();
                        toast('error', error.message || config.labelSearchError);
                    });
            },
            onChange() {
                const selectedId = select.value;
                const isDuplicate = selectedId && [...components.querySelectorAll('.kit-component-select')]
                    .some(otherSelect => otherSelect !== select && otherSelect.value === selectedId);
                if (isDuplicate) {
                    select.tomselect.clear(true);
                    toast('warning', config.labelDuplicateComponent);
                }
                calculateKitPrice();
                document.dispatchEvent(new CustomEvent('kit:components-changed'));
            }
        });
        const selectedOption = select.options[select.selectedIndex];
        if (select.value && selectedOption?.dataset.storeIds && select.tomselect) {
            try {
                select.tomselect.options[select.value].store_ids = JSON.parse(selectedOption.dataset.storeIds);
            } catch (error) {
                select.tomselect.options[select.value].store_ids = [];
            }
        }
    }

    function clearComponentOptions() {
        components?.querySelectorAll('.kit-component-select').forEach(select => {
            if (!select.tomselect) return;
            select.tomselect.clear(true);
            select.tomselect.clearOptions();
            select.tomselect.refreshOptions(false);
        });
    }

    function setActiveState() {
        const active = isKit();
        section.classList.toggle('d-none', !active);
        navLink?.classList.toggle('d-none', !active);
        storeSelect.disabled = !active;
        storeSelect.required = active;
        if (storeSelect.tomselect) {
            if (active) storeSelect.tomselect.enable();
            else storeSelect.tomselect.disable();
        }
        components?.querySelectorAll('.kit-component-row').forEach(row => {
            [row.querySelector('.kit-component-select'), row.querySelector('input[type="number"]'), row.querySelector('.kit-remove-component')]
                .forEach(control => {
                    if (control) control.disabled = !active || !storeSelect.value;
                });
            const select = row.querySelector('.kit-component-select');
            if (select?.tomselect) {
                if (active && storeSelect.value) select.tomselect.enable();
                else select.tomselect.disable();
            }
        });
        addButton.disabled = !active || !storeSelect.value;
        calculateKitPrice();
    }

    function loadStores(selectedId = '') {
        const currentSellerId = sellerId();
        storeSelect.tomselect?.destroy();
        storeSelect.replaceChildren();
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = config.labelStorePlaceholder;
        storeSelect.appendChild(placeholder);
        storeSelect.disabled = true;
        if (!currentSellerId) return Promise.resolve();

        const url = new URL(config.storesUrl, window.location.origin);
        if (url.pathname.includes('/admin/')) url.searchParams.set('seller_id', currentSellerId);
        return fetch(url, { headers: { Accept: 'application/json' } })
            .then(response => response.ok ? response.json() : Promise.reject())
            .then(payload => {
                const stores = Array.isArray(payload.data) ? payload.data : [];
                stores.forEach(store => {
                    const option = document.createElement('option');
                    option.value = store.id;
                    option.textContent = store.name;
                    option.selected = String(store.id) === String(selectedId);
                    storeSelect.appendChild(option);
                });
                storeSelect.disabled = !isKit();
                if (window.TomSelect) {
                    new window.TomSelect(storeSelect, { create: false, allowEmptyOption: true, dropdownParent: 'body' });
                }
                setActiveState();
                document.dispatchEvent(new CustomEvent('kit:store-options-loaded'));
            })
            .catch(() => toast('error', config.labelSearchError));
    }

    function handleStoreChange() {
        clearComponentOptions();
        setActiveState();
        document.dispatchEvent(new CustomEvent('kit:components-changed'));
    }

    components?.addEventListener('input', event => {
        if (event.target.matches('input[type="number"]')) calculateKitPrice();
    });

    recalculateButton?.addEventListener('click', () => {
        automaticPrice = true;
        calculateKitPrice(true);
    });

    inventoryAccordion?.addEventListener('input', event => {
        if (!settingPrice && event.target.matches('.vinv-field[data-field="price"]')) {
            automaticPrice = false;
            updatePriceStatus();
        }
    });

    if (inventoryAccordion && window.MutationObserver) {
        new MutationObserver(() => {
            if (pendingPrice !== null) applyKitPrice(pendingPrice);
            calculateKitPrice();
        }).observe(inventoryAccordion, { childList: true, subtree: true });
    }

    components?.querySelectorAll('.kit-component-select').forEach(initializeComponentSelect);
    typeSelect?.addEventListener('change', () => {
        setActiveState();
        if (isKit() && !storeSelect.tomselect) loadStores(config.storeId);
    });
    storeSelect?.addEventListener('change', handleStoreChange);
    adminSellerSelect?.addEventListener('change', () => {
        clearComponentOptions();
        document.dispatchEvent(new CustomEvent('kit:components-changed'));
        loadStores('');
    });

    addButton?.addEventListener('click', () => {
        if (!storeSelect.value) {
            toast('warning', config.labelSelectStore);
            return;
        }
        const row = template.content.firstElementChild.cloneNode(true);
        components.appendChild(row);
        initializeComponentSelect(row.querySelector('.kit-component-select'));
        reindex();
        setActiveState();
    });

    components?.addEventListener('click', event => {
        const button = event.target.closest('.kit-remove-component');
        if (!button) return;
        if (components.querySelectorAll('.kit-component-row').length <= 1) {
            toast('warning', config.labelComponentsRequired);
            return;
        }
        button.closest('.kit-component-row').remove();
        reindex();
        calculateKitPrice();
        document.dispatchEvent(new CustomEvent('kit:components-changed'));
    });

    loadStores(config.storeId).then(() => {
        setActiveState();
        reindex();
        const currentPrice = Number(config.productPrice);
        const total = kitTotal();
        if (total !== null && Number.isFinite(currentPrice) && Math.abs(currentPrice - total) > 0.005) {
            automaticPrice = false;
        }
        calculateKitPrice();
    });
})();
