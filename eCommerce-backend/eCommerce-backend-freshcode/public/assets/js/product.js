document.addEventListener('show.bs.modal', event => {
    if (event.target.id == 'product-condition-modal') {
        const triggerButton = event.relatedTarget;
        const conditionId = triggerButton ? triggerButton.getAttribute('data-id') : null;
        let url = `${base_url}/${panel}/product-conditions/${conditionId}/edit`;

        const form = document.querySelector('#product-condition-modal .form-submit');
        const modalTitle = document.querySelector('#product-condition-modal .modal-title');
        const submitButton = document.querySelector('#product-condition-modal button[type="submit"]');
        const selectCategory = document.getElementById('select-category');
        let selectCategoryTom = selectCategory && selectCategory.tomselect ? selectCategory.tomselect : null;

        if (conditionId) {
            // Edit mode: Fetch and populate data
            fetch(url, {method: 'GET'})
                .then(response => response.json())
                .then(async responseData => {
                    const data = responseData.data;

                    // Fill form fields
                    form.querySelector('input[name="title"]').value = data.title || '';

                    if (selectCategoryTom) {
                        await loadCategoryAndSetValue(selectCategoryTom, data.category_id);
                    } else if (selectCategory) {
                        selectCategory.value = data.category_id;
                    }

                    form.querySelector('select[name="alignment"]').value = data.alignment || '';

                    // Change form action to update route
                    form.setAttribute('action', `${base_url}/${panel}/product-conditions/${conditionId}`);

                    // Insert/ensure _method=PUT for update, if needed
                    let methodInput = form.querySelector('input[name="_method"]');
                    if (!methodInput) {
                        methodInput = document.createElement('input');
                        methodInput.setAttribute('type', 'hidden');
                        methodInput.setAttribute('name', '_method');
                        form.appendChild(methodInput);
                    }
                    methodInput.value = 'POST';

                    // Update modal title and button
                    modalTitle.textContent = 'Edit Product Condition';
                    submitButton.innerHTML = '<i class="ti ti-edit me-1"></i> Update';
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                });
        } else {
            // New condition mode: Reset fields
            if (form) form.reset();
            if (selectCategoryTom) selectCategoryTom.clear();
            if (selectCategory) selectCategory.value = '';
            // Remove _method input if it exists
            const methodInput = form.querySelector('input[name="_method"]');
            if (methodInput) methodInput.parentNode.removeChild(methodInput);

            // Set action for create
            form.setAttribute('action', `${base_url}/${panel}/product-conditions`);
            modalTitle.textContent = 'Add Product Condition';
            submitButton.innerHTML = '<i class="ti ti-plus me-1"></i> Create';
        }
    }
    if (event.target.id == 'product-faq-modal') {
        const triggerButton = event.relatedTarget;
        const conditionId = triggerButton ? triggerButton.getAttribute('data-id') : null;
        let url = `${base_url}/${panel}/product-faqs/${conditionId}/edit`;

        const form = document.querySelector('#product-faq-modal .form-submit');
        const modalTitle = document.querySelector('#product-faq-modal .modal-title');
        const submitButton = document.querySelector('#product-faq-modal button[type="submit"]');
        const selectProduct = document.getElementById('faq-edit-product');
        let selectProductTom = selectProduct && selectProduct.tomselect ? selectProduct.tomselect : null;

        if (conditionId) {
            // Edit mode: Fetch and populate data
            fetch(url, {method: 'GET'})
                .then(response => response.json())
                .then(async responseData => {
                    const data = responseData.data;

                    // Fill form fields
                    form.querySelector('textarea[name="question"]').value = data.question || '';
                    form.querySelector('textarea[name="answer"]').value = data.answer || '';
                    form.querySelector('select[name="product_id"]').value = data.product_id || '';
                    form.querySelector('select[name="status"]').value = data.status || '';

                    if (selectProductTom) {
                        await loadProductAndSetValue(selectProductTom, data.product_id);
                    } else if (selectProduct) {
                        selectProduct.replaceChildren(new Option(data.product?.title || data.product_id, data.product_id, true, true));
                    }

                    // Change form action to update route
                    form.setAttribute('action', `${base_url}/${panel}/product-faqs/${conditionId}`);

                    // Update modal title and button
                    modalTitle.textContent = 'Edit Product Faq';
                    submitButton.innerHTML = '<i class="ti ti-edit me-1"></i> Update';
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                });
        } else {
            // New condition mode: Reset fields
            if (form) form.reset();
            if (selectProductTom) selectProductTom.clear();
            if (selectProduct) selectProduct.value = '';
            form.querySelector('textarea[name="question"]').value = '';
            form.querySelector('textarea[name="answer"]').value = '';
            form.querySelector('select[name="product_id"]').value = '';
            form.querySelector('select[name="status"]').value = 'active';
            // Set action for create
            form.setAttribute('action', `${base_url}/${panel}/product-faqs`);
            modalTitle.textContent = 'Add Product Faq';
            submitButton.innerHTML = '<i class="ti ti-plus me-1"></i> Add';
        }
    }
});
document.addEventListener('click', function (event) {
    // delete soft store
    handleDelete(event, '.delete-product-condition', `/${panel}/product-conditions/`, 'You are about to delete this Product Condition.');
    handleDelete(event, '.delete-product', `/${panel}/products/`, 'You are about to delete this Product.');
    handleDelete(event, '.delete-product-faq', `/${panel}/product-faqs/`, 'You are about to delete this Product Faq.');
});


async function loadCategoryAndSetValue(tomSelectInstance, categoryId) {
    if (!categoryId) return;

    let parentOption = tomSelectInstance.options[categoryId];
    if (!parentOption) {
        try {
            const res = await fetch(`${base_url}/${panel}/categories/search?find_id=${categoryId}`);
            const json = await res.json();
            // Assuming your endpoint returns an array of categories with id and name
            if (json && json.length) {
                tomSelectInstance.addOption(json[0]);
            }
        } catch (error) {
            console.error(error);
        }
    }
    tomSelectInstance.setValue(categoryId);
}

async function loadProductAndSetValue(tomSelectInstance, productId) {
    if (!productId) return;

    let parentOption = tomSelectInstance.options[productId];
    if (!parentOption) {
        try {
            const res = await fetch(`${base_url}/${panel}/products/search?find_id=${productId}`);
            const json = await res.json();
            // Assuming your endpoint returns an array of categories with id and name
            if (json && json.length) {
                tomSelectInstance.addOption(json[0]);
            }
        } catch (error) {
            console.error(error);
        }
    }
    tomSelectInstance.setValue(productId);
}

document.addEventListener('DOMContentLoaded', function () {
    try {
        const categoriesElement = document.getElementById('categories');
        if (categoriesElement == null) {
            return;
        }
        const categories = JSON.parse(categoriesElement.dataset.categories);
        const selectedInput = document.getElementById('selected_category');
        const categorySelect = document.getElementById('select-category');
        const selectedSummary = document.getElementById('selectedCategorySummary');
        const approvalNotice = document.getElementById('productApprovalNotice');
        let syncingCategory = false;

        function syncCategoryUi(id, title, requiresApproval) {
            if (selectedInput) selectedInput.value = id || '';
            const summaryTitle = selectedSummary?.querySelector('span:not(.badge)');
            const badge = selectedSummary?.querySelector('[data-category-approval-badge]');
            if (summaryTitle) summaryTitle.textContent = title || selectedSummary.dataset.placeholder || '';
            if (badge) {
                badge.classList.remove('d-none', 'bg-orange-lt', 'bg-green-lt');
                badge.classList.add(requiresApproval ? 'bg-orange-lt' : 'bg-green-lt');
                badge.textContent = requiresApproval
                    ? approvalNotice?.dataset.lblRequired || ''
                    : approvalNotice?.dataset.lblNotRequired || '';
            }
            if (approvalNotice) {
                approvalNotice.dataset.requiresApproval = requiresApproval ? '1' : '0';
                approvalNotice.classList.toggle('requires-review', requiresApproval);
                const message = approvalNotice.querySelector('[data-approval-category-message]');
                if (message) message.textContent = requiresApproval
                    ? approvalNotice.dataset.lblRequired || ''
                    : approvalNotice.dataset.lblNotRequired || '';
                approvalNotice.querySelector('[data-approval-critical-fields]')?.classList.toggle('d-none', !requiresApproval);
            }
        }

        $('#categories-tree').jstree({
            'core': {
                'data': categories,
                'multiple': false,
                'themes': {
                    'variant': 'large'
                },
            }, 'checkbox': {
                'keep_selected_style': true
            }, 'plugins': ['wholerow']
        }).on('ready.jstree', function () {
            // Categories ready; you can programmatically select nodes here
            const tree = $('#categories-tree').jstree(true);

            // If in edit mode, select the category
            if (window.productData && window.productData.product && window.productData.product.category_id) {
                tree.select_node(window.productData.product.category_id.toString());
            }
        }).on('select_node.jstree', function (e, data) {
            const category = data.node.original?.data || {};
            const title = category.title || $('<div>').html(data.node.text).text().trim();
            const requiresApproval = category.requires_approval === true || category.requires_approval === 1;
            syncCategoryUi(data.node.id, title, requiresApproval);
            if (categorySelect?.tomselect) {
                const value = String(data.node.id);
                categorySelect.tomselect.addOption({
                    value,
                    text: title,
                    requires_approval: requiresApproval,
                });
                if (String(categorySelect.tomselect.getValue() || '') !== value) {
                    syncingCategory = true;
                    categorySelect.tomselect.setValue(value, true);
                    syncingCategory = false;
                }
            }
        });

        categorySelect?.addEventListener('change', function () {
            if (syncingCategory) return;
            const value = categorySelect.tomselect?.getValue() || categorySelect.value;
            if (!value) return;
            const option = categorySelect.tomselect?.options?.[String(value)] || {};
            syncCategoryUi(value, option.text || '', option.requires_approval === true || option.requires_approval === 1 || option.requires_approval === '1');
            const treeInstance = $('#categories-tree').jstree(true);
            const selected = treeInstance?.get_selected().map(String) || [];
            if (treeInstance && (selected.length !== 1 || selected[0] !== String(value))) {
                syncingCategory = true;
                treeInstance.deselect_all(true);
                treeInstance.select_node(String(value), true);
                syncingCategory = false;
            }
        });

        document.getElementById('expandCategoryTree')?.addEventListener('click', () => $('#categories-tree').jstree('open_all'));
        document.getElementById('collapseCategoryTree')?.addEventListener('click', () => $('#categories-tree').jstree('close_all'));

        if (!selectedInput?.value && approvalNotice) {
            const message = approvalNotice.querySelector('[data-approval-category-message]');
            if (message) message.textContent = approvalNotice.dataset.lblSelectCategory || '';
        }

    } catch (e) {
        console.error(e)
    }

    const returnable = document.getElementById('isReturnable');
    const cancelable = document.getElementById('isCancelable');
    const returnableLabel = document.getElementById('returnableDaysLabel');
    const cancelableLabel = document.getElementById('cancelableTillLabel');
    const syncConditionalRequired = () => {
        returnableLabel?.classList.toggle('required', returnable?.value === '1');
        cancelableLabel?.classList.toggle('required', cancelable?.value === '1');
    };
    returnable?.addEventListener('change', syncConditionalRequired);
    cancelable?.addEventListener('change', syncConditionalRequired);
    syncConditionalRequired();

    // Wizard navigation removed — the seller product form is a single-scroll
    // page now (resources/views/seller/products/_form_body.blade.php). All
    // sections render at once; the left-rail anchor nav (#productFormRail) +
    // sticky save bar replace Previous/Next/step pills. The legacy
    // step-state-machine code that lived here (`.wizard-step`, `.nav-segmented`,
    // `?step=N` URL sync) was deleted along with its DOM hooks.
});
// document.addEventListener('DOMContentLoaded', function () {
// Database attributes - Replace with actual AJAX call
let dbAttributes;
const attributesElement = document.getElementById('attributes');
if (attributesElement != null) {
    dbAttributes = JSON.parse(attributesElement.dataset.attributes);
}


let variants = [], removedVariants = [], attributeCounter = 0;
let productPricing = null;
let selectedStoreIds = new Set();

function seedSelectedStoresFromPricing() {
    if (!productPricing || !productPricing.variant_pricing) return;
    Object.values(productPricing.variant_pricing).forEach(v => {
        if (v && Array.isArray(v.store_pricing)) {
            v.store_pricing.forEach(sp => {
                if (sp && sp.store_id != null) {
                    selectedStoreIds.add(String(sp.store_id));
                }
            });
        }
    });
}

function rerenderStorePricing() {
    const type = document.getElementById('productType')?.value;
    if (type === 'variant') {
        updateVariantPricing();
    } else if (type) {
        initializeSimplePricing();
    }
}

function populateAddStoreDropdown() {
    const list = document.getElementById('addStorePricingList');
    const section = document.getElementById('storePricingSection');
    if (!list || !section) return;

    const lblLoading = section.dataset.lblLoading || 'Loading...';
    const lblAllAdded = section.dataset.lblAllAdded || 'All stores added';
    const lblNoMatch = section.dataset.lblNoMatch || 'No matching records found';

    list.innerHTML = `<div class="text-muted small text-center py-2">${lblLoading}</div>`;

    fetchStores().then(stores => {
        const total = (stores || []).length;
        updateStorePricingCount(total);

        const unselected = (stores || []).filter(s => !selectedStoreIds.has(String(s.id)));
        if (unselected.length === 0) {
            list.innerHTML = `<div class="text-muted small text-center py-2">${lblAllAdded}</div>`;
            return;
        }
        const searchVal = (document.getElementById('addStorePricingSearch')?.value || '').toLowerCase().trim();
        const filtered = searchVal
            ? unselected.filter(s => (s.name || '').toLowerCase().includes(searchVal))
            : unselected;

        if (filtered.length === 0) {
            list.innerHTML = `<div class="text-muted small text-center py-2">${lblNoMatch}</div>`;
            return;
        }
        list.innerHTML = filtered.map(s => `
            <button type="button" class="dropdown-item add-store-pricing-item d-flex align-items-center gap-2" data-store-id="${s.id}">
                <i class="ti ti-building-store text-muted"></i>
                <span class="text-truncate">${s.name}</span>
            </button>`).join('');
    });
}

function updateStorePricingCount(total) {
    const badge = document.getElementById('storePricingCount');
    const section = document.getElementById('storePricingSection');
    if (!badge || !section) return;
    if (selectedStoreIds.size === 0) {
        badge.classList.add('d-none');
        badge.textContent = '';
        return;
    }
    badge.classList.remove('d-none');
    const template = section.dataset.lblStoresAdded || ':count of :total stores added';
    badge.textContent = template
        .replace(':count', String(selectedStoreIds.size))
        .replace(':total', String(total));
}

function renderStorePricingEmptyState() {
    const section = document.getElementById('storePricingSection');
    const lblEmpty = section?.dataset.lblEmpty || 'No store added yet.';
    const lblAdd = section?.dataset.lblAddStore || 'Add Store';
    return `
        <div class="text-center py-5 px-3">
            <div class="mb-3">
                <span class="avatar avatar-lg bg-primary-lt text-primary rounded-circle">
                    <i class="ti ti-building-store fs-2"></i>
                </span>
            </div>
            <p class="text-muted mb-3">${lblEmpty}</p>
        </div>`;
}


function renderStorePricingHeader(store, { isFirst, targetId }) {
    const section = document.getElementById('storePricingSection');
    const lblRemove = section?.dataset.lblRemove || 'Remove';
    const collapsedClass = isFirst ? '' : 'collapsed';
    const showClass = isFirst ? 'show' : '';
    const expanded = isFirst ? 'true' : 'false';
    return {
        headerHtml: `
            <div class="d-flex align-items-stretch bg-body-tertiary">
                <h2 class="accordion-header flex-grow-1 m-0">
                    <button class="accordion-button w-100 ${collapsedClass}"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#${targetId}"
                            aria-expanded="${expanded}"
                            aria-controls="${targetId}">
                        <span class="avatar avatar-sm bg-primary-lt text-primary me-2 rounded">
                            <i class="ti ti-building-store"></i>
                        </span>
                        <span class="fw-medium text-truncate">${store.name}</span>
                    </button>
                </h2>
                <button type="button"
                        class="btn btn-icon btn-sm btn-ghost-danger remove-store-pricing align-self-center me-3"
                        title="${lblRemove}">
                    <i class="ti ti-trash fs-2"></i>
                </button>
            </div>`,
        showClass,
    };
}

// Function to initialize the form in edit mode
function initializeEditMode() {
    if (!window.productData) return;

    // Set product type
    const productTypeSelect = document.getElementById('productType');
    if (productTypeSelect && window.productData.type) {
        productTypeSelect.value = window.productData.type;
        toggleProductVariantSection();
    }

    // Initialize variants if product type is 'variant'
    if (window.productData.type == 'variant' && window.productData.variants) {
        initializeVariantAttributes();

        // Fetch and initialize store pricing
        if (window.productData.product && window.productData.product.id) {
            fetchProductPricing(window.productData.product.id);
        }
    }
    // Initialize simple product fields if product type is 'simple'
    else if (['simple', 'kit'].includes(window.productData.type) && window.productData.variant) {

        // Fetch and initialize store pricing
        if (window.productData.product && window.productData.product.id) {
            fetchProductPricing(window.productData.product.id);
        }
    }
}


// Function to initialize variant attributes in edit mode
function initializeVariantAttributes() {
    if (document.getElementById('productVariantBuilder')) return;
    if (!window.productData || !window.productData.variants) return;

    // Extract unique attributes from variants
    const variantAttributes = new Map();

    window.productData.variants.forEach(variant => {
        if (variant.attributes) {
            variant.attributes.forEach(attr => {
                if (!variantAttributes.has(attr.global_attribute_id)) {
                    variantAttributes.set(attr.global_attribute_id, new Set());
                }
                variantAttributes.get(attr.global_attribute_id).add(attr.global_attribute_value_id);
            });
        }
    });

    // Add attributes to the form
    variantAttributes.forEach((values, attrId) => {
        // Find attribute in dbAttributes
        let attrKey = null;
        for (const key in dbAttributes) {
            if (dbAttributes[key].id == attrId) {
                attrKey = key;
                break;
            }
        }

        if (attrKey) {
            // Add attribute to form
            const id = `attr_${++attributeCounter}`;
            const html = `
                <div class="card mb-3" data-id="${id}">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Attribute</label>
                                <select class="form-select attr-select" onchange="loadValues('${id}', this.value)">
                                    <option value="">Select Attribute</option>
                                    ${Object.keys(dbAttributes).map(key =>
                `<option value="${key}" ${key == attrKey ? 'selected' : ''}>${dbAttributes[key].name}</option>`
            ).join('')}
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">Values</label>
                                <select class="form-select attribute-value-select" multiple size="4" data-values="${id}">
                                    <option disabled>Select attribute first</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-outline-danger me-2 p-1 delete-attribute">
                                    <i class="ti ti-trash fs-2"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('attributesContainer').insertAdjacentHTML('beforeend', html);

            // Load values for this attribute
            loadValues(id, attrKey);

            // Select the values
            setTimeout(() => {
                const select = document.querySelector(`[data-values="${id}"]`);
                if (select && select.tomselect) {
                    const valueIds = Array.from(values).map(v => v.toString());
                    select.tomselect.setValue(valueIds);
                }
            }, 100);
        }
    });

    // Generate variants
    setTimeout(() => {
        generateVariants();

        // Update variant details from productData
        if (window.productData.variants) {
            window.productData.variants.forEach(serverVariant => {

                // Find matching variant in our local variants array
                const matchingVariant = variants.find(v => {
                    // Check if attributes match
                    if (!serverVariant.attributes || !v.attributes) return false;

                    // Convert server variant attributes to the same format as local variants
                    const serverAttrs = {};
                    serverVariant.attributes.forEach(attr => {
                        serverAttrs[attr.global_attribute_id] = attr.global_attribute_value_id;
                    });
                    // Check if all attributes match
                    for (const attrId in v.attributes) {
                        if (serverAttrs[attrId] != v.attributes[attrId]) {
                            return false;
                        }
                    }
                    return true;
                });

                if (matchingVariant) {
                    // Update variant details
                    matchingVariant.db_id = serverVariant.id || null;
                    matchingVariant.title = serverVariant.title || '';
                    matchingVariant.weight = serverVariant.weight || '';
                    matchingVariant.height = serverVariant.height || '';
                    matchingVariant.breadth = serverVariant.breadth || '';
                    matchingVariant.length = serverVariant.length || '';
                    matchingVariant.image = serverVariant.image || '';
                    matchingVariant.availability = serverVariant.availability || '';
                    matchingVariant.barcode = serverVariant.barcode || '';
                    matchingVariant.is_default = serverVariant.is_default || '';
                }
            });

            // Re-render variants
            renderVariants();
        }
    }, 500);
}

// Initialize edit mode if needed
document.addEventListener('DOMContentLoaded', function () {
    if (window.productData) {
        initializeEditMode();
    }
});

// Event listeners
const productType = document.getElementById('productType');
productType?.addEventListener('change', function () {
    toggleProductVariantSection();
});
toggleProductVariantSection()

function toggleProductVariantSection() {
    let value = productType?.value
    const isVariant = value == 'variant';

    // Update pricing containers based on a product type. The legacy
    // #simple/variantPricingContainer divs were removed in the variant-pricing
    // refactor (seller-product-inventory.js owns the new UI) — guard with `?.`
    // so this function stays harmless on the new form.
    if (value) {
        document.getElementById('variationsSection')?.classList.toggle('d-none', !isVariant);
        document.getElementById('simpleProductSection')?.classList.toggle('d-none', isVariant);
        document.getElementById('simplePricingContainer')?.classList.toggle('d-none', isVariant);
        document.getElementById('variantPricingContainer')?.classList.toggle('d-none', !isVariant);

        // Only initialize pricing if we're not in edit mode or if pricing data is already loaded
        if (!window.productData || productPricing) {
            // Initialize the appropriate pricing container
            if (isVariant) {
                initializeVariantPricing();
            } else {
                initializeSimplePricing();
            }
        }
    } else {
        // Hide all containers if no product type is selected
        document.getElementById('simplePricingContainer')?.classList.add('d-none');
        document.getElementById('variantPricingContainer')?.classList.add('d-none');
    }
}

document.getElementById('addAttributeBtn')?.addEventListener('click', () => addAttribute());
document.getElementById('generateVariantsBtn')?.addEventListener('click', () => generateVariants());
document.getElementById('addRemovedVariantBtn')?.addEventListener('click', () => showRemovedVariantsModal());
document.getElementById('removeAllVariantsBtn')?.addEventListener('click', () => removeAllVariants());

document.getElementById('addStorePricingBtn')?.addEventListener('click', () => populateAddStoreDropdown());
document.getElementById('addStorePricingSearch')?.addEventListener('input', () => populateAddStoreDropdown());
document.getElementById('addStorePricingSearch')?.addEventListener('click', (e) => e.stopPropagation());
document.getElementById('addStorePricingList')?.addEventListener('click', function (e) {
    const btn = e.target.closest('.add-store-pricing-item');
    if (!btn) return;
    e.preventDefault();
    const storeId = String(btn.dataset.storeId);
    if (!storeId) return;
    selectedStoreIds.add(storeId);
    rerenderStorePricing();
    populateAddStoreDropdown();
});

function addAttribute() {
    const id = `attr_${++attributeCounter}`;
    const html = `
                <div class="card mb-3" data-id="${id}">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Attribute</label>
                                <select class="form-select attr-select" onchange="loadValues('${id}', this.value)">
                                    <option value="">Select Attribute</option>
                                    ${Object.keys(dbAttributes).map(key => `<option value="${key}">${dbAttributes[key].name}</option>`).join('')}
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">Values</label>
                                <select class="form-select attribute-value-select" multiple size="4" data-values="${id}">
                                    <option disabled>Select attribute first</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-outline-danger me-2 p-1 delete-attribute">
                                    <i class="ti ti-trash fs-2"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
    document.getElementById('attributesContainer').insertAdjacentHTML('beforeend', html);
    updateGenerateButton();
    updateAttributeOptions();
}

// });
document.getElementById('attributesContainer')?.addEventListener('click', function (e) {
    if (e.target.closest('.delete-attribute')) {
        const card = e.target.closest('[data-id]');
        if (card) {
            removeAttribute(card.getAttribute('data-id'));
        }
    }
});

function removeAttribute(id) {
    document.querySelector(`[data-id="${id}"]`).remove();
    generateVariants()
    updateGenerateButton();
    updateAttributeOptions();
}

function updateGenerateButton() {
    const attrs = getAttributes();
    document.getElementById('generateVariantsBtn').disabled = !attrs.length || !attrs.every(a => a.values.length);
}

function getAttributes() {
    return Array.from(document.querySelectorAll('#attributesContainer .card')).map(card => {
        const attrKey = card.querySelector('.attr-select').value;
        if (!attrKey) return null;
        const attr = dbAttributes[attrKey];
        const values = Array.from(card.querySelector('[data-values]').selectedOptions)
            .map(opt => parseInt(opt.value)); // value will be the value ID
        return attr && values.length ? {id: attr.id, key: attrKey, values} : null;
    }).filter(Boolean);
}

function generateCombinations(attrs) {
    return attrs.reduce((acc, attr) => acc.flatMap(combo => attr.values.map(val => ({
        ...combo,
        [attr.id]: val // attr.id is attribute ID, val is value ID
    }))), [{}]);
}

function generateSKU(attrs) {
    // attrs is an object like { 1: 101, 2: 201 } (attributeId: valueId)
    return 'PRD-' + Object.entries(attrs).map(([attrId, valueId]) => {
        // Find attribute key by ID
        let attrKey = Object.keys(dbAttributes).find(key => dbAttributes[key].id == attrId);
        let attr = dbAttributes[attrKey];
        let value = attr.values.find(val => val.id == valueId);
        // Use the first 2 letters of name or value as fallback
        return (attr?.name?.substring(0, 2).toUpperCase() || attrId) +
            (value?.name?.substring(0, 2).toUpperCase() || valueId);
    }).join('-');
}

const attrIdMap = {};

function renderVariants() {
    Object.keys(dbAttributes).forEach(attrKey => {
        const attr = dbAttributes[attrKey];
        attrIdMap[attr.id] = {
            name: attr.name,
            values: Object.fromEntries(attr.values.map(v => [v.id, v.name]))
        };
    });
    document.getElementById('variantsList').innerHTML = variants.map(v =>
        `<div class="col-md-6" data-id="${v.id}" data-db-id="${v.db_id || ''}">
        <div class="card border h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="card-title mb-0">${v.db_id ? `<span class="badge">#${v.db_id}</span>` : ''}
                        ${Object.entries(v.attributes).map(([attrId, valueId]) => {
            const attr = attrIdMap[attrId];
            const attrName = attr ? attr.name : attrId;
            const valueName = attr && attr.values[valueId] ? attr.values[valueId] : valueId;
            const options = ["bg-primary-lt", "bg-teal-lt", "bg-warning-lt"];
            const randomIndex = Math.floor(Math.random() * options.length);
            return `<span class="badge ${options[randomIndex]} me-1">${attrName}: ${valueName}</span>`;
        }).join('')}
                    </h6>
                    <button type="button" class="btn btn-outline-danger btn-sm p-1" onclick="removeVariant('${v.id}')">
                        <i class="ti ti-trash fs-2"></i>
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" min="0" value="${v.title}" onchange="updateVariant('${v.id}', 'title', this.value)">
                    </div>
                    <div class="col-12">
                            <label class="form-label">Variant Image</label>
                            <input type="file" name="variant_image${v.id}" class="form-control variant-image-input" data-image-url="${v.image || ''}" accept="image/*" onchange="updateVariant('${v.id}', 'variant_image', this.value)">
                        </div>
                    <div class="col-6">
                        <label class="form-label">Weight (kg)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" min="0" value="${v.weight}" onchange="updateVariant('${v.id}', 'weight', this.value)">
                            <span class="input-group-text">kg</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Height (cm)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" min="0" value="${v.height}" onchange="updateVariant('${v.id}', 'height', this.value)">
                            <span class="input-group-text">cm</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Breadth (cm)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" min="0" value="${v.breadth}" onchange="updateVariant('${v.id}', 'breadth', this.value)">
                            <span class="input-group-text">cm</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Length (cm)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" min="0" value="${v.length}" onchange="updateVariant('${v.id}', 'length', this.value)">
                            <span class="input-group-text">cm</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Availability</label>
                        <select class="form-select" onchange="updateVariant('${v.id}', 'availability', this.value)">
                            <option value="" ${v.availability == '' ? 'selected' : ''}>Select</option>
                            <option value="yes" ${v.availability == 1 ? 'selected' : ''}>Yes</option>
                            <option value="no" ${v.availability == 0 ? 'selected' : ''}>No</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Barcode</label>
                        <input type="text" class="form-control" value="${v.barcode}" onchange="updateVariant('${v.id}', 'barcode', this.value)">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" name='is_defaults' type="radio" id="flexRadioDefault${v.id}" onchange="updateVariant('${v.id}', 'is_default', this.value)" ${v.is_default == true ? 'checked' : ''}>
                            <label class="form-check-label" for="flexRadioDefault${v.id}">Set as Default</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
`,
    ).join('');
    // Initialize FilePond for all variant image inputs
    document.querySelectorAll('.variant-image-input').forEach(input => {
        const inputName = input.getAttribute('name');
        initializeFilePond(inputName, ['image/*'], '2MB');
    });
}

function initializeFilePond(inputName, allowFileTypes = ['image/*'], maxFileSize = null) {
    const input = document.querySelector(`[name="${inputName}"]`);
    if (!input) return;

    const imageUrl = input.getAttribute('data-image-url') || '';
    FilePond.create(input, {
        allowImagePreview: true,
        credits: false,
        storeAsFile: true,
        maxFileSize: maxFileSize,
        acceptedFileTypes: allowFileTypes,
        files: imageUrl ? [{
            source: imageUrl,
            options: {type: 'remote'}
        }] : []
    });
}

function updateVariant(id, field, value) {
    const variant = variants.find(v => v.id == id);
    if (variant) variant[field] = value;
}

function removeVariant(id) {
    const index = variants.findIndex(v => v.id == id);
    if (index > -1) {
        removedVariants.push(variants.splice(index, 1)[0]);
        document.querySelector(`div[data-id="${id}"]`).remove();
        document.getElementById('addRemovedVariantBtn').disabled = false;
        updateVariantPricing();
    }
}

function updateAttributeOptions() {
    // Get all currently selected attributes
    const selectedAttributes = Array.from(document.querySelectorAll('.attr-select'))
        .map(select => select.value)
        .filter(value => value);

    // Update all attribute selects to disable already selected options
    document.querySelectorAll('.attr-select').forEach(select => {
        const currentValue = select.value;
        select.innerHTML = `
                    <option value="">Select Attribute</option>
                    ${Object.keys(dbAttributes).map(attr => {
            const isDisabled = selectedAttributes.includes(attr) && attr != currentValue;
            return `<option value="${attr}" ${isDisabled ? 'disabled' : ''} ${attr == currentValue ? 'selected' : ''}>${attr}</option>`;
        }).join('')}
                `;
    });
}

function removeAllVariants() {
    Swal.fire({
        title: "Are you sure?",
        html: 'You are about to remove all variants. You can add them back from the removed variants section.',
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, Remove All!"
    }).then((result) => {
        if (result.isConfirmed) {
            removedVariants.push(...variants);
            variants = [];
            document.getElementById('variantsList').innerHTML = '';
            document.getElementById('addRemovedVariantBtn').disabled = false;

            // Update store pricing UI for variants
            updateVariantPricing();
        }
    });
}

function showRemovedVariantsModal() {
    console.log(removedVariants);
    document.getElementById('removedVariantsList').innerHTML = removedVariants.map(v => `
    <div class="d-flex justify-content-between align-items-center p-2 border rounded mb-2">
      <div>
        <strong>
          ${Object.entries(v.attributes).map(([attrId, valueId]) => {
        const attr = attrIdMap[attrId];
        const attrName = attr ? attr.name : attrId;
        const valueName = attr && attr.values[valueId] ? attr.values[valueId] : valueId;
        return `${attrName}: ${valueName}`;
    }).join(', ')}
        </strong><br>
      </div>
      <button type="button" class="btn btn-success btn-sm" onclick="restoreVariant('${v.id}')">
        <i class="fas fa-plus me-1"></i>Add Back
      </button>
    </div>
    `).join('');

    const modalEl = document.getElementById('addRemovedVariantModal');

    if (removedVariants.length == 0) {
        $('#addRemovedVariantModal').modal('hide')
    } else if (!modalEl.classList.contains('show')) {
        $('#addRemovedVariantModal').modal('show')
    }
}

function restoreVariant(id) {
    const index = removedVariants.findIndex(v => v.id == id);
    if (index > -1) {
        variants.push(removedVariants.splice(index, 1)[0]);
        renderVariants();
        document.getElementById('addRemovedVariantBtn').disabled = !removedVariants.length;

        // Update store pricing UI for variants
        updateVariantPricing();
        showRemovedVariantsModal(); // Refresh modal
    }
}

function loadValues(id, attrName) {
    const select = document.querySelector(`[data-values="${id}"]`);
    if (!attrName) {
        select.innerHTML = '<option disabled>Select attribute first</option>';
        updateGenerateButton();
        updateAttributeOptions();
        return;
    }

    select.innerHTML = dbAttributes[attrName].values.map(val => `<option value="${val.id}">${val.name}</option>`).join('');
    select.onchange = updateGenerateButton;
    updateGenerateButton();
    updateAttributeOptions();
    // Initialize TomSelect only if not already initialized
    if (!select.tomselect) {
        new TomSelect(select, {
            create: false
        });
    } else {
        // If already initialized, refresh options
        select.tomselect.clearOptions();
        dbAttributes[attrName].values.forEach(val => {
            select.tomselect.addOption({value: val.id, text: val.name});
        });
        select.tomselect.refreshOptions(false);
    }
}

// Store pricing functions
let stores = [];

// Fetch stores from the server
let cachedStores = null; // Store cached result
let storesPromise = null; // Store the fetch promise for concurrent calls

function fetchStores() {
    // If we already have the stores cached, return them as a resolved Promise
    if (cachedStores != null) {
        return Promise.resolve(cachedStores);
    }
    // If a fetch is already in progress, return the same promise
    if (storesPromise != null) {
        return storesPromise;
    }
    // Admin acts on behalf of a seller — pass the picked seller_id so the endpoint
    // returns only that seller's stores.
    let storesUrl = `${base_url}/${panel}/stores/list`;
    if (panel === 'admin') {
        const adminSellerEl = document.getElementById('adminSellerPicker');
        const sellerId = adminSellerEl?.value;
        if (sellerId) {
            storesUrl += `?seller_id=${encodeURIComponent(sellerId)}`;
        } else {
            // No seller picked yet — don't fetch.
            cachedStores = [];
            return Promise.resolve(cachedStores);
        }
    }
    storesPromise = axios.get(storesUrl)
        .then(response => {
            cachedStores = response.data.data;
            storesPromise = null; // Reset promise after completion
            return cachedStores;
        })
        .catch(error => {
            console.error('Error fetching stores:', error);
            storesPromise = null; // Reset promise on error
            return [];
        });
    return storesPromise;
}

// --- Admin seller picker (option 2: admin acts on behalf of seller via in-form picker) ---
function initAdminSellerPicker() {
    const sellerEl = document.getElementById('adminSellerPicker');
    if (!sellerEl || !window.TomSelect || sellerEl.tomselect) return;

    new TomSelect(sellerEl, {
        copyClassesToDropdown: false,
        dropdownParent: 'body',
        controlInput: '<input>',
        valueField: 'value',
        labelField: 'text',
        searchField: 'text',
        placeholder: sellerEl.getAttribute('placeholder') || 'Select Seller',
        load: function (query, callback) {
            const url = `${base_url}/admin/sellers/search?search=${encodeURIComponent(query)}`;
            fetch(url)
                .then(r => r.json())
                .then(json => callback(json))
                .catch(() => callback());
        },
    });

    sellerEl.addEventListener('change', function () {
        // Read from TomSelect first — the underlying <select>.value isn't
        // always updated before `change` fires when TomSelect uses an ajax
        // load+addOption flow.
        const newSellerId = (sellerEl.tomselect && sellerEl.tomselect.getValue())
            || sellerEl.value
            || '';

        // Keep the underlying select in sync so anything reading sellerEl.value
        // (loadStoresWithLocations, attributes endpoint, hidden seller_id input
        // submitted with the form) gets the right value.
        if (sellerEl.value !== newSellerId) {
            sellerEl.value = newSellerId;
        }

        // Reflect the picked seller in the URL so a refresh keeps it, and so
        // any server-side code reading request()->query('seller_id') sees it.
        // Pure history mutation — no reload.
        try {
            const url = new URL(window.location.href);
            if (newSellerId) {
                url.searchParams.set('seller_id', newSellerId);
            } else {
                url.searchParams.delete('seller_id');
            }
            window.history.replaceState({}, '', url.toString());
        } catch (e) { /* older browsers — ignore */ }

        // The previous seller's stores and prefilled pricing no longer apply.
        cachedStores = null;
        storesPromise = null;
        productPricing = null;
        if (typeof selectedStoreIds !== 'undefined' && selectedStoreIds && typeof selectedStoreIds.clear === 'function') {
            selectedStoreIds.clear();
        }

        // Re-render simple/variant pricing so the empty state replaces the old rows.
        if (typeof rerenderStorePricing === 'function') {
            rerenderStorePricing();
        } else {
            // Fallback if rerender isn't yet defined (form mounted out of order).
            document.getElementById('simplePricingContainer') && (document.getElementById('simplePricingContainer').innerHTML = '');
            document.getElementById('variantPricingContainer') && (document.getElementById('variantPricingContainer').innerHTML = '');
        }

        // Refresh the "Add Store" dropdown so it reflects the new seller's stores.
        if (typeof populateAddStoreDropdown === 'function') {
            populateAddStoreDropdown();
        }

        // Reload attributes for the newly selected seller.
        if (!document.getElementById('productVariantBuilder') && newSellerId) {
            axios.get(`${base_url}/admin/products/attributes-by-seller`, {
                params: { seller_id: newSellerId }
            }).then(function (response) {
                dbAttributes = response.data || {};
                // Clear existing attribute cards and variant state.
                const container = document.getElementById('attributesContainer');
                if (container) container.innerHTML = '';
                variants = [];
                removedVariants = [];
                attributeCounter = 0;
                const variantsList = document.getElementById('variantsList');
                if (variantsList) variantsList.innerHTML = '';
                if (typeof updateGenerateButton === 'function') updateGenerateButton();
            }).catch(function () {
                dbAttributes = {};
            });
        } else if (!document.getElementById('productVariantBuilder')) {
            dbAttributes = {};
            const container = document.getElementById('attributesContainer');
            if (container) container.innerHTML = '';
        }

        const alert = document.getElementById('adminSellerChangeAlert');
        if (alert && newSellerId) alert.classList.remove('d-none');

        // Unlock the rest of the form once a seller is picked. The blade renders
        // the lock screen + d-none on the form shell server-side based on the
        // initial $selectedSellerId; flip them client-side so the admin doesn't
        // have to reload after picking.
        const shell = document.getElementById('productFormShell');
        const locked = document.querySelector('.product-form-locked-empty');
        if (newSellerId) {
            shell?.classList.remove('d-none');
            locked?.classList.add('d-none');
            // New seller picked — let the inventory module repopulate stores +
            // locations for this seller and re-render the pricing accordion.
            if (typeof window.vinvReloadStores === 'function') {
                window.vinvReloadStores();
            }
            // Tax-class options are market-filtered per seller. Rebuild the
            // TomSelect's options from the new seller's markets/tax classes.
            reloadTaxClassesForSeller(newSellerId);
        } else {
            shell?.classList.add('d-none');
            locked?.classList.remove('d-none');
        }
    });
}

/**
 * Replace the #select-tax-group TomSelect's options with the new seller's
 * tax classes (market-scoped). Server-rendered options belong to whichever
 * seller was set when the blade was rendered; on seller change we drop them
 * and refetch.
 *
 * Also rebinds the TomSelect's typeahead `load` so that when the admin types
 * in the search box, results stay scoped to the picked seller's markets.
 * Without this override TomSelect would call /admin/tax-classes/search, which
 * is unscoped and would leak tax classes from markets the seller isn't in.
 */
function reloadTaxClassesForSeller(sellerId) {
    const sel = document.getElementById('select-tax-group');
    const ts = sel?.tomselect;
    if (!ts) return;

    ts.clear(true);
    ts.clearOptions();

    // Swap the typeahead source to the seller-scoped endpoint. `load` runs
    // when the user types — we already pre-populate the full list below, but
    // TomSelect still hits this on focus / when its cache is empty.
    ts.settings.load = function (query, callback) {
        const sid = (document.getElementById('adminSellerPicker')?.tomselect?.getValue())
            || document.getElementById('adminSellerPicker')?.value
            || '';
        if (!sid) return callback();
        axios.get(`${base_url}/admin/products/tax-classes-by-seller`, {
            params: { seller_id: sid, search: query || '' },
        }).then(r => callback(Array.isArray(r.data) ? r.data : []))
          .catch(() => callback());
    };
    // Force TomSelect to drop any cached load results from the previous seller.
    ts.loadedSearches = {};
    ts.clearCache?.();

    if (!sellerId) {
        ts.refreshOptions(false);
        return;
    }

    axios.get(`${base_url}/admin/products/tax-classes-by-seller`, {
        params: { seller_id: sellerId },
    }).then(function (response) {
        const opts = Array.isArray(response.data) ? response.data : [];
        opts.forEach(o => ts.addOption({...o, value: String(o.value)}));
        ts.refreshOptions(false);
        document.dispatchEvent(new CustomEvent('product:tax-options-changed'));
    }).catch(function () {
        // leave the dropdown empty on failure — better than showing stale options
    });
}

document.addEventListener('DOMContentLoaded', initAdminSellerPicker);

/**
 * On the admin product form, swap the tax-class TomSelect's typeahead load
 * to the seller-scoped endpoint immediately — before the admin touches the
 * seller picker. Without this, the first search on an edit page would hit
 * /admin/tax-classes/search (unscoped) and return tax classes from markets
 * the picked seller isn't even in.
 *
 * No-op on the seller panel and on non-product pages — only runs when both
 * the seller picker and the tax-class TomSelect are present.
 */
document.addEventListener('DOMContentLoaded', function () {
    const sellerEl = document.getElementById('adminSellerPicker');
    if (!sellerEl) return;
    // Defer one tick so custom.js has a chance to wrap #select-tax-group in TomSelect.
    setTimeout(function () {
        const sel = document.getElementById('select-tax-group');
        const ts = sel?.tomselect;
        if (!ts) return;
        ts.settings.load = function (query, callback) {
            const sid = (sellerEl.tomselect && sellerEl.tomselect.getValue())
                || sellerEl.value
                || new URL(window.location.href).searchParams.get('seller_id')
                || '';
            if (!sid) return callback();
            axios.get(`${base_url}/admin/products/tax-classes-by-seller`, {
                params: { seller_id: sid, search: query || '' },
            }).then(r => callback(Array.isArray(r.data) ? r.data : []))
              .catch(() => callback());
        };
        ts.loadedSearches = {};
        ts.clearCache?.();
    }, 0);
});

function generateVariants() {
    const attrs = getAttributes();
    const newCombinations = generateCombinations(attrs);
    removedVariants = [];
    // Create a map of existing variants by their attribute combination
    const existingVariants = new Map();
    variants.forEach(variant => {
        const key = JSON.stringify(variant.attributes);
        existingVariants.set(key, variant);
    });

    // Generate new variants, preserving existing data where possible
    variants = newCombinations.map((combo, i) => {
        const key = JSON.stringify(combo);
        const existing = existingVariants.get(key);

        if (existing) {
            // Keep existing variant with its data
            return existing;
        } else {
            // Create new variant
            return {
                id: `v_${Date.now()}_${i}`,
                db_id: null,
                attributes: combo,
                title: '',
                weight: '',
                height: '',
                breadth: '',
                length: '',
                availability: '',
                barcode: '',
                is_default: ''
            };
        }
    });

    renderVariants();
    document.getElementById('variantsContainer').classList.remove('d-none');

    // Update pricing UI for variants
    updateVariantPricing();
}

// Fetch product pricing data
function fetchProductPricing(productId) {
    return axios.get(`${base_url}/${panel}/products/${productId}/pricing`)
        .then(response => {
            if (response.data.success) {
                productPricing = response.data.data;
                seedSelectedStoresFromPricing();

                // Initialize pricing UI with the fetched data
                if (document.getElementById('productType').value == 'variant') {
                    updateVariantPricing();
                } else {
                    initializeSimplePricing();
                }

                return productPricing;
            }
            return null;
        })
        .catch(error => {
            console.error('Error fetching product pricing:', error);
            return null;
        });
}

// Initialize pricing for simple products
function initializeSimplePricing() {
    const container = document.getElementById('simplePricingContainer');
    // Legacy pricing UI was removed in the variant-pricing refactor —
    // seller-product-inventory.js owns it now. Bail out so this function
    // is a harmless no-op on the new form.
    if (!container) return;
    const section = document.getElementById('storePricingSection');
    const lblLoading = section?.dataset.lblLoading || 'Loading...';
    const lblPrice = section?.dataset.lblPrice || 'Price';
    const lblSpecialPrice = section?.dataset.lblSpecialPrice || 'Special Price';
    const lblCost = section?.dataset.lblCost || 'Cost';
    const lblStock = section?.dataset.lblStock || 'Stock';
    const lblSku = section?.dataset.lblSku || 'SKU';
    container.innerHTML = `<div class="text-center py-4"><div class="spinner-border" role="status"></div><p class="mb-0 mt-2 small text-muted">${lblLoading}</p></div>`;

    fetchStores().then(stores => {
        if (stores == null || stores.length == 0) {
            container.innerHTML = '<div class="alert alert-info m-3">No stores available for pricing.</div>';
            populateAddStoreDropdown();
            return;
        }

        const visibleStores = stores.filter(s => selectedStoreIds.has(String(s.id)));

        if (visibleStores.length === 0) {
            container.innerHTML = renderStorePricingEmptyState();
            populateAddStoreDropdown();
            return;
        }

        const accordionContainer = document.createElement('div');
        accordionContainer.className = 'accordion accordion-flush border m-2 rounded';
        accordionContainer.id = 'simplePricingAccordion';

        let html = '';
        visibleStores.forEach((store, index) => {
            let storePrice = '';
            let storeSpecialPrice = '';
            let storeCost = '';
            let storeStock = '';
            let storeSku = '';

            if (productPricing && productPricing.variant_pricing) {
                const variantId = window.productData && window.productData.variant ? window.productData.variant.id : null;
                if (variantId && productPricing.variant_pricing[variantId]) {
                    const storePricing = productPricing.variant_pricing[variantId].store_pricing.find(
                        sp => sp.store_id == store.id
                    );
                    if (storePricing) {
                        storePrice = storePricing.price || '';
                        storeSpecialPrice = storePricing.special_price || '';
                        storeCost = storePricing.cost || '';
                        storeStock = storePricing.stock || '';
                        storeSku = storePricing.sku || '';
                    }
                }
            }

            const targetId = `simple-store-${store.id}`;
            const { headerHtml, showClass } = renderStorePricingHeader(store, { isFirst: index === 0, targetId });

            html += `
                <div class="accordion-item store-pricing-card" data-store-id="${store.id}">
                    ${headerHtml}
                    <div id="${targetId}" class="accordion-collapse collapse ${showClass}" data-bs-parent="#simplePricingAccordion">
                        <div class="accordion-body p-3">
                            <div class="row g-3">
                                <div class="col-12 col-md-6 col-xl-4">
                                    <label class="form-label small text-muted mb-1">
                                        <i class="ti ti-currency-dollar me-1"></i>${lblPrice}
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">${currencySymbol}</span>
                                        <input type="number" class="form-control store-price" name="store_pricing[${store.id}][price]" step="0.01" min="0" value="${storePrice}" placeholder="0.00">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6 col-xl-4">
                                    <label class="form-label small text-muted mb-1">
                                        <i class="ti ti-discount me-1"></i>${lblSpecialPrice}
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">${currencySymbol}</span>
                                        <input type="number" class="form-control store-special-price" name="store_pricing[${store.id}][special_price]" step="0.01" min="0" value="${storeSpecialPrice}" placeholder="0.00">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6 col-xl-4">
                                    <label class="form-label small text-muted mb-1">
                                        <i class="ti ti-wallet me-1"></i>${lblCost}
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">${currencySymbol}</span>
                                        <input type="number" class="form-control store-cost" name="store_pricing[${store.id}][cost]" step="0.01" min="0" value="${storeCost}" placeholder="0.00">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6 col-xl-4">
                                    <label class="form-label small text-muted mb-1">
                                        <i class="ti ti-package me-1"></i>${lblStock}
                                    </label>
                                    <input type="number" class="form-control form-control-sm store-stock" name="store_pricing[${store.id}][stock]" min="0" value="${storeStock}" placeholder="0">
                                </div>
                                <div class="col-12 col-md-6 col-xl-8">
                                    <label class="form-label small text-muted mb-1">
                                        <i class="ti ti-barcode me-1"></i>${lblSku}
                                    </label>
                                    <input type="text" class="form-control form-control-sm store-sku" name="store_pricing[${store.id}][sku]" value="${storeSku}" placeholder="e.g. SKU-001">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        accordionContainer.innerHTML = html;
        container.innerHTML = '';
        container.appendChild(accordionContainer);

        accordionContainer.querySelectorAll('.remove-store-pricing').forEach(function (element) {
            element.addEventListener('click', function (e) {
                e.stopPropagation();
                const card = e.target.closest('.store-pricing-card');
                if (card && card.dataset.storeId) {
                    selectedStoreIds.delete(String(card.dataset.storeId));
                }
                rerenderStorePricing();
                populateAddStoreDropdown();
            });
        });

        populateAddStoreDropdown();
    });
}

// Initialize pricing for variant products
function initializeVariantPricing() {
    const container = document.getElementById('storePricingAccordion');
    // Legacy pricing UI was removed — see initializeSimplePricing comment.
    if (!container) return;
    container.innerHTML = '<div class="alert alert-info">Please generate variants first to set store-specific pricing.</div>';

    // If variants are already generated, update the pricing UI
    if (variants.length > 0) {
        updateVariantPricing();
    }
}

// Update pricing UI for variants
function updateVariantPricing() {
    const container = document.getElementById('storePricingAccordion');
    if (!container) return; // legacy UI removed — see initializeSimplePricing
    const section = document.getElementById('storePricingSection');
    const lblVariant = section?.dataset.lblVariant || 'Variant';
    const lblPrice = section?.dataset.lblPrice || 'Price';
    const lblSpecialPrice = section?.dataset.lblSpecialPrice || 'Special Price';
    const lblCost = section?.dataset.lblCost || 'Cost';
    const lblStock = section?.dataset.lblStock || 'Stock';
    const lblSku = section?.dataset.lblSku || 'SKU';
    fetchStores().then(stores => {
        if (stores == null || stores.length == 0 || variants.length == 0) {
            container.innerHTML = '<div class="alert alert-info m-3">No stores or variants available for pricing.</div>';
            populateAddStoreDropdown();
            return;
        }

        const visibleStores = stores.filter(s => selectedStoreIds.has(String(s.id)));

        if (visibleStores.length === 0) {
            container.innerHTML = renderStorePricingEmptyState();
            populateAddStoreDropdown();
            return;
        }

        let html = '';
        visibleStores.forEach((store, index) => {
            const targetId = `store-${store.id}`;
            const { headerHtml, showClass } = renderStorePricingHeader(store, { isFirst: index === 0, targetId });

            const rowsHtml = variants.map(variant => {
                const variantId = variant.id;
                let storePrice = '';
                let storeSpecialPrice = '';
                let storeCost = '';
                let storeStock = '';
                let storeSku = '';

                if (productPricing && productPricing.variant_pricing) {
                    if (productPricing.variant_pricing[variantId]) {
                        const serverVariant = productPricing.variant_pricing[variantId];
                        const storePricing = serverVariant.store_pricing.find(
                            sp => sp.store_id == store.id
                        );
                        if (storePricing) {
                            storePrice = storePricing.price || '';
                            storeSpecialPrice = storePricing.special_price || '';
                            storeCost = storePricing.cost || '';
                            storeStock = storePricing.stock || '';
                            storeSku = storePricing.sku || '';
                        }
                    } else {
                        const serverVariants = window.productData && window.productData.variants ? window.productData.variants : [];
                        const matchingServerVariant = serverVariants.find(sv => {
                            if (!sv.attributes || !variant.attributes) return false;
                            const serverAttrs = {};
                            sv.attributes.forEach(attr => {
                                serverAttrs[attr.global_attribute_id] = attr.global_attribute_value_id;
                            });
                            for (const attrId in variant.attributes) {
                                if (serverAttrs[attrId] != variant.attributes[attrId]) {
                                    return false;
                                }
                            }
                            return true;
                        });
                        if (matchingServerVariant && matchingServerVariant.id) {
                            const serverVariantId = matchingServerVariant.id;
                            if (productPricing.variant_pricing[serverVariantId]) {
                                const serverVariant = productPricing.variant_pricing[serverVariantId];
                                const storePricing = serverVariant.store_pricing.find(
                                    sp => sp.store_id == store.id
                                );
                                if (storePricing) {
                                    storePrice = storePricing.price || '';
                                    storeSpecialPrice = storePricing.special_price || '';
                                    storeCost = storePricing.cost || '';
                                    storeStock = storePricing.stock || '';
                                    storeSku = storePricing.sku || '';
                                }
                            }
                        }
                    }
                }

                const badgesHtml = Object.entries(variant.attributes).map(([attrId, valueId]) => {
                    const attr = attrIdMap[attrId];
                    const attrName = attr ? attr.name : attrId;
                    const valueName = attr && attr.values[valueId] ? attr.values[valueId] : valueId;
                    return `<span class="badge bg-primary-subtle text-primary me-1 mb-1">${attrName}: ${valueName}</span>`;
                }).join('');

                return `
                    <tr>
                        <td class="align-middle" style="min-width: 180px;">
                            <div class="d-flex flex-wrap">${badgesHtml}</div>
                        </td>
                        <td style="min-width: 130px;">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">${currencySymbol}</span>
                                <input type="number" class="form-control store-price" name="variant_pricing[${store.id}][${variantId}][price]" step="0.01" min="0" value="${storePrice}" placeholder="0.00">
                            </div>
                        </td>
                        <td style="min-width: 130px;">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">${currencySymbol}</span>
                                <input type="number" class="form-control store-special-price" name="variant_pricing[${store.id}][${variantId}][special_price]" step="0.01" min="0" value="${storeSpecialPrice}" placeholder="0.00">
                            </div>
                        </td>
                        <td style="min-width: 130px;">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">${currencySymbol}</span>
                                <input type="number" class="form-control store-cost" name="variant_pricing[${store.id}][${variantId}][cost]" step="0.01" min="0" value="${storeCost}" placeholder="0.00">
                            </div>
                        </td>
                        <td style="min-width: 100px;">
                            <input type="number" class="form-control form-control-sm store-stock" name="variant_pricing[${store.id}][${variantId}][stock]" min="0" value="${storeStock}" placeholder="0">
                        </td>
                        <td style="min-width: 140px;">
                            <input type="text" class="form-control form-control-sm store-sku" name="variant_pricing[${store.id}][${variantId}][sku]" value="${storeSku}" placeholder="SKU-001">
                        </td>
                    </tr>
                `;
            }).join('');

            html += `
                <div class="accordion-item store-pricing-card" data-store-id="${store.id}">
                    ${headerHtml}
                    <div id="${targetId}" class="accordion-collapse collapse ${showClass}" data-bs-parent="#storePricingAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr class="text-muted small text-uppercase">
                                            <th class="ps-3"><i class="ti ti-tags me-1"></i>${lblVariant}</th>
                                            <th><i class="ti ti-currency-dollar me-1"></i>${lblPrice}</th>
                                            <th><i class="ti ti-discount me-1"></i>${lblSpecialPrice}</th>
                                            <th><i class="ti ti-wallet me-1"></i>${lblCost}</th>
                                            <th><i class="ti ti-package me-1"></i>${lblStock}</th>
                                            <th class="pe-3"><i class="ti ti-barcode me-1"></i>${lblSku}</th>
                                        </tr>
                                    </thead>
                                    <tbody>${rowsHtml}</tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
        container.querySelectorAll('.remove-store-pricing').forEach(function (element) {
            element.addEventListener('click', function (e) {
                e.stopPropagation();
                const card = e.target.closest('.store-pricing-card');
                if (card && card.dataset.storeId) {
                    selectedStoreIds.delete(String(card.dataset.storeId));
                }
                rerenderStorePricing();
                populateAddStoreDropdown();
            });
        });

        populateAddStoreDropdown();
    });
}


function addVariantInputsToForm() {
    document.querySelectorAll('.variant-hidden-input').forEach(el => el.remove());
    const form = document.querySelector('#product-form-submit');
    if (!form) return;

    // Create a simplified variants array
    const simplifiedVariants = variants.map(variant => {
        // Create a new variant object with a simpler structure
        const newVariant = {
            id: variant.id,
            title: variant.title || '',
            weight: variant.weight || '',
            breadth: variant.breadth || '',
            length: variant.length || '',
            height: variant.height || '',
            availability: variant.availability || '',
            barcode: variant.barcode || '',
            is_default: variant.is_default || '',
            attributes: []
        };

        // Add attributes in a simpler format
        Object.entries(variant.attributes).forEach(([attrId, valueId]) => {
            newVariant.attributes.push({
                attribute_id: attrId,
                value_id: valueId
            });
        });

        return newVariant;
    });

    // Add the simplified variants as a single JSON string
    const input = document.createElement('input');
    input.type = 'hidden';
    input.className = 'variant-hidden-input';
    input.name = 'variants_json';
    input.value = JSON.stringify(simplifiedVariants);
    form.appendChild(input);
}

// Function to restructure form data into a simpler format
function restructureFormData(originalFormData) {
    // Create a new FormData object
    const newFormData = new FormData();

    // Extract and restructure pricing data
    const storePricing = [];
    const variantPricing = [];

    // Temporary storage for collecting all fields for each store/variant
    const storePricingTemp = {};
    const variantPricingTemp = {};

    // Process all form fields
    for (let [key, value] of originalFormData.entries()) {
        // Handle store pricing for simple products
        if (key.startsWith('store_pricing[')) {
            // Extract store ID and field name from the key
            // Format: store_pricing[storeId][fieldName]
            const matches = key.match(/store_pricing\[(\d+)\]\[([^\]]+)\]/);
            if (matches) {
                const storeId = matches[1];
                const field = matches[2];

                if (!storePricingTemp[storeId]) {
                    storePricingTemp[storeId] = {store_id: storeId};
                }
                storePricingTemp[storeId][field] = value;
            }
        }
        // Handle variant pricing
        else if (key.startsWith('variant_pricing[')) {
            // Extract store ID, variant ID, and field name from the key
            // Format: variant_pricing[storeId][variantId][fieldName]
            const matches = key.match(/variant_pricing\[(\d+)\]\[([^\]]+)\]\[([^\]]+)\]/);
            if (matches) {
                const storeId = matches[1];
                const variantId = matches[2];
                const field = matches[3];

                const key = `${storeId}_${variantId}`;
                if (!variantPricingTemp[key]) {
                    variantPricingTemp[key] = {
                        store_id: storeId,
                        variant_id: variantId
                    };
                }
                variantPricingTemp[key][field] = value;
            }
        }
        // Pass through all other fields unchanged
        else {
            newFormData.append(key, value);
        }
    }

    // Convert temporary objects to arrays
    for (const storeId in storePricingTemp) {
        storePricing.push(storePricingTemp[storeId]);
    }

    for (const key in variantPricingTemp) {
        variantPricing.push(variantPricingTemp[key]);
    }

    // Add restructured data to the new FormData
    newFormData.append('pricing', JSON.stringify({
        store_pricing: storePricing,
        variant_pricing: variantPricing
    }));

    return newFormData;
}

let productForm = document.getElementById('product-form-submit');
productForm?.addEventListener('submit', function (e) {
    if (e.defaultPrevented || productForm.dataset.productSubmitting === '1') return;
    e.preventDefault();
    productForm.dataset.productSubmitting = '1';
    addVariantInputsToForm();

    const action = productForm.getAttribute('action');
    const originalFormData = new FormData(productForm);
    const submitButton = productForm.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    const originalButtonContent = submitButton.innerHTML;
    submitButton.innerHTML = `<div class="spinner-border text-white me-2" role="status"><span class="visually-hidden">Loading...</span></div> ${originalButtonContent}`;


    // Restructure form data
    const formData = restructureFormData(originalFormData);

    // Prepare headers
    const headers = {
        'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'
    };

    // Prepare axios config
    const config = {
        method: 'POST', url: action, headers: headers
    };
    config.data = formData;

    axios(config)
        .then(function (response) {
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonContent;
            let data = response.data;
            if (data.success == false) {
                return Toast.fire({
                    icon: "error", title: data.message
                });
            }
            clearValidationErrors(productForm);
            setTimeout(function () {
                location.reload();
            }, 3000);
            return Toast.fire({
                icon: "success", title: data.message
            });
            // Handle success UI update here
        })
        .catch(function (error) {
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonContent;

            if (error.response && error.response.status == 422) {
                // Handle validation errors
                const validationErrors = error.response.data.data || error.response.data.errors;
                if (validationErrors) {
                    displayValidationErrors(productForm, validationErrors);

                    // Show toast with first error or generic message
                    const firstErrorMessage = error.response.data.message ||
                        Object.values(validationErrors).flat()[0] ||
                        "Validation failed";

                    return Toast.fire({
                        icon: "error",
                        title: firstErrorMessage
                    });
                }
            }

            if (error.response && error.response.data && error.response.data.message) {
                return Toast.fire({
                    icon: "error", title: error.response.data.message
                });
            } else {
                console.error('Error:', error);
                return Toast.fire({
                    icon: "error", title: "An error occurred while submitting the form."
                });
            }
        })
        .finally(function () {
            delete productForm.dataset.productSubmitting;
        });
});

try {
    new TomSelect('.product-tags', {
        create: true
    });
} catch (e) {
    // console.error(e);
}


const videoTypeSelect = document.getElementById('videoType');
const videoLinkDiv = document.querySelector('input[name="video_link"]')?.closest('.mb-3');
const videoUploadDiv = document.querySelector('input[name="product_video"]')?.closest('.mb-3');


function toggleVideoFields() {
    const selectedType = videoTypeSelect != null ? videoTypeSelect.value.toLowerCase() : "";
    if (videoLinkDiv != null && videoUploadDiv != null && videoLinkDiv != undefined && videoUploadDiv != undefined) {
        if (selectedType == 'self_hosted') {
            videoLinkDiv.style.display = 'none';
            videoUploadDiv.style.display = 'block';
        } else if (selectedType) {
            videoLinkDiv.style.display = 'block';
            videoUploadDiv.style.display = 'none';
        } else {
            // If no type is selected, hide both
            videoLinkDiv.style.display = 'none';
            videoUploadDiv.style.display = 'none';
        }
    }
}

// Initial toggle on a load
toggleVideoFields();

// Add event listener on change
videoTypeSelect?.addEventListener('change', toggleVideoFields);
$(document).ready(function () {
    // ----- Custom Fields (dynamic key-value) -----
    (function initCustomFields() {
        const container = document.getElementById('customFieldsContainer');
        const addBtn = document.getElementById('addCustomFieldBtn');
        const toggleQuickAdd = document.getElementById('toggleCustomFieldsQuickAdd');
        const quickAdd = document.getElementById('customFieldsQuickAdd');
        const bulkInput = document.getElementById('customFieldsBulkInput');
        const applyBulk = document.getElementById('applyCustomFieldsBulk');
        if (!container || !addBtn) return;

        function createRow(key = '', value = '') {
            const row = document.createElement('div');
            row.className = 'custom-field-row';

            const keyInput = document.createElement('input');
            keyInput.type = 'text';
            keyInput.className = 'form-control';
            keyInput.dataset.customFieldKey = '1';
            keyInput.placeholder = 'Field name (e.g., color)';
            keyInput.value = key;

            const valueInput = document.createElement('input');
            valueInput.type = 'text';
            valueInput.className = 'form-control';
            valueInput.dataset.customFieldValue = '1';
            valueInput.placeholder = 'Value (e.g., red)';
            valueInput.value = value;

            // Update name attributes based on current key
            function syncNames() {
                const k = keyInput.value.trim();
                // Default temp name so field is still submitted
                const safe = k || `__custom_${Date.now()}_${Math.floor(Math.random()*9999)}__`;
                valueInput.name = `custom_fields[${safe}]`;
            }

            keyInput.addEventListener('input', syncNames);
            // Initialize name
            syncNames();

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-outline-danger';
            removeBtn.innerHTML = '<i class="ti ti-x"></i>';
            removeBtn.addEventListener('click', () => row.remove());

            row.appendChild(keyInput);
            row.appendChild(valueInput);
            row.appendChild(removeBtn);
            return row;
        }

        // Preload existing fields from data attribute (object map)
        try {
            const existingJson = container.getAttribute('data-existing');
            if (existingJson) {
                const existing = JSON.parse(existingJson || '{}') || {};
                Object.keys(existing).forEach(k => {
                    container.appendChild(createRow(k, existing[k]));
                });
            }
        } catch (e) {
            console.error('Error parsing existing custom fields:', e);
        }

        addBtn.addEventListener('click', function () {
            container.appendChild(createRow());
        });

        toggleQuickAdd?.addEventListener('click', function () {
            quickAdd?.classList.toggle('d-none');
            if (!quickAdd?.classList.contains('d-none')) bulkInput?.focus();
        });

        applyBulk?.addEventListener('click', function () {
            const parsed = String(bulkInput?.value || '')
                .split(/\r?\n/)
                .map(line => {
                    const match = line.match(/^\s*([^:=\t]+?)\s*(?::|=|\t)\s*(.+?)\s*$/);
                    return match ? {key: match[1].trim(), value: match[2].trim()} : null;
                })
                .filter(field => field?.key && field?.value);

            if (!parsed.length) {
                Toast.fire({icon: 'warning', title: quickAdd?.dataset.emptyMessage || ''});
                return;
            }

            parsed.forEach(field => {
                const existingRow = Array.from(container.querySelectorAll('.custom-field-row')).find(row =>
                    row.querySelector('[data-custom-field-key]')?.value.trim().toLowerCase() === field.key.toLowerCase()
                );
                if (existingRow) {
                    existingRow.querySelector('[data-custom-field-value]').value = field.value;
                    return;
                }
                container.appendChild(createRow(field.key, field.value));
            });
            bulkInput.value = '';
            quickAdd?.classList.add('d-none');
            Toast.fire({
                icon: 'success',
                title: String(quickAdd?.dataset.addedMessage || '').replace(':count', parsed.length),
            });
        });
    })();

    const table = $('#products-table').DataTable();
    const faqTable = $('#product-faqs-table').DataTable();

    (function initProductViewToggle() {
        const buttons = Array.from(document.querySelectorAll('[data-product-view]'));
        const tableWrapper = document.getElementById('products-table_wrapper');
        if (!buttons.length || !tableWrapper) return;

        const setView = (view) => {
            const isGrid = view === 'grid';
            tableWrapper.classList.toggle('product-view-grid', isGrid);
            buttons.forEach((button) => {
                const isActive = button.dataset.productView === view;
                button.classList.toggle('active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        };

        setView('grid');

        buttons.forEach((button) => {
            button.addEventListener('click', () => setView(button.dataset.productView));
        });
    })();

    (function initProductPricingExpand() {
        const meta = document.getElementById('product-pricing-meta');
        if (!meta) return;

        let labels = {};
        try {
            labels = JSON.parse(meta.dataset.pricingLabels || '{}');
        } catch (e) {
            labels = {};
        }
        const urlTemplate = meta.dataset.pricingUrl || '';

        const escapeHtml = (val) => {
            if (val === null || val === undefined) return '';
            return String(val)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };

        const renderPricingTable = (variantPricing) => {
            const variants = Object.values(variantPricing || {});
            if (variants.length === 0) {
                return `<div class="empty py-4"><div class="empty-icon"><i class="ti ti-currency-off"></i></div><p class="empty-title mb-0">${escapeHtml(labels.empty || '')}</p></div>`;
            }

            const rows = variants.map((variant, index) => {
                const locations = Array.isArray(variant.stock_locations) ? variant.stock_locations : [];
                const attributes = (Array.isArray(variant.attributes) ? variant.attributes : [])
                    .map((attribute) => `<span class="product-variant-chip">${escapeHtml(attribute.name)} · ${escapeHtml(attribute.value)}</span>`)
                    .join('');
                const locationRows = locations.map((location) => {
                    const selling = location.selling
                        ? `<span class="product-availability is-available"><i></i>${escapeHtml(labels.available || '')}</span>`
                        : `<span class="product-availability is-unavailable"><i></i>${escapeHtml(labels.unavailable || '')}</span>`;
                    const locationStock = location.tracked === false
                        ? escapeHtml(labels.unlimited || '')
                        : `${escapeHtml(location.available_stock)} ${escapeHtml(labels.inStock || '')}`;
                    return `<tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="product-location-icon"><i class="ti ti-building-store"></i></span>
                                <div><div class="fw-semibold">${escapeHtml(location.store_name || '—')}</div><div class="text-secondary small">${escapeHtml(location.store_location_name || '—')}</div></div>
                            </div>
                        </td>
                        <td>${locationStock}</td>
                        <td>${selling}</td>
                    </tr>`;
                }).join('');
                const inventory = variant.has_unlimited_stock
                    ? escapeHtml(labels.unlimited || '')
                    : `${escapeHtml(variant.available_stock)} ${escapeHtml(labels.inStock || '')}`;
                const availability = variant.availability
                    ? `<span class="product-availability is-available"><i></i>${escapeHtml(labels.available || '')}</span>`
                    : `<span class="product-availability is-unavailable"><i></i>${escapeHtml(labels.unavailable || '')}</span>`;
                const pricing = variant.special_price
                    ? `<div class="fw-semibold">${escapeHtml(variant.special_price)}</div><div class="text-secondary text-decoration-line-through small">${escapeHtml(variant.price)}</div>`
                    : `<div class="fw-semibold">${escapeHtml(variant.price)}</div>`;
                const open = index === 0 && locations.length > 0;
                const image = variant.image
                    ? `<img src="${escapeHtml(variant.image)}" alt="">`
                    : '<span><i class="ti ti-photo-off"></i></span>';
                const locationChild = locations.length > 0 ? `<tr class="product-variant-location-child${open ? '' : ' d-none'}" data-locations-for="${escapeHtml(variant.variant_id)}">
                    <td colspan="6">
                        <div class="product-variant-locations">
                            <div class="product-variant-locations-title"><i class="ti ti-map-pin"></i>${escapeHtml(labels.sellingLocationsFor || '')} <strong>${escapeHtml(variant.title || '—')}</strong> (${locations.length})</div>
                            <div class="table-responsive">
                                <table class="table table-vcenter mb-0">
                                    <thead><tr><th>${escapeHtml(labels.location || '')}</th><th>${escapeHtml(labels.stock || '')}</th><th>${escapeHtml(labels.selling || '')}</th></tr></thead>
                                    <tbody>${locationRows}</tbody>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>` : '';

                return `<tr class="product-variant-row">
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            ${locations.length > 0 ? `<button type="button" class="btn btn-sm btn-icon btn-ghost-secondary toggle-variant-locations" data-variant-id="${escapeHtml(variant.variant_id)}" aria-expanded="${open ? 'true' : 'false'}"><i class="ti ${open ? 'ti-chevron-down' : 'ti-chevron-right'}"></i></button>` : '<span class="product-variant-toggle-spacer"></span>'}
                            <span class="product-variant-image">${image}</span>
                            <div>
                                <div class="fw-semibold">${escapeHtml(variant.title || '—')}</div>
                                <div class="d-flex align-items-center gap-2 flex-wrap mt-1"><div class="product-variant-chips">${attributes}</div>${locations.length > 0 ? `<span class="text-secondary small"><i class="ti ti-map-pin me-1"></i>${locations.length} ${escapeHtml(labels.locations || '')}</span>` : ''}</div>
                            </div>
                        </div>
                    </td>
                    <td><span class="font-monospace small">${escapeHtml(variant.sku || '—')}</span></td>
                    <td>${pricing}</td>
                    <td>${inventory}</td>
                    <td>${availability}</td>
                    <td class="text-end">
                        <a href="${escapeHtml(variant.manage_url || '#')}" class="btn btn-sm btn-outline-primary">${escapeHtml(labels.manage || '')}</a>
                    </td>
                </tr>${locationChild}`;
            }).join('');

            return `<div class="product-variant-panel">
                <div class="product-variant-panel-header">
                    <div>
                        <div class="fw-semibold">${variants.length} ${escapeHtml(labels.variants || labels.variant || '')}</div>
                        <div class="text-secondary small">${escapeHtml(labels.variantInventoryHint || '')}</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter product-variant-table mb-0">
                        <thead><tr>
                            <th>${escapeHtml(labels.variant || '')}</th>
                            <th>${escapeHtml(labels.sku || '')}</th>
                            <th>${escapeHtml(labels.price || '')}</th>
                            <th>${escapeHtml(labels.inventory || labels.stock || '')}</th>
                            <th>${escapeHtml(labels.availability || '')}</th>
                            <th></th>
                        </tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            </div>`;
        };

        $('#products-table tbody').on('click', '.toggle-variant-locations', function (event) {
            event.stopPropagation();
            const button = this;
            const panel = button.closest('.product-variant-panel');
            const variantId = button.dataset.variantId;
            const child = panel?.querySelector(`[data-locations-for="${CSS.escape(String(variantId))}"]`);
            if (!child) return;
            const willOpen = child.classList.contains('d-none');
            panel.querySelectorAll('.product-variant-location-child').forEach(row => row.classList.add('d-none'));
            panel.querySelectorAll('.toggle-variant-locations').forEach(toggle => {
                toggle.setAttribute('aria-expanded', 'false');
                toggle.querySelector('i')?.classList.replace('ti-chevron-down', 'ti-chevron-right');
            });
            if (willOpen) {
                child.classList.remove('d-none');
                button.setAttribute('aria-expanded', 'true');
                button.querySelector('i')?.classList.replace('ti-chevron-right', 'ti-chevron-down');
            }
        });

        const wrap = (inner) => `<div class="p-3 border-top">${inner}</div>`;

        const setChevron = ($btn, open) => {
            const $icon = $btn.find('i');
            $icon.removeClass('ti-chevron-right ti-chevron-down')
                .addClass(open ? 'ti-chevron-down' : 'ti-chevron-right');
            $btn.attr('aria-expanded', open ? 'true' : 'false');
            $btn.attr('title', open ? (labels.hideVariants || '') : (labels.showVariants || ''));
        };

        const pricingCache = new Map();

        $('#products-table tbody').on('click', '.expand-pricing', function (e) {
            e.stopPropagation();
            const $btn = $(this);
            const $tr = $btn.closest('tr');
            const row = table.row($tr);
            if (!row || !row.node()) return;

            if (row.child.isShown()) {
                row.child.hide();
                $tr.removeClass('shown');
                setChevron($btn, false);
                return;
            }

            $('#products-table tbody tr.shown').each(function () {
                if (this === $tr.get(0)) return;
                const otherRow = table.row(this);
                if (otherRow.child.isShown()) otherRow.child.hide();
                $(this).removeClass('shown');
                setChevron($(this).find('.expand-pricing'), false);
            });

            const productId = $btn.data('productId');
            if (!productId || !urlTemplate) return;

            row.child(wrap(`<div class="d-flex align-items-center justify-content-center py-4 text-secondary"><div class="spinner-border spinner-border-sm me-2" role="status"></div>${escapeHtml(labels.loading || '')}</div>`)).show();
            $tr.addClass('shown');
            setChevron($btn, true);

            if (pricingCache.has(productId)) {
                row.child(wrap(renderPricingTable(pricingCache.get(productId)))).show();
                return;
            }

            const url = urlTemplate.replace('__ID__', encodeURIComponent(productId));
            axios.get(url)
                .then((response) => {
                    const data = response?.data?.data;
                    const html = (!data || !data.variant_pricing)
                        ? wrap(`<div class="text-secondary">${escapeHtml(labels.empty || '')}</div>`)
                        : wrap(renderPricingTable(data.variant_pricing));
                    if (data?.variant_pricing) pricingCache.set(productId, data.variant_pricing);
                    row.child(html).show();
                    $tr.addClass('shown');
                })
                .catch(() => {
                    row.child(wrap(`<div class="text-danger">${escapeHtml(labels.error || '')}</div>`)).show();
                    $tr.addClass('shown');
                });
        });
    })();

    // Prefill filters from URL params if present
    try {
        const params = new URLSearchParams(window.location.search);
        const vs = params.get('verification_status');
        const pf = params.get('product_filter');
        let prefilled = false;
        if (vs && $('#productVerificationStatusFilter').length) {
            $('#productVerificationStatusFilter').val(vs);
            prefilled = true;
        }
        if (pf && $('#productFilter').length) {
            $('#productFilter').val(pf);
            prefilled = true;
        }
        if (prefilled) {
            // Trigger an initial reload with the preselected filter
            setTimeout(function () {
                table.ajax.reload(null, false);
            }, 50);
        }
    } catch (e) {
        console.error(e);
    }

    // Initialize Tom Select for Category Filter (server-side loading)
    try {
        const catEl = document.getElementById('productCategoryFilter');
        if (catEl) {
            window.TomSelect && new TomSelect(catEl, {
                copyClassesToDropdown: false,
                dropdownParent: 'body',
                controlInput: '<input>',
                valueField: 'value',
                labelField: 'text',
                searchField: 'text',
                placeholder: catEl.dataset.placeholder || 'Category',
                load: function (query, callback) {
                    if (!query.length) return callback();
                    const url = `${base_url}/${panel}/categories/search?search=${encodeURIComponent(query)}`;
                    fetch(url)
                        .then(response => response.json())
                        .then(json => callback(json))
                        .catch(() => callback());
                }
            });
        }
    } catch (e) {
        console.error(e);
    }

    try {
        const brandEl = document.getElementById('productBrandFilter');
        if (brandEl && window.TomSelect) {
            new TomSelect(brandEl, {
                copyClassesToDropdown: false,
                dropdownParent: 'body',
                controlInput: '<input>',
                valueField: 'value',
                labelField: 'text',
                searchField: 'text',
                placeholder: brandEl.dataset.placeholder || 'Brand',
                load: function (query, callback) {
                    if (!query.length) return callback();
                    fetch(`${base_url}/${panel}/brands/search?q=${encodeURIComponent(query)}`)
                        .then(response => response.json())
                        .then(json => callback(json))
                        .catch(() => callback());
                }
            });
        }
    } catch (e) {
        console.error(e);
    }

    // Initialize Tom Select for Store Filter (admin + seller panels)
    try {
        const storeEl = document.getElementById('productStoreFilter');
        if (storeEl && window.TomSelect) {
            new TomSelect(storeEl, {
                copyClassesToDropdown: false,
                dropdownParent: 'body',
                controlInput: '<input>',
                valueField: 'value',
                labelField: 'text',
                searchField: 'text',
                placeholder: storeEl.dataset.placeholder || 'Store',
                load: function (query, callback) {
                    const url = panel === 'admin'
                        ? `${base_url}/admin/sellers/store/search?search=${encodeURIComponent(query)}`
                        : `${base_url}/seller/stores/search?search=${encodeURIComponent(query)}`;
                    fetch(url)
                        .then(response => response.json())
                        .then(json => callback(json))
                        .catch(() => callback());
                }
            });
        }
    } catch (e) {
        console.error(e);
    }

    // Initialize Tom Select for Seller Filter (admin panel only)
    try {
        const sellerEl = document.getElementById('productSellerFilter');
        if (sellerEl && window.TomSelect) {
            new TomSelect(sellerEl, {
                copyClassesToDropdown: false,
                dropdownParent: 'body',
                controlInput: '<input>',
                valueField: 'value',
                labelField: 'text',
                searchField: 'text',
                placeholder: sellerEl.dataset.placeholder || 'Seller',
                load: function (query, callback) {
                    if (!query.length) return callback();
                    const url = `${base_url}/${panel}/sellers/search?search=${encodeURIComponent(query)}`;
                    fetch(url)
                        .then(response => response.json())
                        .then(json => callback(json))
                        .catch(() => callback());
                }
            });
        }
    } catch (e) {
        console.error(e);
    }

    const productFilterSelector = '#productVerificationStatusFilter, #productStatusFilter, #productTypeFilter, #productCategoryFilter, #productBrandFilter, #productFilter, #productStoreFilter, #productSellerFilter, #productBadgeFilter, #productDateFrom, #productDateTo';
    const quickFilterSelectors = ['#productStatusFilter', '#productVerificationStatusFilter', '#productFilter'];
    const syncProductQuickFilters = function () {
        const values = Object.fromEntries(quickFilterSelectors.map((selector) => [selector, String($(selector).val() || '')]));
        let matched = false;
        document.querySelectorAll('[data-product-quick-filter]').forEach(function (button) {
            const target = button.dataset.target || '';
            const value = button.dataset.value || '';
            const isAll = target === '';
            const otherValuesEmpty = quickFilterSelectors.every((selector) => selector === target || values[selector] === '');
            const active = isAll
                ? quickFilterSelectors.every((selector) => values[selector] === '')
                : values[target] === value && otherValuesEmpty;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            matched = matched || active;
        });
        if (!matched) {
            document.querySelector('[data-product-quick-filter]')?.classList.remove('active');
        }
    };

    document.querySelectorAll('[data-product-quick-filter]').forEach(function (button) {
        button.addEventListener('click', function () {
            quickFilterSelectors.forEach((selector) => $(selector).val(''));
            if (button.dataset.target) {
                $(button.dataset.target).val(button.dataset.value || '');
            }
            updateProductFilterCount();
            syncProductQuickFilters();
            table.ajax.reload(null, false);
        });
    });

    const updateProductFilterCount = function () {
        const count = $(productFilterSelector).filter(function () {
            return String($(this).val() || '').length > 0;
        }).length;
        const badge = document.getElementById('product-active-filter-count');
        if (!badge) return;
        badge.textContent = count;
        badge.classList.toggle('d-none', count === 0);
    };

    $(productFilterSelector).on('change', function () {
        updateProductFilterCount();
        syncProductQuickFilters();
        table.ajax.reload(null, false);
    });

    document.getElementById('clearProductFilters')?.addEventListener('click', function () {
        $(productFilterSelector).each(function () {
            if (this.tomselect) {
                this.tomselect.clear(true);
            } else {
                $(this).val('');
            }
        });
        updateProductFilterCount();
        syncProductQuickFilters();
        table.ajax.reload(null, false);
    });

    const productListMeta = document.getElementById('product-list-meta');
    if (productListMeta) {
        $('#products-table_wrapper .dt-search input').attr('placeholder', productListMeta.dataset.searchPlaceholder || 'Search products');
    }
    updateProductFilterCount();
    syncProductQuickFilters();
    $('#faqStatusFilter, [name=\'product_id_filter\']').on('change', function () {
        faqTable.ajax.reload(null, false);
    });

    // Add filter params to AJAX request
    $('#product-faqs-table').on('preXhr.dt', function (e, settings, data) {
        data.status = $('#faqStatusFilter').val();
        data.product_id = $('[name=\'product_id_filter\']').val();
    });

    $('#products-table').on('preXhr.dt', function (e, settings, data) {
        data.product_type = $('#productTypeFilter').val();
        data.product_filter = $('#productFilter').val();
        data.product_status = $('#productStatusFilter').val();
        data.verification_status = $('#productVerificationStatusFilter').val();
        data.badge_id = $('#productBadgeFilter').val();
        data.category_id = $('#productCategoryFilter').val();
        data.brand_id = $('#productBrandFilter').val();
        data.store_id = $('#productStoreFilter').val();
        data.seller_id = $('#productSellerFilter').val();
        data.date_from = $('#productDateFrom').val();
        data.date_to = $('#productDateTo').val();
    });
    (function () {
        const select = document.getElementById('verification_status');
        const reasonWrap = document.getElementById('rejection-reason-wrapper');
        const toggleReason = () => {
            const val = (select != undefined && select != null && select != "") ? select.value : null;
            if (reasonWrap == undefined || reasonWrap == null) return;
            reasonWrap.style.display = (val == 'rejected') ? 'block' : 'none';
            if (val != 'rejected') {
                const ta = document.getElementById('rejection_reason');
                if (ta) ta.value = '';
            }
        };
        select?.addEventListener('change', toggleReason);
        toggleReason();
    })();
});

$(document).ready(function () {
    document.addEventListener('click', function (event) {
        const updateProductStatus = event.target.closest('.update-product-status');
        if (!updateProductStatus) return;

        const id = updateProductStatus.getAttribute('data-id');

        // Disable button
        updateProductStatus.disabled = true;

        // Save original text
        let originalText = updateProductStatus.innerHTML;

        // Show spinner
        updateProductStatus.innerHTML = `
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
    `;

        axios.post(`${base_url}/${panel}/products/${id}/update-status`)
            .then(function (response) {
                let data = response.data;

                if (data.success) {
                    $(`#products-table`).DataTable().ajax.reload(null, false);
                    Toast.fire({
                        icon: "success", title: data.message
                    });
                } else {
                    Toast.fire({
                        icon: "error", title: data.message
                    });
                }

                // Re-enable and restore text
                updateProductStatus.disabled = false;
                updateProductStatus.innerHTML = originalText;
            })
            .catch(function (error) {
                console.error('Error:', error);

                Toast.fire({
                    icon: "error", title: "An error occurred while updating product status."
                });

                // Re-enable and restore text
                updateProductStatus.disabled = false;
                updateProductStatus.innerHTML = originalText;
            });
    });

    // ── Bulk row selection ────────────────────────────────────────────────────
    var selectedProductIds = [];

    $('#products-table').on('draw.dt', function () {
        selectedProductIds = [];
        updateBulkToolbar();
        var selectAll = document.getElementById('select-all-products');
        if (selectAll) selectAll.checked = false;
    });

    // Select-all header checkbox
    document.addEventListener('change', function (event) {
        var selectAll = event.target.closest('#select-all-products');
        if (!selectAll) return;
        document.querySelectorAll('.product-row-checkbox').forEach(function (cb) {
            cb.checked = selectAll.checked;
            var id = parseInt(cb.value, 10);
            if (selectAll.checked) {
                if (selectedProductIds.indexOf(id) === -1) selectedProductIds.push(id);
            } else {
                selectedProductIds = selectedProductIds.filter(function (v) { return v !== id; });
            }
        });
        updateBulkToolbar();
    });

    // Individual row checkbox
    document.addEventListener('change', function (event) {
        var cb = event.target.closest('.product-row-checkbox');
        if (!cb) return;
        var id = parseInt(cb.value, 10);
        if (cb.checked) {
            if (selectedProductIds.indexOf(id) === -1) selectedProductIds.push(id);
        } else {
            selectedProductIds = selectedProductIds.filter(function (v) { return v !== id; });
        }
        var visibleCheckboxes = Array.from(document.querySelectorAll('.product-row-checkbox'));
        var selectAll = document.getElementById('select-all-products');
        if (selectAll) {
            selectAll.checked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(function (checkbox) { return checkbox.checked; });
            selectAll.indeterminate = visibleCheckboxes.some(function (checkbox) { return checkbox.checked; }) && !selectAll.checked;
        }
        updateBulkToolbar();
    });

    function updateBulkToolbar() {
        var toolbar = document.getElementById('bulk-badge-toolbar');
        var count   = document.getElementById('bulk-selected-count');
        if (!toolbar) return;
        toolbar.classList.toggle('d-none', selectedProductIds.length === 0);
        if (count) count.textContent = selectedProductIds.length;
    }

    // Deselect all
    var deselectBtn = document.getElementById('bulk-deselect-btn');
    if (deselectBtn) {
        deselectBtn.addEventListener('click', function () {
            selectedProductIds = [];
            document.querySelectorAll('.product-row-checkbox').forEach(function (cb) { cb.checked = false; });
            var selectAll = document.getElementById('select-all-products');
            if (selectAll) selectAll.checked = false;
            updateBulkToolbar();
        });
    }

    // ── Bulk assign badge — validate then open modal via hidden trigger ────────
    var bulkAssignBtn = document.getElementById('bulk-assign-badge-btn');
    if (bulkAssignBtn) {
        bulkAssignBtn.addEventListener('click', function () {
            var labels = window.productBadgeLabels || {};
            if (selectedProductIds.length === 0) {
                Toast.fire({ icon: 'warning', title: labels.selectProductsFirst || 'Select at least one product.' });
                return;
            }
            // Reset select then open modal via the hidden data-bs-toggle trigger
            var sel = document.getElementById('bulk-badge-select');
            if (sel && sel.tomselect) {
                sel.tomselect.clear(true);
            } else if (sel) {
                sel.value = '';
            }
            var trigger = document.getElementById('bulk-badge-modal-trigger');
            if (trigger) trigger.click();
        });
    }

    // Bulk assign confirm button
    var bulkConfirmBtn = document.getElementById('bulk-badge-confirm');
    if (bulkConfirmBtn) {
        bulkConfirmBtn.addEventListener('click', function () {
            var labels  = window.productBadgeLabels || {};
            var sel     = document.getElementById('bulk-badge-select');
            var badgeId = sel ? sel.value : '';
            if (!badgeId) {
                Toast.fire({ icon: 'warning', title: labels.selectBadgeFirst || 'Select a badge.' });
                return;
            }
            bulkConfirmBtn.disabled = true;
            axios.post(labels.bulkAssignUrl, { product_ids: selectedProductIds, badge_id: badgeId }, {
                headers: { 'X-CSRF-TOKEN': csrfToken },
            }).then(function (res) {
                var data = res.data;
                $('#bulk-badge-modal').modal('hide');
                Toast.fire({ icon: data.success ? 'success' : 'error', title: data.message });
                if (data.success) {
                    selectedProductIds = [];
                    updateBulkToolbar();
                    $('#products-table').DataTable().ajax.reload(null, false);
                }
            }).catch(function () {
                Toast.fire({ icon: 'error', title: 'Something went wrong.' });
            }).finally(function () {
                bulkConfirmBtn.disabled = false;
            });
        });
    }

    // ── Bulk remove badge ─────────────────────────────────────────────────────
    var bulkRemoveBtn = document.getElementById('bulk-remove-badge-btn');
    if (bulkRemoveBtn) {
        bulkRemoveBtn.addEventListener('click', function () {
            var labels = window.productBadgeLabels || {};
            if (selectedProductIds.length === 0) {
                Toast.fire({ icon: 'warning', title: labels.selectProductsFirst || 'Select at least one product.' });
                return;
            }
            Swal.fire({
                title: 'Remove badges?',
                text: 'Badge will be removed from ' + selectedProductIds.length + ' product(s).',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, remove',
            }).then(function (result) {
                if (!result.isConfirmed) return;
                axios.post(labels.bulkRemoveUrl, { product_ids: selectedProductIds }, {
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                }).then(function (res) {
                    var data = res.data;
                    Toast.fire({ icon: data.success ? 'success' : 'error', title: data.message });
                    if (data.success) {
                        selectedProductIds = [];
                        updateBulkToolbar();
                        $('#products-table').DataTable().ajax.reload(null, false);
                    }
                }).catch(function () {
                    Toast.fire({ icon: 'error', title: 'Something went wrong.' });
                });
            });
        });
    }

    // ── Single-product assign badge modal ─────────────────────────────────────
    // Button in product-actions partial carries data-bs-toggle="modal" data-bs-target="#assign-badge-modal"
    // so Bootstrap opens it natively; we populate the select on show.bs.modal below.
    document.addEventListener('show.bs.modal', function (event) {
        if (event.target.id !== 'assign-badge-modal') return;
        var trigger   = event.relatedTarget;
        var productId = trigger ? trigger.getAttribute('data-id')       : '';
        var badgeId   = trigger ? trigger.getAttribute('data-badge-id') : '';
        var idInput   = document.getElementById('assign-badge-product-id');
        var sel       = document.getElementById('assign-badge-select');
        var ts        = sel ? sel.tomselect : null;
        if (idInput) idInput.value = productId || '';
        if (ts && window.productBadgeTomSelect) {
            window.productBadgeTomSelect.setValue(ts, badgeId);
        } else if (sel) {
            sel.value = badgeId || '';
        }
    });

    var assignConfirmBtn = document.getElementById('assign-badge-confirm');
    if (assignConfirmBtn) {
        assignConfirmBtn.addEventListener('click', function () {
            var labels    = window.productBadgeLabels || {};
            var idInput   = document.getElementById('assign-badge-product-id');
            var sel       = document.getElementById('assign-badge-select');
            var productId = idInput ? parseInt(idInput.value, 10) : null;
            var badgeId   = sel ? (sel.value || null) : null;

            if (!productId) return;
            assignConfirmBtn.disabled = true;
            axios.post(labels.bulkAssignUrl, {
                product_ids: [productId],
                badge_id: badgeId,
            }, {
                headers: { 'X-CSRF-TOKEN': csrfToken },
            }).then(function (res) {
                var data = res.data;
                $('#assign-badge-modal').modal('hide');
                Toast.fire({ icon: data.success ? 'success' : 'error', title: data.message });
                if (data.success) $('#products-table').DataTable().ajax.reload(null, false);
            }).catch(function () {
                Toast.fire({ icon: 'error', title: 'Something went wrong.' });
            }).finally(function () {
                assignConfirmBtn.disabled = false;
            });
        });
    }

});
