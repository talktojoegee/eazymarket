(function () {
    const root = document.getElementById('sellerLandingBuilder');
    if (!root) return;

    const state = JSON.parse(root.dataset.configuration || '{}');
    const languages = JSON.parse(root.dataset.languages || '[]');
    const variants = JSON.parse(root.dataset.variants || '{}');
    const sectionTemplates = JSON.parse(root.dataset.sectionTemplates || '{}');
    const mediaBase = root.dataset.mediaBase || '/storage';
    const sectionsRoot = root.querySelector('[data-role="sections"]');
    const seoRoot = root.querySelector('[data-role="seo"]');
    const languageRoot = root.querySelector('[data-role="languages"]');
    const form = document.getElementById('sellerLandingForm');
    const hidden = document.getElementById('sellerLandingConfiguration');
    const infoButtonTemplate = document.getElementById('sellerLandingFieldInfoTemplate')?.innerHTML.trim() || '';
    const asRecord = value => value && typeof value === 'object' && !Array.isArray(value) ? value : {};
    const normalizeEntity = entity => {
        entity.content = asRecord(entity.content);
        Object.keys(entity.content).forEach(language => {
            entity.content[language] = asRecord(entity.content[language]);
        });
        entity.settings = asRecord(entity.settings);
        entity.media = asRecord(entity.media);
        entity.mediaDefaults = asRecord(entity.mediaDefaults);
    };
    const normalizeSection = section => {
        normalizeEntity(section);
        section.items = Array.isArray(section.items) ? section.items : [];
        section.items.forEach(normalizeEntity);
    };
    state.seo = asRecord(state.seo);
    Object.keys(state.seo).forEach(language => {
        state.seo[language] = asRecord(state.seo[language]);
    });
    state.seoMedia = asRecord(state.seoMedia);
    state.seoMediaSettings = asRecord(state.seoMediaSettings);
    state.sections = Array.isArray(state.sections) ? state.sections : [];
    state.sections.forEach(normalizeSection);
    Object.values(sectionTemplates).forEach(normalizeSection);
    const expandedSections = new Set((state.sections || []).map(section => section.id));
    const pendingFiles = new Map();
    const removedFiles = new Set();
    const fileInputNames = new Set();
    let sectionPonds = [];
    let sectionTomSelects = [];
    let rebuildingFilePonds = false;
    let locale = languages[0]?.code || 'en';

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const label = (value) => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase());
    const uid = (type) => `${type}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const infoButton = (help) => infoButtonTemplate.replaceAll('__HELP_TEXT__', escapeHtml(help));
    const fieldLabel = (title, help) => `<div class="d-flex align-items-center mb-1"><label class="form-label mb-0">${escapeHtml(title)}</label>${infoButton(help)}</div>`;
    const contentHelp = (title) => `Customizes ${title.toLowerCase()} for the selected language. Leave it blank to use the HyperCommerce default.`;
    const settingHelp = (title) => `Controls ${title.toLowerCase()} for this section or item.`;
    const mediaHelp = (title) => `Upload a custom ${title.toLowerCase()}, or enable the static default option to use the HyperCommerce image.`;
    const content = (entity) => {
        entity.content ||= {};
        entity.content[locale] ||= {};
        return entity.content[locale];
    };

    function field(entity, fieldName, title, options = {}) {
        const values = options.scope === 'settings' ? (entity.settings ||= {}) : content(entity);
        const value = values[fieldName] ?? '';
        const path = options.scope === 'settings' ? 'settings' : 'content';
        const type = options.type || 'text';
        const help = options.help || (options.scope === 'settings' ? settingHelp(title) : contentHelp(title));
        if (type === 'textarea') {
            return `<div class="${options.className || 'col-md-6'}">${fieldLabel(title, help)}<textarea class="form-control" rows="${options.rows || 3}" data-edit="${path}" data-field="${fieldName}" placeholder="Uses the static HyperCommerce default">${escapeHtml(Array.isArray(value) ? value.join('\n') : value)}</textarea></div>`;
        }
        const bounds = type === 'number'
            ? `${options.min !== undefined ? ` min="${escapeHtml(options.min)}"` : ''}${options.max !== undefined ? ` max="${escapeHtml(options.max)}"` : ''}${options.step !== undefined ? ` step="${escapeHtml(options.step)}"` : ''}`
            : '';
        return `<div class="${options.className || 'col-md-6'}">${fieldLabel(title, help)}<input class="form-control" type="${type}" data-edit="${path}" data-field="${fieldName}" value="${escapeHtml(value)}"${bounds} placeholder="Uses the static HyperCommerce default"></div>`;
    }

    function pointsField(entity) {
        const values = Array.isArray(content(entity).points) ? content(entity).points : [];
        const options = values.map(value => `<option value="${escapeHtml(value)}" selected>${escapeHtml(value)}</option>`).join('');
        return `<div class="col-md-6">${fieldLabel('Bullet points', contentHelp('Bullet points'))}<select class="form-select" multiple data-tom-select-points data-placeholder="Type a point and press Enter">${options}</select></div>`;
    }

    function mediaField(section, item, fieldName, title) {
        const entity = item || section;
        const current = entity.media?.[fieldName] || '';
        entity.mediaDefaults ||= {};
        const useDefault = entity.mediaDefaults[fieldName] ?? !current;
        const preview = current && !current.startsWith('http') && !current.startsWith('/') ? `${mediaBase}/${current}` : current;
        const name = item
            ? `item_media[${section.id}][${item.id}][${fieldName}]`
            : `section_media[${section.id}][${fieldName}]`;
        const clearName = item
            ? `clear_item_media[${section.id}][${item.id}][${fieldName}]`
            : `clear_section_media[${section.id}][${fieldName}]`;
        return `<div class="col-md-6">${fieldLabel(title, mediaHelp(title))}<input type="file" name="${escapeHtml(name)}" accept="image/jpeg,image/png,image/webp" data-filepond-image data-image-url="${escapeHtml(preview)}"><div class="d-flex align-items-center mt-2"><label class="form-check mb-0"><input type="hidden" name="${escapeHtml(clearName)}" value="0"><input class="form-check-input" type="checkbox" name="${escapeHtml(clearName)}" value="1" data-clear-media="${fieldName}" ${useDefault ? 'checked' : ''}><span class="form-check-label">Use static default</span></label>${infoButton('When enabled, this image is loaded from the static HyperCommerce storefront assets.')}</div></div>`;
    }

    function itemFields(section, item, index) {
        const common = `<div class="d-flex justify-content-between align-items-center mb-3"><strong>${label(section.type)} item ${index + 1}</strong><div class="btn-list"><button type="button" class="btn btn-sm" data-action="item-up">↑</button><button type="button" class="btn btn-sm" data-action="item-down">↓</button><button type="button" class="btn btn-sm btn-outline-danger" data-action="remove-item">Remove</button></div></div>`;
        let fields = '';
        if (section.type === 'benefits') {
            fields = field(item, 'title', 'Title') + field(item, 'description', 'Description', {type: 'textarea'}) + mediaField(section, item, 'image', 'Benefit image');
        }
        if (section.type === 'steps') {
            fields = field(item, 'title', 'Title') + field(item, 'description', 'Description', {type: 'textarea'}) + pointsField(item) + mediaField(section, item, 'image', 'Step image');
        }
        if (section.type === 'testimonials') {
            fields = field(item, 'name', 'Name') + field(item, 'business', 'Business') + field(item, 'text', 'Quote', {type: 'textarea'}) + field(item, 'rating', 'Rating', {scope: 'settings', type: 'number', min: 1, max: 5, step: 0.1}) + mediaField(section, item, 'avatar', 'Avatar');
        }
        return `<div class="border rounded p-3 mb-3" data-item-id="${escapeHtml(item.id)}">${common}<div class="row g-3">${fields}</div></div>`;
    }

    function sectionFields(section) {
        if (section.type === 'registration_form') {
            return '<div class="alert alert-info mb-0">The seller registration form is required and continues to use its existing fields and validation.</div>';
        }
        if (section.type === 'hero') {
            return `<div class="row g-3">${field(section, 'badge', 'Badge')}${field(section, 'titleMain', 'Main title')}${field(section, 'titleAccent', 'Accent title')}${field(section, 'description', 'Description', {type: 'textarea'})}${field(section, 'ctaLabel', 'CTA label')}${field(section, 'ctaHref', 'CTA link', {scope: 'settings'})}${field(section, 'trust', 'Trust text')}${mediaField(section, null, 'desktopImage', 'Desktop image')}${mediaField(section, null, 'mobileImage', 'Mobile image')}</div>`;
        }
        let heading = '';
        if (section.type === 'benefits') heading = field(section, 'title', 'Section title') + field(section, 'subtitle', 'Subtitle');
        if (section.type === 'steps') heading = field(section, 'titleMain', 'Main title') + field(section, 'titleAccent', 'Accent title') + field(section, 'subtitle', 'Subtitle') + field(section, 'stepLabel', 'Step label');
        if (section.type === 'testimonials') heading = field(section, 'title', 'Section title') + field(section, 'subtitle', 'Subtitle') + field(section, 'verifiedLabel', 'Verified label');
        const items = (section.items || []).map((item, index) => itemFields(section, item, index)).join('');
        return `<div class="row g-3 mb-4">${heading}</div>${items}<button type="button" class="btn btn-outline-primary btn-sm" data-action="add-item"><i class="ti ti-plus me-1"></i>Add item</button>`;
    }

    function renderLanguages() {
        languageRoot.innerHTML = languages.map(language => `<button type="button" class="nav-link ${language.code === locale ? 'active' : ''}" data-locale="${language.code}">${escapeHtml(language.name)}</button>`).join('');
    }

    function renderSeo() {
        state.seo ||= {};
        state.seo[locale] ||= {};
        const value = state.seo[locale];
        seoRoot.innerHTML = ['title', 'description', 'keywords'].map(name => `<div class="col-md-${name === 'description' ? '12' : '6'}">${fieldLabel(label(name), `Customizes the page ${name} for the selected language. Leave it blank to use the HyperCommerce default.`)}${name === 'description' ? `<textarea class="form-control" data-seo="${name}" rows="3" placeholder="Uses the static HyperCommerce default">${escapeHtml(value[name] || '')}</textarea>` : `<input class="form-control" data-seo="${name}" value="${escapeHtml(value[name] || '')}" placeholder="Uses the static HyperCommerce default">`}</div>`).join('');
        initializeTooltips(seoRoot);
    }

    function renderSections() {
        rebuildingFilePonds = true;
        try {
            sectionPonds.forEach(pond => pond.destroy());
            sectionPonds = [];
            sectionTomSelects.forEach(tomSelect => tomSelect.destroy());
            sectionTomSelects = [];
        } finally {
            rebuildingFilePonds = false;
        }
        sectionsRoot.innerHTML = (state.sections || []).map((section, index) => {
            const locked = section.type === 'registration_form';
            const choices = (variants[section.type] || []).map(value => `<option value="${value}" ${value === section.variant ? 'selected' : ''}>${label(value)}</option>`).join('');
            const duplicate = !locked && section.type !== 'hero' ? '<button type="button" class="btn btn-sm" data-action="duplicate-section">Duplicate</button>' : '';
            const collapseId = `seller-landing-section-${section.id}`;
            const expanded = expandedSections.has(section.id);
            return `<div class="card mb-3" data-section-id="${escapeHtml(section.id)}"><div class="card-header"><button type="button" class="btn btn-sm btn-ghost-secondary me-2" draggable="true" data-drag-section="${escapeHtml(section.id)}" aria-label="Drag section"><i class="ti ti-grip-vertical"></i></button><div class="flex-grow-1"><div class="d-flex align-items-center gap-2"><span class="badge bg-primary-lt">${label(section.type)}</span><strong>${label(section.variant)}</strong>${section.preset ? '<span class="badge bg-green-lt">HyperCommerce default</span>' : ''}</div></div><div class="btn-list"><button type="button" class="btn btn-sm" data-action="section-up">↑</button><button type="button" class="btn btn-sm" data-action="section-down">↓</button>${duplicate}${locked ? '' : '<button type="button" class="btn btn-sm btn-outline-danger" data-action="remove-section">Remove</button>'}<button type="button" class="btn btn-icon btn-sm btn-ghost-secondary ${expanded ? '' : 'collapsed'}" data-bs-toggle="collapse" data-bs-target="#${escapeHtml(collapseId)}" aria-expanded="${expanded}" aria-controls="${escapeHtml(collapseId)}"><i class="ti ${expanded ? 'ti-chevron-up' : 'ti-chevron-down'}" data-collapse-icon></i></button></div></div><div id="${escapeHtml(collapseId)}" class="collapse ${expanded ? 'show' : ''}" data-section-collapse="${escapeHtml(section.id)}"><div class="card-body"><div class="row g-3 mb-4"><div class="col-md-6">${fieldLabel('Design', 'Selects the visual layout used to render this section on the customer website.')}<select class="form-select" data-edit="variant">${choices}</select></div><div class="col-md-6 d-flex align-items-end"><div class="d-flex align-items-center"><label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" data-edit="enabled" ${section.enabled ? 'checked' : ''} ${locked ? 'disabled' : ''}><span class="form-check-label">Visible</span></label>${infoButton('Controls whether this section is shown on the customer website.')}</div></div></div>${sectionFields(section)}</div></div></div>`;
        }).join('');
        initializeFilePonds(sectionsRoot, true);
        initializeTomSelects(sectionsRoot);
        initializeTooltips(sectionsRoot);
    }

    function initializeTooltips(container) {
        if (!window.bootstrap?.Tooltip) return;
        container.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => window.bootstrap.Tooltip.getOrCreateInstance(element));
    }

    function registerFilePondPlugins() {
        if (!window.FilePond) return;
        const plugins = [window.FilePondPluginImagePreview, window.FilePondPluginFileValidateType, window.FilePondPluginFileValidateSize].filter(Boolean);
        if (plugins.length) window.FilePond.registerPlugin(...plugins);
    }

    function initializeFilePonds(container, trackSectionPonds = false) {
        if (!window.FilePond) return;
        registerFilePondPlugins();
        container.querySelectorAll('input[data-filepond-image]').forEach(input => {
            fileInputNames.add(input.name);
            if (window.FilePond.find(input)) return;
            const pending = pendingFiles.get(input.name);
            const imageUrl = input.dataset.imageUrl || '';
            const files = pending ? [pending] : (imageUrl ? [{source: imageUrl, options: {type: 'local'}}] : []);
            const pond = window.FilePond.create(input, {
                allowMultiple: false,
                allowImagePreview: true,
                acceptedFileTypes: ['image/jpeg', 'image/png', 'image/webp'],
                maxFileSize: '5MB',
                credits: false,
                instantUpload: false,
                storeAsFile: false,
                files,
                server: {
                    load: (source, load, error) => fetch(source).then(response => response.blob()).then(load).catch(error)
                }
            });
            pond.on('updatefiles', items => {
                const selected = items.find(item => item.origin === window.FilePond.FileOrigin.INPUT && item.file instanceof File);
                if (selected) {
                    pendingFiles.set(input.name, selected.file);
                    removedFiles.delete(input.name);
                }
                if (!items.length) pendingFiles.delete(input.name);
                hidden.value = JSON.stringify(state);
            });
            pond.on('removefile', error => {
                if (error || rebuildingFilePonds) return;
                const selected = pond.getFiles().find(item => item.origin === window.FilePond.FileOrigin.INPUT && item.file instanceof File);
                if (selected) {
                    pendingFiles.set(input.name, selected.file);
                    removedFiles.delete(input.name);
                } else {
                    pendingFiles.delete(input.name);
                    removedFiles.add(input.name);
                }
                hidden.value = JSON.stringify(state);
            });
            if (trackSectionPonds) sectionPonds.push(pond);
        });
    }

    function initializeTomSelects(container) {
        if (!window.TomSelect) return;
        container.querySelectorAll('[data-tom-select-points]').forEach(select => {
            if (select.tomselect) return;
            const item = findContext(select).item;
            if (!item) return;
            const tomSelect = new window.TomSelect(select, {
                create: true,
                persist: false,
                placeholder: select.dataset.placeholder,
                onChange: values => {
                    content(item).points = Array.isArray(values) ? values : (values ? [values] : []);
                    hidden.value = JSON.stringify(state);
                }
            });
            sectionTomSelects.push(tomSelect);
        });
    }

    function render() {
        renderLanguages();
        renderSeo();
        renderSections();
        hidden.value = JSON.stringify(state);
    }

    function findContext(target) {
        const sectionElement = target.closest('[data-section-id]');
        const sectionIndex = sectionElement ? state.sections.findIndex(section => section.id === sectionElement.dataset.sectionId) : -1;
        const section = state.sections[sectionIndex];
        const itemElement = target.closest('[data-item-id]');
        const itemIndex = section && itemElement ? section.items.findIndex(item => item.id === itemElement.dataset.itemId) : -1;
        return {section, sectionIndex, item: itemIndex >= 0 ? section.items[itemIndex] : null, itemIndex};
    }

    function confirmRemoval(title, text, callback) {
        if (typeof window.Swal === 'undefined') {
            Toast.fire({icon: 'error', title: 'The confirmation dialog is unavailable'});
            return;
        }
        window.Swal.fire({
            title,
            text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, remove it',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d63939'
        }).then(result => {
            if (result.isConfirmed) callback();
        });
    }

    function clearPendingFiles(sectionId, itemId = null) {
        const sectionToken = `[${sectionId}]`;
        const itemToken = itemId ? `[${itemId}]` : null;
        pendingFiles.forEach((file, name) => {
            if (name.includes(sectionToken) && (!itemToken || name.includes(itemToken))) pendingFiles.delete(name);
        });
    }

    root.addEventListener('input', event => {
        const target = event.target;
        if (target.dataset.seo) {
            state.seo[locale][target.dataset.seo] = target.value;
            return;
        }
        if (!target.dataset.edit) return;
        const {section, item} = findContext(target);
        const entity = item || section;
        if (!entity) return;
        if (target.dataset.edit === 'variant') section.variant = target.value;
        if (target.dataset.edit === 'enabled') section.enabled = target.checked;
        if (target.dataset.edit === 'content') {
            const value = target.dataset.field === 'points' ? target.value.split('\n').filter(Boolean) : target.value;
            content(entity)[target.dataset.field] = value;
        }
        if (target.dataset.edit === 'settings') {
            entity.settings ||= {};
            if (target.type === 'number') {
                if (target.value === '') {
                    entity.settings[target.dataset.field] = '';
                    hidden.value = JSON.stringify(state);
                    return;
                }
                const minimum = target.min === '' ? Number.NEGATIVE_INFINITY : Number(target.min);
                const maximum = target.max === '' ? Number.POSITIVE_INFINITY : Number(target.max);
                const value = Math.min(maximum, Math.max(minimum, Number(target.value)));
                entity.settings[target.dataset.field] = value;
                target.value = String(value);
            } else {
                entity.settings[target.dataset.field] = target.value;
            }
        }
        hidden.value = JSON.stringify(state);
    });

    root.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.dataset.locale) {
            locale = button.dataset.locale;
            render();
            return;
        }
        const action = button.dataset.action;
        if (!action) return;
        const context = findContext(button);
        if (action === 'add-section') {
            const type = root.querySelector('[data-role="new-section-type"]').value;
            if (type === 'hero' && state.sections.some(section => section.type === 'hero')) {
                Toast.fire({icon: 'warning', title: 'Only one hero section is allowed'});
                return;
            }
            const section = JSON.parse(JSON.stringify(sectionTemplates[type] || {type, variant: variants[type][0], enabled: true, content: {}, settings: {}, media: {}, items: []}));
            section.id = uid(type);
            section.items = (section.items || []).map(item => ({...item, id: uid('item')}));
            state.sections.push(section);
            expandedSections.add(section.id);
        }
        if (action === 'remove-section') {
            confirmRemoval('Remove this section?', 'This section and its customized content will be removed when you save.', () => {
                expandedSections.delete(context.section.id);
                clearPendingFiles(context.section.id);
                state.sections.splice(context.sectionIndex, 1);
                render();
            });
            return;
        }
        if (action === 'duplicate-section') {
            const duplicate = JSON.parse(JSON.stringify(context.section));
            duplicate.id = uid(duplicate.type);
            duplicate.items = (duplicate.items || []).map(item => ({...item, id: uid('item')}));
            state.sections.splice(context.sectionIndex + 1, 0, duplicate);
            expandedSections.add(duplicate.id);
        }
        if (action === 'section-up' && context.sectionIndex > 0) [state.sections[context.sectionIndex - 1], state.sections[context.sectionIndex]] = [state.sections[context.sectionIndex], state.sections[context.sectionIndex - 1]];
        if (action === 'section-down' && context.sectionIndex < state.sections.length - 1) [state.sections[context.sectionIndex + 1], state.sections[context.sectionIndex]] = [state.sections[context.sectionIndex], state.sections[context.sectionIndex + 1]];
        if (action === 'add-item') {
            context.section.items ||= [];
            context.section.items.push({id: uid('item'), content: {}, settings: {}, media: {}});
        }
        if (action === 'remove-item') {
            confirmRemoval('Remove this item?', 'This item and its customized content will be removed when you save.', () => {
                clearPendingFiles(context.section.id, context.item.id);
                context.section.items.splice(context.itemIndex, 1);
                render();
            });
            return;
        }
        if (action === 'item-up' && context.itemIndex > 0) [context.section.items[context.itemIndex - 1], context.section.items[context.itemIndex]] = [context.section.items[context.itemIndex], context.section.items[context.itemIndex - 1]];
        if (action === 'item-down' && context.itemIndex < context.section.items.length - 1) [context.section.items[context.itemIndex + 1], context.section.items[context.itemIndex]] = [context.section.items[context.itemIndex], context.section.items[context.itemIndex + 1]];
        render();
    });

    root.addEventListener('change', event => {
        const target = event.target;
        if (target.dataset.clearSeoMedia) {
            state.seoMedia ||= {};
            state.seoMediaSettings ||= {};
            state.seoMediaSettings.imageUseDefault = target.checked;
            hidden.value = JSON.stringify(state);
            return;
        }
        if (!target.dataset.clearMedia) return;
        const {section, item} = findContext(target);
        const entity = item || section;
        entity.media ||= {};
        entity.mediaDefaults ||= {};
        entity.mediaDefaults[target.dataset.clearMedia] = target.checked;
        hidden.value = JSON.stringify(state);
    });

    form.addEventListener('submit', () => {
        hidden.value = JSON.stringify(state);
    }, true);

    form.addEventListener('formdata', event => {
        fileInputNames.forEach(name => event.formData.delete(name));
        pendingFiles.forEach((file, name) => {
            event.formData.append(name, file, file.name);
        });
        removedFiles.forEach(name => {
            const removalName = name.replace(/^seo_media/, 'remove_seo_media')
                .replace(/^section_media/, 'remove_section_media')
                .replace(/^item_media/, 'remove_item_media');
            event.formData.set(removalName, '1');
        });
    });

    root.addEventListener('dragstart', event => {
        const handle = event.target.closest('[data-drag-section]');
        if (handle) event.dataTransfer.setData('text/plain', handle.dataset.dragSection);
    });

    root.addEventListener('dragover', event => {
        if (event.target.closest('[data-section-id]')) event.preventDefault();
    });

    root.addEventListener('drop', event => {
        const target = event.target.closest('[data-section-id]');
        const sourceId = event.dataTransfer.getData('text/plain');
        if (!target || !sourceId || sourceId === target.dataset.sectionId) return;
        event.preventDefault();
        const sourceIndex = state.sections.findIndex(section => section.id === sourceId);
        const targetIndex = state.sections.findIndex(section => section.id === target.dataset.sectionId);
        const [moved] = state.sections.splice(sourceIndex, 1);
        state.sections.splice(targetIndex, 0, moved);
        render();
    });

    root.addEventListener('shown.bs.collapse', event => {
        const sectionId = event.target.dataset.sectionCollapse;
        if (sectionId) expandedSections.add(sectionId);
        const iconElement = event.target.previousElementSibling?.querySelector('[data-collapse-icon]') || root.querySelector(`[data-bs-target="#${event.target.id}"] [data-collapse-icon]`);
        if (iconElement) iconElement.className = 'ti ti-chevron-up';
    });

    root.addEventListener('hidden.bs.collapse', event => {
        const sectionId = event.target.dataset.sectionCollapse;
        if (sectionId) expandedSections.delete(sectionId);
        const iconElement = event.target.previousElementSibling?.querySelector('[data-collapse-icon]') || root.querySelector(`[data-bs-target="#${event.target.id}"] [data-collapse-icon]`);
        if (iconElement) iconElement.className = 'ti ti-chevron-down';
    });

    render();
    if (!window.TomSelect && document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initializeTomSelects(sectionsRoot), {once: true});
    }
    initializeFilePonds(root);
    initializeTooltips(root);
})();
