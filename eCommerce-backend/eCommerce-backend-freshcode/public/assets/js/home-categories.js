(function () {
    const root = document.getElementById('home-categories-manager');
    if (!root) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const cfg = {
        listUrl: root.dataset.listUrl,
        createUrl: root.dataset.createUrl,
        orderUrl: root.dataset.orderUrl,
        itemBase: root.dataset.itemBase,
        globalShowUrl: root.dataset.globalShowUrl,
        globalSaveUrl: root.dataset.globalSaveUrl,
        canEdit: root.dataset.canEdit === '1',
        emptyText: root.dataset.emptyText || 'No home categories yet.',
    };
    const L = safeJson(root.dataset.labels, {});
    const T = (k, fallback) => (L[k] != null && L[k] !== '' ? L[k] : fallback);

    if (window.axios) {
        window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
    }
    if (window.FilePond) {
        try { window.FilePond.registerPlugin(window.FilePondPluginImagePreview, window.FilePondPluginFileValidateType, window.FilePondPluginFileValidateSize); } catch (e) { /* already registered */ }
    }

    const listEl = document.getElementById('hc-list');
    const createTitleEl = document.getElementById('hc-create-title');
    const createButton = document.getElementById('hc-create');
    const countEl = document.getElementById('hc-count');
    const editorEl = document.getElementById('hc-editor');
    const mediaControlTemplate = document.getElementById('hc-media-control-template');
    const allRow = document.getElementById('hc-all-row');
    const allTitleEl = document.getElementById('hc-all-title');
    const sortPlatformEl = document.getElementById('hc-sort-platform');

    const MEDIA_FIELDS = {
        appIcon: 'app_icon',
        appActiveIcon: 'app_active_icon',
        appBackgroundImage: 'app_background_image',
        appTabletBackgroundImage: 'app_tablet_background_image',
        desktopIcon: 'desktop_icon',
        desktopActiveIcon: 'desktop_active_icon',
        desktopBackgroundImage: 'desktop_background_image',
        desktopTabletBackgroundImage: 'desktop_tablet_background_image',
        desktopMobileBackgroundImage: 'desktop_mobile_background_image',
    };
    const BG = [['none', T('none', 'None')], ['gradient', T('gradient', 'Gradient')], ['image', T('image', 'Image')], ['lottie', T('lottie', 'Lottie')]];

    let chosen = [];
    let sortable = null;
    let selected = null;     // 'all' | category id
    let appearance = {};     // normalized appearance for the selected target
    let labelsTs = null;
    let segCounter = 0;
    let sortPlatform = 'app';
    const platformDrafts = {app: null, web: null};
    const editorHome = editorEl.parentElement;
    let integration = null;
    let appearanceBaseline = '';
    let orderBaseline = '';
    let integrationSavedPreview = null;
    root.dataset.unsaved = '0';

    const esc = s => { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    const toast = (icon, title) => { Toast.fire({ icon, title }); };
    function safeJson(s, d) { try { return JSON.parse(s); } catch (e) { return d; } }
    function ensureLottiePlayer() {
        if (window.customElements && window.customElements.get('lottie-player')) return;
        if (document.querySelector('script[data-hb-lottie-player]')) return;
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js';
        script.defer = true;
        script.dataset.hbLottiePlayer = '1';
        script.addEventListener('error', () => script.remove(), {once: true});
        document.head.appendChild(script);
    }

    function appearanceSignature() {
        const fileState = object => Object.fromEntries(Object.entries(object || {}).map(([key, file]) => [
            key,
            file ? [file.name, file.size, file.lastModified] : null,
        ]));
        return JSON.stringify({
            target: appearance.target,
            title: appearance.title,
            slug: appearance.slug,
            isActive: appearance.isActive,
            showOnApp: appearance.showOnApp,
            showOnWeb: appearance.showOnWeb,
            platforms: appearance.platforms,
            searchLabels: appearance.searchLabels,
            files: fileState(appearance._files),
            remove: appearance._remove,
            copy: appearance._copy,
        });
    }

    function orderSignature() {
        return JSON.stringify(chosen.map(item => Number(item.id)));
    }

    function integratedPreviewData() {
        if (!appearance.target) return null;
        const previewPlatforms = JSON.parse(JSON.stringify(appearance.platforms || {}));
        previewPlatforms.app = Object.assign({}, previewPlatforms.app, {
            background_image: previewFor('appBackgroundImage'),
            mobile_background_image: previewFor('appBackgroundImage'),
            tablet_background_image: previewFor('appTabletBackgroundImage'),
        });
        previewPlatforms.desktop = Object.assign({}, previewPlatforms.desktop, {
            background_image: previewFor('desktopBackgroundImage'),
            desktop_background_image: previewFor('desktopBackgroundImage'),
            tablet_background_image: previewFor('desktopTabletBackgroundImage'),
            mobile_background_image: previewFor('desktopMobileBackgroundImage'),
        });
        const common = {
            title: appearance.title || '',
            home_appearance: previewPlatforms,
            homeAppearance: previewPlatforms,
            search_labels: appearance.searchLabels || [],
            searchLabels: appearance.searchLabels || [],
        };
        if (appearance.target === 'all') return Object.assign(common, {
            icon: previewFor('appIcon'),
            activeIcon: previewFor('appActiveIcon'),
            backgroundImage: previewFor('appBackgroundImage'),
            appTabletBackgroundImage: previewFor('appTabletBackgroundImage'),
            desktopIcon: previewFor('desktopIcon'),
            desktopActiveIcon: previewFor('desktopActiveIcon'),
            desktopBackgroundImage: previewFor('desktopBackgroundImage'),
            desktopTabletBackgroundImage: previewFor('desktopTabletBackgroundImage'),
            desktopMobileBackgroundImage: previewFor('desktopMobileBackgroundImage'),
        });
        return Object.assign(common, {
            id: Number(appearance.target),
            slug: appearance.slug || '',
            is_active: appearance.isActive,
            show_on_app: appearance.showOnApp,
            show_on_web: appearance.showOnWeb,
            icon: previewFor('appIcon'),
            active_icon: previewFor('appActiveIcon'),
            background_image: previewFor('appBackgroundImage'),
            app_tablet_background_image: previewFor('appTabletBackgroundImage'),
            desktop_icon: previewFor('desktopIcon'),
            desktop_active_icon: previewFor('desktopActiveIcon'),
            desktop_background_image: previewFor('desktopBackgroundImage'),
            desktop_tablet_background_image: previewFor('desktopTabletBackgroundImage'),
            desktop_mobile_background_image: previewFor('desktopMobileBackgroundImage'),
        });
    }

    function notifyIntegrationState() {
        if (!appearance.target) return;
        const dirty = appearanceBaseline !== appearanceSignature() || orderBaseline !== orderSignature();
        root.dataset.unsaved = dirty ? '1' : '0';
        if (!integration) return;
        document.dispatchEvent(new CustomEvent('home-navbar:integration-state', {
            detail: {
                owner: integration.owner,
                dirty,
                platform: integration.platform,
                target: appearance.target,
                data: integratedPreviewData(),
            },
        }));
    }

    function markSavedState() {
        appearanceBaseline = appearanceSignature();
        orderBaseline = orderSignature();
        notifyIntegrationState();
    }

    function restoreIntegrationDraft() {
        if (!integration) return;
        if (integrationSavedPreview) {
            document.dispatchEvent(new CustomEvent('home-navbar:integration-reset', {
                detail: {owner: integration.owner, target: appearance.target, data: integrationSavedPreview},
            }));
        }
        if (orderBaseline) {
            const byId = new Map(chosen.map(item => [Number(item.id), item]));
            chosen = JSON.parse(orderBaseline).map(Number).map(id => byId.get(id)).filter(Boolean);
            platformDrafts[sortPlatform] = chosen.slice();
            renderList();
            broadcastItems([], sortPlatform, chosen);
        }
    }

    /* ---------------- membership list ---------------- */
    function load(force = false) {
        const platform = sortPlatform;
        if (!force && Array.isArray(platformDrafts[platform])) {
            chosen = platformDrafts[platform].slice();
            renderList();
            updateSortPlatformButtons();
            broadcastItems();
            orderBaseline = orderSignature();
            notifyIntegrationState();
            return;
        }
        window.axios.get(cfg.listUrl, {params: {platform}})
            .then(r => {
                const items = (r.data.data || []).map(normalizeListItem);
                platformDrafts[platform] = items.slice();
                if (sortPlatform !== platform) return;
                chosen = items;
                renderList();
                updateSortPlatformButtons();
                broadcastItems([], platform, items);
                orderBaseline = orderSignature();
                notifyIntegrationState();
            })
            .catch(() => { renderList(); });
    }

    function updateSortPlatformButtons() {
        if (!sortPlatformEl) return;
        sortPlatformEl.querySelectorAll('[data-sort-platform]').forEach(button => {
            const active = button.dataset.sortPlatform === sortPlatform;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function normalizeListItem(item) {
        return Object.assign({}, item, {
            id: Number(item.id),
            title: item.title || '',
            is_active: item.is_active !== false,
            show_on_app: item.show_on_app !== false,
            show_on_web: item.show_on_web !== false,
        });
    }

    function itemBelongsToPlatform(item, platform) {
        return platform === 'web' ? item.show_on_web !== false : item.show_on_app !== false;
    }

    function syncItemAcrossDrafts(item) {
        Object.keys(platformDrafts).forEach(platform => {
            const draft = platformDrafts[platform];
            if (!Array.isArray(draft)) return;
            const index = draft.findIndex(existing => Number(existing.id) === Number(item.id));
            if (!itemBelongsToPlatform(item, platform)) {
                if (index !== -1) draft.splice(index, 1);
                return;
            }
            if (index === -1) draft.push(item);
            else draft[index] = Object.assign({}, draft[index], item);
        });
        chosen = (platformDrafts[sortPlatform] || []).slice();
    }

    function renderList() {
        countEl.textContent = chosen.length;
        listEl.innerHTML = chosen.length
            ? chosen.map((c, i) =>
                '<div class="card card-sm mb-2 hc-row' + (selected === c.id ? ' hc-selected' : '') + '" data-id="' + c.id + '"><div class="card-body d-flex align-items-center gap-2 py-2">' +
                '<i class="ti ti-grip-vertical hc-grip text-secondary" style="cursor:grab" aria-hidden="true"></i>' +
                '<span class="badge bg-primary-lt">' + (i + 1) + '</span>' +
                '<span class="flex-fill hc-row-title">' + esc(c.title) + '<small class="d-block text-secondary">' +
                (c.show_on_app ? 'App' : '') + (c.show_on_app && c.show_on_web ? ' · ' : '') + (c.show_on_web ? 'Web' : '') +
                (!c.is_active ? ' · Inactive' : '') + '</small></span>' +
                '<button type="button" class="btn btn-ghost-danger btn-icon btn-sm hc-remove" aria-label="remove"><i class="ti ti-x" aria-hidden="true"></i></button>' +
                '</div></div>').join('')
            : '<div class="text-secondary small py-2">' + esc(cfg.emptyText) + '</div>';

        listEl.querySelectorAll('.hc-row').forEach(rowEl => {
            rowEl.addEventListener('click', e => {
                if (e.target.closest('.hc-remove') || e.target.closest('.hc-grip')) return;
                selectTarget(+rowEl.dataset.id);
            });
        });
        listEl.querySelectorAll('.hc-remove').forEach(b => b.addEventListener('click', event => {
            event.stopPropagation();
            const id = +b.closest('.hc-row').dataset.id;
            if (!cfg.canEdit) return;
            if (!window.Swal) {
                toast('error', 'Confirmation dialog is unavailable');
                return;
            }

            const item = chosen.find(entry => entry.id === id);
            window.Swal.fire({
                title: 'Delete custom navbar item?',
                text: (item ? '“' + item.title + '”' : 'This navbar item') + ' and its saved home layouts will be permanently removed.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                focusCancel: true,
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-ghost-secondary me-2',
                },
            }).then(result => {
                if (!result.isConfirmed) return;
                b.disabled = true;
                window.axios.delete(cfg.itemBase + '/' + id)
                    .then(r => {
                        toast('success', r.data.message || 'Navbar item deleted');
                        Object.keys(platformDrafts).forEach(platform => {
                            if (Array.isArray(platformDrafts[platform])) {
                                platformDrafts[platform] = platformDrafts[platform].filter(entry => entry.id !== id);
                            }
                        });
                        chosen = (platformDrafts[sortPlatform] || []).slice();
                        if (selected === id) selectTarget('all');
                        renderList();
                        broadcastItems([id]);
                    })
                    .catch(err => {
                        b.disabled = false;
                        toast('error', err.response?.data?.message || 'Delete failed');
                    });
            });
        }));
        initSortable();
    }

    function initSortable() {
        if (!window.Sortable) return;
        if (sortable) { try { sortable.destroy(); } catch (e) { /* noop */ } }
        sortable = window.Sortable.create(listEl, {
            handle: '.hc-grip', animation: 150, ghostClass: 'hc-ghost',
            disabled: !cfg.canEdit,
            onEnd: () => {
                syncChosenOrderFromDom();
                platformDrafts[sortPlatform] = chosen.slice();
                updateOrderBadges();
                broadcastItems();
                notifyIntegrationState();
            },
        });
    }

    function syncChosenOrderFromDom() {
        const byId = new Map(chosen.map(item => [Number(item.id), item]));
        chosen = Array.from(listEl.querySelectorAll('.hc-row'))
            .map(row => byId.get(Number(row.dataset.id)))
            .filter(Boolean);
    }

    function updateOrderBadges() {
        listEl.querySelectorAll('.hc-row').forEach((row, index) => {
            const badge = row.querySelector('.badge');
            if (badge) badge.textContent = String(index + 1);
        });
    }

    function broadcastItems(deletedIds = [], platform = sortPlatform, items = chosen) {
        document.dispatchEvent(new CustomEvent('home-navbar:items-saved', {
            detail: {categories: items.slice(), platform, deleted_ids: deletedIds}
        }));
    }

    function createItem(requestedTitle) {
        const title = String(requestedTitle ?? createTitleEl?.value ?? '').trim();
        if (!title || !cfg.canEdit) return;
        if (createButton) createButton.disabled = true;
        const integratedButton = editorEl.querySelector('#hc-integrated-navbar-add');
        if (integratedButton) integratedButton.disabled = true;
        window.axios.post(cfg.createUrl, {title, show_on_app: true, show_on_web: true, platform: sortPlatform})
            .then(r => {
                const item = normalizeListItem(r.data.data || {});
                syncItemAcrossDrafts(item);
                if (createTitleEl) createTitleEl.value = '';
                renderList();
                broadcastItems();
                selectTarget(item.id);
                if (integration) {
                    document.dispatchEvent(new CustomEvent('home-navbar:integration-selection', {
                        detail: {owner: integration.owner, target: item.id},
                    }));
                }
                toast('success', r.data.message || 'Navbar item created');
            })
            .catch(err => toast('error', err.response?.data?.message || 'Create failed'))
            .finally(() => {
                if (createButton) createButton.disabled = !cfg.canEdit;
                const integratedButton = editorEl.querySelector('#hc-integrated-navbar-add');
                if (integratedButton) integratedButton.disabled = !cfg.canEdit;
            });
    }

    if (createButton) createButton.addEventListener('click', () => createItem());
    if (createTitleEl) createTitleEl.addEventListener('keydown', event => {
        if (event.key === 'Enter') { event.preventDefault(); createItem(); }
    });
    if (sortPlatformEl) sortPlatformEl.querySelectorAll('[data-sort-platform]').forEach(button => {
        button.addEventListener('click', () => {
            const platform = button.dataset.sortPlatform;
            if (!['app', 'web'].includes(platform) || platform === sortPlatform) return;
            syncChosenOrderFromDom();
            platformDrafts[sortPlatform] = chosen.slice();
            sortPlatform = platform;
            updateSortPlatformButtons();
            load();
        });
    });

    function saveOrder(options) {
        const settings = options || {};
        syncChosenOrderFromDom();
        const platform = sortPlatform;
        const itemIds = chosen.map(c => c.id);
        platformDrafts[platform] = chosen.slice();
        return window.axios.post(cfg.orderUrl, {platform, item_ids: itemIds})
            .then(r => {
                if (!settings.silent) toast(r.data.success === false ? 'error' : 'success', r.data.message);
                if (r.data.success !== false) {
                    const saved = Array.isArray(r.data.data) ? r.data.data : [];
                    const savedItems = saved.map(normalizeListItem);
                    platformDrafts[platform] = savedItems.slice();
                    if (sortPlatform === platform) {
                        chosen = savedItems;
                        renderList();
                        broadcastItems([], platform, savedItems);
                    }
                    orderBaseline = orderSignature();
                    notifyIntegrationState();
                }
                return r;
            })
            .catch(err => {
                if (!settings.silent) toast('error', err.response?.data?.message || 'Save failed');
                throw err;
            });
    }

    document.getElementById('hc-save').addEventListener('click', () => saveOrder());

    /* ---------------- selection + appearance ---------------- */
    function selectTarget(target) {
        selected = target;
        markSelection();
        loadAppearance(target);
    }

    function markSelection() {
        if (allRow) allRow.classList.toggle('hc-selected', selected === 'all');
        listEl.querySelectorAll('.hc-row').forEach(rowEl => {
            rowEl.classList.toggle('hc-selected', selected === +rowEl.dataset.id);
        });
    }

    function loadAppearance(target) {
        destroyLabelsTs();
        editorEl.innerHTML = '<div class="text-muted py-3"><span class="spinner-border spinner-border-sm me-2"></span>' + esc(T('appearance', 'Appearance')) + '…</div>';
        const url = target === 'all' ? cfg.globalShowUrl : cfg.itemBase + '/' + target;
        window.axios.get(url)
            .then(r => {
                appearance = normalize(target, r.data.data || {});
                if (integration) appearance.activePlatform = integration.platform === 'web' ? 'desktop' : 'app';
                appearanceBaseline = appearanceSignature();
                integrationSavedPreview = integratedPreviewData();
                renderEditor();
                notifyIntegrationState();
            })
            .catch(() => { editorEl.innerHTML = '<div class="text-danger py-3">' + esc(T('appearance', 'Appearance')) + ' — failed to load</div>'; });
    }

    function normalize(target, d) {
        const legacyType = (d.background_type || d.backgroundType || '') === 'image'
            ? 'image'
            : ((d.background_type || d.backgroundType || '') === 'color' ? 'gradient' : 'none');
        const legacyColor = d.background_color || d.backgroundColor || '#ffffff';
        const stored = d.home_appearance || d.homeAppearance || {};
        const appDefaults = {
            background_type: legacyType,
            gradient_start: legacyColor,
            gradient_end: legacyColor,
            gradient_angle: 90,
            font_color: d.font_color || d.fontColor || '#475569',
            active_font_color: '#111827',
        };
        const appStyle = Object.assign({}, appDefaults, stored.app || {});
        const desktopStyle = Object.assign({}, appStyle, stored.desktop || {});
        const a = {
            target: target,
            activePlatform: 'app',
            platforms: {app: appStyle, desktop: desktopStyle},
            _files: {},
            _previews: {},
            _remove: {},
            _copy: {},
        };
        if (target === 'all') {
            a.title = d.title || '';
            a.appIcon = d.icon || '';
            a.appActiveIcon = d.activeIcon || '';
            a.appBackgroundImage = d.backgroundImage || '';
            a.appTabletBackgroundImage = d.appTabletBackgroundImage || '';
            a.desktopIcon = d.desktopIcon || d.icon || '';
            a.desktopActiveIcon = d.desktopActiveIcon || d.activeIcon || d.desktopIcon || d.icon || '';
            a.desktopBackgroundImage = d.desktopBackgroundImage || '';
            a.desktopTabletBackgroundImage = d.desktopTabletBackgroundImage || '';
            a.desktopMobileBackgroundImage = d.desktopMobileBackgroundImage || '';
            a.searchLabels = Array.isArray(d.searchLabels) ? d.searchLabels : [];
        } else {
            a.title = d.title || '';
            a.slug = d.slug || '';
            a.isActive = d.is_active !== false;
            a.showOnApp = d.show_on_app !== false;
            a.showOnWeb = d.show_on_web !== false;
            a.appIcon = d.icon || '';
            a.appActiveIcon = d.active_icon || '';
            a.appBackgroundImage = d.background_image || '';
            a.appTabletBackgroundImage = d.app_tablet_background_image || '';
            a.desktopIcon = d.desktop_icon || d.icon || '';
            a.desktopActiveIcon = d.desktop_active_icon || d.active_icon || d.desktop_icon || d.icon || '';
            a.desktopBackgroundImage = d.desktop_background_image || '';
            a.desktopTabletBackgroundImage = d.desktop_tablet_background_image || '';
            a.desktopMobileBackgroundImage = d.desktop_mobile_background_image || '';
            a.searchLabels = Array.isArray(d.search_labels) ? d.search_labels : [];
        }
        return a;
    }

    function previewFor(key) {
        if (appearance._remove[key]) return '';
        return appearance._previews[key] || appearance[key] || '';
    }

    function resolvedBackgroundKey(key) {
        if (previewFor(key)) return key;
        const fallback = {
            appTabletBackgroundImage: ['appBackgroundImage'],
            desktopTabletBackgroundImage: ['desktopBackgroundImage'],
            desktopMobileBackgroundImage: ['desktopTabletBackgroundImage', 'desktopBackgroundImage'],
        }[key] || [];
        return fallback.map(resolvedBackgroundKey).find(sourceKey => previewFor(sourceKey)) || key;
    }

    function resolvedBackgroundPreview(key) {
        return previewFor(resolvedBackgroundKey(key));
    }

    function imageSlot(key, label, preview, isLottie, options) {
        const settings = options || {};
        const displayPreview = preview || settings.fallbackPreview || '';
        if (isLottie && displayPreview) ensureLottiePlayer();
        const inner = displayPreview
            ? (isLottie
                ? '<lottie-player class="hc-lottie-preview" src="' + esc(displayPreview) + '" background="transparent" speed="1" loop autoplay></lottie-player>'
                : '<img src="' + esc(displayPreview) + '" alt="">')
            : '<i class="ti ' + (isLottie ? 'ti-player-play' : 'ti-photo') + ' text-muted"></i>';
        const copySources = settings.copySources || [];
        const copyControl = copySources.length
            ? '<div class="hc-media-copy"><select class="form-select form-select-sm" data-copy-source-for="' + key + '"' + (cfg.canEdit ? '' : ' disabled') + '>' +
              copySources.map(source => '<option value="' + source[0] + '">' + esc(source[1]) + '</option>').join('') +
              '</select><button type="button" class="btn btn-sm btn-outline-secondary" data-copy-media="' + key + '"' + (cfg.canEdit ? '' : ' disabled') + '><i class="ti ti-copy me-1"></i>Copy</button></div>'
            : '';
        return '<div class="hc-field hc-media-field">' +
            '<label class="form-label' + (settings.required ? ' required' : '') + '">' + esc(label) + '</label>' +
            '<div class="hc-slot-wrap">' +
            '<button type="button" class="hc-img-slot' + (!preview && settings.fallbackPreview ? ' is-fallback' : '') + '" data-slot="' + key + '"' + (cfg.canEdit ? '' : ' disabled') + '>' + inner + (!preview && settings.fallbackPreview ? '<span class="hc-fallback-badge">Fallback</span>' : '') + '</button>' +
            (preview ? '<button type="button" class="btn btn-sm btn-ghost-danger hc-img-remove" data-slot="' + key + '"' + (cfg.canEdit ? '' : ' disabled') + '><i class="ti ti-trash me-1"></i>' + esc(T('remove', 'Remove')) + '</button>' : '') +
            '</div>' + (settings.hint ? '<div class="form-hint">' + esc(settings.hint) + '</div>' : '') + copyControl + '</div>';
    }

    function mediaConfigLocation(key) {
        return {
            appBackgroundImage: ['app', 'background_media_mobile'],
            appTabletBackgroundImage: ['app', 'background_media_tablet'],
            desktopBackgroundImage: ['desktop', 'background_media_desktop'],
            desktopTabletBackgroundImage: ['desktop', 'background_media_tablet'],
            desktopMobileBackgroundImage: ['desktop', 'background_media_mobile'],
        }[key] || null;
    }

    function setMediaControlField(control, fieldName, value, configName) {
        const fieldEl = control.querySelector('[data-media-control-field="' + fieldName + '"]');
        if (!fieldEl) return;
        fieldEl.name = configName + '[' + fieldName + ']';
        fieldEl.value = value == null ? '' : value;
    }

    function mediaControlSlot(key, label, preview, isLottie, options) {
        if (!mediaControlTemplate) return imageSlot(key, label, preview, isLottie, options);

        const settings = options || {};
        const location = mediaConfigLocation(key);
        const platform = location[0];
        const configKey = location[1];
        const configName = 'home_appearance[' + platform + '][' + configKey + ']';
        const style = appearance.platforms[platform];
        const mediaType = isLottie ? 'lottie' : 'image';
        const config = Object.assign({
            media_type: mediaType,
            aspect_ratio: 'original',
            aspect_width: 100,
            aspect_width_unit: '%',
            aspect_height: 300,
            aspect_height_unit: 'px',
            alt: '',
            object_fit: 'cover',
            object_position: 'center',
        }, style[configKey] || {}, {media_type: mediaType});
        delete config.extension;
        delete config.custom_extension;

        const fragment = mediaControlTemplate.content.cloneNode(true);
        const control = fragment.querySelector('[data-media-control]');
        const controlId = 'hc-media-control-' + key;
        const fileInput = control.querySelector('[data-media-control-file]');
        const removeInput = control.querySelector('[data-media-control-remove]');
        const labelEl = control.querySelector('.media-control__header label');
        const mediaTypeSelect = control.querySelector('[data-media-control-field="media_type"]');

        control.id = controlId;
        control.classList.add('hc-navbar-media-control');
        control.dataset.mediaControlSlot = key;
        control.dataset.mediaControlPlatform = platform;
        control.dataset.mediaControlPreviewUrl = preview || '';
        if (labelEl) {
            labelEl.textContent = label;
            labelEl.htmlFor = controlId + '-file';
            if (settings.required) labelEl.classList.add('required');
        }
        if (fileInput) {
            fileInput.id = controlId + '-file';
            fileInput.name = MEDIA_FIELDS[key];
        }
        if (removeInput) removeInput.name = 'remove_' + MEDIA_FIELDS[key];
        if (mediaTypeSelect) mediaTypeSelect.closest('.media-control__field').hidden = true;

        Object.entries(config).forEach(([fieldName, value]) => {
            setMediaControlField(control, fieldName, value, configName);
        });

        const copySources = settings.copySources || [];
        const copyControl = copySources.length
            ? '<div class="hc-media-copy"><select class="form-select form-select-sm" data-copy-source-for="' + key + '"' + (cfg.canEdit ? '' : ' disabled') + '>' +
              copySources.map(source => '<option value="' + source[0] + '">' + esc(source[1]) + '</option>').join('') +
              '</select><button type="button" class="btn btn-sm btn-outline-secondary" data-copy-media="' + key + '"' + (cfg.canEdit ? '' : ' disabled') + '><i class="ti ti-copy me-1"></i>Copy</button></div>'
            : '';

        return '<div class="hc-field hc-media-field hc-media-control-field">' + control.outerHTML +
            (settings.hint ? '<div class="form-hint">' + esc(settings.hint) + '</div>' : '') + copyControl + '</div>';
    }

    function clearMediaKey(key) {
        appearance._remove[key] = true;
        delete appearance._files[key];
        delete appearance._copy[key];
        if (appearance._previews[key]) {
            try { URL.revokeObjectURL(appearance._previews[key]); } catch {}
        }
        delete appearance._previews[key];
        appearance[key] = '';
    }

    function copyMediaValue(targetKey, requestedSourceKey) {
        let sourceKey = resolvedBackgroundKey(requestedSourceKey);
        const visited = [];
        while (appearance._copy[sourceKey] && !visited.includes(sourceKey)) {
            visited.push(sourceKey);
            sourceKey = appearance._copy[sourceKey];
        }
        if (targetKey === sourceKey) return;
        const targetConfigLocation = mediaConfigLocation(targetKey);
        const sourceConfigLocation = mediaConfigLocation(sourceKey);
        if (targetConfigLocation && sourceConfigLocation) {
            const sourceConfig = appearance.platforms[sourceConfigLocation[0]][sourceConfigLocation[1]];
            if (sourceConfig) {
                appearance.platforms[targetConfigLocation[0]][targetConfigLocation[1]] = Object.assign({}, sourceConfig);
            }
        }
        if (appearance._previews[targetKey]) {
            try { URL.revokeObjectURL(appearance._previews[targetKey]); } catch {}
        }
        delete appearance._previews[targetKey];
        delete appearance._files[targetKey];
        delete appearance._copy[targetKey];
        appearance._remove[targetKey] = false;

        if (appearance._files[sourceKey]) {
            appearance._files[targetKey] = appearance._files[sourceKey];
            appearance._previews[targetKey] = URL.createObjectURL(appearance._files[sourceKey]);
            appearance[targetKey] = '';
            return;
        }

        const sourcePreview = previewFor(sourceKey);
        if (!sourcePreview) {
            clearMediaKey(targetKey);
            return;
        }
        appearance[targetKey] = sourcePreview;
        appearance._copy[targetKey] = sourceKey;
    }

    function copyPlatformAppearance(sourcePlatform, targetPlatform) {
        appearance.platforms[targetPlatform] = Object.assign({}, appearance.platforms[sourcePlatform]);
        if (targetPlatform === 'app') {
            copyMediaValue('appIcon', 'desktopIcon');
            copyMediaValue('appActiveIcon', 'desktopActiveIcon');
            copyMediaValue('appBackgroundImage', 'desktopMobileBackgroundImage');
            copyMediaValue('appTabletBackgroundImage', 'desktopTabletBackgroundImage');
        } else {
            copyMediaValue('desktopIcon', 'appIcon');
            copyMediaValue('desktopActiveIcon', 'appActiveIcon');
            copyMediaValue('desktopBackgroundImage', 'appBackgroundImage');
            copyMediaValue('desktopTabletBackgroundImage', 'appTabletBackgroundImage');
            copyMediaValue('desktopMobileBackgroundImage', 'appBackgroundImage');
        }
    }

    function seg(field, options, current) {
        const name = 'hcseg-' + (segCounter++);
        return '<div class="form-selectgroup form-selectgroup-pills hc-seg" data-seg="' + field + '">' +
            options.map(o => '<label class="form-selectgroup-item"><input type="radio" name="' + name + '" value="' + o[0] + '" class="form-selectgroup-input"' + (o[0] === current ? ' checked' : '') + (cfg.canEdit ? '' : ' disabled') + '><span class="form-selectgroup-label">' + esc(o[1]) + '</span></label>').join('') +
            '</div>';
    }

    function colorField(id, label, value) {
        return '<div class="hc-field"><label class="form-label">' + esc(label) + '</label>' +
            '<input type="color" class="form-control form-control-color w-100" id="' + id + '" value="' + esc(value || '#000000') + '"' + (cfg.canEdit ? '' : ' disabled') + '></div>';
    }

    function renderEditor() {
        destroyLabelsTs();
        const isAll = appearance.target === 'all';
        const heading = isAll
            ? T('globalAppearance', 'Appearance — All (global)')
            : T('appearance', 'Appearance') + ' — ' + (appearance.title || ('#' + appearance.target));

        let h = '<div class="d-flex align-items-center gap-2 mb-3">' +
            '<i class="ti ' + (isAll ? 'ti-world' : 'ti-category') + ' fs-2"></i>' +
            '<h3 class="card-title m-0">' + esc(heading) + '</h3>' + (integration ? '' :
            '<button type="button" class="btn btn-primary ms-auto" id="hc-appr-save"' + (cfg.canEdit ? '' : ' disabled') + '><i class="ti ti-device-floppy me-1"></i>' + esc(T('saveAppearance', 'Save appearance')) + '</button>') +
            '</div>';

        if (integration) {
            h += '<div class="hc-field"><label class="form-label">New navbar item</label>' +
                '<div class="input-group input-group-sm"><input type="text" class="form-control" id="hc-integrated-navbar-title" placeholder="e.g. Summer"' + (cfg.canEdit ? '' : ' disabled') + '>' +
                '<button type="button" class="btn btn-outline-primary" id="hc-integrated-navbar-add"' + (cfg.canEdit ? '' : ' disabled') + '><i class="ti ti-plus me-1"></i>Add navbar</button></div></div>';
        }

        if (isAll) {
            h += '<div class="hc-field"><label class="form-label required">' + esc(T('barTitle', 'Bar title')) + '</label>' +
                '<input type="text" class="form-control" id="hc-bartitle" value="' + esc(appearance.title || '') + '"' + (cfg.canEdit ? '' : ' disabled') + '></div>';
        } else {
            h += '<div class="row g-2"><div class="col-md-6"><div class="hc-field"><label class="form-label required">' + esc(T('itemTitle', 'Navbar title')) + '</label>' +
                '<input type="text" class="form-control" id="hc-item-title" value="' + esc(appearance.title || '') + '"' + (cfg.canEdit ? '' : ' disabled') + '></div></div>' +
                '<div class="col-md-6"><div class="hc-field"><label class="form-label required">' + esc(T('slug', 'URL key')) + '</label>' +
                '<input type="text" class="form-control" id="hc-item-slug" value="' + esc(appearance.slug || '') + '"' + (cfg.canEdit ? '' : ' disabled') + '></div></div></div>' +
                '<div class="hc-visibility-options mb-3">' +
                toggleField('hc-is-active', T('active', 'Active'), appearance.isActive, 'Makes this item available to storefronts') +
                (!integration || integration.platform === 'app' ? toggleField('hc-show-app', T('showOnApp', 'Show on app'), appearance.showOnApp, 'Use the app header and app home layout') : '') +
                (!integration || integration.platform === 'web' ? toggleField('hc-show-web', T('showOnWeb', 'Show on web'), appearance.showOnWeb, 'Use the website header and web home layout') : '') +
                '</div>';
        }

        const platform = integration ? (integration.platform === 'web' ? 'desktop' : 'app') : (appearance.activePlatform || 'app');
        appearance.activePlatform = platform;
        const style = appearance.platforms[platform];
        const prefix = platform === 'desktop' ? 'desktop' : 'app';
        if (!integration) h += '<div class="hc-platform-tabs" role="tablist">' +
            '<button type="button" class="hc-platform-tab' + (platform === 'app' ? ' active' : '') + '" data-appearance-platform="app"><i class="ti ti-device-mobile"></i><span>App</span></button>' +
            '<button type="button" class="hc-platform-tab' + (platform === 'desktop' ? ' active' : '') + '" data-appearance-platform="desktop"><i class="ti ti-device-desktop"></i><span>Desktop</span></button>' +
            '</div>';

        if (!integration) h += '<div class="d-flex justify-content-end mb-3"><button type="button" class="btn btn-sm btn-outline-secondary" id="hc-copy-platform"' + (cfg.canEdit ? '' : ' disabled') + '>' +
            '<i class="ti ti-copy me-1"></i>Copy from ' + (platform === 'app' ? 'Desktop' : 'App') + '</button></div>';

        h += '<div class="row g-2"><div class="col-6">' + imageSlot(prefix + 'Icon', T('icon', 'Icon'), previewFor(prefix + 'Icon')) + '</div>' +
            '<div class="col-6">' + imageSlot(prefix + 'ActiveIcon', T('activeIcon', 'Active icon'), previewFor(prefix + 'ActiveIcon')) + '</div></div>';

        h += '<div class="hc-field"><label class="form-label">' + esc(T('backgroundType', 'Header background')) + '</label>' + seg('platformBackgroundType', BG, style.background_type || 'none') + '</div>';

        if (style.background_type === 'gradient') {
            h += '<div class="row g-2"><div class="col-6">' + colorField('hc-gradient-start', 'Gradient start', style.gradient_start) + '</div>' +
                '<div class="col-6">' + colorField('hc-gradient-end', 'Gradient end', style.gradient_end) + '</div></div>' +
                '<div class="hc-field"><label class="form-label">Gradient angle</label><div class="input-group input-group-sm"><input type="number" min="0" max="360" class="form-control" id="hc-gradient-angle" value="' + esc(style.gradient_angle || 0) + '"><span class="input-group-text">°</span></div></div>';
        } else if (['image', 'lottie'].includes(style.background_type)) {
            const isLottie = style.background_type === 'lottie';
            const mediaLabel = isLottie ? T('backgroundLottie', 'Header background animation') : T('backgroundImage', 'Header background image');
            if (platform === 'app') {
                h += '<div class="hc-responsive-media-grid">' +
                    mediaControlSlot('appBackgroundImage', 'Mobile ' + mediaLabel, previewFor('appBackgroundImage'), isLottie, {
                        required: true,
                        hint: 'Required for the app header.',
                        copySources: [['appTabletBackgroundImage', 'App tablet'], ['desktopMobileBackgroundImage', 'Web mobile'], ['desktopBackgroundImage', 'Web desktop']]
                    }) +
                    mediaControlSlot('appTabletBackgroundImage', 'Tablet ' + mediaLabel, previewFor('appTabletBackgroundImage'), isLottie, {
                        fallbackPreview: resolvedBackgroundPreview('appTabletBackgroundImage'),
                        hint: 'Optional. Mobile is used when this is empty.',
                        copySources: [['appBackgroundImage', 'App mobile'], ['desktopTabletBackgroundImage', 'Web tablet'], ['desktopBackgroundImage', 'Web desktop']]
                    }) + '</div>';
            } else {
                h += '<div class="hc-responsive-media-grid">' +
                    mediaControlSlot('desktopBackgroundImage', 'Desktop ' + mediaLabel, previewFor('desktopBackgroundImage'), isLottie, {
                        required: true,
                        hint: 'Required for the website header.',
                        copySources: [['appBackgroundImage', 'App mobile'], ['appTabletBackgroundImage', 'App tablet'], ['desktopTabletBackgroundImage', 'Web tablet']]
                    }) +
                    mediaControlSlot('desktopTabletBackgroundImage', 'Tablet ' + mediaLabel, previewFor('desktopTabletBackgroundImage'), isLottie, {
                        fallbackPreview: resolvedBackgroundPreview('desktopTabletBackgroundImage'),
                        hint: 'Optional. Desktop is used when this is empty.',
                        copySources: [['desktopBackgroundImage', 'Web desktop'], ['appTabletBackgroundImage', 'App tablet'], ['appBackgroundImage', 'App mobile']]
                    }) +
                    mediaControlSlot('desktopMobileBackgroundImage', 'Mobile / app ' + mediaLabel, previewFor('desktopMobileBackgroundImage'), isLottie, {
                        fallbackPreview: resolvedBackgroundPreview('desktopMobileBackgroundImage'),
                        hint: 'Optional. Tablet, then desktop, is used when this is empty.',
                        copySources: [['desktopTabletBackgroundImage', 'Web tablet'], ['desktopBackgroundImage', 'Web desktop'], ['appBackgroundImage', 'App mobile']]
                    }) + '</div>';
            }
        }

        h += '<div class="row g-2"><div class="col-6">' + colorField('hc-fontcolor', T('fontColor', 'Font color'), style.font_color) + '</div>' +
            '<div class="col-6">' + colorField('hc-active-fontcolor', 'Active font color', style.active_font_color) + '</div></div>';

        h += '<div class="hc-field"><label class="form-label">' + esc(T('searchLabels', 'Search labels')) + '</label>' +
            '<select class="form-select" id="hc-labels" multiple' + (cfg.canEdit ? '' : ' disabled') + '></select></div>';

        editorEl.innerHTML = h;
        wireEditor();
    }

    function wireEditor() {
        const integratedTitle = editorEl.querySelector('#hc-integrated-navbar-title');
        const integratedAdd = editorEl.querySelector('#hc-integrated-navbar-add');
        const addIntegratedNavbar = () => {
            const title = (integratedTitle?.value || '').trim();
            if (!title) return;
            document.dispatchEvent(new CustomEvent('home-navbar:integrated-create-intent', {
                detail: {owner: integration?.owner, title},
            }));
        };
        if (integratedAdd) integratedAdd.addEventListener('click', addIntegratedNavbar);
        if (integratedTitle) integratedTitle.addEventListener('keydown', event => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            addIntegratedNavbar();
        });

        editorEl.querySelectorAll('[data-appearance-platform]').forEach(button => button.addEventListener('click', () => {
            appearance.activePlatform = button.dataset.appearancePlatform;
            renderEditor();
        }));
        const copyPlatformButton = editorEl.querySelector('#hc-copy-platform');
        if (copyPlatformButton) copyPlatformButton.addEventListener('click', () => {
            const targetPlatform = appearance.activePlatform;
            const sourcePlatform = targetPlatform === 'app' ? 'desktop' : 'app';
            copyPlatformAppearance(sourcePlatform, targetPlatform);
            renderEditor();
            toast('success', 'Copied ' + (sourcePlatform === 'app' ? 'App' : 'Desktop') + ' appearance');
        });
        editorEl.querySelectorAll('[data-seg]').forEach(g => {
            const fld = g.dataset.seg;
            g.querySelectorAll('input.form-selectgroup-input').forEach(inp => inp.addEventListener('change', () => {
                if (!inp.checked) return;
                if (fld === 'platformBackgroundType') {
                    const platform = appearance.activePlatform;
                    appearance.platforms[platform].background_type = inp.value;
                }
                else appearance[fld] = inp.value;
                notifyIntegrationState();
                renderEditor();
            }));
        });

        bindInput('#hc-bartitle', v => {
            appearance.title = v;
            if (allTitleEl) allTitleEl.textContent = v || T('all', 'All');
        });
        bindInput('#hc-item-title', v => { appearance.title = v; });
        bindInput('#hc-item-slug', v => { appearance.slug = slugify(v); });
        bindInput('#hc-gradient-start', v => { appearance.platforms[appearance.activePlatform].gradient_start = v; });
        bindInput('#hc-gradient-end', v => { appearance.platforms[appearance.activePlatform].gradient_end = v; });
        bindInput('#hc-gradient-angle', v => { appearance.platforms[appearance.activePlatform].gradient_angle = Math.min(360, Math.max(0, Number(v) || 0)); });
        bindInput('#hc-fontcolor', v => { appearance.platforms[appearance.activePlatform].font_color = v; });
        bindInput('#hc-active-fontcolor', v => { appearance.platforms[appearance.activePlatform].active_font_color = v; });

        editorEl.querySelectorAll('.hc-img-slot').forEach(btn => btn.addEventListener('click', () => openImageModal(btn.dataset.slot)));
        editorEl.querySelectorAll('.hc-img-remove').forEach(btn => btn.addEventListener('click', () => {
            clearMediaKey(btn.dataset.slot);
            notifyIntegrationState();
            renderEditor();
        }));
        editorEl.querySelectorAll('[data-copy-media]').forEach(button => button.addEventListener('click', () => {
            const targetKey = button.dataset.copyMedia;
            const sourceKey = editorEl.querySelector('[data-copy-source-for="' + targetKey + '"]')?.value;
            if (!sourceKey) return;
            copyMediaValue(targetKey, sourceKey);
            notifyIntegrationState();
            renderEditor();
        }));
        editorEl.querySelectorAll('[data-media-control-slot]').forEach(control => {
            control.addEventListener('media-control-change', event => {
                if (event.target !== control) return;
                const key = control.dataset.mediaControlSlot;
                const location = mediaConfigLocation(key);
                if (!location) return;
                appearance.platforms[location[0]][location[1]] = Object.assign({}, event.detail.value);

                if (event.detail.file) {
                    if (appearance._previews[key]) {
                        try { URL.revokeObjectURL(appearance._previews[key]); } catch {}
                    }
                    appearance._files[key] = event.detail.file;
                    appearance._previews[key] = URL.createObjectURL(event.detail.file);
                    appearance._remove[key] = false;
                    delete appearance._copy[key];
                } else if (event.detail.removed) {
                    clearMediaKey(key);
                }
                notifyIntegrationState();
            });
        });

        const saveBtn = editorEl.querySelector('#hc-appr-save');
        if (saveBtn) saveBtn.addEventListener('click', saveAppearance);

        bindCheck('#hc-is-active', value => { appearance.isActive = value; });
        bindCheck('#hc-show-app', value => { appearance.showOnApp = value; });
        bindCheck('#hc-show-web', value => { appearance.showOnWeb = value; });

        initLabelsTs();
    }

    function bindInput(selector, cb) { const el = editorEl.querySelector(selector); if (el) el.addEventListener('input', () => { cb(el.value); notifyIntegrationState(); }); }
    function bindCheck(selector, cb) { const el = editorEl.querySelector(selector); if (el) el.addEventListener('change', () => { cb(el.checked); notifyIntegrationState(); }); }
    function slugify(value) { return String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); }
    function toggleField(id, label, checked, hint) {
        return '<label class="hc-toggle-option" for="' + id + '"><span><strong>' + esc(label) + '</strong><small>' + esc(hint) + '</small></span>' +
            '<input class="form-check-input" type="checkbox" id="' + id + '"' + (checked ? ' checked' : '') + (cfg.canEdit ? '' : ' disabled') + '></label>';
    }

    function initLabelsTs() {
        const el = editorEl.querySelector('#hc-labels');
        if (!el || !window.TomSelect) return;
        labelsTs = new TomSelect(el, {
            create: true, persist: false, copyClassesToDropdown: false,
            valueField: 'value', labelField: 'text', searchField: 'text',
            options: (appearance.searchLabels || []).map(v => ({ value: v, text: v })),
            items: (appearance.searchLabels || []).slice(),
        });
        labelsTs.on('change', () => {
            const v = labelsTs.getValue();
            appearance.searchLabels = Array.isArray(v) ? v : (v ? [v] : []);
            notifyIntegrationState();
        });
    }
    function destroyLabelsTs() { if (labelsTs) { try { labelsTs.destroy(); } catch (e) { /* noop */ } labelsTs = null; } }

    /* ---------------- FilePond image modal ---------------- */
    let modalPond = null;
    let slotTarget = null;
    const imgModalEl = document.getElementById('hcImageModal');

    function openImageModal(slot) {
        if (!cfg.canEdit || !window.FilePond) return;
        slotTarget = slot;
        const existing = previewFor(slot);
        const isBackground = /BackgroundImage$/.test(slot);
        const isLottie = isBackground && appearance.platforms[appearance.activePlatform].background_type === 'lottie';
        const input = document.getElementById('hcImagePond');
        if (modalPond) { try { modalPond.destroy(); } catch (e) { /* noop */ } modalPond = null; }
        modalPond = window.FilePond.create(input, {
            allowMultiple: false, credits: false, storeAsFile: true,
            allowImagePreview: !isLottie,
            acceptedFileTypes: isLottie ? ['application/json', 'text/plain'] : ['image/*'],
            maxFileSize: '5MB',
            fileValidateTypeDetectType: (source, type) => new Promise(resolve => {
                if (isLottie && (!type || type === 'text/plain') && /\.json$/i.test(source.name || '')) resolve('application/json');
                else resolve(type);
            }),
            files: existing ? [{ source: existing, options: { type: 'local' } }] : [],
            server: { load: (src, load, error) => { fetch(src).then(r => r.blob()).then(load).catch(error); } },
        });
        if (window.jQuery) window.jQuery(imgModalEl).modal('show');
    }

    const imgDone = document.getElementById('hcImageDone');
    if (imgDone) imgDone.addEventListener('click', () => {
        const files = modalPond ? modalPond.getFiles() : [];
        const native = files.length ? files[0].file : null;
        if (slotTarget && native) {
            if (appearance._previews[slotTarget]) { try { URL.revokeObjectURL(appearance._previews[slotTarget]); } catch (e) { /* noop */ } }
            appearance._files[slotTarget] = native;
            appearance._previews[slotTarget] = URL.createObjectURL(native);
            appearance._remove[slotTarget] = false;
            delete appearance._copy[slotTarget];
            notifyIntegrationState();
        }
        if (window.jQuery) window.jQuery(imgModalEl).modal('hide');
        renderEditor();
    });
    if (imgModalEl) imgModalEl.addEventListener('hidden.bs.modal', () => { if (modalPond) { try { modalPond.destroy(); } catch (e) { /* noop */ } modalPond = null; } });

    /* ---------------- appearance save ---------------- */
    function appendFormValue(formData, name, value) {
        if (value && typeof value === 'object' && !(value instanceof File)) {
            Object.entries(value).forEach(([key, nestedValue]) => {
                appendFormValue(formData, name + '[' + key + ']', nestedValue);
            });
            return;
        }
        formData.append(name, value == null ? '' : value);
    }

    function saveAppearance(options) {
        const settings = options || {};
        const isAll = appearance.target === 'all';
        const fd = new FormData();

        if (isAll) {
            fd.append('title', appearance.title || '');
        } else {
            if (!appearance.showOnApp && !appearance.showOnWeb) {
                toast('error', 'Select at least one platform');
                return Promise.resolve(false);
            }
            fd.append('title', appearance.title || '');
            fd.append('slug', slugify(appearance.slug || appearance.title));
            fd.append('is_active', appearance.isActive ? '1' : '0');
            fd.append('show_on_app', appearance.showOnApp ? '1' : '0');
            fd.append('show_on_web', appearance.showOnWeb ? '1' : '0');
        }

        const requiresAppBackground = (isAll || appearance.showOnApp) && ['image', 'lottie'].includes(appearance.platforms.app.background_type);
        const requiresDesktopBackground = (isAll || appearance.showOnWeb) && ['image', 'lottie'].includes(appearance.platforms.desktop.background_type);
        if (requiresAppBackground && !previewFor('appBackgroundImage')) {
            appearance.activePlatform = 'app';
            renderEditor();
            toast('error', 'Add the required app mobile background');
            return Promise.resolve(false);
        }
        if (requiresDesktopBackground && !previewFor('desktopBackgroundImage')) {
            appearance.activePlatform = 'desktop';
            renderEditor();
            toast('error', 'Add the required web desktop background');
            return Promise.resolve(false);
        }

        ['app', 'desktop'].forEach(platform => {
            appendFormValue(fd, 'home_appearance[' + platform + ']', appearance.platforms[platform]);
        });

        (appearance.searchLabels || []).forEach(v => fd.append(isAll ? 'searchLabels[]' : 'search_labels[]', v));

        Object.entries(MEDIA_FIELDS).forEach(([key, field]) => {
            if (appearance._files[key]) fd.append(field, appearance._files[key]);
            else if (appearance._remove[key]) fd.append('remove_' + field, '1');
        });
        Object.entries(appearance._copy).forEach(([targetKey, sourceKey]) => {
            if (MEDIA_FIELDS[targetKey] && MEDIA_FIELDS[sourceKey] && !appearance._files[targetKey]) {
                fd.append('copy_media[' + MEDIA_FIELDS[targetKey] + ']', MEDIA_FIELDS[sourceKey]);
            }
        });

        const url = isAll ? cfg.globalSaveUrl : cfg.itemBase + '/' + appearance.target;
        return window.axios.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
            .then(r => {
                if (r.data.success === false) { toast('error', r.data.message); return false; }
                if (!settings.silent) toast('success', r.data.message);
                appearance = normalize(appearance.target, r.data.data || {});
                if (integration) appearance.activePlatform = integration.platform === 'web' ? 'desktop' : 'app';
                appearanceBaseline = appearanceSignature();
                integrationSavedPreview = integratedPreviewData();
                if (isAll && allTitleEl) allTitleEl.textContent = appearance.title || T('all', 'All');
                if (!isAll) {
                    syncItemAcrossDrafts(normalizeListItem(r.data.data || {}));
                    renderList();
                }
                document.dispatchEvent(new CustomEvent('home-navbar:appearance-saved', {
                    detail: {target: appearance.target, data: r.data.data || {}}
                }));
                if (!isAll) broadcastItems();
                renderEditor();
                notifyIntegrationState();
                return true;
            })
            .catch(err => {
                if (!settings.silent) toast('error', err.response?.data?.message || 'Save failed');
                return false;
            });
    }

    document.addEventListener('home-navbar:integrated-open', event => {
        const detail = event.detail || {};
        if (!detail.mount || !['app', 'web'].includes(detail.platform)) return;
        integration = {owner: detail.owner, platform: detail.platform};
        sortPlatform = detail.platform;
        appearanceBaseline = '';
        orderBaseline = '';
        integrationSavedPreview = null;
        detail.mount.classList.add('hb-header-editor-mount');
        detail.mount.replaceChildren(editorEl);
        updateSortPlatformButtons();
        load(true);
        selectTarget(detail.target === 'all' ? 'all' : Number(detail.target));
        document.dispatchEvent(new CustomEvent('home-navbar:integration-state', {
            detail: {owner: integration.owner, dirty: false, platform: integration.platform},
        }));
    });

    document.addEventListener('home-navbar:integrated-close', event => {
        if (!integration || (event.detail && event.detail.owner) !== integration.owner) return;
        if (event.detail && event.detail.discard) restoreIntegrationDraft();
        destroyLabelsTs();
        const mount = editorEl.parentElement;
        if (mount) mount.classList.remove('hb-header-editor-mount');
        editorHome.appendChild(editorEl);
        integration = null;
        appearanceBaseline = '';
        orderBaseline = '';
        integrationSavedPreview = null;
        root.dataset.unsaved = '0';
    });

    document.addEventListener('home-navbar:integrated-select', event => {
        const detail = event.detail || {};
        if (!integration || detail.owner !== integration.owner) return;
        if (detail.discard) restoreIntegrationDraft();
        appearanceBaseline = '';
        integrationSavedPreview = null;
        selectTarget(detail.target === 'all' ? 'all' : Number(detail.target));
    });

    document.addEventListener('home-navbar:integrated-add-focus', event => {
        if (!integration || (event.detail && event.detail.owner) !== integration.owner) return;
        const input = editorEl.querySelector('#hc-integrated-navbar-title');
        if (input) {
            input.scrollIntoView({behavior: 'smooth', block: 'center'});
            input.focus();
        }
    });

    document.addEventListener('home-navbar:integrated-create', event => {
        const detail = event.detail || {};
        if (!integration || detail.owner !== integration.owner) return;
        if (detail.discard) restoreIntegrationDraft();
        createItem(detail.title);
    });

    document.addEventListener('home-navbar:integrated-order', event => {
        const detail = event.detail || {};
        if (!integration || detail.owner !== integration.owner || detail.platform !== integration.platform) return;
        const byId = new Map(chosen.map(item => [Number(item.id), item]));
        const ordered = (detail.itemIds || []).map(Number).map(id => byId.get(id)).filter(Boolean);
        chosen.forEach(item => {
            if (!ordered.some(entry => Number(entry.id) === Number(item.id))) ordered.push(item);
        });
        chosen = ordered;
        platformDrafts[sortPlatform] = chosen.slice();
        renderList();
        broadcastItems([], sortPlatform, chosen);
        notifyIntegrationState();
    });

    document.addEventListener('home-navbar:integrated-save', event => {
        const detail = event.detail || {};
        if (!integration || detail.owner !== integration.owner) return;
        Promise.resolve(saveAppearance({silent: true}))
            .then(saved => saved ? saveOrder({silent: true}) : Promise.reject(new Error('Header validation failed')))
            .then(() => {
                markSavedState();
                toast('success', 'Header and navbar saved');
                document.dispatchEvent(new CustomEvent('home-navbar:integrated-save-finished', {
                    detail: {owner: integration.owner, success: true},
                }));
            })
            .catch(error => {
                toast('error', error.response?.data?.message || error.message || 'Save failed');
                document.dispatchEvent(new CustomEvent('home-navbar:integrated-save-finished', {
                    detail: {owner: integration.owner, success: false},
                }));
            });
    });

    document.addEventListener('home-navbar:direct-discard', event => {
        if (integration) return;
        root.dataset.unsaved = '0';
        load(true);
        selectTarget(selected || 'all');
        if (typeof event.detail?.done === 'function') event.detail.done();
    });

    document.addEventListener('home-navbar:direct-save', event => {
        if (integration) return;
        Promise.resolve(saveAppearance({silent: true}))
            .then(saved => saved ? saveOrder({silent: true}) : Promise.reject(new Error('Header validation failed')))
            .then(() => {
                markSavedState();
                toast('success', 'Header and navbar saved');
                if (typeof event.detail?.done === 'function') event.detail.done();
            })
            .catch(error => toast('error', error.response?.data?.message || error.message || 'Save failed'));
    });

    /* ---------------- init ---------------- */
    if (allRow) {
        allRow.addEventListener('click', () => selectTarget('all'));
        allRow.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectTarget('all'); } });
    }

    load();
    selectTarget('all');
})();
