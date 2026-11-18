'use strict';

(function () {
    const root = document.getElementById('productVariantBuilder');
    if (!root) return;
    if (window.remove_button && window.TomSelect) TomSelect.define('remove_button', window.remove_button);

    const state = {
        rows: [],
        applied: [],
        draft: [],
        conflicts: new Map(),
        counter: 0,
    };

    const maxVariants = Number(root.dataset.maxVariants || 100);
    const sellerPicker = document.getElementById('adminSellerPicker') || document.querySelector('[name="seller_id"]');
    const container = document.getElementById('attributesContainer');
    const review = document.getElementById('variantReview');
    const message = document.getElementById('variantBuilderMessage');
    const conflictsContainer = document.getElementById('variantConflicts');
    const variantsList = document.getElementById('variantsList');
    const variantsEmpty = document.getElementById('variantsEmpty');
    const manageAttributesLink = document.getElementById('manageProductAttributes');

    function escapeHtml(value) {
        return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function sellerId() {
        return sellerPicker?.tomselect?.getValue() || sellerPicker?.value || new URL(window.location.href).searchParams.get('seller_id') || '';
    }

    function syncManageAttributesLink() {
        if (!manageAttributesLink || !root.dataset.manageUrl) return;
        const selectedSellerId = sellerId();
        const target = new URL(root.dataset.manageUrl, window.location.origin);
        if (root.dataset.panel === 'admin') {
            manageAttributesLink.classList.toggle('disabled', !selectedSellerId);
            manageAttributesLink.setAttribute('aria-disabled', selectedSellerId ? 'false' : 'true');
            manageAttributesLink.tabIndex = selectedSellerId ? 0 : -1;
            if (selectedSellerId) target.searchParams.set('seller_id', selectedSellerId);
        }
        manageAttributesLink.href = selectedSellerId || root.dataset.panel !== 'admin' ? target.toString() : '#';
    }

    function urlWithParams(url, params) {
        const target = new URL(url, window.location.origin);
        Object.entries(params).forEach(([key, value]) => {
            if (Array.isArray(value)) value.forEach(item => target.searchParams.append(`${key}[]`, item));
            else if (value !== '' && value !== null && value !== undefined) target.searchParams.set(key, value);
        });
        return target.toString();
    }

    async function lookup(url, params = {}) {
        const response = await fetch(urlWithParams(url, {...params, seller_id: sellerId()}), {
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        });
        if (!response.ok) throw new Error('Unable to load options');
        return response.json();
    }

    function canonical(attributes) {
        return Object.entries(attributes)
            .map(([attributeId, valueId]) => [Number(attributeId), Number(valueId)])
            .sort((a, b) => a[0] - b[0])
            .map(pair => `${pair[0]}:${pair[1]}`)
            .join('|');
    }

    function normalizeServerVariant(variant, index) {
        const attributes = {};
        (variant.attributes || []).forEach(item => {
            attributes[String(item.global_attribute_id ?? item.attribute_id)] = String(item.global_attribute_value_id ?? item.value_id);
        });
        return {
            id: String(variant.id ?? `v_initial_${index}`),
            db_id: variant.id || null,
            key: canonical(attributes),
            attributes,
            title: variant.title || '',
            weight: variant.weight ?? '',
            height: variant.height ?? '',
            breadth: variant.breadth ?? '',
            length: variant.length ?? '',
            availability: variant.availability === false || variant.availability === 0 ? 'no' : 'yes',
            barcode: variant.barcode || '',
            is_default: !!variant.is_default,
            image: variant.image || '',
            price: variant.price_exclude_tax ?? variant.price ?? '',
            special_price: variant.special_price_exclude_tax ?? variant.special_price ?? '',
            cost: variant.cost ?? '',
            sku: variant.sku || '',
            inventory: variant.location_inventories || variant.locationInventories || [],
            optionMeta: {},
        };
    }

    function optionMarkup(option) {
        const type = option.swatche_type || 'text';
        const value = option.swatche_value || '';
        let swatch = '<span class="product-option-swatch product-option-swatch-text"></span>';
        if (type === 'color') swatch = `<span class="product-option-swatch" style="background:${escapeHtml(value)}"></span>`;
        if (type === 'image' && value) swatch = `<img class="product-option-swatch" src="${escapeHtml(value)}" alt="">`;
        return `<div class="d-flex align-items-center gap-2">${swatch}<span>${escapeHtml(option.text)}</span></div>`;
    }

    function createRemoteSelect(select, url, initialOptions, multiple, onChange) {
        initialOptions.forEach(option => select.add(new Option(option.text, option.value, false, false)));
        const paging = {query: '', page: 1, more: false, loading: false};
        const control = new TomSelect(select, {
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            maxItems: multiple ? null : 1,
            plugins: multiple ? ['remove_button'] : [],
            preload: true,
            options: initialOptions,
            render: {
                option: optionMarkup,
                item: optionMarkup,
            },
            load(query, callback) {
                paging.query = query || '';
                paging.page = 1;
                paging.more = false;
                lookup(url, {search: query || '', page: 1})
                    .then(payload => {
                        paging.more = !!payload.pagination?.more;
                        callback(payload.data || []);
                    })
                    .catch(() => callback());
            },
            onChange,
        });
        control.dropdown_content.addEventListener('scroll', () => {
            const dropdown = control.dropdown_content;
            if (!paging.more || paging.loading || dropdown.scrollTop + dropdown.clientHeight < dropdown.scrollHeight - 24) return;
            paging.loading = true;
            lookup(url, {search: paging.query, page: paging.page + 1})
                .then(payload => {
                    paging.page += 1;
                    paging.more = !!payload.pagination?.more;
                    control.addOptions(payload.data || []);
                    control.refreshOptions(false);
                })
                .catch(() => Toast.fire({icon: 'error', title: 'Unable to load more options.'}))
                .finally(() => paging.loading = false);
        });
        return control;
    }

    async function hydrateOptions(url, ids) {
        if (!ids.length) return [];
        const payload = await lookup(url, {ids});
        return payload.data || [];
    }

    function selectedAttributeIds(exceptRow = null) {
        return state.rows.filter(row => row !== exceptRow && row.attributeId).map(row => String(row.attributeId));
    }

    async function addAttributeRow(initial = null) {
        const row = {
            key: `option_${++state.counter}`,
            attributeId: initial?.attributeId ? String(initial.attributeId) : '',
            persisted: initial?.persisted === true,
            attribute: null,
            values: new Map(),
            element: document.createElement('div'),
            attributeControl: null,
            valueControl: null,
        };
        row.element.className = 'card product-option-row';
        row.element.innerHTML = `
            <div class="card-body py-3">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Attribute</label>
                        <select class="form-select product-attribute-select" aria-label="Attribute"></select>
                    </div>
                    <div class="col-12 col-lg">
                        <label class="form-label">Values</label>
                        <select class="form-select product-value-select" multiple aria-label="Attribute values"></select>
                    </div>
                    <div class="col-12 col-lg-auto">
                        <button type="button" class="btn btn-outline-danger remove-product-attribute" aria-label="Remove attribute"><i class="ti ti-trash"></i></button>
                    </div>
                </div>
                <div class="small text-muted mt-2 option-impact"></div>
            </div>`;
        container.appendChild(row.element);
        state.rows.push(row);

        const initialAttributeOptions = initial?.attributeId
            ? await hydrateOptions(root.dataset.attributeUrl, [initial.attributeId])
            : [];
        row.attributeControl = createRemoteSelect(
            row.element.querySelector('.product-attribute-select'),
            root.dataset.attributeUrl,
            initialAttributeOptions,
            false,
            value => changeAttribute(row, value)
        );

        row.element.querySelector('.remove-product-attribute').addEventListener('click', () => removeAttributeRow(row));
        if (initial?.attributeId) {
            row.attributeControl.setValue(String(initial.attributeId), true);
            row.attribute = initialAttributeOptions[0] || {value: String(initial.attributeId), text: `#${initial.attributeId}`};
            await createValueControl(row, initial.valueIds || []);
        } else {
            await createValueControl(row, []);
        }
        updateReview();
    }

    async function changeAttribute(row, value) {
        const selected = String(value || '');
        if (selected && selectedAttributeIds(row).includes(selected)) {
            if (row.attributeId) row.attributeControl.setValue(row.attributeId, true);
            else row.attributeControl.clear(true);
            Toast.fire({icon: 'warning', title: 'Each attribute can only be selected once.'});
            return;
        }
        row.attributeId = selected;
        row.attribute = selected ? row.attributeControl.options[selected] : null;
        await createValueControl(row, []);
        updateReview();
    }

    async function createValueControl(row, selectedIds) {
        row.valueControl?.destroy();
        row.values.clear();
        const select = row.element.querySelector('.product-value-select');
        select.replaceChildren();
        select.disabled = !row.attributeId;
        if (!row.attributeId) return;
        const url = root.dataset.valueUrl.replace('__ATTRIBUTE__', row.attributeId);
        const options = await hydrateOptions(url, selectedIds.map(String));
        row.valueControl = createRemoteSelect(select, url, options, true, values => {
            row.values.clear();
            (Array.isArray(values) ? values : [values]).filter(Boolean).forEach(id => {
                const option = row.valueControl.options[String(id)];
                row.values.set(String(id), option || {value: String(id), text: `#${id}`});
            });
            updateReview();
        });
        if (selectedIds.length) {
            row.valueControl.setValue(selectedIds.map(String), true);
            selectedIds.map(String).forEach(id => {
                const option = row.valueControl.options[id];
                if (option) row.values.set(id, option);
            });
        }
    }

    function removeAttributeRow(row) {
        row.attributeControl?.destroy();
        row.valueControl?.destroy();
        row.element.remove();
        state.rows = state.rows.filter(item => item !== row);
        updateReview();
    }

    function combinations(rows) {
        return rows.reduce((result, row) => {
            const entries = Array.from(row.values.values());
            return result.flatMap(combo => entries.map(value => ({
                attributes: {...combo.attributes, [row.attributeId]: String(value.value)},
                optionMeta: {...combo.optionMeta, [row.attributeId]: {attribute: row.attribute, value}},
            })));
        }, [{attributes: {}, optionMeta: {}}]);
    }

    function matchingCount(source, target) {
        return Object.entries(target.attributes).filter(([attributeId, valueId]) => source.attributes[attributeId] === valueId).length;
    }

    function preferredSource(candidates, target) {
        return [...candidates].sort((left, right) => {
            const score = matchingCount(right, target) - matchingCount(left, target);
            if (score) return score;
            if (left.is_default !== right.is_default) return left.is_default ? -1 : 1;
            return Number(left.db_id || Number.MAX_SAFE_INTEGER) - Number(right.db_id || Number.MAX_SAFE_INTEGER);
        })[0] || null;
    }

    function updateReview() {
        const complete = state.rows.length > 0 && state.rows.every(row => row.attributeId && row.values.size > 0);
        if (!complete) {
            state.draft = [];
            review.classList.add('d-none');
            message.classList.remove('d-none');
            message.className = 'alert alert-info mt-3 mb-0';
            message.textContent = 'Every attribute needs at least one selected value.';
            return;
        }
        state.draft = combinations(state.rows).map(item => ({...item, key: canonical(item.attributes)}));
        const tooMany = state.draft.length > maxVariants;
        message.classList.toggle('d-none', !tooMany);
        if (tooMany) {
            message.className = 'alert alert-danger mt-3 mb-0';
            message.textContent = `This selection creates ${state.draft.length} variants. The maximum is ${maxVariants}.`;
        }
        review.classList.remove('d-none');
        const currentByKey = new Map(state.applied.map(variant => [variant.key, variant]));
        const nextKeys = new Set(state.draft.map(item => item.key));
        const kept = state.draft.filter(item => currentByKey.has(item.key)).length;
        const added = state.draft.length - kept;
        const removed = state.applied.filter(item => !nextKeys.has(item.key)).length;
        state.conflicts.clear();
        state.draft.forEach(item => {
            if (currentByKey.has(item.key)) return;
            const sources = state.applied.filter(source => Object.entries(item.attributes).every(([attributeId, valueId]) => source.attributes[attributeId] === valueId));
            if (sources.length > 1) state.conflicts.set(item.key, sources);
        });
        review.querySelector('[data-review="total"]').textContent = `${state.draft.length} total`;
        review.querySelector('[data-review="kept"]').textContent = `${kept} kept`;
        review.querySelector('[data-review="added"]').textContent = `${added} added`;
        review.querySelector('[data-review="removed"]').textContent = `${removed} removed`;
        review.querySelector('[data-review="conflicts"]').textContent = `${state.conflicts.size} conflicts`;
        document.getElementById('applyVariantMatrix').disabled = tooMany;
        renderConflicts();
        state.rows.forEach(row => {
            row.element.querySelector('.option-impact').textContent = `${row.values.size} selected value${row.values.size === 1 ? '' : 's'} · ${state.draft.length} total combinations`;
        });
    }

    function renderConflicts() {
        conflictsContainer.replaceChildren();
        conflictsContainer.classList.toggle('d-none', state.conflicts.size === 0);
        state.conflicts.forEach((sources, key) => {
            const block = document.createElement('div');
            block.className = 'alert alert-warning mb-2';
            block.innerHTML = `<label class="form-label">Several existing variants collapse into one option. Choose which data to keep.</label><select class="form-select"></select>`;
            const select = block.querySelector('select');
            sources.forEach(source => select.add(new Option(source.title || `Variant #${source.db_id || source.id}`, source.id)));
            select.value = preferredSource(sources, state.draft.find(item => item.key === key))?.id || '';
            select.addEventListener('change', () => block.dataset.sourceId = select.value);
            block.dataset.key = key;
            block.dataset.sourceId = select.value;
            conflictsContainer.appendChild(block);
        });
    }

    function sourceFor(item) {
        const exact = state.applied.find(source => source.key === item.key);
        if (exact) return exact;
        const conflict = conflictsContainer.querySelector(`[data-key="${CSS.escape(item.key)}"]`);
        if (conflict) return state.applied.find(source => source.id === conflict.dataset.sourceId) || null;
        return preferredSource(state.applied, item);
    }

    function derivedVariant(item, index) {
        const source = sourceFor(item);
        const exact = source?.key === item.key;
        return {
            id: exact ? source.id : `v_${Date.now()}_${index}`,
            db_id: exact ? source.db_id : null,
            source_variant_id: exact ? null : source?.db_id || null,
            key: item.key,
            attributes: item.attributes,
            optionMeta: item.optionMeta,
            title: exact ? source.title : optionTitle(item),
            weight: source?.weight ?? '',
            height: source?.height ?? '',
            breadth: source?.breadth ?? '',
            length: source?.length ?? '',
            availability: source?.availability ?? 'yes',
            barcode: exact ? source.barcode : '',
            is_default: exact ? source.is_default : false,
            image: source?.image || '',
            price: source?.price ?? '',
            special_price: source?.special_price ?? '',
            cost: source?.cost ?? '',
            sku: exact ? source.sku : '',
            inventory: exact ? source.inventory : [],
        };
    }

    function optionTitle(item) {
        return Object.values(item.optionMeta).map(meta => meta.value.text).join(' / ');
    }

    async function applyMatrix() {
        const nextKeys = new Set(state.draft.map(item => item.key));
        const removed = state.applied.filter(item => !nextKeys.has(item.key)).length;
        if (removed > 0) {
            if (!window.Swal) {
                Toast.fire({icon: 'error', title: 'Confirmation is unavailable. Please reload the page.'});
                return;
            }
            const result = await Swal.fire({
                title: 'Apply variant changes?',
                text: `${removed} existing variant${removed === 1 ? '' : 's'} will be removed when the product is saved.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Apply changes',
            });
            if (!result.isConfirmed) return;
        }
        state.applied = state.draft.map(derivedVariant);
        if (!state.applied.some(variant => variant.is_default) && state.applied[0]) state.applied[0].is_default = true;
        renderVariants();
        updateReview();
        dispatchChange();
        Toast.fire({icon: 'success', title: 'Variant matrix applied.'});
    }

    function optionChips(variant) {
        return Object.entries(variant.attributes).map(([attributeId, valueId]) => {
            const meta = variant.optionMeta[attributeId] || {};
            const attribute = meta.attribute || {};
            const value = meta.value || {};
            return `<span class="product-option-chip">${optionMarkup({text: `${attribute.text || `#${attributeId}`}: ${value.text || `#${valueId}`}`, swatche_type: value.swatche_type || attribute.swatche_type, swatche_value: value.swatche_value})}</span>`;
        }).join('');
    }

    async function removeVariant(variant, row, details) {
        if (state.applied.length === 1) {
            Toast.fire({icon: 'warning', title: 'A product needs at least one variant.'});
            return;
        }
        if (!window.Swal) {
            Toast.fire({icon: 'error', title: 'Confirmation is unavailable. Please reload the page.'});
            return;
        }
        const result = await Swal.fire({
            title: 'Remove this variant?',
            text: variant.db_id
                ? 'This variant will be removed when you save the product.'
                : 'This generated variant will be removed from the product form.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Remove variant',
        });
        if (!result.isConfirmed) return;
        const imageInput = details.querySelector('.variant-image-input');
        window.FilePond?.find?.(imageInput)?.destroy();
        if (variant._previewImageUrl) URL.revokeObjectURL(variant._previewImageUrl);
        state.applied = state.applied.filter(item => item !== variant);
        details.remove();
        row.remove();
        if (!state.applied.some(item => item.is_default)) {
            state.applied[0].is_default = true;
            variantsList.querySelector('[name="variant_default"]').checked = true;
        }
        updateReview();
        dispatchChange();
    }

    function renderVariants() {
        variantsList.replaceChildren();
        variantsEmpty.classList.toggle('d-none', state.applied.length > 0);
        state.applied.forEach(variant => {
            const row = document.createElement('tr');
            row.dataset.id = variant.id;
            row.dataset.dbId = variant.db_id || '';
            row.innerHTML = `
                <td><div class="d-flex flex-wrap gap-1">${optionChips(variant)}</div></td>
                <td><input class="form-control form-control-sm" data-field="title" value="${escapeHtml(variant.title)}"></td>
                <td><input class="form-control form-control-sm" data-field="barcode" value="${escapeHtml(variant.barcode)}"></td>
                <td><select class="form-select form-select-sm" data-field="availability"><option value="yes">Yes</option><option value="no">No</option></select></td>
                <td class="text-center"><input class="form-check-input" type="radio" name="variant_default" ${variant.is_default ? 'checked' : ''}></td>
                <td><div class="d-flex gap-1"><button type="button" class="btn btn-icon btn-ghost-primary toggle-variant-details" aria-label="Edit details" title="Edit details"><i class="ti ti-adjustments"></i></button><button type="button" class="btn btn-icon btn-outline-danger remove-variant" aria-label="Remove variant" title="Remove variant"><i class="ti ti-trash"></i></button></div></td>`;
            row.querySelector('[data-field="availability"]').value = variant.availability;
            const details = document.createElement('tr');
            details.className = 'variant-details d-none';
            details.innerHTML = `<td colspan="6"><div class="row g-3 py-2">
                <div class="col-12 col-lg-4"><label class="form-label">Variant image</label><input type="file" name="variant_image${escapeHtml(variant.id)}" class="form-control variant-image-input" data-image-url="${escapeHtml(variant.image)}" accept="image/*"><label class="form-check mt-2"><input type="checkbox" class="form-check-input" data-field="remove_image"><span class="form-check-label">Remove current image</span></label></div>
                ${['weight', 'height', 'length', 'breadth'].map(field => `<div class="col-6 col-lg-2"><label class="form-label text-capitalize">${field}</label><input type="number" min="0" step="any" class="form-control" data-field="${field}" value="${escapeHtml(variant[field])}"></div>`).join('')}
            </div></td>`;
            variantsList.append(row, details);
            row.querySelectorAll('[data-field]').forEach(input => input.addEventListener('input', () => {
                variant[input.dataset.field] = input.type === 'checkbox' ? input.checked : input.value;
                dispatchChange();
            }));
            details.querySelectorAll('[data-field]').forEach(input => input.addEventListener('input', () => {
                variant[input.dataset.field] = input.type === 'checkbox' ? input.checked : input.value;
                dispatchChange();
            }));
            row.querySelector('[name="variant_default"]').addEventListener('change', () => {
                state.applied.forEach(item => item.is_default = item === variant);
                dispatchChange();
            });
            row.querySelector('.toggle-variant-details').addEventListener('click', () => details.classList.toggle('d-none'));
            row.querySelector('.remove-variant').addEventListener('click', () => removeVariant(variant, row, details));
            const imageInput = details.querySelector('.variant-image-input');
            if (window.FilePond) {
                const pond = window.FilePond.create(imageInput, {
                    allowImagePreview: true,
                    credits: false,
                    storeAsFile: true,
                    maxFileSize: '2MB',
                    acceptedFileTypes: ['image/*'],
                    files: variant.image ? [{source: variant.image, options: {type: 'remote'}}] : [],
                });
                pond.on('addfile', (error, item) => {
                    if (error || !(item?.file instanceof Blob)) return;
                    if (variant._previewImageUrl) URL.revokeObjectURL(variant._previewImageUrl);
                    variant._previewImageUrl = URL.createObjectURL(item.file);
                    variant.image = variant._previewImageUrl;
                    variant.remove_image = false;
                    details.querySelector('[data-field="remove_image"]').checked = false;
                    dispatchChange();
                });
                pond.on('removefile', () => {
                    if (variant._previewImageUrl) URL.revokeObjectURL(variant._previewImageUrl);
                    variant._previewImageUrl = '';
                    variant.image = '';
                    variant.remove_image = true;
                    details.querySelector('[data-field="remove_image"]').checked = true;
                    dispatchChange();
                });
            } else {
                imageInput.addEventListener('change', () => {
                    const file = imageInput.files?.[0];
                    if (!file) return;
                    if (variant._previewImageUrl) URL.revokeObjectURL(variant._previewImageUrl);
                    variant._previewImageUrl = URL.createObjectURL(file);
                    variant.image = variant._previewImageUrl;
                    variant.remove_image = false;
                    dispatchChange();
                });
            }
        });
    }

    function serializableVariants() {
        return state.applied.map(variant => ({
            id: variant.id,
            title: variant.title,
            weight: variant.weight,
            height: variant.height,
            breadth: variant.breadth,
            length: variant.length,
            availability: variant.availability,
            barcode: variant.barcode,
            is_default: variant.is_default ? 'on' : '',
            source_variant_id: variant.source_variant_id || null,
            remove_image: !!variant.remove_image,
            price: variant.price,
            special_price: variant.special_price,
            cost: variant.cost,
            sku: variant.sku,
            inventory: variant.inventory,
            attributes: Object.entries(variant.attributes).map(([attributeId, valueId]) => ({attribute_id: Number(attributeId), value_id: Number(valueId)})),
        }));
    }

    function dispatchChange() {
        document.dispatchEvent(new CustomEvent('product:variants-changed', {detail: {variants: state.applied}}));
    }

    function writeInput() {
        const form = document.getElementById('product-form-submit');
        if (!form) return;
        form.querySelectorAll('.variant-hidden-input').forEach(input => input.remove());
        const input = document.createElement('input');
        input.type = 'hidden';
        input.className = 'variant-hidden-input';
        input.name = 'variants_json';
        input.value = JSON.stringify(serializableVariants());
        form.appendChild(input);
    }

    async function hydrateInitialRows() {
        const grouped = new Map();
        state.applied.forEach(variant => Object.entries(variant.attributes).forEach(([attributeId, valueId]) => {
            if (!grouped.has(attributeId)) grouped.set(attributeId, new Set());
            grouped.get(attributeId).add(valueId);
        }));
        for (const [attributeId, values] of grouped) await addAttributeRow({attributeId, valueIds: Array.from(values), persisted: true});
        if (!grouped.size) await addAttributeRow();
        state.applied.forEach(variant => {
            variant.optionMeta = {};
            state.rows.forEach(row => {
                const valueId = variant.attributes[row.attributeId];
                if (valueId) variant.optionMeta[row.attributeId] = {attribute: row.attribute, value: row.values.get(valueId)};
            });
        });
        renderVariants();
        dispatchChange();
    }

    async function clearCaches() {
        state.rows.forEach(row => {
            if (row.attributeControl) {
                row.attributeControl.loadedSearches = {};
                row.attributeControl.clearCache?.();
                row.attributeControl.load('');
            }
            if (row.valueControl) {
                row.valueControl.loadedSearches = {};
                row.valueControl.clearCache?.();
                row.valueControl.load('');
            }
        });

        const rowsToCheck = state.rows.filter(row => row.attributeId && !row.persisted);
        const attributeResults = await Promise.all(rowsToCheck.map(async row => {
            try {
                const active = await hydrateOptions(root.dataset.attributeUrl, [row.attributeId]);
                return active.length ? null : row;
            } catch (error) {
                return null;
            }
        }));
        const valueResults = await Promise.all(state.rows.map(async row => {
            const selectedIds = Array.from(row.values.keys());
            if (!row.attributeId || !selectedIds.length || !row.valueControl) return [];
            try {
                const url = root.dataset.valueUrl.replace('__ATTRIBUTE__', row.attributeId);
                const active = await hydrateOptions(url, selectedIds);
                const activeIds = new Set(active.map(option => String(option.value)));
                active.forEach(option => {
                    const valueId = String(option.value);
                    row.valueControl.updateOption(valueId, option);
                    if (row.values.has(valueId)) row.values.set(valueId, option);
                });
                const removedIds = selectedIds.filter(valueId => !activeIds.has(String(valueId)));
                removedIds.forEach(valueId => {
                    row.valueControl.removeItem(String(valueId), true);
                    row.valueControl.removeOption(String(valueId));
                    row.values.delete(String(valueId));
                });
                row.valueControl.refreshItems();
                return removedIds;
            } catch (error) {
                return [];
            }
        }));
        const removedRows = attributeResults.filter(Boolean);
        const removedValueCount = valueResults.reduce((total, valueIds) => total + valueIds.length, 0);
        const appliedRowsAreUnsaved = state.applied.every(variant => !variant.db_id);
        removedRows.forEach(removeAttributeRow);
        if (removedRows.length || removedValueCount) {
            if (!state.rows.length) await addAttributeRow();
            updateReview();
            if (appliedRowsAreUnsaved) {
                const complete = state.rows.length > 0 && state.rows.every(row => row.attributeId && row.values.size > 0);
                state.applied = complete ? state.draft.map(derivedVariant) : [];
                if (!state.applied.some(variant => variant.is_default) && state.applied[0]) state.applied[0].is_default = true;
                renderVariants();
                dispatchChange();
            }
        }
        if (removedRows.length) {
            Toast.fire({icon: 'info', title: root.dataset.lblAttributeRemoved || ''});
        }
        if (removedValueCount) {
            Toast.fire({icon: 'info', title: root.dataset.lblAttributeValueRemoved || ''});
        }
    }

    state.applied = (window.productData?.variants || []).map(normalizeServerVariant);
    document.getElementById('addProductAttribute')?.addEventListener('click', () => addAttributeRow());
    document.getElementById('applyVariantMatrix')?.addEventListener('click', applyMatrix);
    window.addEventListener('focus', clearCaches);
    window.addEventListener('storage', async event => {
        if (event.key !== 'product-attribute-created') return;
        try {
            const detail = JSON.parse(event.newValue || '{}');
            await clearCaches();
            if (root.dataset.panel === 'admin' && String(detail.seller_id || '') !== String(sellerId())) return;
            if (detail.id && !selectedAttributeIds().includes(String(detail.id))) {
                await addAttributeRow({attributeId: detail.id, valueIds: detail.value_ids || []});
            }
        } catch (error) {
            clearCaches();
        }
    });
    sellerPicker?.addEventListener('change', () => {
        syncManageAttributesLink();
        state.rows.slice().forEach(removeAttributeRow);
        state.applied = [];
        renderVariants();
        addAttributeRow();
    });

    window.productVariantBuilder = {
        getVariants: () => state.applied.map(variant => ({...variant})),
        serialize: serializableVariants,
        writeInput,
        canonical,
    };
    window.addVariantInputsToForm = writeInput;

    syncManageAttributesLink();
    hydrateInitialRows().catch(() => Toast.fire({icon: 'error', title: 'Unable to load product attributes.'}));
})();
