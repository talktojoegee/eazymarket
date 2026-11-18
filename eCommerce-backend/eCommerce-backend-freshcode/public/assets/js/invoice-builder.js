document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('invoice-builder');
    if (!root) return;

    const config = JSON.parse(root.dataset.config || '{}');
    const published = JSON.parse(root.dataset.published || '{}');
    const isHexColor = value => typeof value === 'string' && /^#[0-9a-fA-F]{6}$/.test(value);
    config.showPageLines = config.showPageLines === true;
    config.pageLineColor = isHexColor(config.pageLineColor) ? config.pageLineColor : '#f4c20d';
    const syncInvoiceColors = () => {
        config.pageBackgroundColor = isHexColor(config.pageBackgroundColor) ? config.pageBackgroundColor : '#ffffff';
        config.sections = config.sections.map(section => {
            return {
                ...section,
                titleColor: isHexColor(section.titleColor) ? section.titleColor : '',
                subtitleColor: ['payment', 'terms', 'footer'].includes(section.key) ? '' : (isHexColor(section.subtitleColor) ? section.subtitleColor : ''),
                backgroundColor: isHexColor(section.backgroundColor) ? section.backgroundColor : '',
                itemHeaderTitleColor: isHexColor(section.itemHeaderTitleColor) ? section.itemHeaderTitleColor : '',
                itemHeaderBackgroundColor: isHexColor(section.itemHeaderBackgroundColor) ? section.itemHeaderBackgroundColor : '',
                itemDataTitleColor: isHexColor(section.itemDataTitleColor) ? section.itemDataTitleColor : '',
                itemDataSubtitleColor: isHexColor(section.itemDataSubtitleColor) ? section.itemDataSubtitleColor : '',
                itemDataBackgroundColor: isHexColor(section.itemDataBackgroundColor) ? section.itemDataBackgroundColor : '',
            };
        });
    };
    const colorToHex = color => {
        const match = String(color).match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
        return match ? `#${match.slice(1, 4).map(value => Number(value).toString(16).padStart(2, '0')).join('')}` : '#182433';
    };
    const syncSectionColorPickers = () => {
        document.querySelectorAll('.invoice-section-color-field input[type="color"]').forEach(input => {
            const section = config.sections.find(item => item.key === input.dataset.sectionKey);
            const property = input.dataset.colorProperty;
            if (!section || !property) return;
            if (isHexColor(section[property])) {
                input.value = section[property];
                return;
            }
            if (property === 'backgroundColor' || property.endsWith('BackgroundColor')) {
                const previewSection = paper.querySelector(`[data-preview-section="${section.key}"]`);
                const selector = property === 'itemHeaderBackgroundColor' ? 'thead th'
                    : property === 'itemDataBackgroundColor' ? 'tbody td'
                        : section.key === 'addresses' ? '.preview-details > div'
                            : section.key === 'items' ? '.preview-table'
                                : section.key === 'totals' ? ':scope > div'
                                    : null;
                const target = selector ? previewSection?.querySelector(selector) : previewSection;
                const background = target ? getComputedStyle(target).backgroundColor : '';
                input.value = background && !background.endsWith(', 0)') ? colorToHex(background) : colorToHex(getComputedStyle(paper).backgroundColor);
                return;
            }
            const previewSection = paper.querySelector(`[data-preview-section="${section.key}"]`);
            const selector = property === 'itemHeaderTitleColor' ? 'thead th'
                : property === 'itemDataTitleColor' ? 'tbody td strong'
                    : property === 'itemDataSubtitleColor' ? 'tbody td'
                        : property === 'titleColor' && section.key === 'totals' ? '.preview-grand-total'
                            : property === 'subtitleColor' && section.key === 'totals' ? ':scope > div:not(.preview-grand-total)'
                                : property === 'titleColor'
                ? 'h1, h2, .preview-company-name, .preview-details strong, .preview-total span, .preview-total b, .preview-table th'
                : 'small, .preview-order-meta, .preview-details span';
            const target = previewSection?.querySelector(selector) || previewSection;
            input.value = target ? colorToHex(getComputedStyle(target).color) : (property === 'titleColor' ? '#182433' : '#617286');
        });
    };
    syncInvoiceColors();
    config.templateType = ({ standard: 'blue', tax: 'blue', receipt: 'grid', green: 'blue' }[config.templateType]) || config.templateType || ({ modern: 'blue', compact: 'grid' }[config.layout] || 'blue');
    const sectionList = document.getElementById('invoice-section-list');
    const paper = document.getElementById('invoice-preview-paper');
    const itemTableBody = document.getElementById('invoice-preview-items');
    const sampleItemRows = itemTableBody.innerHTML;
    const previewFields = [...paper.querySelectorAll('[data-preview-field]')];
    const samplePreviewValues = previewFields.map(element => element.textContent);
    let previewTimer = null;
    let previewRequest = 0;
    const titleInput = document.getElementById('invoice-title');
    const totalLabelInput = document.getElementById('invoice-total-label');
    const footerInput = document.getElementById('invoice-footer-text');
    const termsInput = document.getElementById('invoice-terms-text');
    const fontInput = document.getElementById('invoice-font');
    const pageBackgroundInput = document.getElementById('invoice-page-background-color');
    const showPageLinesInput = document.getElementById('invoice-show-page-lines');
    const pageLineColorInput = document.getElementById('invoice-page-line-color');
    const marginInput = document.getElementById('invoice-margin');
    const marginRange = document.getElementById('invoice-margin-range');
    const status = document.getElementById('invoice-builder-status');
    const showLogoInput = document.getElementById('invoice-show-logo');
    const itemStatusInput = document.getElementById('invoice-show-item-status');
    const addonsInput = document.getElementById('invoice-show-addons');
    const paymentStatusInput = document.getElementById('invoice-show-payment-status');
    const templateNameInput = document.getElementById('invoice-template-name');
    const previewModeInput = document.getElementById('invoice-preview-mode');
    const previewOrderField = document.getElementById('invoice-preview-order-field');
    const previewOrderInput = document.getElementById('invoice-preview-order');
    const configuredSystemColor = getComputedStyle(document.documentElement).getPropertyValue('--tblr-primary').trim() || '#066fd1';
    const systemColor = isHexColor(configuredSystemColor) ? configuredSystemColor : colorToHex(configuredSystemColor);
    if (config.brandColorMode === 'system') config.brandColor = systemColor;
    const headerSection = config.sections.find(section => section.key === 'header');
    if (headerSection && (!isHexColor(headerSection.titleColor) || headerSection.titleColor.toLowerCase() === '#182433')) {
        headerSection.titleColor = isHexColor(config.brandColor) ? config.brandColor : systemColor;
    }
    let previous = JSON.stringify(config);
    let activeStyleSection = null;
    let activeItemStyleSection = null;

    const applyInvoiceFont = () => {
        const font = String(config.font || 'Inter').trim();
        const systemFonts = ['Arial', 'Georgia', 'Helvetica', 'Times New Roman'];
        let fontLink = document.getElementById('invoice-google-font');
        paper.style.fontFamily = `"${font.replace(/["\\]/g, '')}", Arial, sans-serif`;
        if (systemFonts.includes(font)) {
            fontLink?.remove();
            return;
        }
        if (!fontLink) {
            fontLink = document.createElement('link');
            fontLink.id = 'invoice-google-font';
            fontLink.rel = 'stylesheet';
            document.head.appendChild(fontLink);
        }
        fontLink.href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(font).replace(/%20/g, '+')}&display=swap`;
    };

    const toast = (icon, message) => {
        if (window.Toast?.fire) window.Toast.fire({ icon, title: message });
    };

    const applyItemPreviewColors = () => {
        const itemStyles = config.sections.find(section => section.key === 'items');
        const itemPreview = paper.querySelector('[data-preview-section="items"]');
        if (!itemStyles || !itemPreview) return;
        const applyStyle = (elements, property, value) => elements.forEach(element => {
            if (value) element.style.setProperty(property, value, 'important');
            else element.style.removeProperty(property);
        });
        applyStyle(itemPreview.querySelectorAll('thead th'), 'color', itemStyles.itemHeaderTitleColor);
        applyStyle(itemPreview.querySelectorAll('thead th'), 'background-color', itemStyles.itemHeaderBackgroundColor);
        applyStyle(itemPreview.querySelectorAll('tbody td strong'), 'color', itemStyles.itemDataTitleColor);
        applyStyle(itemPreview.querySelectorAll('tbody td'), 'color', itemStyles.itemDataSubtitleColor);
        applyStyle(itemPreview.querySelectorAll('tbody td small'), 'color', itemStyles.itemDataSubtitleColor);
        applyStyle(itemPreview.querySelectorAll('tbody tr'), 'background-color', itemStyles.itemDataBackgroundColor);
    };

    const renderSections = () => {
        sectionList.replaceChildren();
        config.sections.forEach((section, index) => {
            section.titleColor ??= '';
            section.subtitleColor ??= '';
            section.backgroundColor ??= '';
            section.itemHeaderTitleColor ??= '';
            section.itemHeaderBackgroundColor ??= '';
            section.itemDataTitleColor ??= '';
            section.itemDataSubtitleColor ??= '';
            section.itemDataBackgroundColor ??= '';
            const row = document.createElement('div');
            row.className = 'invoice-section-row';
            row.draggable = true;
            row.dataset.index = index;
            const grip = document.createElement('span');
            grip.className = 'invoice-section-grip';
            grip.setAttribute('aria-hidden', 'true');
            grip.textContent = '⠿';
            const name = document.createElement('span');
            name.className = 'invoice-section-name';
            name.textContent = section.label;
            name.tabIndex = 0;
            name.setAttribute('role', 'button');
            name.setAttribute('aria-expanded', String(activeStyleSection === section.key));
            name.addEventListener('click', () => {
                activeStyleSection = activeStyleSection === section.key ? null : section.key;
                renderSections();
            });
            name.addEventListener('keydown', event => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                activeStyleSection = activeStyleSection === section.key ? null : section.key;
                renderSections();
            });
            const expand = document.createElement('span');
            expand.className = 'invoice-section-expand';
            expand.tabIndex = 0;
            expand.setAttribute('role', 'button');
            expand.setAttribute('aria-label', `${activeStyleSection === section.key ? 'Collapse' : 'Expand'} ${section.label} color settings`);
            expand.setAttribute('aria-expanded', String(activeStyleSection === section.key));
            expand.textContent = '⌄';
            expand.addEventListener('click', () => {
                activeStyleSection = activeStyleSection === section.key ? null : section.key;
                renderSections();
            });
            expand.addEventListener('keydown', event => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                activeStyleSection = activeStyleSection === section.key ? null : section.key;
                renderSections();
            });
            row.append(grip, name);
            if (section.required) {
                const required = document.createElement('span');
                required.className = 'invoice-required-pill';
                required.textContent = 'Required';
                row.appendChild(required);
            } else {
                const toggle = document.createElement('input');
                toggle.className = 'invoice-section-toggle';
                toggle.type = 'checkbox';
                toggle.checked = Boolean(section.enabled);
                toggle.setAttribute('aria-label', `Show ${section.label}`);
                row.appendChild(toggle);
                toggle.addEventListener('change', () => {
                    section.enabled = toggle.checked;
                    updatePreview();
                    markDirty();
                });
            }
            row.appendChild(expand);
            if (activeStyleSection === section.key) {
                const styleOptions = document.createElement('div');
                styleOptions.className = 'invoice-section-style-options';
                const addColorField = (container, property, label, fallback) => {
                    const field = document.createElement('label');
                    field.className = 'invoice-section-color-field';
                    const fieldName = document.createElement('span');
                    fieldName.textContent = label;
                    const input = document.createElement('input');
                    input.type = 'color';
                    input.dataset.sectionKey = section.key;
                    input.dataset.colorProperty = property;
                    input.value = section[property] || fallback;
                    input.setAttribute('aria-label', `${section.label} ${label}`);
                    const applyColor = () => {
                        section[property] = input.value;
                        updatePreview();
                        markDirty();
                    };
                    input.addEventListener('input', applyColor);
                    input.addEventListener('change', applyColor);
                    field.append(fieldName, input);
                    container.appendChild(field);
                };
                const sectionColorControls = section.key === 'items'
                    ? [['titleColor', 'Title color', '#182433']]
                    : ['payment', 'terms', 'footer'].includes(section.key)
                        ? [['titleColor', 'Title color', '#182433'], ['backgroundColor', 'Background color', '#ffffff']]
                        : [['titleColor', 'Title color', '#182433'], ['subtitleColor', 'Subtitle color', '#617286'], ['backgroundColor', 'Background color', '#ffffff']];
                sectionColorControls.forEach(([property, label, fallback]) => {
                    addColorField(styleOptions, property, label, fallback);
                });
                if (section.key === 'items') {
                    const subTabs = document.createElement('div');
                    subTabs.className = 'invoice-item-style-tabs';
                    [['header', 'Item header'], ['data', 'Item data']].forEach(([key, label]) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = `invoice-item-style-tab${activeItemStyleSection === key ? ' active' : ''}`;
                        button.setAttribute('aria-expanded', String(activeItemStyleSection === key));
                        const buttonLabel = document.createElement('span');
                        buttonLabel.textContent = label;
                        const buttonArrow = document.createElement('span');
                        buttonArrow.className = 'invoice-item-style-arrow';
                        buttonArrow.setAttribute('aria-hidden', 'true');
                        buttonArrow.textContent = activeItemStyleSection === key ? '⌃' : '⌄';
                        button.append(buttonLabel, buttonArrow);
                        button.addEventListener('click', () => {
                            activeItemStyleSection = activeItemStyleSection === key ? null : key;
                            renderSections();
                        });
                        subTabs.appendChild(button);
                    });
                    styleOptions.appendChild(subTabs);
                    if (activeItemStyleSection) {
                        const subOptions = document.createElement('div');
                        subOptions.className = 'invoice-item-style-options';
                        const controls = activeItemStyleSection === 'header'
                            ? [['itemHeaderTitleColor', 'Text color', '#ffffff'], ['itemHeaderBackgroundColor', 'Background color', '#206bc4']]
                            : [['itemDataTitleColor', 'Item title color', '#182433'], ['itemDataSubtitleColor', 'Item subtitle color', '#617286'], ['itemDataBackgroundColor', 'Background color', '#ffffff']];
                        controls.forEach(([property, label, fallback]) => addColorField(subOptions, property, label, fallback));
                        styleOptions.appendChild(subOptions);
                    }
                }
                const resetButton = document.createElement('button');
                resetButton.type = 'button';
                resetButton.className = 'invoice-section-style-reset';
                resetButton.textContent = 'Reset colors';
                resetButton.addEventListener('click', () => {
                    section.titleColor = '';
                    section.subtitleColor = '';
                    section.backgroundColor = '';
                    section.itemHeaderTitleColor = '';
                    section.itemHeaderBackgroundColor = '';
                    section.itemDataTitleColor = '';
                    section.itemDataSubtitleColor = '';
                    section.itemDataBackgroundColor = '';
                    updatePreview();
                    renderSections();
                    markDirty();
                });
                styleOptions.appendChild(resetButton);
                row.appendChild(styleOptions);
            }
            row.addEventListener('dragstart', event => {
                event.dataTransfer.setData('text/plain', String(index));
                row.classList.add('is-dragging');
            });
            row.addEventListener('dragend', () => row.classList.remove('is-dragging'));
            row.addEventListener('dragover', event => event.preventDefault());
            row.addEventListener('drop', event => {
                event.preventDefault();
                const from = Number(event.dataTransfer.getData('text/plain'));
                const to = Number(row.dataset.index);
                if (Number.isNaN(from) || from === to) return;
                const [moved] = config.sections.splice(from, 1);
                config.sections.splice(to, 0, moved);
                renderSections();
                updatePreview();
                markDirty();
            });
            sectionList.appendChild(row);
        });
        syncSectionColorPickers();
    };

    const updatePreview = () => {
        paper.style.setProperty('--preview-color', config.brandColor);
        const pageBackgroundColor = config.pageBackgroundColor || '#ffffff';
        paper.style.backgroundColor = pageBackgroundColor;
        paper.classList.toggle('has-page-lines', config.showPageLines === true);
        paper.style.setProperty('--invoice-page-line-color', config.pageLineColor);
        applyInvoiceFont();
        const margin = Math.min(30, Math.max(5, Number(config.margin) || 15));
        paper.style.setProperty('--invoice-margin', `${margin}mm`);
        paper.style.paddingLeft = `${margin * 3.78}px`;
        paper.style.paddingRight = `${margin * 3.78}px`;
        paper.classList.remove('layout-classic', 'layout-modern', 'layout-compact');
        paper.classList.add(`layout-${config.layout}`);
        paper.classList.remove('template-grid', 'template-green', 'template-blue');
        paper.classList.add(`template-${config.templateType}`);
        paper.classList.toggle('has-preview-addresses', config.sections.some(section => section.key === 'addresses' && section.enabled));
        titleInput.value = config.title || '';
        totalLabelInput.value = config.totalLabel || '';
        footerInput.value = config.footer || '';
        termsInput.value = config.terms || '';
        showLogoInput.checked = config.showLogo !== false;
        itemStatusInput.checked = config.showItemStatus !== false;
        addonsInput.checked = config.showAddons !== false;
        paymentStatusInput.checked = config.showPaymentStatus !== false;
        templateNameInput.value = config.templateName || 'Order Invoice';
        previewModeInput.value = config.previewMode || (config.previewOrderUuid ? 'existing' : 'sample');
        previewOrderField.hidden = previewModeInput.value !== 'existing';
        previewOrderInput.value = config.previewOrderUuid || '';
        fontInput.value = config.font || 'Arial';
        pageBackgroundInput.value = pageBackgroundColor;
        showPageLinesInput.checked = config.showPageLines === true;
        pageLineColorInput.value = config.pageLineColor;
        marginInput.value = margin;
        marginRange.value = margin;
        updateRangeProgress(marginRange);
        document.querySelector('[data-preview-title]').textContent = config.title || '';
        document.querySelector('[data-preview-footer]').textContent = config.footer || '';
        document.querySelector('.preview-terms').textContent = config.terms || '';
        document.querySelector('[data-preview-total-label]').textContent = config.totalLabel || 'Total Payable';
        const previewLogo = document.querySelector('.preview-logo');
        if (previewLogo) previewLogo.hidden = config.showLogo === false;
        document.querySelectorAll('.preview-item-status').forEach(element => element.hidden = config.showItemStatus === false);
        document.querySelectorAll('.preview-addon').forEach(element => element.hidden = config.showAddons === false);
        document.querySelector('.preview-payment').hidden = config.showPaymentStatus === false;
        document.querySelectorAll('[data-preview-section]').forEach(element => {
            const section = config.sections.find(item => item.key === element.dataset.previewSection);
            element.hidden = Boolean(section && !section.enabled);
            if (section?.backgroundColor) element.style.setProperty('background-color', section.backgroundColor, 'important');
            else element.style.removeProperty('background-color');
            const backgroundSurfaces = element.dataset.previewSection === 'addresses'
                ? element.querySelectorAll('.preview-details > div')
                : element.dataset.previewSection === 'items'
                    ? element.querySelectorAll('.preview-table')
                    : element.dataset.previewSection === 'totals'
                        ? element.querySelectorAll(':scope > div')
                        : [];
            backgroundSurfaces.forEach(surface => {
                if (section?.backgroundColor) surface.style.setProperty('background-color', section.backgroundColor, 'important');
                else surface.style.removeProperty('background-color');
            });
            [['--section-title-color', section?.titleColor], ['--section-subtitle-color', section?.subtitleColor], ['--section-background-color', section?.backgroundColor]].forEach(([property, value]) => {
                if (value) element.style.setProperty(property, value);
                else element.style.removeProperty(property);
            });
            const titleColor = section?.titleColor || '';
            const subtitleColor = section?.subtitleColor || '';
            const titleElements = element.dataset.previewSection === 'items'
                ? element.querySelectorAll('h2')
                : element.dataset.previewSection === 'totals'
                    ? element.querySelectorAll('.preview-grand-total, .preview-grand-total *')
                    : element.querySelectorAll('h1, h2, .preview-company-name, .preview-details strong, .preview-table th');
            const subtitleElements = element.dataset.previewSection === 'totals'
                ? element.querySelectorAll(':scope > div:not(.preview-grand-total), :scope > div:not(.preview-grand-total) *')
                : element.querySelectorAll('small, .preview-order-meta, .preview-details span');
            titleElements.forEach(child => {
                if (titleColor) child.style.setProperty('color', titleColor, 'important');
                else child.style.removeProperty('color');
            });
            subtitleElements.forEach(child => {
                if (subtitleColor) child.style.setProperty('color', subtitleColor, 'important');
                else child.style.removeProperty('color');
            });
            if (['payment', 'terms', 'footer'].includes(element.dataset.previewSection)) {
                const sectionText = [element, ...element.querySelectorAll('*')];
                sectionText.forEach(child => {
                    if (titleColor) child.style.setProperty('color', titleColor, 'important');
                    else child.style.removeProperty('color');
                });
            }
        });
        applyItemPreviewColors();
        config.sections.forEach(section => {
            const element = paper.querySelector(`[data-preview-section="${section.key}"]`);
            if (element) paper.appendChild(element);
        });
        document.querySelectorAll('[data-template]').forEach(button => button.classList.toggle('active', button.dataset.template === config.templateType));
        syncSectionColorPickers();
        scheduleOrderPreview();
    };

    const scheduleOrderPreview = () => {
        window.clearTimeout(previewTimer);
        if (!config.previewOrderUuid) {
            previewRequest++;
            itemTableBody.innerHTML = sampleItemRows;
            applyItemPreviewColors();
            previewFields.forEach((element, index) => { element.textContent = samplePreviewValues[index]; });
            return;
        }
        previewTimer = window.setTimeout(loadOrderPreview, 350);
    };

    const loadOrderPreview = async () => {
        const requestId = ++previewRequest;
        try {
            const response = await fetch(root.dataset.previewUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf },
                body: JSON.stringify({ orderUuid: config.previewOrderUuid, config })
            });
            if (!response.ok) throw new Error('Unable to load this order preview.');
            const data = await response.json();
            if (requestId !== previewRequest || !config.previewOrderUuid) return;
            Object.entries(data.order).forEach(([key, value]) => {
                const field = paper.querySelector(`[data-preview-field="${key}"]`);
                if (field) field.textContent = value ?? '';
            });
            itemTableBody.replaceChildren(...data.order.items.map(item => {
                const row = document.createElement('tr');
                const title = document.createElement('td');
                const titleText = document.createElement('strong');
                titleText.textContent = item.title;
                title.appendChild(titleText);
                if (item.details) {
                    const details = document.createElement('small');
                    details.textContent = item.details;
                    title.appendChild(details);
                }
                if (config.showAddons !== false) item.addons.forEach(addon => {
                    const addonDetails = document.createElement('small');
                    addonDetails.className = 'preview-addon';
                    addonDetails.textContent = `+ ${addon.name} · ${addon.price}`;
                    title.appendChild(addonDetails);
                });
                const quantity = document.createElement('td');
                quantity.textContent = item.quantity;
                const itemStatus = document.createElement('td');
                itemStatus.className = 'preview-item-status';
                itemStatus.textContent = item.status;
                const tax = document.createElement('td');
                tax.textContent = `${item.tax} (${item.taxPercent}%)`;
                const unitPrice = document.createElement('td');
                unitPrice.textContent = item.unitPrice;
                const total = document.createElement('td');
                total.textContent = item.total;
                row.append(title, quantity, itemStatus, tax, unitPrice, total);
                return row;
            }));
            applyItemPreviewColors();
            paper.querySelectorAll('.preview-item-status').forEach(element => { element.hidden = config.showItemStatus === false; });
        } catch (error) {
            if (requestId === previewRequest) status.textContent = error.message;
        }
    };

    const markDirty = () => { status.textContent = 'Unsaved changes'; };
    const updateRangeProgress = input => {
        const minimum = Number(input.min) || 0;
        const maximum = Number(input.max) || 100;
        const progress = ((Number(input.value) - minimum) / (maximum - minimum)) * 100;
        input.style.setProperty('--range-progress', `${progress}%`);
    };

    const save = async action => {
        const buttons = [...root.querySelectorAll('button')];
        buttons.forEach(button => button.disabled = true);
        try {
            const response = await fetch(root.dataset.saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf },
                body: JSON.stringify({ action, config })
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Unable to save invoice design.');
            if (data.draft) Object.assign(config, data.draft);
            syncInvoiceColors();
            previous = JSON.stringify(config);
            status.textContent = action === 'publish' ? 'Published design · New invoice views use this design' : action === 'reset' ? 'Draft reset to published design' : 'Draft saved';
            if (action === 'publish') root.dataset.hasPublished = '1';
            updatePreview();
            renderSections();
            toast('success', data.message);
        } catch (error) {
            toast('error', error.message || 'Unable to save invoice design.');
        } finally {
            buttons.forEach(button => button.disabled = false);
        }
    };

    titleInput.addEventListener('input', () => { config.title = titleInput.value; updatePreview(); markDirty(); });
    totalLabelInput.addEventListener('input', () => { config.totalLabel = totalLabelInput.value; updatePreview(); markDirty(); });
    footerInput.addEventListener('input', () => { config.footer = footerInput.value; updatePreview(); markDirty(); });
    termsInput.addEventListener('input', () => { config.terms = termsInput.value; updatePreview(); markDirty(); });
    showLogoInput.addEventListener('change', () => { config.showLogo = showLogoInput.checked; updatePreview(); markDirty(); });
    itemStatusInput.addEventListener('change', () => { config.showItemStatus = itemStatusInput.checked; updatePreview(); markDirty(); });
    addonsInput.addEventListener('change', () => { config.showAddons = addonsInput.checked; updatePreview(); markDirty(); });
    paymentStatusInput.addEventListener('change', () => { config.showPaymentStatus = paymentStatusInput.checked; updatePreview(); markDirty(); });
    templateNameInput.addEventListener('input', () => { config.templateName = templateNameInput.value; markDirty(); });
    previewModeInput.addEventListener('change', () => {
        config.previewMode = previewModeInput.value;
        if (config.previewMode === 'sample') config.previewOrderUuid = null;
        updatePreview();
        markDirty();
    });
    previewOrderInput.addEventListener('change', () => {
        config.previewMode = 'existing';
        config.previewOrderUuid = previewOrderInput.value || null;
        updatePreview();
        markDirty();
    });
    fontInput.addEventListener('change', () => { config.font = fontInput.value || 'Inter'; updatePreview(); markDirty(); });
    pageBackgroundInput.addEventListener('input', () => { config.pageBackgroundColor = pageBackgroundInput.value; updatePreview(); markDirty(); });
    showPageLinesInput.addEventListener('change', () => { config.showPageLines = showPageLinesInput.checked; updatePreview(); markDirty(); });
    pageLineColorInput.addEventListener('input', () => { config.pageLineColor = pageLineColorInput.value; updatePreview(); markDirty(); });
    const templateLayouts = { grid: 'compact', blue: 'modern' };
    document.querySelectorAll('[data-template]').forEach(button => button.addEventListener('click', () => {
        config.templateType = button.dataset.template;
        config.layout = templateLayouts[config.templateType];
        updatePreview();
        markDirty();
    }));
    marginInput.addEventListener('input', () => {
        config.margin = Math.min(30, Math.max(5, Number(marginInput.value) || 15));
        updatePreview();
        markDirty();
    });
    marginRange.addEventListener('input', () => {
        config.margin = Number(marginRange.value);
        updatePreview();
        markDirty();
    });
    document.querySelectorAll('[data-settings-tab]').forEach(button => button.addEventListener('click', () => {
        document.querySelectorAll('[data-settings-tab]').forEach(tab => tab.classList.toggle('active', tab === button));
        document.querySelectorAll('[data-settings-panel]').forEach(panel => panel.classList.toggle('d-none', panel.dataset.settingsPanel !== button.dataset.settingsTab));
    }));
    document.getElementById('invoice-zoom').addEventListener('input', event => {
        const zoom = Number(event.target.value);
        updateRangeProgress(event.target);
        paper.style.transform = `scale(${zoom / 100})`;
        document.getElementById('invoice-zoom-value').textContent = `${zoom}%`;
    });
    updateRangeProgress(document.getElementById('invoice-zoom'));
    document.getElementById('invoice-save').addEventListener('click', () => save('draft'));
    document.getElementById('invoice-publish').addEventListener('click', () => save('publish'));
    document.getElementById('invoice-print').addEventListener('click', () => window.print());
    document.getElementById('invoice-undo').addEventListener('click', () => {
        Object.assign(config, JSON.parse(previous));
        updatePreview();
        renderSections();
        status.textContent = 'Last saved changes restored';
    });
    document.getElementById('invoice-reset').addEventListener('click', async () => {
        if (!window.Swal?.fire) {
            toast('error', 'Confirmation is unavailable. The draft was not reset.');
            return;
        }
        const result = await window.Swal.fire({ title: 'Reset invoice draft?', text: 'The current draft will be replaced by the published design.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Reset draft', cancelButtonText: 'Keep editing' });
        if (!result.isConfirmed) return;
        if (root.dataset.hasPublished !== '1') {
            Object.assign(config, published);
            await save('draft');
        } else {
            await save('reset');
        }
    });

    updatePreview();
    renderSections();
});
