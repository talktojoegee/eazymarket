(function () {
    const roots = document.querySelectorAll('[data-home-builder]');
    if (!roots.length) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (window.axios) {
        window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
    }
    if (window.FilePond) {
        try {
            window.FilePond.registerPlugin(window.FilePondPluginImagePreview, window.FilePondPluginFileValidateType, window.FilePondPluginFileValidateSize);
        } catch (e) { /* already registered */
        }
    }

    /* ---------- shared constants ---------- */
    const ICON = {
        hero: 'ti-photo',
        products: 'ti-shopping-bag',
        categories: 'ti-category',
        brands: 'ti-tag',
        play_image: 'ti-layout-grid-add'
    };
    const TYPE = [['play_image', 'Play Image'], ['hero', 'Hero'], ['products', 'Products'], ['categories', 'Categories'], ['brands', 'Brands']];
    const HERO_MEDIA = [['image', 'Image'], ['lottie', 'Animation']];
    const HERO_LAYOUT = [['none', 'Banner only'], ['items', 'With items']];
    const ITEM_SOURCE = [['products', 'Products'], ['brands', 'Brands'], ['categories', 'Categories']];
    const PRODUCT_SOURCE = [['newly_added', 'Newly added'], ['top_rated', 'Top rated'], ['best_seller', 'Best seller'], ['featured', 'Featured'], ['recommended', 'Recommended'], ['custom', 'Custom selection']];
    const ORIENTATION = [['vertical', 'Vertical'], ['horizontal', 'Horizontal']];
    const BACKGROUND = [['none', 'None'], ['color', 'Color'], ['image', 'Image']];
    const SOURCE_MODE = [['all', 'All'], ['custom', 'Custom']];
    const STYLES = {
        hero: [
            ['full', 'Full image', '/assets/images/homebuilder-styles/hero-full-image.png'],
            ['image_title', 'Image + title', '/assets/images/homebuilder-styles/hero-image-title.png']
        ],
        products: [['quick_add', 'Quick Add'], ['catalog', 'Catalog'], ['standard', 'Standard'], ['showcase', 'Showcase'], ['compact', 'Compact'], ['minimal', 'Minimal']],
        categories: [['full', 'Full image'], ['overlay', 'Overlay title'], ['default', 'Default']],
        brands: [['full', 'Full image'], ['image_title', 'Image + title']],
        play_image: [['grid', 'Grid Layout'], ['scroll', 'Scroll/Slider Layout']],
    };
    const GRID_LINK_TYPES = [['none', 'No Link'], ['product', 'Product'], ['category', 'Category'], ['brand', 'Brand'], ['custom', 'Custom URL']];
    const SRC_TO_KEY = {products: 'product', brands: 'brand', categories: 'category'};
    const MEDIA_FIELD = {
        background: 'background_image',
        hero_media: 'hero_media',
        card_background: 'card_background_image'
    };

    let segCounter = 0; // shared so radio-group names stay unique across instances

    /* ---------- shared helpers ---------- */
    function safeJson(s, d) {
        try {
            return JSON.parse(s);
        } catch (e) {
            return d;
        }
    }

    function parseBool(val, defaultVal) {
        if (val === undefined || val === null) return defaultVal;
        if (typeof val === 'boolean') return val;
        if (typeof val === 'number') return val !== 0;
        if (typeof val === 'string') {
            const s = val.toLowerCase().trim();
            return s === 'true' || s === '1' || s === 'yes' || s === 'on';
        }
        return !!val;
    }

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

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function contrastRatio(foreground, background) {
        const rgb = value => {
            const hex = String(value || '').trim().replace(/^#/, '');
            const normalized = hex.length === 3 ? hex.split('').map(part => part + part).join('') : hex;
            if (!/^[0-9a-f]{6}$/i.test(normalized)) return null;
            return [0, 2, 4].map(offset => parseInt(normalized.slice(offset, offset + 2), 16) / 255);
        };
        const luminance = color => {
            const channels = rgb(color);
            if (!channels) return null;
            const values = channels.map(channel => channel <= .03928 ? channel / 12.92 : Math.pow((channel + .055) / 1.055, 2.4));
            return .2126 * values[0] + .7152 * values[1] + .0722 * values[2];
        };
        const first = luminance(foreground);
        const second = luminance(background);
        if (first === null || second === null) return null;
        return (Math.max(first, second) + .05) / (Math.min(first, second) + .05);
    }

    function getLottieSize(val, defaultVal) {
        if (!val) return defaultVal;
        if (!isNaN(val)) return val + 'px';
        return String(val);
    }

    function newRowMediaKey() {
        return 'row_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 9);
    }

    function newUiKey(prefix) {
        return prefix + '_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 9);
    }

    function sectionUiKey(section) {
        section._ui_key = section._ui_key || (section.id ? 'section_' + section.id : newUiKey('section'));
        return section._ui_key;
    }

    function itemUiKey(item) {
        item._ui_key = item._ui_key || (item.id ? 'item_' + item.id : newUiKey('item'));
        return item._ui_key;
    }

    function defaultRowConfig() {
        return {
            style: 'grid',
            container_width: 'container',
            outer_padding: 0,
            outer_padding_custom: '',
            grid_gap: 0,
            border_radius: 0,
            hover_effect: 'none',
            background_type: 'none',
            background_color: '#ffffff',
            background_image: null,
            media_key: newRowMediaKey(),
            items_to_show: 1,
            items_to_show_mobile: 1,
            show_scroll_buttons: 1,
            show_bullets: 0,
            auto_scroll: 0,
            auto_scroll_time: 3
        };
    }

    function normalizeRowsConfig(config) {
        const normalized = config || {};
        if (Array.isArray(normalized.rows)) {
            normalized.rows.forEach(row => {
                if (!row.media_key) row.media_key = newRowMediaKey();

                // Rows created before spacing became opt-in were persisted with
                // the old 16px / 12px / 8px visual preset. Remove that complete
                // legacy preset once; newly configured values are saved from
                // the current zero-based controls.
                const hasLegacySpacingPreset = Number(row.outer_padding) === 16
                    && Number(row.grid_gap) === 12
                    && Number(row.border_radius) === 8;
                if (hasLegacySpacingPreset) {
                    row.outer_padding = 0;
                    row.outer_padding_custom = '';
                    row.grid_gap = 0;
                    row.border_radius = 0;
                }

                row.outer_padding = Number(row.outer_padding) || 0;
                row.grid_gap = Number(row.grid_gap) || 0;
            });
        }
        return normalized;
    }

    function paddingSides(rowConfig) {
        const fallback = Number(rowConfig.outer_padding || 0);
        const values = String(rowConfig.outer_padding_custom || '').split('/').map(value => {
            const normalized = String(value).replace(/[^0-9.-]/g, '').trim();
            return normalized === '' ? NaN : Number(normalized);
        });
        return ['top', 'right', 'bottom', 'left'].map((side, index) => ({
            side,
            value: Number.isFinite(values[index]) ? values[index] : fallback
        }));
    }

    function rowBoxSpacing(rowConfig) {
        const values = paddingSides(rowConfig).map(entry => entry.value);
        return {
            padding: values.map(value => Math.max(0, value) + 'px').join(' '),
            margin: values.map(value => Math.min(0, value) + 'px').join(' ')
        };
    }

    function playItemAspectRatio(config) {
        const itemConfig = config || {};
        const ratio = itemConfig.aspect_ratio || 'original';
        if (ratio === 'original') return null;
        if (ratio === 'custom') {
            if (playItemCustomSize(itemConfig)) return null;
            const width = Math.max(.1, Number(itemConfig.aspect_width) || 1);
            const height = Math.max(.1, Number(itemConfig.aspect_height) || 1);
            return width + ' / ' + height;
        }
        return String(ratio).replace(':', ' / ');
    }

    function playItemCustomSize(config) {
        const itemConfig = config || {};
        if (itemConfig.aspect_ratio !== 'custom') return null;
        const units = ['px', '%', 'rem', 'em', 'vw', 'vh'];
        const widthUnit = units.includes(itemConfig.aspect_width_unit) ? itemConfig.aspect_width_unit : null;
        const heightUnit = units.includes(itemConfig.aspect_height_unit) ? itemConfig.aspect_height_unit : null;
        if (!widthUnit && !heightUnit) return null;
        const width = Math.max(.1, Number(itemConfig.aspect_width) || 100);
        const height = Math.max(.1, Number(itemConfig.aspect_height) || 300);
        return {
            width: width + (widthUnit || '%'),
            height: height + (heightUnit || 'px')
        };
    }

    function playItemFrameStyle(config) {
        const customSize = playItemCustomSize(config);
        if (customSize) {
            return 'width:min(' + customSize.width + ', 100%);max-width:100%;height:' + customSize.height + ';margin-inline:auto;';
        }
        const aspect = playItemAspectRatio(config);
        return aspect ? 'width:100%;aspect-ratio:' + aspect + ';' : 'width:100%;';
    }

    function playItemObjectFit(config) {
        const value = String((config && config.object_fit) || 'cover');
        return ['cover', 'contain', 'fill', 'none', 'scale-down'].includes(value) ? value : 'cover';
    }

    function playItemObjectPosition(config) {
        const value = String((config && config.object_position) || 'center');
        return ['center', 'top', 'right', 'bottom', 'left'].includes(value) ? value : 'center';
    }

    function calculateSpansJS(items, isMobile) {
        const spanKey = isMobile ? 'cols_mobile' : 'cols';
        const rows = [];
        let currentRow = [];
        let currentExplicitSum = 0;
        let autoCount = 0;

        items.forEach(item => {
            item.config = item.config || {};
            const cols = item.config[spanKey] || 'auto';
            const isAuto = cols === 'auto';
            const spanNum = isAuto ? 0 : Math.min(12, Math.max(1, +cols));
            const minSpan = isAuto ? 2 : spanNum;
            const isNewRow = item.config.new_row === true;

            if ((isNewRow || currentExplicitSum + (autoCount * 2) + minSpan > 12) && currentRow.length > 0) {
                rows.push(currentRow);
                currentRow = [];
                currentExplicitSum = 0;
                autoCount = 0;
            }

            currentRow.push(item);
            if (isAuto) {
                autoCount++;
            } else {
                currentExplicitSum += spanNum;
            }
        });

        if (currentRow.length > 0) {
            rows.push(currentRow);
        }

        const result = [];
        rows.forEach(row => {
            let rowExplicitSum = 0;
            let rowAutoCount = 0;

            row.forEach(item => {
                const cols = item.config[spanKey] || 'auto';
                const isAuto = cols === 'auto';
                if (isAuto) {
                    rowAutoCount++;
                } else {
                    rowExplicitSum += Math.min(12, Math.max(1, +cols));
                }
            });

            const remainingCols = 12 - rowExplicitSum;
            const autoSpan = rowAutoCount > 0 ? Math.max(1, Math.floor(remainingCols / rowAutoCount)) : 0;

            row.forEach(item => {
                const cols = item.config[spanKey] || 'auto';
                const isAuto = cols === 'auto';
                const finalSpan = isAuto ? autoSpan : Math.min(12, Math.max(1, +cols));
                result.push({ item, span: finalSpan });
            });
        });

        return result;
    }

    function rebuildItemsFromRows(rows) {
        const flat = [];
        let currentSortOrder = 0;
        rows.forEach((rowItems, rIdx) => {
            rowItems.forEach(it => {
                it.config = it.config || {};
                it.config.row_index = rIdx;
                it.sort_order = currentSortOrder++;
                flat.push(it);
            });
        });
        return flat;
    }

    function nameOf(v, list) {
        const f = list.find(o => o[0] === v);
        return f ? f[1] : v;
    }

    function api(method, url, data) {
        return window.axios({method, url, data, headers: {Accept: 'application/json'}});
    }

    /* ---------- shared confirm modal ---------- */
    const ConfirmModal = (function () {
        let cb = null;
        let saveCb = null;
        const ok = document.getElementById('hbConfirmOk');
        const save = document.getElementById('hbConfirmSave');
        if (ok) ok.addEventListener('click', () => {
            if (window.jQuery) window.jQuery('#hbConfirmModal').modal('hide');
            const f = cb;
            cb = null;
            saveCb = null;
            if (f) f();
        });
        if (save) save.addEventListener('click', () => {
            if (window.jQuery) window.jQuery('#hbConfirmModal').modal('hide');
            const f = saveCb;
            cb = null;
            saveCb = null;
            if (f) f();
        });
        return {
            open(msg, callback, options) {
                cb = callback;
                const settings = options || {};
                saveCb = typeof settings.onSave === 'function' ? settings.onSave : null;
                const m = document.getElementById('hbConfirmMessage');
                const title = document.getElementById('hbConfirmTitle');
                const cancel = document.getElementById('hbConfirmCancel');
                if (m) m.textContent = msg;
                if (title) title.textContent = settings.title || 'Confirm';
                if (cancel) cancel.textContent = settings.cancelText || 'Cancel';
                if (ok) {
                    ok.textContent = settings.confirmText || 'Confirm';
                    ok.className = 'btn ' + (settings.confirmClass || 'btn-danger');
                }
                if (save) {
                    save.hidden = !saveCb;
                    save.textContent = settings.saveText || 'Save changes';
                }
                if (window.jQuery) window.jQuery('#hbConfirmModal').modal('show');
            }
        };
    })();
    /* ---------- shared paste modal ---------- */
    const PasteModal = (function () {
        // Inject paste modal dynamically to document body
        if (!document.getElementById('hbPasteModal')) {
            const modalHtml =
                '<div class="modal modal-blur fade" id="hbPasteModal" tabindex="-1" role="dialog" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered" role="document">' +
                '<div class="modal-content">' +
                '<div class="modal-header">' +
                '<h5 class="modal-title">Paste Section Design</h5>' +
                '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cancel"></button>' +
                '</div>' +
                '<div class="modal-body">' +
                '<p class="text-muted small mb-2">Paste your copied JSON design code here:</p>' +
                '<textarea class="form-control" id="hbPasteTextarea" rows="10" placeholder="Paste section JSON here..." style="font-family: monospace; font-size: 12px;"></textarea>' +
                '</div>' +
                '<div class="modal-footer">' +
                '<button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Cancel</button>' +
                '<button type="button" class="btn btn-primary" id="hbPasteSubmitBtn">Import & Paste</button>' +
                '</div></div></div></div>';
            const wrapper = document.createElement('div');
            wrapper.innerHTML = modalHtml;
            document.body.appendChild(wrapper.firstChild);
        }

        let cb = null;

        document.addEventListener('click', e => {
            if (e.target && e.target.id === 'hbPasteSubmitBtn') {
                const textarea = document.getElementById('hbPasteTextarea');
                const txt = textarea ? textarea.value.trim() : '';
                if (window.jQuery) window.jQuery('#hbPasteModal').modal('hide');
                const f = cb;
                cb = null;
                if (f) f(txt);
            }
        });

        return {
            open(callback) {
                cb = callback;
                const textarea = document.getElementById('hbPasteTextarea');
                if (textarea) textarea.value = '';
                if (window.jQuery) window.jQuery('#hbPasteModal').modal('show');
            }
        };
    })();

    /* ---------- shared image modal (FilePond storeAsFile) ---------- */
    const ImageModal = (function () {
        const modalEl = document.getElementById('hbImageModal');
        const dialog = modalEl ? modalEl.querySelector('.hb-image-modal-dialog') : null;
        const doneBtn = document.getElementById('hbImageDone');
        const modalFooter = document.getElementById('hbImageModalFooter');
        const uploadPane = document.getElementById('hbImageUploadPane');
        const cropEditor = document.getElementById('hbImageCropEditor');
        const cropSource = document.getElementById('hbImageCropSource');
        const cropApply = document.getElementById('hbImageCropApply');
        const cropCancel = document.getElementById('hbImageCropCancel');
        const cropOutputWidth = document.getElementById('hbImageCropOutputWidth');
        const ratioButtons = cropEditor ? Array.from(cropEditor.querySelectorAll('[data-crop-ratio]')) : [];
        let pond = null;
        let onDone = null;
        let cropper = null;
        let currentFileItem = null;
        let cropEnabled = false;
        let replacingFile = false;
        let modalShown = false;
        let pendingCropFileItem = null;

        function setCropMode(active) {
            if (dialog) dialog.classList.toggle('is-cropping', active);
            if (uploadPane) uploadPane.classList.toggle('d-none', active);
            if (cropEditor) cropEditor.classList.toggle('d-none', !active);
            if (modalFooter) modalFooter.classList.toggle('d-none', active);
        }

        function resetRatioButtons() {
            ratioButtons.forEach(button => {
                const active = button.dataset.cropRatio === 'free';
                button.classList.toggle('btn-primary', active);
                button.classList.toggle('btn-outline-secondary', !active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }

        function destroyCropper() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        }

        function closeCropper() {
            destroyCropper();
            if (cropSource) cropSource.removeAttribute('src');
            if (cropOutputWidth) cropOutputWidth.value = '';
            currentFileItem = null;
            pendingCropFileItem = null;
            resetRatioButtons();
            setCropMode(false);
        }

        function openCropper(fileItem) {
            if (!cropEnabled || !fileItem || !fileItem.file || !cropSource) return;
            if (!modalShown) {
                pendingCropFileItem = fileItem;
                return;
            }
            pendingCropFileItem = null;
            currentFileItem = fileItem;
            const fileItemId = fileItem.id;
            const reader = new FileReader();
            reader.onload = event => {
                if (!currentFileItem || currentFileItem.id !== fileItemId) return;
                destroyCropper();
                cropSource.src = event.target.result;
                resetRatioButtons();
                setCropMode(true);
                window.requestAnimationFrame(() => {
                    if (!currentFileItem || currentFileItem.id !== fileItemId || !modalShown) return;
                    cropper = new window.Cropper(cropSource, {
                        aspectRatio: NaN,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: .8,
                        responsive: true,
                        background: false,
                        movable: true,
                        zoomable: true,
                        rotatable: true,
                        scalable: true,
                        cropBoxMovable: true,
                        cropBoxResizable: true
                    });
                });
            };
            reader.readAsDataURL(fileItem.file);
        }

        function outputMimeType(file) {
            return ['image/png', 'image/jpeg', 'image/webp'].includes(file.type) ? file.type : 'image/jpeg';
        }

        function applyCrop() {
            if (!cropper || !currentFileItem || !pond) return;
            const originalFile = currentFileItem.file;
            const canvasOptions = {
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            };
            const maxWidth = Number(cropOutputWidth && cropOutputWidth.value) || 0;
            if (maxWidth) canvasOptions.maxWidth = maxWidth;
            const canvas = cropper.getCroppedCanvas(canvasOptions);
            if (!canvas) return;
            const mimeType = outputMimeType(originalFile);
            cropApply.disabled = true;
            canvas.toBlob(async blob => {
                if (!blob) {
                    cropApply.disabled = false;
                    return;
                }
                const croppedFile = new File([blob], originalFile.name || 'play-image', {
                    type: mimeType,
                    lastModified: Date.now()
                });
                try {
                    replacingFile = true;
                    await pond.removeFile(currentFileItem.id);
                    await pond.addFile(croppedFile);
                    closeCropper();
                } catch (error) {
                    replacingFile = false;
                } finally {
                    cropApply.disabled = false;
                }
            }, mimeType, .92);
        }

        function destroyPond() {
            closeCropper();
            if (pond) {
                try {
                    pond.destroy();
                } catch (e) { /* noop */
                }
                pond = null;
            }
            cropEnabled = false;
            replacingFile = false;
        }

        if (doneBtn) doneBtn.addEventListener('click', () => {
            const files = pond ? pond.getFiles() : [];
            const fileItem = files.length ? files[0] : null;
            const cb = onDone;
            if (window.jQuery) window.jQuery(modalEl).modal('hide');
            if (cb) cb(fileItem ? fileItem.file : null);
        });
        if (modalEl) {
            modalEl.addEventListener('show.bs.modal', () => {
                modalShown = false;
            });
            modalEl.addEventListener('shown.bs.modal', () => {
                modalShown = true;
                if (pendingCropFileItem) openCropper(pendingCropFileItem);
            });
            modalEl.addEventListener('hidden.bs.modal', () => {
                modalShown = false;
                destroyPond();
            });
        }
        if (cropApply) cropApply.addEventListener('click', applyCrop);
        if (cropCancel) cropCancel.addEventListener('click', closeCropper);
        ratioButtons.forEach(button => button.addEventListener('click', () => {
            if (!cropper) return;
            ratioButtons.forEach(option => {
                const active = option === button;
                option.classList.toggle('btn-primary', active);
                option.classList.toggle('btn-outline-secondary', !active);
                option.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            cropper.setAspectRatio(button.dataset.cropRatio === 'free' ? NaN : Number(button.dataset.cropRatio));
        }));
        const cropActions = [
            ['hbImageCropZoomIn', () => cropper && cropper.zoom(.1)],
            ['hbImageCropZoomOut', () => cropper && cropper.zoom(-.1)],
            ['hbImageCropRotateLeft', () => cropper && cropper.rotate(-90)],
            ['hbImageCropRotateRight', () => cropper && cropper.rotate(90)],
            ['hbImageCropReset', () => {
                if (!cropper) return;
                cropper.reset();
                resetRatioButtons();
            }]
        ];
        cropActions.forEach(action => {
            const button = document.getElementById(action[0]);
            if (button) button.addEventListener('click', action[1]);
        });

        return {
            open(opts, callback) {
                if (!window.FilePond) return;
                onDone = callback;
                const isLottie = !!opts.isLottie;
                const existing = opts.existing || null;
                const input = document.getElementById('hbImagePond');
                destroyPond();
                cropEnabled = !!opts.allowCrop && !isLottie && !!window.Cropper;
                pond = window.FilePond.create(input, {
                    allowMultiple: false, credits: false, storeAsFile: true,
                    allowImagePreview: !isLottie,
                    instantUpload: false,
                    acceptedFileTypes: isLottie ? ['application/json'] : (cropEnabled ? ['image/png', 'image/jpeg', 'image/webp'] : ['image/*']),
                    maxFileSize: '10MB',
                    fileValidateTypeDetectType: (source, type) => new Promise((resolve) => {
                        if (isLottie && !type && /\.json$/i.test(source.name || '')) {
                            resolve('application/json');
                            return;
                        }
                        resolve(type);
                    }),
                    files: existing ? [{source: existing, options: {type: 'local'}}] : [],
                    server: {
                        load: (src, load, error) => {
                            fetch(src).then(r => r.blob()).then(load).catch(error);
                        }
                    },
                });
                pond.on('addfile', (error, fileItem) => {
                    if (error || !cropEnabled) return;
                    if (replacingFile) {
                        replacingFile = false;
                        return;
                    }
                    openCropper(fileItem);
                });
                setCropMode(false);
                if (window.jQuery) window.jQuery(modalEl).modal('show');
            }
        };
    })();

    /* ====================================================================
       One builder instance, scoped to its root element.
       ==================================================================== */
    function initHomeBuilder(root) {
        const cfg = {
            showUrl: root.dataset.showUrl,
            previewCatalogUrl: root.dataset.previewCatalogUrl,
            saveUrl: root.dataset.saveUrl,
            productSearch: root.dataset.productSearch,
            categorySearch: root.dataset.categorySearch,
            brandSearch: root.dataset.brandSearch,
            marketSearch: root.dataset.marketSearch,
            deviceAssets: {
                android: root.dataset.androidMockup,
                iphone: root.dataset.iphoneMockup
            },
            storefront: safeJson(root.dataset.storefrontSettings, {}),
            homeGeneral: safeJson(root.dataset.homeGeneralSettings, {}),
            homeNavbarItems: safeJson(root.dataset.navbarItems, []),
            previewCatalog: safeJson(root.dataset.previewCatalog, {}),
            canEdit: root.dataset.canEdit === '1',
        };
        const defaultPreviewCatalog = JSON.parse(JSON.stringify(cfg.previewCatalog));
        const PLATFORM = root.dataset.platform || 'app';
        const OPTIONS = safeJson(root.dataset.options, {});
        const ALLOWED_TYPES = safeJson(root.dataset.sectionTypes, null);
        const TYPES = TYPE.filter(t => !ALLOWED_TYPES || ALLOWED_TYPES.indexOf(t[0]) !== -1);
        const allowedSourceModes = OPTIONS.source_mode || SOURCE_MODE.map(option => option[0]);
        const sourceModes = SOURCE_MODE.filter(option => allowedSourceModes.includes(option[0]));
        const allowedCategoryStyles = OPTIONS.category_style || STYLES.categories.map(option => option[0]);
        const categoryStyles = STYLES.categories.filter(option => allowedCategoryStyles.includes(option[0]));
        const allowedProductStyles = OPTIONS.product_card_style || [];
        const productStyles = STYLES.products.filter(option => allowedProductStyles.includes(option[0]));
        const TEMPLATES = safeJson(root.dataset.templates, []);
        const THEMES = safeJson(root.dataset.themes, []);
        const LBL = safeJson(root.dataset.labels, {});
        const L = (k, fallback) => (LBL[k] != null ? LBL[k] : fallback);

        const sectionsEl = root.querySelector('[data-role="sections"]');
        const editorEl = root.querySelector('[data-role="editor"]');
        const previewEl = root.querySelector('[data-role="preview"]');
        const typePickerEl = root.querySelector('[data-role="type-picker"]');
        const addBtn = root.querySelector('[data-role="add-section"]');
        const builderCanvasEl = root.querySelector('[data-role="builder-canvas"]');
        const structurePanelEl = root.querySelector('[data-role="structure-panel"]');
        const structureHandleEl = root.querySelector('[data-role="structure-drag-handle"]');
        const structureToggleEl = root.querySelector('[data-role="structure-toggle"]');
        const previewMarketEl = root.querySelector('[data-role="preview-market"]');
        const resetMarketOrderEl = root.querySelector('[data-role="reset-market-order"]');
        const marketViewStatusEl = root.querySelector('[data-role="market-view-status"]');
        const issuesToggleEl = root.querySelector('[data-role="issues-toggle"]');
        const issuesPanelEl = root.querySelector('[data-role="issues-panel"]');
        const issuesLabelEl = root.querySelector('[data-role="issues-label"]');
        const issuesCountEl = root.querySelector('[data-role="issues-count"]');
        const saveStatusEl = root.querySelector('[data-role="save-status"]');
        const sectionCountEl = root.querySelector('[data-role="section-count"]');
        const headerModeBarEl = root.querySelector('[data-role="header-mode-bar"]');
        const exitHeaderModeEl = root.querySelector('[data-role="exit-header-mode"]');
        const newNavbarEl = root.querySelector('[data-role="new-navbar"]');

        const state = {platform: PLATFORM, scope_type: 'global', scope_id: null, is_active: true, sections: []};
        const SECTION_PAGE_SIZE = 6;
        let sel = -1;
        let lastSelIndex = -1;
        let editorTab = 'content';
        let previewDevice = PLATFORM === 'web' ? 'desktop' : 'android';
        let cleanPreview = false;
        let previewZoom = 100;
        const previewPan = {x: 0, y: 0};
        const PREVIEW_RENDER_DEBOUNCE_MS = 180;
        const EDITOR_ANALYSIS_DEBOUNCE_MS = 160;
        let previewRenderTimer = null;
        let editorAnalysisTimer = null;
        let previewSwiperInstances = [];
        let resetPreviewViewOnRender = false;
        let pendingPreviewSectionScroll = null;
        let focusedPlayRow = null;
        let previewMarketId = Number(new URLSearchParams(window.location.search).get('home_market_id')) || null;
        let previewMarketLabel = previewMarketId ? ('Market #' + previewMarketId) : 'All markets';
        let previewMarketControl = null;
        let marketCatalogLoading = false;
        let marketCatalogError = false;
        let marketCatalogRequest = 0;
        let marketCatalogTimer = null;
        let marketCatalogSignature = '';
        const disclosureState = new Map();
        let savedSignature = null;
        const savedSectionSignatures = new Map();
        let unsavedChanges = false;
        let headerMode = false;
        let headerDirty = false;
        let headerEditorMounted = false;
        let headerTarget = 'all';
        let afterSaveAction = null;
        let issuesOpen = false;
        let sectionTotal = 0;
        let sectionLastPage = 1;
        let sectionLoadGeneration = 0;
        let sectionPreviewObserver = null;
        let sectionBatchHydrationCount = 0;
        let sectionSelectionRequest = 0;
        let previewTargetLoading = false;
        let previewTargetLoadingLabel = '';
        const loadedSectionPages = new Set();
        const sectionPageRequests = new Map();
        const layoutCache = new Map();
        let cachedPreviewViewState = null;

        const historyStack = [];
        let historyIndex = -1;
        let isHistoryAction = false;

        function initStructurePanel() {
            if (!structurePanelEl) return;

            if (structureToggleEl) {
                structureToggleEl.setAttribute('aria-expanded', 'true');
                structureToggleEl.addEventListener('click', event => {
                    event.stopPropagation();
                    const collapsed = structurePanelEl.classList.toggle('is-collapsed');
                    structureToggleEl.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                });
            }

            if (!builderCanvasEl || !structureHandleEl) return;

            let drag = null;

            structureHandleEl.addEventListener('pointerdown', event => {
                if (event.button !== 0 || event.target.closest('button') || window.matchMedia('(max-width: 991.98px)').matches) return;

                const panelRect = structurePanelEl.getBoundingClientRect();
                const canvasRect = builderCanvasEl.getBoundingClientRect();

                structurePanelEl.style.right = 'auto';
                structurePanelEl.style.left = (panelRect.left - canvasRect.left) + 'px';
                structurePanelEl.style.top = (panelRect.top - canvasRect.top) + 'px';
                structurePanelEl.classList.add('is-dragging');

                drag = {
                    pointerId: event.pointerId,
                    offsetX: event.clientX - panelRect.left,
                    offsetY: event.clientY - panelRect.top,
                };

                structureHandleEl.setPointerCapture(event.pointerId);
                event.preventDefault();
            });

            structureHandleEl.addEventListener('pointermove', event => {
                if (!drag || event.pointerId !== drag.pointerId) return;

                const canvasRect = builderCanvasEl.getBoundingClientRect();
                const panelRect = structurePanelEl.getBoundingClientRect();
                const maxLeft = Math.max(8, canvasRect.width - panelRect.width - 8);
                const maxTop = Math.max(8, canvasRect.height - panelRect.height - 8);
                const left = Math.max(8, Math.min(event.clientX - canvasRect.left - drag.offsetX, maxLeft));
                const top = Math.max(8, Math.min(event.clientY - canvasRect.top - drag.offsetY, maxTop));

                structurePanelEl.style.left = left + 'px';
                structurePanelEl.style.top = top + 'px';
            });

            const stopDragging = event => {
                if (!drag || event.pointerId !== drag.pointerId) return;
                if (structureHandleEl.hasPointerCapture(event.pointerId)) {
                    structureHandleEl.releasePointerCapture(event.pointerId);
                }
                drag = null;
                structurePanelEl.classList.remove('is-dragging');
            };

            structureHandleEl.addEventListener('pointerup', stopDragging);
            structureHandleEl.addEventListener('pointercancel', stopDragging);
        }

        function scheduleEditorAnalysis() {
            if (editorAnalysisTimer) clearTimeout(editorAnalysisTimer);
            editorAnalysisTimer = window.setTimeout(() => {
                editorAnalysisTimer = null;
                updateUnsavedState();
                renderIssues();
            }, EDITOR_ANALYSIS_DEBOUNCE_MS);
        }

        const handleEditorMutation = event => {
            schedulePreviewRender(event);
            scheduleEditorAnalysis();
        };
        editorEl.addEventListener('input', handleEditorMutation);
        editorEl.addEventListener('change', handleEditorMutation);

        function sectionHasPendingMediaChanges(section) {
            const sectionFiles = section._mediaFiles && Object.values(section._mediaFiles).some(Boolean);
            const rowFiles = section._rowBackgroundFiles && Object.values(section._rowBackgroundFiles).some(Boolean);
            const itemFiles = (section.items || []).some(item => item._imageFile || item._mobileImageFile || item._removeImage);
            return sectionFiles || rowFiles || itemFiles;
        }

        function hasPendingMediaChanges() {
            return state.sections.some(sectionHasPendingMediaChanges);
        }

        function currentSignature() {
            return JSON.stringify(serialize());
        }

        function scopeCacheKey(scopeType, scopeId) {
            return state.platform + ':' + scopeType + ':' + (scopeId || 'global');
        }

        function cloneLayoutValue(value) {
            return JSON.parse(JSON.stringify(value));
        }

        function cacheCurrentLayout() {
            if (savedSignature === null || unsavedChanges || marketCatalogLoading || previewTargetLoading || sectionBatchHydrationCount > 0) return;
            layoutCache.set(scopeCacheKey(state.scope_type, state.scope_id), {
                is_active: state.is_active,
                sections: cloneLayoutValue(state.sections),
                sectionTotal,
                sectionLastPage,
                loadedSectionPages: Array.from(loadedSectionPages),
                selectedIndex: sel,
                editorTab,
                history: historyStack.slice(),
                historyIndex,
                savedSignature,
                previewViewState: capturePreviewViewState(),
                previewCatalog: cloneLayoutValue(cfg.previewCatalog),
                marketCatalogSignature,
                marketCatalogError,
                previewMarketId,
                previewMarketLabel,
            });
        }

        function restoreCachedLayout(scopeType, scopeId) {
            const cached = layoutCache.get(scopeCacheKey(scopeType, scopeId));
            if (!cached) return false;

            sectionLoadGeneration++;
            marketCatalogRequest++;
            sectionSelectionRequest++;
            if (editorAnalysisTimer) clearTimeout(editorAnalysisTimer);
            editorAnalysisTimer = null;
            sectionPageRequests.clear();
            loadedSectionPages.clear();
            cached.loadedSectionPages.forEach(page => loadedSectionPages.add(Number(page)));
            if (sectionPreviewObserver) sectionPreviewObserver.disconnect();
            if (marketCatalogTimer) clearTimeout(marketCatalogTimer);
            marketCatalogTimer = null;
            sectionBatchHydrationCount = 0;
            previewTargetLoading = false;
            previewTargetLoadingLabel = '';
            state.is_active = cached.is_active;
            state.sections = cloneLayoutValue(cached.sections);
            captureSavedSectionSignatures();
            sectionTotal = cached.sectionTotal;
            sectionLastPage = cached.sectionLastPage;
            sel = Math.min(cached.selectedIndex, state.sections.length - 1);
            editorTab = cached.editorTab;
            historyStack.length = 0;
            historyStack.push(...cached.history);
            historyIndex = cached.historyIndex;
            savedSignature = cached.savedSignature;
            unsavedChanges = false;
            root.dataset.unsaved = '0';
            cfg.previewCatalog = cloneLayoutValue(cached.previewCatalog);
            marketCatalogSignature = cached.previewMarketId === previewMarketId ? cached.marketCatalogSignature : '';
            marketCatalogLoading = false;
            marketCatalogError = cached.previewMarketId === previewMarketId ? cached.marketCatalogError : false;
            previewMarketLabel = cached.previewMarketId === previewMarketId ? cached.previewMarketLabel : previewMarketLabel;
            cachedPreviewViewState = cached.previewViewState;
            resetPreviewViewOnRender = false;

            isHistoryAction = true;
            renderSections();
            renderEditor();
            if (sel >= 0 && state.sections[sel] && state.sections[sel]._loaded === false) renderPreview();
            isHistoryAction = false;
            updateHistoryButtons();
            setSaveStatus('saved', 'Saved');
            if (cached.previewMarketId !== previewMarketId) refreshMarketPreviewCatalog();
            return true;
        }

        function setSaveStatus(stateName, label) {
            if (!saveStatusEl) return;
            saveStatusEl.dataset.state = stateName;
            const text = saveStatusEl.querySelector('span');
            if (text) text.textContent = label;
        }

        function updateUnsavedState(sectionIndex) {
            const layoutDirty = savedSignature !== null && (currentSignature() !== savedSignature || hasPendingMediaChanges());
            unsavedChanges = layoutDirty || headerDirty;
            root.dataset.unsaved = unsavedChanges ? '1' : '0';
            setSaveStatus(unsavedChanges ? 'unsaved' : 'saved', unsavedChanges ? 'Unsaved changes' : 'Saved');
            refreshSectionModifiedIndicators(Number.isInteger(sectionIndex) ? sectionIndex : sel);
        }

        function markSavedState() {
            savedSignature = currentSignature();
            captureSavedSectionSignatures();
            unsavedChanges = false;
            root.dataset.unsaved = '0';
            setSaveStatus('saved', 'Saved');
            refreshSectionModifiedIndicators();
        }

        function finishSaveAction() {
            const action = afterSaveAction;
            afterSaveAction = null;
            if (action) action();
        }

        function recordStateChange(sectionIndex) {
            if (isHistoryAction) return;
            const serialized = JSON.stringify(state.sections);
            if (historyIndex >= 0 && historyStack[historyIndex] === serialized) {
                updateUnsavedState(sectionIndex);
                return;
            }
            if (historyIndex < historyStack.length - 1) {
                historyStack.splice(historyIndex + 1);
            }
            historyStack.push(serialized);
            if (historyStack.length > 50) {
                historyStack.shift();
            }
            historyIndex = historyStack.length - 1;
            updateHistoryButtons();
            updateUnsavedState(sectionIndex);
        }

        function updateHistoryButtons() {
            const undoBtn = editorEl.querySelector('#hb-undo-btn');
            const redoBtn = editorEl.querySelector('#hb-redo-btn');
            if (undoBtn) {
                undoBtn.disabled = historyIndex <= 0;
            }
            if (redoBtn) {
                redoBtn.disabled = historyIndex >= historyStack.length - 1;
            }
        }

        function undo() {
            if (historyIndex > 0) {
                isHistoryAction = true;
                historyIndex--;
                state.sections = JSON.parse(historyStack[historyIndex]);
                if (sel >= state.sections.length) sel = state.sections.length - 1;
                renderSections();
                renderEditor();
                isHistoryAction = false;
                updateHistoryButtons();
                updateUnsavedState();
            }
        }

        function redo() {
            if (historyIndex < historyStack.length - 1) {
                isHistoryAction = true;
                historyIndex++;
                state.sections = JSON.parse(historyStack[historyIndex]);
                if (sel >= state.sections.length) sel = state.sections.length - 1;
                renderSections();
                renderEditor();
                isHistoryAction = false;
                updateHistoryButtons();
                updateUnsavedState();
            }
        }

        /* ---------- load ---------- */
        function load() {
            setSaveStatus('loading', 'Loading…');
            const generation = ++sectionLoadGeneration;
            savedSectionSignatures.clear();
            if (editorAnalysisTimer) clearTimeout(editorAnalysisTimer);
            editorAnalysisTimer = null;
            sectionSelectionRequest++;
            marketCatalogRequest++;
            sectionBatchHydrationCount = 0;
            previewTargetLoading = false;
            previewTargetLoadingLabel = '';
            loadedSectionPages.clear();
            sectionPageRequests.clear();
            if (sectionPreviewObserver) sectionPreviewObserver.disconnect();
            const params = new URLSearchParams({
                platform: state.platform,
                scope_type: state.scope_type,
                paginate_sections: '1',
                include_manifest: '1',
                page: '1',
                per_page: String(SECTION_PAGE_SIZE),
            });
            if (state.scope_id) params.set('scope_id', state.scope_id);
            api('get', cfg.showUrl + '?' + params.toString())
                .then(r => {
                    if (generation !== sectionLoadGeneration) return;
                    try {
                        const d = r.data.data || {};
                        state.is_active = d.is_active !== false;
                        const pagination = d.sections_pagination || {};
                        const hydratedById = new Map((d.sections || []).map(section => [Number(section.id), section]));
                        const manifest = Array.isArray(d.section_manifest) ? d.section_manifest : [];
                        state.sections = (manifest.length ? manifest : (d.sections || [])).map((summary, index) => {
                            const hydrated = hydratedById.get(Number(summary.id));
                            const section = normalizeSection(hydrated || summary);
                            section._loaded = Boolean(hydrated) || !manifest.length;
                            section._load_page = Math.floor(index / Number(pagination.per_page || SECTION_PAGE_SIZE)) + 1;
                            section._item_count = Number(summary.item_count ?? section.items.length);
                            return section;
                        });
                        sectionTotal = Number(pagination.total ?? state.sections.length);
                        sectionLastPage = Math.max(1, Number(pagination.last_page || 1));
                        if ((d.sections || []).length || sectionTotal === 0) loadedSectionPages.add(1);
                        sel = state.sections.length ? 0 : -1;

                        historyStack.length = 0;
                        historyStack.push(JSON.stringify(state.sections));
                        historyIndex = 0;

                        isHistoryAction = true;
                        markSavedState();
                        renderSections();
                        renderEditor();
                        refreshMarketPreviewCatalog();
                    } catch (error) {
                        console.error('Home builder preview render failed', error);
                    } finally {
                        isHistoryAction = false;
                    }
                }, error => {
                    if (generation !== sectionLoadGeneration) return;
                    console.error('Home builder layout request failed', error);
                    setSaveStatus('error', 'Load failed');
                    Toast.fire({
                    icon: 'error',
                    title: 'Failed to load layout'
                    });
                });
        }

        function sectionPageParams(page) {
            const params = new URLSearchParams({
                platform: state.platform,
                scope_type: state.scope_type,
                paginate_sections: '1',
                include_manifest: '0',
                page: String(page),
                per_page: String(SECTION_PAGE_SIZE),
            });
            if (state.scope_id) params.set('scope_id', state.scope_id);
            return params;
        }

        function rebaseHydratedState() {
            savedSignature = currentSignature();
            captureSavedSectionSignatures();
            historyStack.length = 0;
            historyStack.push(JSON.stringify(state.sections));
            historyIndex = 0;
            updateHistoryButtons();
            unsavedChanges = false;
            root.dataset.unsaved = '0';
            setSaveStatus('saved', 'Saved');
        }

        function mergeSectionPage(sections, page, preserveManifestChanges) {
            const incoming = new Map((sections || []).map(section => [Number(section.id), section]));
            const selectedId = state.sections[sel] ? Number(state.sections[sel].id) : null;
            state.sections = state.sections.map(previous => {
                const raw = incoming.get(Number(previous.id));
                if (!raw) return previous;
                const section = normalizeSectionKeepingUi(raw, previous);
                savedSectionSignatures.set(sectionUiKey(section), sectionSavedSignature(section));
                if (preserveManifestChanges) {
                    section.is_active = previous.is_active;
                    section.market_positions = previous.market_positions;
                }
                section._loaded = true;
                section._load_page = previous._load_page || page;
                section._item_count = previous._item_count ?? section.items.length;
                return section;
            });
            loadedSectionPages.add(page);
            return selectedId !== null && incoming.has(selectedId);
        }

        function loadSectionPage(page) {
            const normalizedPage = Math.max(1, Math.min(sectionLastPage, Number(page) || 1));
            if (loadedSectionPages.has(normalizedPage)) return Promise.resolve();
            if (sectionPageRequests.has(normalizedPage)) return sectionPageRequests.get(normalizedPage);

            const generation = sectionLoadGeneration;
            const wasUnsaved = unsavedChanges;
            const request = api('get', cfg.showUrl + '?' + sectionPageParams(normalizedPage).toString())
                .then(r => {
                    if (generation !== sectionLoadGeneration) return;
                    const data = r.data.data || {};
                    const selectedHydrated = mergeSectionPage(data.sections || [], normalizedPage, wasUnsaved || unsavedChanges);
                    if (!wasUnsaved && !unsavedChanges) rebaseHydratedState();
                    if (sectionBatchHydrationCount === 0) {
                        renderSections();
                        if (selectedHydrated) renderEditor();
                        else renderPreview();
                        if (previewMarketId) refreshMarketPreviewCatalog();
                    }
                })
                .catch(error => {
                    if (generation !== sectionLoadGeneration) return;
                    console.error('Home builder section page request failed', error);
                    Toast.fire({icon: 'error', title: 'Failed to load more sections'});
                    throw error;
                })
                .finally(() => {
                    if (sectionPageRequests.get(normalizedPage) === request) sectionPageRequests.delete(normalizedPage);
                });
            sectionPageRequests.set(normalizedPage, request);
            return request;
        }

        function ensureSectionLoaded(index) {
            const section = state.sections[index];
            if (!section || section._loaded !== false) return Promise.resolve();
            return loadSectionPage(section._load_page);
        }

        function loadSectionPagesTogether(pages) {
            const missingPages = Array.from(new Set(pages.map(Number)))
                .filter(page => page > 0 && page <= sectionLastPage && !loadedSectionPages.has(page));
            if (!missingPages.length) return Promise.resolve();
            sectionBatchHydrationCount++;
            return Promise.allSettled(missingPages.map(loadSectionPage))
                .then(results => {
                    const failed = results.find(result => result.status === 'rejected');
                    if (failed) throw failed.reason;
                })
                .finally(() => {
                    sectionBatchHydrationCount = Math.max(0, sectionBatchHydrationCount - 1);
                });
        }

        function ensureSectionsThrough(index) {
            const section = state.sections[index];
            if (!section) return Promise.resolve();
            const targetPage = Number(section._load_page) || 1;
            const pages = [];
            for (let page = 1; page <= targetPage; page++) pages.push(page);
            return loadSectionPagesTogether(pages);
        }

        function ensureAllSectionsLoaded() {
            const pages = Array.from(new Set(state.sections
                .filter(section => section._loaded === false)
                .map(section => section._load_page)));
            return loadSectionPagesTogether(pages);
        }

        function normalizeSection(s) {
            const config = normalizeRowsConfig(s.config || {});
            const showTitle = s.show_title !== undefined
                ? parseBool(s.show_title, true)
                : parseBool(config.show_title, true);
            delete config.show_title;
            const section = {
                id: s.id || null,
                _ui_key: s._ui_key || null,
                type: s.type,
                title: s.title || '',
                show_title: showTitle,
                style: s.style || null,
                config,
                sort_order: s.sort_order || 0,
                is_active: s.is_active !== false,
                market_ids: s.market_ids || [],
                market_positions: (s.market_positions || []).map(position => ({
                    market_id: Number(position.market_id),
                    sort_order: Number(position.sort_order) || 0,
                })),
                background_image: s.background_image || null,
                hero_media: s.hero_media || null,
                card_background_image: s.card_background_image || null,
                _heroItemsBySource: s._heroItemsBySource || {},
                items: (s.items || []).map(it => ({
                    id: it.id || null,
                    _ui_key: it._ui_key || null,
                    purpose: it.purpose || 'display',
                    itemable_type: it.itemable_type || null,
                    itemable_id: it.itemable_id || null,
                    itemable_label: it.itemable_label || null,
                    itemable_image: it.itemable_image || null,
                    itemable_meta: it.itemable_meta || {},
                    title: it.title || '',
                    link_url: it.link_url || '',
                    image: it.image || null,
                    mobile_image: it.mobile_image || null,
                    config: it.config || {}
                })),
            };
            section._loaded = s._loaded !== false;
            section._load_page = s._load_page || null;
            section._item_count = Number(s._item_count ?? s.item_count ?? section.items.length);
            if (section.type === 'categories' || section.type === 'brands') {
                if (!allowedSourceModes.includes(section.config.source)) {
                    section.config.source = allowedSourceModes[0] || 'custom';
                }
            }
            if (section.type === 'categories' && !allowedCategoryStyles.includes(section.style)) {
                section.style = allowedCategoryStyles.includes('default') ? 'default' : (allowedCategoryStyles[0] || null);
            }
            if (section.type === 'products' && !allowedProductStyles.includes(section.style)) {
                const defaultStyle = PLATFORM === 'app' ? 'quick_add' : 'standard';
                section.style = allowedProductStyles.includes(defaultStyle) ? defaultStyle : (allowedProductStyles[0] || null);
            }
            if (section.type === 'hero') {
                const source = section.config.item_source || 'categories';
                section.config.item_source = source;
                section.config.card_bg_type = section.config.card_bg_type || 'color';
                section.config.card_bg_color = section.config.card_bg_color || '#F1EFE8';
                section.config.card_title_color = section.config.card_title_color || '#222222';
                if (!STYLES.hero.some(option => option[0] === section.style)) section.style = 'image_title';
                section._heroItemsBySource[source] = section.items;
            }
            sectionUiKey(section);
            section.items.forEach(itemUiKey);
            return section;
        }

        function normalizeSectionKeepingUi(s, previous) {
            if (previous) {
                s._ui_key = previous._ui_key;
                s._heroItemsBySource = previous._heroItemsBySource || {};
                (s.items || []).forEach((item, index) => {
                    const previousItem = previous.items.find(candidate => item.id && candidate.id === item.id) || previous.items[index];
                    if (previousItem) item._ui_key = previousItem._ui_key;
                });
            }
            const section = normalizeSection(s);
            if (previous) {
                section._load_page = previous._load_page || section._load_page;
                section._item_count = previous._item_count ?? section._item_count;
            }
            return section;
        }

        function defaultSection(type) {
            const s = {
                id: null,
                _ui_key: newUiKey('section'),
                type,
                title: '',
                show_title: true,
                style: null,
                config: {},
                sort_order: 0,
                is_active: true,
                market_ids: [],
                market_positions: [],
                items: [],
                background_image: null,
                hero_media: null,
                card_background_image: null
            };
            if (type === 'hero') {
                s.style = 'image_title';
                s.config = {
                    media_type: 'image',
                    layout: 'items',
                    item_source: 'categories',
                    card_bg_type: 'color',
                    card_bg_color: '#F1EFE8',
                    card_title_color: '#222222'
                };
            }
            if (type === 'products') {
                s.style = PLATFORM === 'web' ? 'standard' : 'quick_add';
                s.config = {source: 'newly_added', orientation: 'horizontal', background_type: 'none'};
            }
            if (type === 'categories') {
                s.style = PLATFORM === 'app' ? 'default' : 'overlay';
                s.config = {source: PLATFORM === 'app' ? 'custom' : 'all'};
            }
            if (type === 'brands') {
                s.style = 'image_title';
                s.config = {source: PLATFORM === 'app' ? 'custom' : 'all'};
            }
            if (type === 'play_image') {
                s.style = 'grid';
                s.config = {
                    outer_padding: 0,
                    grid_gap: 0,
                    border_radius: 0,
                    hover_effect: 'none'
                };
            }
            return s;
        }

        function sectionFromTemplate(template) {
            const section = defaultSection(template.type);
            section.title = template.title_default || '';
            section.show_title = Boolean(template.title_default);
            section.style = template.style !== undefined ? template.style : section.style;
            section.config = Object.assign({}, section.config, JSON.parse(JSON.stringify(template.config || {})));
            section.items = JSON.parse(JSON.stringify(template.items || []));

            return normalizeSection(section);
        }

        function insertTemplate(template) {
            const section = sectionFromTemplate(template);
            state.sections.push(section);
            sel = state.sections.length - 1;
            editorTab = 'content';
            recordStateChange();
            renderSections();
            renderEditor();
            Toast.fire({icon: 'success', title: template.title + ' added. Add your content before saving.'});
        }

        function applyTheme(theme) {
            const sections = (theme.templateIds || [])
                .map(id => TEMPLATES.find(template => template.id === id))
                .filter(Boolean)
                .map(sectionFromTemplate);
            if (!sections.length) return;

            state.sections.push(...sections);
            sel = state.sections.length - sections.length;
            editorTab = 'content';
            recordStateChange();
            renderSections();
            renderEditor();
            Toast.fire({icon: 'success', title: theme.title + ' structure added. Add your content before saving.'});
        }

        function disclosureInfo(section, kind, identifier, defaultOpen) {
            const key = sectionUiKey(section) + ':' + kind + ':' + identifier;
            const open = disclosureState.has(key) ? disclosureState.get(key) : defaultOpen;
            return {
                key,
                open,
                show: open ? ' show' : '',
                expanded: open ? 'true' : 'false'
            };
        }

        /* ---------- sections list ---------- */
        function badge(s) {
            if (s.type === 'products') return nameOf(s.config.source, PRODUCT_SOURCE);
            if (s.type === 'hero') return s.config.layout === 'items' ? 'With items' : 'Banner only';
            if (s.type === 'play_image') return 'Custom Grid';
            if (STYLES[s.type]) return nameOf(s.style, STYLES[s.type]);
            return nameOf(s.type, TYPE);
        }

        function sectionVisibleInPreviewMarket(section) {
            if (!previewMarketId) return true;
            const marketIds = (section.market_ids || []).map(Number);
            return marketIds.length === 0 || marketIds.includes(previewMarketId);
        }

        function sectionMarketPosition(section) {
            if (!previewMarketId) return null;
            return (section.market_positions || []).find(position => Number(position.market_id) === previewMarketId) || null;
        }

        function marketProductPlans() {
            return state.sections.flatMap(section => {
                if (section._loaded === false) return [];
                if (!sectionVisibleInPreviewMarket(section)) return [];
                const isProductSection = section.type === 'products';
                const isProductHero = section.type === 'hero'
                    && section.config.layout === 'items'
                    && section.config.item_source === 'products';
                if (!isProductSection && !isProductHero) return [];

                const displayProductIds = (section.items || [])
                    .filter(item => item.purpose === 'display' && item.itemable_type === 'product' && item.itemable_id)
                    .map(item => Number(item.itemable_id));
                const categoryIds = isProductSection
                    ? (section.items || [])
                        .filter(item => item.purpose === 'filter' && item.itemable_type === 'category' && item.itemable_id)
                        .map(item => Number(item.itemable_id))
                    : [];

                return [{
                    key: sectionUiKey(section),
                    source: isProductHero ? 'custom' : (section.config.source || 'newly_added'),
                    limit: isProductHero ? 6 : Math.max(1, Number(section.config.limit) || 12),
                    category_ids: categoryIds,
                    product_ids: displayProductIds,
                }];
            });
        }

        function scheduleMarketCatalogRefresh() {
            if (marketCatalogTimer) clearTimeout(marketCatalogTimer);
            marketCatalogTimer = window.setTimeout(refreshMarketPreviewCatalog, 220);
        }

        function refreshMarketPreviewCatalog() {
            if (marketCatalogTimer) {
                clearTimeout(marketCatalogTimer);
                marketCatalogTimer = null;
            }
            const requestId = ++marketCatalogRequest;

            if (!cfg.previewCatalogUrl) {
                marketCatalogSignature = '';
                marketCatalogLoading = false;
                marketCatalogError = false;
                cfg.previewCatalog = JSON.parse(JSON.stringify(defaultPreviewCatalog));
                updateProductCountSummary();
                renderSections();
                renderPreview();
                return Promise.resolve();
            }

            marketCatalogSignature = (previewMarketId || 'all') + ':' + JSON.stringify(marketProductPlans());
            marketCatalogLoading = true;
            marketCatalogError = false;
            cfg.previewCatalog = Object.assign({}, defaultPreviewCatalog, {section_products: {}});
            updateProductCountSummary();
            renderSections();
            renderPreview();

            return api('post', cfg.previewCatalogUrl, {
                market_id: previewMarketId || null,
                sections: marketProductPlans(),
            }).then(response => {
                if (requestId !== marketCatalogRequest) return;
                const data = response.data.data || {};
                cfg.previewCatalog = Object.assign({}, defaultPreviewCatalog, {
                    section_products: data.section_products || {},
                });
                if (data.market && data.market.name) {
                    previewMarketLabel = data.market.code
                        ? data.market.code + ' — ' + data.market.name
                        : data.market.name;
                }
                marketCatalogLoading = false;
                marketCatalogError = false;
                updateProductCountSummary();
                updateMarketViewControls();
                renderSections();
                renderPreview();
            }).catch(error => {
                if (requestId !== marketCatalogRequest) return;
                console.error('Market-aware home preview catalog request failed', error);
                cfg.previewCatalog = Object.assign({}, defaultPreviewCatalog, {section_products: {}});
                marketCatalogLoading = false;
                marketCatalogError = true;
                updateProductCountSummary();
                updateMarketViewControls();
                renderSections();
                renderPreview();
            });
        }

        function sectionHasMarketProducts(section) {
            if (section._loaded === false) return true;
            if (!previewMarketId || marketCatalogLoading) return true;
            if (section.type !== 'products' && !(section.type === 'hero' && section.config.item_source === 'products')) return true;
            const products = (cfg.previewCatalog.section_products || {})[sectionUiKey(section)];
            return Array.isArray(products) && products.length > 0;
        }

        function marketOrderedSectionEntries(includeHidden) {
            const entries = state.sections.map((section, index) => ({section, index}));
            if (!previewMarketId) return entries;

            const visible = entries.filter(entry => sectionVisibleInPreviewMarket(entry.section));
            const hidden = entries.filter(entry => !sectionVisibleInPreviewMarket(entry.section));
            visible.sort((left, right) => {
                const leftPos = sectionMarketPosition(left.section);
                const rightPos = sectionMarketPosition(right.section);
                const leftOrder = leftPos !== null ? leftPos.sort_order : left.index;
                const rightOrder = rightPos !== null ? rightPos.sort_order : right.index;
                if (leftOrder !== rightOrder) {
                    return leftOrder - rightOrder;
                }
                return left.index - right.index;
            });

            return includeHidden ? visible.concat(hidden) : visible;
        }

        function applyPreviewMarketOrder(sectionIndexes) {
            if (!previewMarketId) return;
            state.sections.forEach(section => {
                section.market_positions = (section.market_positions || [])
                    .filter(position => Number(position.market_id) !== previewMarketId);
            });
            sectionIndexes.forEach((sectionIndex, sortOrder) => {
                const section = state.sections[sectionIndex];
                if (!section) return;
                section.market_positions.push({market_id: previewMarketId, sort_order: sortOrder});
            });
            updateMarketViewControls();
        }

        function marketHasCustomOrder() {
            return !!previewMarketId && state.sections.some(section => sectionMarketPosition(section));
        }

        function updateMarketViewControls() {
            const customized = marketHasCustomOrder();
            const pickerBox = root.querySelector('.hb-market-picker-box');
            if (pickerBox) pickerBox.classList.toggle('is-active-market', Boolean(previewMarketId));
            if (resetMarketOrderEl) resetMarketOrderEl.classList.toggle('d-none', !customized);
            if (marketViewStatusEl) {
                marketViewStatusEl.textContent = previewMarketId
                    ? (marketCatalogLoading
                        ? 'Loading products available in ' + previewMarketLabel + '…'
                        : marketCatalogError
                            ? 'Could not load market products · retry by selecting the market again'
                            : customized
                        ? 'Custom order for ' + previewMarketLabel + ' · drag visible sections to reorder'
                        : 'Using default order for ' + previewMarketLabel + ' · drag to customize')
                    : 'Previewing default homepage order (All markets)';
            }
        }

        function renderSections() {
            if (!marketCatalogLoading && cfg.previewCatalogUrl) {
                const signature = (previewMarketId || 'all') + ':' + JSON.stringify(marketProductPlans());
                if (signature !== marketCatalogSignature) scheduleMarketCatalogRefresh();
            }
            sectionsEl.innerHTML = '';
            sectionTotal = state.sections.length;
            if (sectionCountEl) sectionCountEl.textContent = String(sectionTotal);
            marketOrderedSectionEntries(true).forEach(entry => {
                const s = entry.section;
                const i = entry.index;
                const marketHidden = previewMarketId && !sectionVisibleInPreviewMarket(s);
                const marketEmpty = previewMarketId && !marketHidden && !sectionHasMarketProducts(s);
                const row = document.createElement('div');
                row.className = 'hb-row' + (i === sel ? ' active' : '') + (marketHidden || marketEmpty ? ' is-market-hidden' : '');
                if (s._loaded === false) row.classList.add('is-loading');
                row.dataset.i = i;
                row.dataset.marketVisible = marketHidden ? '0' : '1';
                row.innerHTML =
                    '<i class="ti ti-grip-vertical hb-handle"></i>' +
                    '<i class="ti ' + ICON[s.type] + '"></i>' +
                    '<div class="hb-row-title"><div>' + esc(s.title || nameOf(s.type, TYPE)) + '</div>' +
                    '<span class="badge bg-secondary-lt hb-badge">' + esc(badge(s)) + '</span>' +
                    (sectionIsModified(s) ? '<span class="hb-section-modified-label"><i class="ti ti-pencil"></i>Modified</span>' : '') +
                    (s._loaded === false ? '<span class="hb-section-loading-label"><i class="ti ti-cloud-download"></i> Loads on visit</span>' : '') +
                    (marketHidden ? '<span class="hb-market-row-badge">Hidden in market</span>' : '') +
                    (marketEmpty ? '<span class="hb-market-row-badge">No products in market</span>' : '') + '</div>' +
                    '<label class="form-check form-switch m-0"><input class="form-check-input hb-enable" type="checkbox"' + (s.is_active ? ' checked' : '') + (cfg.canEdit && s._loaded !== false ? '' : ' disabled') + '></label>';
                row.addEventListener('click', e => {
                    if (e.target.classList.contains('hb-enable')) return;
                    const requestId = ++sectionSelectionRequest;
                    sel = i;
                    editorTab = 'content';
                    focusedPlayRow = null;
                    renderSections();
                    const firstMissingIndex = state.sections.findIndex((section, index) => index <= i && section._loaded === false);
                    if (firstMissingIndex < 0) {
                        previewTargetLoading = false;
                        previewTargetLoadingLabel = '';
                        pendingPreviewSectionScroll = i;
                        renderEditor();
                        return;
                    }
                    previewTargetLoading = true;
                    previewTargetLoadingLabel = 'Loading sections ' + (firstMissingIndex + 1) + '–' + (i + 1) + '…';
                    renderEditor();
                    if (state.sections[i] && state.sections[i]._loaded === false) renderPreview();
                    ensureSectionsThrough(i)
                        .then(() => {
                            const signature = (previewMarketId || 'all') + ':' + JSON.stringify(marketProductPlans());
                            return signature !== marketCatalogSignature ? refreshMarketPreviewCatalog() : null;
                        })
                        .then(() => {
                            if (requestId !== sectionSelectionRequest || sel !== i) return;
                            previewTargetLoading = false;
                            previewTargetLoadingLabel = '';
                            pendingPreviewSectionScroll = i;
                            renderSections();
                            renderEditor();
                        })
                        .catch(() => {
                            if (requestId !== sectionSelectionRequest) return;
                            previewTargetLoading = false;
                            previewTargetLoadingLabel = '';
                            renderSections();
                            renderEditor();
                        });
                });
                row.querySelector('.hb-enable').addEventListener('change', e => {
                    s.is_active = e.target.checked;
                    recordStateChange(i);
                    if (i === sel) renderEditor();
                    else renderPreview();
                });
                sectionsEl.appendChild(row);
            });
            initSortable();
            renderAddButtons();
            updateMarketViewControls();
            renderIssues();
        }

        function initSortable() {
            if (!cfg.canEdit || !window.Sortable) return;
            const handleOnly = window.matchMedia('(max-width: 991.98px)').matches;
            window.Sortable.create(sectionsEl, {
                handle: handleOnly ? '.hb-handle' : '.hb-row',
                filter: 'button, a, input, select, textarea, label',
                preventOnFilter: false,
                animation: 150,
                ghostClass: 'hb-ghost',
                onEnd: function () {
                    const order = Array.from(sectionsEl.children).map(c => +c.dataset.i);
                    if (previewMarketId) {
                        const visibleOrder = Array.from(sectionsEl.children)
                            .filter(child => child.dataset.marketVisible === '1')
                            .map(child => Number(child.dataset.i));
                        applyPreviewMarketOrder(visibleOrder);
                        recordStateChange();
                        renderSections();
                        renderPreview();
                        return;
                    }
                    const selObj = state.sections[sel];
                    state.sections = order.map(idx => state.sections[idx]);
                    sel = state.sections.indexOf(selObj);
                    recordStateChange();
                    renderSections();
                    renderPreview();
                },
            });
        }

        function schedulePreviewRender(event, requestedDelay) {
            // A field handler may replace the editor markup before the delegated
            // event arrives here. In that case it already chose how to render.
            if (event && event.target && !editorEl.contains(event.target)) return;

            const target = event && event.target;
            const isDiscreteChange = event && event.type === 'change' && target && target.matches(
                'select, input[type="checkbox"], input[type="radio"], input[type="file"]'
            );
            const delay = requestedDelay !== undefined
                ? requestedDelay
                : (isDiscreteChange ? 0 : PREVIEW_RENDER_DEBOUNCE_MS);

            if (previewRenderTimer) clearTimeout(previewRenderTimer);
            previewRenderTimer = window.setTimeout(() => {
                previewRenderTimer = null;
                renderPreview();
            }, delay);
        }

        function previewItemImage(item, useMobile) {
            return useMobile
                ? (itemMobilePreview(item) || itemPreview(item) || item.itemable_image || '')
                : (itemPreview(item) || itemMobilePreview(item) || item.itemable_image || '');
        }

        function previewProductCard(item, useMobile, index, requestedStyle) {
            const meta = item.itemable_meta || {};
            const image = previewItemImage(item, useMobile);
            const title = item.itemable_label || item.title || ('Product ' + (index + 1));
            const badge = meta.badge || null;
            const currentPrice = meta.formatted_special_price || meta.formatted_price || '';
            const originalPrice = meta.formatted_special_price ? meta.formatted_price : '';
            const imageFit = meta.image_fit === 'cover' ? 'cover' : 'contain';
            const style = STYLES.products.some(option => option[0] === requestedStyle) ? requestedStyle : 'standard';
            const brand = meta.brand || '';
            const category = meta.category || '';
            const hasRating = Number(meta.rating_count || 0) > 0 && Number(meta.rating || 0) > 0;
            const rating = hasRating ? Number(meta.rating).toFixed(1) : '0.0';
            const stockClass = meta.is_out_of_stock ? ' is-out' : '';
            const stockLabel = meta.is_out_of_stock ? 'Out of stock' : 'In stock';
            const description = meta.short_description || '';
            const tags = Array.isArray(meta.detail_tags) ? meta.detail_tags.slice(0, 3) : [];
            const showShare = style === 'standard' || style === 'showcase';
            const media = '<div class="hb-product-media">' +
                (image ? '<img src="' + esc(image) + '" alt="" style="object-fit:' + imageFit + '">' : '<span class="hb-product-image-placeholder"></span>') +
                (badge && badge.label ? '<span class="hb-product-badge" style="background:' + esc(badge.bg_color || '#16a34a') + ';color:' + esc(badge.text_color || '#fff') + ';border-color:' + esc(badge.border_color || 'transparent') + '">' + esc(badge.label) + '</span>' : '') +
                '<div class="hb-product-actions"><i class="ti ti-heart"></i>' + (showShare ? '<i class="ti ti-share"></i>' : '') + '</div>' +
                '<div class="hb-product-dots"><i></i><i></i><i></i></div></div>';
            const price = currentPrice ? '<div class="hb-product-price"><b>' + esc(currentPrice) + '</b>' + (originalPrice ? '<del>' + esc(originalPrice) + '</del>' : '') + (meta.discount_percent && style !== 'showcase' ? '<em>' + esc(meta.discount_percent) + '% off</em>' : '') + '</div>' : '';
            const ratingHtml = '<span class="hb-product-rating' + (hasRating ? '' : ' is-empty') + '"><i class="ti ti-star-filled"></i>' + esc(rating) + (style === 'standard' || style === 'minimal' || style === 'showcase' ? '<small>(' + esc(meta.rating_count || 0) + ')</small>' : '') + '</span>';
            const action = '<button type="button" tabindex="-1"' + (meta.is_out_of_stock ? ' disabled' : '') + '><i class="ti ' + (style === 'compact' || style === 'minimal' ? 'ti-circle-plus' : 'ti-shopping-cart-plus') + '"></i>' + (meta.is_out_of_stock ? 'Out of stock' : (style === 'compact' || style === 'minimal' ? 'Add' : 'Add To Cart')) + '</button>';
            const stock = '<span class="hb-product-stock' + stockClass + '"><i></i>' + stockLabel + '</span>';

            let body = '';
            if (style === 'showcase') {
                body = '<div class="hb-product-body"><div class="hb-product-meta">' +
                    (meta.discount_percent ? '<span class="hb-product-discount">' + esc(meta.discount_percent) + '% off</span>' : '<span class="hb-product-brand">' + esc(brand || category) + '</span>') + stock + '</div>' +
                    '<strong class="hb-product-title">' + esc(title) + '</strong>' +
                    '<div class="hb-product-context">' + esc(brand) + (brand && category ? '<i>•</i>' : '') + esc(category) + '</div>' +
                    (description ? '<p class="hb-product-description">' + esc(description) + '</p>' : '') +
                    (tags.length ? '<div class="hb-product-tags">' + tags.map(tag => '<span>' + esc(tag) + '</span>').join('') + '</div>' : '') +
                    ratingHtml + price + action + '</div>';
            } else if (style === 'compact') {
                body = '<div class="hb-product-body"><div class="hb-product-meta"><span class="hb-product-context">' + esc(category || brand) + '</span>' + (hasRating ? ratingHtml : '') + '</div>' +
                    '<strong class="hb-product-title">' + esc(title) + '</strong><div class="hb-product-purchase">' + price + action + '</div></div>';
            } else if (style === 'minimal') {
                body = '<div class="hb-product-body"><div class="hb-product-meta"><span class="hb-product-context">' + esc(category || brand) + '</span>' + (hasRating ? ratingHtml : '') + '</div>' +
                    '<strong class="hb-product-title">' + esc(title) + '</strong>' + price + action + '</div>';
            } else {
                body = '<div class="hb-product-body"><div class="hb-product-meta"><span class="hb-product-brand">' + esc(brand || category) + '</span>' + stock + '</div>' +
                    '<strong class="hb-product-title">' + esc(title) + '</strong>' +
                    (description ? '<p class="hb-product-description">' + esc(description) + '</p>' : '') +
                    (tags.length ? '<div class="hb-product-tags">' + tags.map(tag => '<span>' + esc(tag) + '</span>').join('') + '</div>' : '') +
                    price + (hasRating ? ratingHtml : '') + action + '</div>';
            }

            return '<article class="hb-product-card hb-product-card-' + style + '" data-card-style="' + style + '">' + media + body + '</article>';
        }

        function previewAppProductCard(item, useMobile, index, requestedStyle) {
            const meta = item.itemable_meta || {};
            const image = previewItemImage(item, useMobile);
            const title = item.itemable_label || item.title || ('Product ' + (index + 1));
            const brand = meta.brand || meta.category || '';
            const currentPrice = meta.formatted_special_price || meta.formatted_price || '';
            const originalPrice = meta.formatted_special_price ? meta.formatted_price : '';
            const discount = Number(meta.discount_percent || 0);
            const style = requestedStyle === 'catalog' ? 'catalog' : 'quick_add';
            const isOutOfStock = Boolean(meta.is_out_of_stock);
            const badge = meta.badge && meta.badge.label && !isOutOfStock
                ? '<span class="hb-app-product-badge" style="background:' + esc(meta.badge.bg_color || '#256533') + ';color:' + esc(meta.badge.text_color || '#fff') + ';border-color:' + esc(meta.badge.border_color || 'transparent') + '">' + esc(meta.badge.label) + '</span>'
                : '';
            const status = isOutOfStock ? '<span class="hb-app-product-badge is-stock">Out of stock</span>' : badge;
            const addButton = style === 'quick_add' && !isOutOfStock
                ? '<button type="button" class="hb-app-product-add" aria-label="Add to cart"><i class="ti ti-plus"></i></button>'
                : '';
            const rating = Number(meta.rating || 0);
            const ratingCount = Number(meta.rating_count || 0);
            const ratingHtml = style === 'quick_add' && rating > 0 && ratingCount > 0
                ? '<span class="hb-app-product-rating"><i class="ti ti-star-filled"></i>' + esc(rating.toFixed(1)) + '<small>(' + esc(ratingCount) + ')</small></span>'
                : '';
            const displayTitle = style === 'catalog' ? title + title : title;

            return '<article class="hb-app-product-card hb-app-product-card-' + style + '">' +
                '<div class="hb-app-product-media">' +
                (image ? '<img src="' + esc(image) + '" alt="" style="object-fit:' + (meta.image_fit === 'cover' ? 'cover' : 'contain') + '">' : '<span class="hb-product-image-placeholder"></span>') +
                status + (!isOutOfStock ? '<i class="ti ti-heart hb-app-product-heart"></i>' : '') + addButton + '</div>' +
                '<div class="hb-app-product-body">' + (brand ? '<strong class="hb-app-product-brand">' + esc(brand) + '</strong>' : '') +
                '<div class="hb-app-product-title">' + esc(displayTitle) + '</div>' +
                (style === 'quick_add' && discount ? '<div class="hb-app-product-discount">' + esc(discount) + '% OFF <span>– – –</span></div>' : '') +
                '<div class="hb-app-product-price"><b>' + esc(currentPrice) + '</b>' + (originalPrice ? '<del>' + esc(originalPrice) + '</del>' : '') +
                (style === 'catalog' && discount ? '<em>' + esc(discount) + '% off</em>' : '') + '</div>' + ratingHtml + '</div></article>';
        }

        function previewCategoryCard(item, style, useMobile, index) {
            const image = previewItemImage(item, useMobile);
            const title = item.itemable_label || item.title || ('Category ' + (index + 1));
            const tints = ['#e7f6ed', '#e7f2fc', '#fff3c9', '#ffeadc', '#fbe7ec', '#efe8fa', '#eadff7', '#f3eadc'];

            if (PLATFORM === 'app') {
                if (style === 'overlay') {
                    return '<article class="hb-app-category-card hb-app-category-overlay">' +
                        (image ? '<img src="' + esc(image) + '" alt="">' : '<span class="hb-category-placeholder"></span>') +
                        '<span class="hb-app-category-scrim"></span><div><strong>' + esc(title) + '</strong><i class="ti ti-chevron-right"></i></div></article>';
                }
                if (style === 'full') {
                    return '<article class="hb-app-category-card hb-app-category-full">' +
                        (image ? '<img src="' + esc(image) + '" alt="">' : '<span class="hb-category-placeholder"></span>') + '</article>';
                }
                return '<article class="hb-app-category-card hb-app-category-grid-card"><div>' +
                    (image ? '<img src="' + esc(image) + '" alt="">' : '<span class="hb-category-placeholder"></span>') +
                    '</div><strong>' + esc(title) + '</strong></article>';
            }

            if (style === 'overlay') {
                return '<article class="hb-category-card hb-category-overlay">' +
                    (image ? '<img src="' + esc(image) + '" alt="">' : '<span class="hb-category-placeholder"></span>') +
                    '<span>' + esc(title) + '</span></article>';
            }
            if (style === 'card' || style === 'full') {
                return '<article class="hb-category-card hb-category-pill" style="background:' + tints[index % tints.length] + '"><div><strong>' + esc(title) + '</strong><small>Shop category</small></div><span>' +
                    (image ? '<img src="' + esc(image) + '" alt="">' : '<i class="ti ti-category"></i>') + '</span></article>';
            }
            return '<article class="hb-category-card hb-category-image">' +
                '<div>' + (image ? '<img src="' + esc(image) + '" alt="">' : '<span class="hb-category-placeholder"></span>') + '</div>' +
                '<strong>' + esc(title) + '</strong><small>Shop now <i class="ti ti-arrow-right"></i></small></article>';
        }

        function previewBrandCard(item, style, useMobile, index) {
            const image = previewItemImage(item, useMobile);
            const title = item.itemable_label || item.title || ('Brand ' + (index + 1));
            const showName = style === 'image_title';
            return '<article class="hb-brand-card' + (PLATFORM === 'app' ? ' hb-app-brand-card' : '') + (showName ? ' hb-brand-card-title' : ' hb-brand-card-full') + '"><div>' +
                (image ? '<img src="' + esc(image) + '" alt="">' : '<span>' + esc(title.charAt(0)) + '</span>') +
                '</div>' + (showName ? '<strong>' + esc(title) + '</strong>' : '') + '</article>';
        }

        function previewSortableCard(card, itemIndex, style, className) {
            return '<div class="hb-preview-sort-item' + (className ? ' ' + className : '') + '" data-preview-item-index="' + itemIndex + '"' + (style ? ' style="' + style + '"' : '') + '>' +
                '<button type="button" class="hb-preview-item-handle" title="Drag card to reorder"><i class="ti ti-grip-horizontal"></i></button>' +
                card + '</div>';
        }

        function previewCarousel(track, options) {
            const showButtons = options.showButtons !== false;
            const showBullets = options.showBullets && options.bulletCount > 1;
            const nativeScroll = track.includes('hb-product-rail');
            if (nativeScroll) {
                return '<div class="hb-preview-carousel hb-native-preview-carousel" data-show-buttons="' + (showButtons ? '1' : '0') + '">' +
                    track +
                    (showButtons ? '<button type="button" class="hb-preview-slide hb-preview-slide-prev" aria-label="Previous products"><i class="ti ti-chevron-left"></i></button>' : '') +
                    (showButtons ? '<button type="button" class="hb-preview-slide hb-preview-slide-next" aria-label="Next products"><i class="ti ti-chevron-right"></i></button>' : '') + '</div>';
            }
            const swiperTrack = track
                .replace('class="', 'class="swiper-wrapper ')
                .replaceAll('class="hb-preview-sort-item', 'class="swiper-slide hb-preview-sort-item');
            return '<div class="hb-preview-carousel swiper" data-show-buttons="' + (showButtons ? '1' : '0') + '" data-show-bullets="' + (showBullets ? '1' : '0') + '"' + (options.autoScroll ? ' data-auto-scroll="' + esc(options.autoScroll) + '"' : '') + '>' +
                swiperTrack +
                (showButtons ? '<button type="button" class="hb-preview-slide hb-preview-slide-prev" aria-label="Previous slide"><i class="ti ti-chevron-left"></i></button>' : '') +
                (showButtons ? '<button type="button" class="hb-preview-slide hb-preview-slide-next" aria-label="Next slide"><i class="ti ti-chevron-right"></i></button>' : '') +
                (showBullets ? '<div class="hb-preview-carousel-dots swiper-pagination"></div>' : '') + '</div>';
        }

        function previewCollectionPanel(section, content) {
            let style = '';
            let className = 'hb-collection-panel';
            if (PLATFORM === 'app') {
                className += ' hb-app-collection-panel hb-app-collection-panel-' + section.type;
                if (section.config.background_type === 'color') {
                    style = 'background-color:' + esc(section.config.background_color || '#fff') + ';';
                    className += ' has-background';
                }
                if (section.config.background_type === 'image' && section.background_image) {
                    style = 'background-image:url(&quot;' + esc(section.background_image) + '&quot;);';
                    className += ' has-background is-image';
                }
                return '<div class="' + className + '"' + (style ? ' style="' + style + '"' : '') + '>' + content + '</div>';
            }
            if (section.config.background_type === 'color') {
                style = 'background-color:' + esc(section.config.background_color || '#fff') + ';';
                className += ' is-padded';
            }
            if (section.config.background_type === 'image' && section.background_image) {
                style = 'background-image:linear-gradient(rgba(0,0,0,.2),rgba(0,0,0,.55)),url(&quot;' + esc(section.background_image) + '&quot;);';
                className += ' is-padded is-image';
            }
            return '<div class="' + className + '"' + (style ? ' style="' + style + '"' : '') + '>' + content + '</div>';
        }

        function previewAppSectionHeading(section, kind) {
            const title = section.show_title && section.title
                ? '<strong>' + esc(section.title) + '</strong>'
                : '';
            return '<div class="hb-app-section-heading hb-app-section-heading-' + kind.toLowerCase() + '">' + title + '<span>See All</span></div>';
        }

        function previewCatalogItems(kind, section) {
            if (kind === 'Product') {
                const sections = cfg.previewCatalog.section_products || {};
                const key = sectionUiKey(section);
                if (Object.prototype.hasOwnProperty.call(sections, key)) {
                    return Array.isArray(sections[key]) ? sections[key] : [];
                }
            }
            if (kind === 'Product' && section.config.source && section.config.source !== 'custom') {
                const sources = cfg.previewCatalog.product_sources || {};
                if (Array.isArray(sources[section.config.source])) return sources[section.config.source];
            }
            const catalogKey = kind === 'Category' ? 'categories' : kind.toLowerCase() + 's';
            return Array.isArray(cfg.previewCatalog[catalogKey]) ? cfg.previewCatalog[catalogKey] : [];
        }

        function previewCollectionSection(section, kind, useMobile, sectionIndex) {
            const useSelectedItems = section.config.source === 'custom';
            const sectionProducts = cfg.previewCatalog.section_products || {};
            const sectionKey = sectionUiKey(section);
            const hasResolvedProducts = kind === 'Product' && !marketCatalogLoading
                && Object.prototype.hasOwnProperty.call(sectionProducts, sectionKey);
            const resolvedProducts = hasResolvedProducts ? previewCatalogItems(kind, section) : null;
            let entries = useSelectedItems
                ? (section.items || []).map((item, index) => ({item, index})).filter(entry => entry.item.purpose === 'display')
                : [];
            let source = 'section';
            if (resolvedProducts) {
                if (useSelectedItems) {
                    const productsById = new Map(resolvedProducts.map(item => [Number(item.itemable_id), item]));
                    entries = entries
                        .filter(entry => productsById.has(Number(entry.item.itemable_id)))
                        .map(entry => ({
                            item: Object.assign({}, entry.item, productsById.get(Number(entry.item.itemable_id))),
                            index: entry.index,
                        }));
                } else {
                    entries = resolvedProducts.map((item, index) => ({item, index}));
                    source = 'catalog';
                }
            }
            if (PLATFORM !== 'web') {
                if (!entries.length && section.config.source !== 'custom' && !hasResolvedProducts) {
                    entries = previewCatalogItems(kind, section).map((item, index) => ({item, index}));
                    source = 'catalog';
                }
                if (kind === 'Product') {
                    if (previewMarketId && !entries.length) return '';
                    if (!entries.length) return cleanPreview
                        ? ''
                        : '<div class="hb-live-data-empty"><i class="ti ti-database-off"></i><span>No product data available</span></div>';
                    const productStyle = allowedProductStyles.includes(section.style) ? section.style : 'quick_add';
                    const cards = entries.map((entry, index) => previewSortableCard(previewAppProductCard(entry.item, useMobile, index, productStyle), entry.index)).join('');
                    const className = 'hb-live-card-grid hb-live-card-grid-product hb-app-product-grid hb-app-product-grid-' + productStyle +
                        (section.config.orientation === 'horizontal' ? ' hb-product-rail' : '');
                    const track = '<div class="' + className + ' hb-preview-sortable-items" data-preview-items="' + sectionIndex + '" data-preview-items-source="' + source + '" data-preview-items-kind="product" data-preview-track-key="' + esc(sectionUiKey(section) + ':collection') + '">' + cards + '</div>';
                    const body = section.config.orientation === 'horizontal'
                        ? previewCarousel(track, {showButtons: false})
                        : track;
                    return previewCollectionPanel(section, previewAppSectionHeading(section, kind) + body);
                }
                const appStyle = section.style || (kind === 'Category' ? 'default' : 'image_title');
                if (!entries.length) {
                    if (!cleanPreview) return '<div class="hb-live-data-empty"><i class="ti ti-database-off"></i><span>No ' + esc(kind.toLowerCase()) + ' data available</span></div>';
                    const emptyTrack = '<div class="hb-live-card-grid hb-live-card-grid-' + kind.toLowerCase() + ' hb-app-' + kind.toLowerCase() + '-layout-' + appStyle + '"></div>';
                    return previewCollectionPanel(section, previewAppSectionHeading(section, kind) + emptyTrack);
                }
                const cards = kind === 'Category'
                    ? entries.map((entry, index) => previewSortableCard(previewCategoryCard(entry.item, appStyle, useMobile, index), entry.index)).join('')
                    : entries.map((entry, index) => previewSortableCard(previewBrandCard(entry.item, appStyle, useMobile, index), entry.index)).join('');
                const className = 'hb-live-card-grid hb-live-card-grid-' + kind.toLowerCase() + ' hb-app-' + kind.toLowerCase() + '-layout-' + appStyle + ' hb-preview-sortable-items';
                const track = '<div class="' + className + '" data-preview-items="' + sectionIndex + '" data-preview-items-source="' + source + '" data-preview-items-kind="' + kind.toLowerCase() + '" data-preview-track-key="' + esc(sectionUiKey(section) + ':collection') + '">' + cards + '</div>';
                return previewCollectionPanel(section, previewAppSectionHeading(section, kind) + track);
            }
            if (!entries.length && section.config.source === 'custom' && !(kind === 'Product' && hasResolvedProducts)) {
                return '<div class="hb-live-data-empty"><i class="ti ti-pointer"></i><span>Select ' + esc(kind.toLowerCase()) + ' items in Design</span></div>';
            }
            if (!entries.length && !(kind === 'Product' && hasResolvedProducts)) {
                const catalog = previewCatalogItems(kind, section);
                entries = catalog.map((item, index) => ({item, index}));
                source = 'catalog';
            }
            if (!entries.length) {
                if (kind === 'Product' && previewMarketId) return '';
                return '<div class="hb-live-data-empty"><i class="ti ti-database-off"></i><span>No ' + esc(kind.toLowerCase()) + ' data available</span></div>';
            }

            let cards;
            let className = 'hb-live-card-grid hb-live-card-grid-' + kind.toLowerCase();
            if (kind === 'Product') {
                const productStyle = PLATFORM === 'web' && allowedProductStyles.includes(section.style) ? section.style : 'standard';
                cards = entries.map((entry, index) => previewSortableCard(previewProductCard(entry.item, useMobile, index, productStyle), entry.index)).join('');
                className += ' hb-product-grid-' + productStyle;
                if (section.config.orientation === 'horizontal') className += ' hb-product-rail';
            } else if (kind === 'Category') {
                cards = entries.map((entry, index) => previewSortableCard(previewCategoryCard(entry.item, section.style || 'default', useMobile, index), entry.index)).join('');
                className += ' hb-category-grid-' + (section.style || 'default');
            } else {
                cards = entries.map((entry, index) => previewSortableCard(previewBrandCard(entry.item, section.style || 'full', useMobile, index), entry.index)).join('');
                className += ' hb-brand-grid-' + (section.style || 'full');
            }
            const track = '<div class="' + className + ' hb-preview-sortable-items" data-preview-items="' + sectionIndex + '" data-preview-items-source="' + source + '" data-preview-items-kind="' + kind.toLowerCase() + '" data-preview-track-key="' + esc(sectionUiKey(section) + ':collection') + '">' + cards + '</div>';
            return previewCollectionPanel(section, section.config.orientation === 'horizontal' && kind === 'Product'
                ? previewCarousel(track, {showButtons: true})
                : track);
        }

        function previewHeroSection(section, useMobile, sectionIndex) {
            const media = sectionPreview(section, 'hero_media');
            const isShowcase = PLATFORM === 'app' || section.config.layout === 'items';
            const isLottie = section.config.media_type === 'lottie';
            if (isLottie && media) ensureLottiePlayer();
            let entries = (section.items || [])
                .map((item, index) => ({item, index}))
                .filter(entry => entry.item.purpose === 'display')
                .slice(0, 6);
            const heroProducts = cfg.previewCatalog.section_products || {};
            const hasResolvedHeroProducts = section.config.item_source === 'products' && !marketCatalogLoading
                && Object.prototype.hasOwnProperty.call(heroProducts, sectionUiKey(section));
            if (hasResolvedHeroProducts) {
                const resolvedProducts = previewCatalogItems('Product', section);
                const productsById = new Map(resolvedProducts.map(item => [Number(item.itemable_id), item]));
                entries = entries
                    .filter(entry => productsById.has(Number(entry.item.itemable_id)))
                    .map(entry => ({
                        item: Object.assign({}, entry.item, productsById.get(Number(entry.item.itemable_id))),
                        index: entry.index,
                    }));
                if (previewMarketId && !entries.length) return '';
            }
            const heroMedia = isLottie
                ? (media ? '<lottie-player class="hb-app-showcase-media" src="' + esc(media) + '" background="transparent" speed="1" loop autoplay></lottie-player>' : '')
                : '';
            const heroStyle = !isLottie && media
                ? ' style="background-image:url(&quot;' + esc(media) + '&quot;)"'
                : '';

            if (PLATFORM === 'app') {
                const cardBackground = section.config.card_bg_type === 'image' && sectionPreview(section, 'card_background')
                    ? 'background-image:url(&quot;' + esc(sectionPreview(section, 'card_background')) + '&quot;);background-position:center;background-size:cover;'
                    : 'background-color:' + esc(section.config.card_bg_color || '#f1efe8') + ';';
                const cardColor = 'color:' + esc(section.config.card_title_color || '#222222') + ';';
                const cards = entries.map(entry => {
                    const item = entry.item;
                    const image = previewItemImage(item, useMobile);
                    const title = item.itemable_label || item.title || 'Showcase item';
                    const card = '<article class="hb-app-showcase-card hb-app-showcase-card-' + esc(section.style || 'image_title') + '" style="' + cardBackground + cardColor + '">' +
                        (image ? '<img src="' + esc(image) + '" alt="' + esc(title) + '">' : '<span class="hb-app-showcase-placeholder"><i class="ti ti-photo"></i></span>') +
                        '<strong>' + esc(title) + '</strong></article>';
                    return previewSortableCard(card, entry.index);
                }).join('');
                const grid = isShowcase
                    ? (entries.length
                        ? '<div class="hb-app-showcase-grid hb-preview-sortable-items" data-preview-items="' + sectionIndex + '" data-preview-items-source="section" data-preview-items-kind="hero" data-preview-track-key="' + esc(sectionUiKey(section) + ':hero') + '">' + cards + '</div>'
                        : (cleanPreview ? '' : '<div class="hb-app-showcase-empty"><i class="ti ti-pointer"></i><span>Select category, product, or brand items in Design</span></div>'))
                    : '';
                return '<div class="hb-live-hero hb-app-showcase-hero' + (isShowcase ? ' has-items' : ' is-background-only') + '"' + heroStyle + '>' +
                    heroMedia + grid + '</div>';
            }

            return '<div class="hb-live-hero"' + heroStyle + '>' + heroMedia +
                '<div class="hb-live-hero-shade"></div>' +
                '<div class="hb-live-hero-copy"><strong>' + esc(section.title || 'Hero banner') + '</strong><small>' + esc(isShowcase ? 'Featured collection' : 'Background') + '</small></div>' +
                (entries.length ? '<div class="hb-live-hero-items">' + entries.slice(0, 4).map(entry => {
                    const image = previewItemImage(entry.item, useMobile);
                    return '<span>' + (image ? '<img src="' + esc(image) + '" alt="">' : '') + '</span>';
                }).join('') + '</div>' : '') +
                '</div>';
        }

        function previewPlayMedia(item, useMobile, rowConfig, fillRowHeight) {
            const image = previewItemImage(item, useMobile);
            const radius = (Number(rowConfig.border_radius) || 0) + 'px';
            const aspect = playItemAspectRatio(item.config);
            const objectFit = playItemObjectFit(item.config);
            const objectPosition = playItemObjectPosition(item.config);
            const customSize = playItemCustomSize(item.config);
            const itemStyle = playItemFrameStyle(item.config);

            if (item.config && item.config.media_type === 'lottie') {
                const width = getLottieSize(item.config.lottie_width, '100%');
                const height = getLottieSize(item.config.lottie_height, '150px');
                if (image) ensureLottiePlayer();
                return '<div class="hb-live-play-item hb-live-play-lottie" style="' + itemStyle + '">' +
                    (image ? '<lottie-player src="' + esc(image) + '" background="transparent" speed="1" style="width:' + width + ';height:' + height + ';border-radius:' + radius + '" loop autoplay></lottie-player>' : '<span><i class="ti ti-movie"></i></span>') +
                    '</div>';
            }

            const imageStyle = aspect || customSize || fillRowHeight
                ? 'width:100%;height:100%;object-fit:' + objectFit + ';object-position:' + objectPosition + ';'
                : 'width:100%;height:auto;object-fit:' + objectFit + ';object-position:' + objectPosition + ';';
            return '<div class="hb-live-play-item" style="' + itemStyle + (fillRowHeight && !aspect && !customSize ? 'height:100%;' : '') + '">' +
                (image ? '<img src="' + esc(image) + '" alt="' + esc((item.config && item.config.alt) || item.title || '') + '" style="' + imageStyle + '">' : '<span></span>') +
                '</div>';
        }

        function previewPlayImageSection(section, useMobile, sectionIndex) {
            const groupedRows = [];
            (section.items || []).forEach((item, itemIndex) => {
                const rowIndex = Number(item.config && item.config.row_index !== undefined ? item.config.row_index : 0);
                groupedRows[rowIndex] = groupedRows[rowIndex] || [];
                groupedRows[rowIndex].push({item, itemIndex});
            });
            const rowConfigs = Array.isArray(section.config.rows) ? section.config.rows : [];
            const rowCount = Math.max(rowConfigs.length, groupedRows.length);
            if (!rowCount) return '<div class="hb-live-data-empty"><i class="ti ti-layout-grid"></i><span>Add a row in Design</span></div>';

            const rowsHtml = Array.from({length: rowCount}, (_, rowIndex) => {
                const entries = groupedRows[rowIndex] || [];
                const rowConfig = rowConfigs[rowIndex] || defaultRowConfig();
                const backgroundImage = (section._rowBackgroundPreview && section._rowBackgroundPreview[rowIndex]) || rowConfig.background_image;
                let background = '';
                if (rowConfig.background_type === 'color') background = 'background-color:' + esc(rowConfig.background_color || '#fff') + ';';
                if (rowConfig.background_type === 'image' && backgroundImage) background = 'background-image:url(&quot;' + esc(backgroundImage) + '&quot;);background-size:cover;background-position:center;';
                const boxSpacing = rowBoxSpacing(rowConfig);
                const gap = Number(rowConfig.grid_gap) || 0;
                const renderedGap = Math.max(0, gap);
                const hoverClass = rowConfig.hover_effect && rowConfig.hover_effect !== 'none' ? 'hover-' + rowConfig.hover_effect : '';
                const containerClass = ' hb-play-container-' + (rowConfig.container_width || 'container');
                const layout = rowConfig.style || 'grid';
                const computedSpans = layout === 'grid' ? calculateSpansJS(entries.map(entry => entry.item), useMobile) : [];
                const spanByItem = new Map(computedSpans.map(entry => [entry.item, entry.span]));
                const visibleItems = Math.max(.5, Number(useMobile ? rowConfig.items_to_show_mobile : rowConfig.items_to_show) || 1);
                const itemWidth = 'calc(' + (100 / visibleItems) + '% - ' + (gap * Math.max(0, visibleItems - 1) / visibleItems) + 'px)';
                const cards = entries.map(entry => {
                    const wrapperStyle = layout === 'grid'
                        ? 'grid-column:span ' + (spanByItem.get(entry.item) || 12) + ';'
                        : '';
                    return previewSortableCard(previewPlayMedia(entry.item, useMobile, rowConfig, layout === 'grid'), entry.itemIndex, wrapperStyle, hoverClass);
                }).join('');
                const track = '<div class="hb-live-play-row hb-live-play-row-' + layout + ' hb-preview-sortable-items' + containerClass + '" data-preview-items="' + sectionIndex + '" data-preview-row-index="' + rowIndex + '" data-preview-track-key="' + esc(sectionUiKey(section) + ':row:' + (rowConfig.media_key || rowIndex)) + '" style="' + background + 'padding:' + boxSpacing.padding + ';gap:' + renderedGap + 'px;--hb-grid-gap:' + renderedGap + 'px;--hb-negative-gap:' + Math.min(0, gap) + 'px;--hb-visible-items:' + visibleItems + ';--hb-item-width:' + itemWidth + ';--hb-radius:' + (Number(rowConfig.border_radius) || 0) + 'px;">' + cards + '</div>';
                const rowContent = layout === 'scroll'
                    ? previewCarousel(track, {
                        showButtons: PLATFORM === 'app' ? false : parseBool(rowConfig.show_scroll_buttons, true),
                        showBullets: parseBool(rowConfig.show_bullets, false),
                        bulletCount: entries.length,
                        autoScroll: parseBool(rowConfig.auto_scroll, false) ? Math.max(1, Number(rowConfig.auto_scroll_time) || 3) : 0
                    })
                    : track;
                return '<div class="hb-live-play-row-shell" data-preview-row="' + rowIndex + '" style="margin:' + boxSpacing.margin + ';">' +
                    '<div class="hb-live-play-row-toolbar"><button type="button" class="hb-preview-row-handle" title="Drag row"><i class="ti ti-grip-horizontal"></i></button><span>Row ' + (rowIndex + 1) + '</span><small>' + (layout === 'scroll' ? 'Slider · ' + visibleItems + ' visible' : 'Grid') + '</small>' +
                    '<button type="button" data-preview-row-action="up" title="Move row up"' + (rowIndex === 0 ? ' disabled' : '') + '><i class="ti ti-arrow-up"></i></button>' +
                    '<button type="button" data-preview-row-action="down" title="Move row down"' + (rowIndex === rowCount - 1 ? ' disabled' : '') + '><i class="ti ti-arrow-down"></i></button></div>' +
                    rowContent + '</div>';
            }).join('');

            const heading = PLATFORM === 'app' && section.show_title && section.title
                ? '<div class="hb-app-play-image-title">' + esc(section.title) + '</div>'
                : '';
            return heading + '<div class="hb-live-play-rows" data-preview-rows="' + sectionIndex + '">' + rowsHtml + '</div>';
        }

        function previewSectionHtml(section, index, useMobile) {
            if (section._loaded === false) {
                return '<section class="hb-live-section hb-live-section-loading" data-preview-section="' + index + '" data-section-load-page="' + section._load_page + '">' +
                    '<div class="hb-section-preview-skeleton"><span></span><span></span><span></span><small>Loading ' + esc(section.title || nameOf(section.type, TYPE)) + '…</small></div></section>';
            }
            if (cleanPreview && !section.is_active) return '';
            let content = '';
            if (section.type === 'hero') content = previewHeroSection(section, useMobile, index);
            if (section.type === 'products') content = previewCollectionSection(section, 'Product', useMobile, index);
            if (section.type === 'categories') content = previewCollectionSection(section, 'Category', useMobile, index);
            if (section.type === 'brands') content = previewCollectionSection(section, 'Brand', useMobile, index);
            if (section.type === 'play_image') content = previewPlayImageSection(section, useMobile, index);
            if (cleanPreview && section.type === 'products' && !content) return '';
            if (previewMarketId && !marketCatalogLoading && !content && (
                section.type === 'products'
                || (section.type === 'hero' && section.config.item_source === 'products')
            )) return '';

            const appOwnsHeading = PLATFORM === 'app' && ['products', 'categories', 'brands', 'play_image'].includes(section.type);
            const heading = !appOwnsHeading && section.type !== 'hero' && section.title && section.show_title
                ? '<div class="hb-live-section-title">' + esc(section.title) + '<span>' + (PLATFORM === 'app' ? 'See All' : 'View all') + '</span></div>'
                : '';
            const disabled = cfg.canEdit ? '' : ' disabled';
            const previewOrder = marketOrderedSectionEntries(false).map(entry => entry.index);
            const previewOrderIndex = previewOrder.indexOf(index);
            const actions = cleanPreview ? '' : '<div class="hb-live-section-actions" aria-label="Section actions">' +
                '<button type="button" class="hb-live-section-handle" title="Drag section"><i class="ti ti-grip-vertical"></i></button>' +
                '<button type="button" data-preview-action="edit" title="Edit section"><i class="ti ti-pencil"></i></button>' +
                '<button type="button" data-preview-action="up" title="Move section up"' + (previewOrderIndex <= 0 ? ' disabled' : disabled) + '><i class="ti ti-arrow-up"></i></button>' +
                '<button type="button" data-preview-action="down" title="Move section down"' + (previewOrderIndex < 0 || previewOrderIndex === previewOrder.length - 1 ? ' disabled' : disabled) + '><i class="ti ti-arrow-down"></i></button>' +
                '<button type="button" data-preview-action="visibility" title="' + (section.is_active ? 'Hide section' : 'Show section') + '"' + disabled + '><i class="ti ' + (section.is_active ? 'ti-eye' : 'ti-eye-off') + '"></i></button>' +
                '<button type="button" data-preview-action="copy" title="Copy section"><i class="ti ti-copy"></i></button>' +
                '<button type="button" class="is-danger" data-preview-action="delete" title="Delete section"' + disabled + '><i class="ti ti-trash"></i></button>' +
                '</div>';
            const availability = !previewMarketId && (section.market_ids || []).length
                ? '<span class="hb-market-availability-label"><i class="ti ti-map-pin"></i> Only available in selected markets</span>'
                : '';
            return '<section class="hb-live-section hb-live-section-' + esc(section.type) + (index === sel ? ' active' : '') + (section.is_active ? '' : ' is-hidden') + '" data-preview-section="' + index + '" data-preview-section-key="' + esc(sectionUiKey(section)) + '">' +
                actions +
                availability +
                (section.is_active ? '' : '<span class="hb-live-hidden-label">Hidden</span>') +
                heading + content + '</section>';
        }

        function currentHeaderTarget() {
            if (headerMode) return headerTarget;
            return state.scope_type === 'navbar' ? Number(state.scope_id) : 'all';
        }

        function mountHeaderEditor() {
            if (!headerMode || headerEditorMounted) return;
            headerEditorMounted = true;
            editorEl.innerHTML = '<div class="hb-editor-empty text-muted"><span class="spinner-border spinner-border-sm"></span>Loading header settings…</div>';
            document.dispatchEvent(new CustomEvent('home-navbar:integrated-open', {
                detail: {owner: root.id, platform: PLATFORM, target: currentHeaderTarget(), mount: editorEl},
            }));
        }

        function enterHeaderMode() {
            const activate = () => {
                headerMode = true;
                headerDirty = false;
                headerEditorMounted = false;
                headerTarget = state.scope_type === 'navbar' ? Number(state.scope_id) : 'all';
                root.classList.add('hb-header-editing');
                if (headerModeBarEl) headerModeBarEl.hidden = false;
                if (newNavbarEl) newNavbarEl.hidden = false;
                mountHeaderEditor();
                renderPreview();
                window.requestAnimationFrame(() => {
                    const storefront = previewEl.querySelector('.hb-live-storefront');
                    if (storefront) storefront.scrollTo({top: 0, behavior: 'smooth'});
                });
            };
            if (!unsavedChanges) {
                activate();
                return;
            }
            ConfirmModal.open(
                'This homepage has unsaved section changes. They will remain in this editor while you configure the header.',
                activate,
                {
                    title: 'Unsaved changes',
                    confirmText: 'Edit without saving',
                    confirmClass: 'btn-warning',
                    cancelText: 'Keep editing sections',
                    onSave: () => { afterSaveAction = activate; save(); },
                },
            );
        }

        function leaveHeaderMode(discardChanges) {
            if (!headerMode) return;
            document.dispatchEvent(new CustomEvent('home-navbar:integrated-close', {
                detail: {owner: root.id, discard: discardChanges === true},
            }));
            headerMode = false;
            headerDirty = false;
            headerEditorMounted = false;
            root.classList.remove('hb-header-editing');
            root.classList.remove('hb-header-previewing');
            if (headerModeBarEl) headerModeBarEl.hidden = true;
            if (newNavbarEl) newNavbarEl.hidden = true;
            updateUnsavedState();
            renderEditor();
        }

        function requestLeaveHeaderMode() {
            if (!headerDirty) {
                leaveHeaderMode(false);
                return;
            }
            ConfirmModal.open(
                'Discard the unsaved header and navbar changes and return to section editing?',
                () => leaveHeaderMode(true),
                {
                    title: 'Unsaved header changes',
                    confirmText: 'Discard changes',
                    confirmClass: 'btn-warning',
                    cancelText: 'Keep editing',
                    onSave: () => { afterSaveAction = () => leaveHeaderMode(false); save(); },
                },
            );
        }

        function selectHeaderTarget(target) {
            const normalizedTarget = target === 'all' ? 'all' : Number(target);
            if (!headerMode || String(headerTarget) === String(normalizedTarget)) return;
            const select = discard => {
                headerTarget = normalizedTarget;
                document.dispatchEvent(new CustomEvent('home-navbar:integrated-select', {
                    detail: {owner: root.id, target: normalizedTarget, discard: discard === true},
                }));
                renderPreview();
            };
            if (!headerDirty) {
                select(false);
                return;
            }
            ConfirmModal.open(
                'Save or discard the current header changes before editing another navbar item.',
                () => select(true),
                {
                    title: 'Unsaved header changes',
                    confirmText: 'Discard changes',
                    confirmClass: 'btn-warning',
                    cancelText: 'Keep editing',
                    onSave: () => { afterSaveAction = () => select(false); save(); },
                },
            );
        }

        function previewLogoHtml() {
            const logo = cfg.storefront.siteHeaderDarkLogo || cfg.storefront.siteHeaderLogo;
            return logo
                ? '<img class="hb-storefront-logo" src="' + esc(logo) + '" alt="' + esc(cfg.storefront.siteName || 'Store') + '">'
                : '<span class="hb-storefront-logo-fallback"><b>H</b><span>HYPER<br>COMMERCE</span></span>';
        }

        function categoryAppearance(category, platform) {
            const source = category.homeAppearance || category.home_appearance || category.resolved_home_appearance || {};
            const legacyType = (category.background_type || category.backgroundType || '') === 'image'
                ? 'image'
                : ((category.background_type || category.backgroundType || '') === 'color' ? 'gradient' : 'none');
            const legacyColor = category.background_color || category.backgroundColor || '#ffffff';
            const app = Object.assign({
                background_type: legacyType,
                gradient_start: legacyColor,
                gradient_end: legacyColor,
                gradient_angle: 90,
                font_color: category.font_color || category.fontColor || '#475569',
                active_font_color: '#111827',
            }, source.app || {});
            return platform === 'desktop' ? Object.assign({}, app, source.desktop || {}) : app;
        }

        function categoryMedia(category, platform, active) {
            if (platform === 'desktop') {
                return active
                    ? (category.desktop_active_icon || category.desktopActiveIcon || category.desktop_icon || category.desktopIcon || category.active_icon || category.activeIcon || category.icon || '')
                    : (category.desktop_icon || category.desktopIcon || category.icon || '');
            }
            return active ? (category.active_icon || category.activeIcon || category.icon || '') : (category.icon || '');
        }

        function categoryBackgroundConfig(platform, appearance) {
            if (platform !== 'desktop') {
                return previewDevice === 'tablet'
                    ? (appearance.background_media_tablet || appearance.background_media_mobile || {})
                    : (appearance.background_media_mobile || {});
            }
            if (previewDevice === 'mobile') {
                return appearance.background_media_mobile || appearance.background_media_tablet || appearance.background_media_desktop || {};
            }
            if (previewDevice === 'tablet') {
                return appearance.background_media_tablet || appearance.background_media_desktop || {};
            }
            return appearance.background_media_desktop || {};
        }

        function categoryBackgroundSize(config) {
            if (config.aspect_ratio === 'custom') {
                const units = ['px', '%', 'rem', 'em', 'vw', 'vh'];
                const widthUnit = units.includes(config.aspect_width_unit) ? config.aspect_width_unit : '%';
                const heightUnit = units.includes(config.aspect_height_unit) ? config.aspect_height_unit : 'px';
                const width = Math.max(.1, Number(config.aspect_width) || 100);
                const height = Math.max(.1, Number(config.aspect_height) || 300);
                return width + widthUnit + ' ' + height + heightUnit;
            }
            return {
                contain: 'contain',
                fill: '100% 100%',
                'scale-down': 'contain',
                none: 'auto',
            }[config.object_fit] || 'cover';
        }

        function categoryBackgroundStyle(category, platform, appearance) {
            if (appearance.background_type === 'gradient') {
                return 'background-image:linear-gradient(' + (Number(appearance.gradient_angle) || 0) + 'deg,' +
                    esc(appearance.gradient_start || '#ffffff') + ',' + esc(appearance.gradient_end || appearance.gradient_start || '#ffffff') + ');';
            }
            if (appearance.background_type === 'image') {
                const config = categoryBackgroundConfig(platform, appearance);
                const image = platform === 'desktop'
                    ? (previewDevice === 'mobile'
                        ? (appearance.mobile_background_image || appearance.app_background_image || appearance.tablet_background_image || appearance.desktop_background_image || appearance.background_image || '')
                        : (previewDevice === 'tablet'
                            ? (appearance.tablet_background_image || appearance.desktop_background_image || appearance.background_image || '')
                            : (appearance.desktop_background_image || appearance.background_image || '')))
                    : (previewDevice === 'tablet'
                        ? (appearance.tablet_background_image || appearance.mobile_background_image || appearance.background_image || '')
                        : (appearance.mobile_background_image || appearance.background_image || ''));
                if (image) {
                    const position = ['center', 'top', 'right', 'bottom', 'left'].includes(config.object_position)
                        ? config.object_position
                        : 'center';
                    return 'background-image:url(&quot;' + esc(image) + '&quot;);background-position:' + position +
                        ';background-size:' + categoryBackgroundSize(config) + ';background-repeat:no-repeat;';
                }
            }
            return '';
        }

        function categoryBackgroundMediaHtml(category, platform, appearance) {
            if (appearance.background_type !== 'lottie') return '';
            const media = platform === 'desktop'
                ? (previewDevice === 'mobile'
                    ? (appearance.mobile_background_image || appearance.app_background_image || appearance.tablet_background_image || appearance.desktop_background_image || appearance.background_image || '')
                    : (previewDevice === 'tablet'
                        ? (appearance.tablet_background_image || appearance.desktop_background_image || appearance.background_image || '')
                        : (appearance.desktop_background_image || appearance.background_image || '')))
                : (previewDevice === 'tablet'
                    ? (appearance.tablet_background_image || appearance.mobile_background_image || appearance.background_image || '')
                    : (appearance.mobile_background_image || appearance.background_image || ''));
            if (!media) return '';
            ensureLottiePlayer();
            return '<lottie-player class="hb-category-header-lottie" src="' + esc(media) + '" background="transparent" speed="1" loop autoplay></lottie-player>';
        }

        function navbarItemsForPlatform(platform) {
            const web = platform === 'desktop' || platform === 'web';
            const sortKey = web ? 'web_sort_order' : 'app_sort_order';
            return (cfg.homeNavbarItems || []).filter(item => item.is_active !== false && (web
                ? item.show_on_web !== false
                : item.show_on_app !== false)).slice().sort((left, right) =>
                Number(left[sortKey] ?? left.sort_order ?? 0) - Number(right[sortKey] ?? right.sort_order ?? 0) ||
                Number(left.id || 0) - Number(right.id || 0)
            );
        }

        function previewCategoryContext(platform) {
            const visibleNavbarItems = navbarItemsForPlatform(platform);
            const categoryItems = [Object.assign({id: '', title: cfg.homeGeneral.title || 'All'}, cfg.homeGeneral)].concat(visibleNavbarItems).slice(0, 12);
            const activeTarget = headerMode ? headerTarget : (state.scope_type === 'navbar' ? state.scope_id : 'all');
            const activeCategory = categoryItems.find(category => {
                const id = category.id === '' ? '' : String(category.id);
                return activeTarget === 'all' ? id === '' : String(activeTarget || '') === id;
            }) || categoryItems[0];
            const appearance = categoryAppearance(activeCategory, platform);

            return {categoryItems, activeCategory, appearance};
        }

        function previewCategoryHeaderStyle(platform, context) {
            const appearance = context.appearance;
            return '--hb-header-font:' + esc(appearance.font_color || '#475569') +
                ';--hb-category-font:' + esc(appearance.font_color || '#475569') +
                ';--hb-category-active:' + esc(appearance.active_font_color || '#111827') + ';' +
                categoryBackgroundStyle(context.activeCategory, platform, appearance);
        }

        function previewCategoryNavHtml(platform, context) {
            const categoryContext = context || previewCategoryContext(platform);
            const categoryItems = categoryContext.categoryItems;

            return '<nav class="hb-storefront-categories hb-storefront-categories-' + platform + '" aria-label="Preview categories">' + categoryItems.map(category => {
                const categoryId = category.id === '' ? '' : String(category.id);
                const activeTarget = headerMode ? headerTarget : (state.scope_type === 'navbar' ? state.scope_id : 'all');
                const active = activeTarget === 'all' ? categoryId === '' : String(activeTarget || '') === categoryId;
                const icon = categoryMedia(category, platform, active);
                return '<button type="button" data-preview-navbar="' + esc(categoryId) + '" class="' + (active ? 'active' : '') + '">' +
                    '<span class="hb-storefront-category-icon">' + (icon ? '<img src="' + esc(icon) + '" alt="">' : '<i class="ti ti-category"></i>') + '</span>' +
                    '<span>' + esc(category.title || '') + '</span></button>';
            }).join('') + (headerMode && !cleanPreview && cfg.canEdit
                ? '<button type="button" class="hb-navbar-add-item" data-add-navbar title="New navbar item" aria-label="New navbar item"><i class="ti ti-plus"></i></button>'
                : '') + '</nav>';
        }

        function previewHeaderHtml(device) {
            const searchLabels = Array.isArray(cfg.homeGeneral.searchLabels) ? cfg.homeGeneral.searchLabels : [];
            const searchPlaceholder = searchLabels.length ? 'Search "' + searchLabels[0] + '"' : 'Search products';
            const context = previewCategoryContext('desktop');
            const settings = cfg.storefront;
            const socialIcons = [
                [settings.facebookLink, 'ti-brand-facebook'],
                [settings.instagramLink, 'ti-brand-instagram'],
                [settings.youtubeLink, 'ti-brand-youtube'],
                [settings.xLink, 'ti-brand-x']
            ].filter(social => social[0]).map(social => '<i class="ti ' + social[1] + '"></i>').join('');
            return '<div class="hb-storefront-mobile-location"><i class="ti ti-map-pin"></i><span>Default location</span><i class="ti ti-chevron-down"></i></div>' +
                '<header class="hb-storefront-header" style="' + previewCategoryHeaderStyle('desktop', context) + '">' +
                categoryBackgroundMediaHtml(context.activeCategory, 'desktop', context.appearance) +
                '<div class="hb-storefront-utility"><span>Follow us</span><span class="hb-storefront-utility-socials">' + socialIcons + '</span><span class="hb-storefront-utility-end">' +
                (settings.supportNumber ? '<i class="ti ti-phone"></i>' + esc(settings.supportNumber) : '') + '<b>En</b><i class="ti ti-chevron-down"></i></span></div>' +
                '<div class="hb-storefront-header-main">' + previewLogoHtml() +
                '<div class="hb-storefront-location"><i class="ti ti-map-pin"></i><span><small>DELIVER TO</small>Default location</span><i class="ti ti-chevron-down"></i></div>' +
                '<div class="hb-storefront-search"><i class="ti ti-search"></i><span>' + esc(searchPlaceholder) + '</span><i class="ti ti-shopping-bag"></i></div>' +
                '<div class="hb-storefront-actions"><span><i class="ti ti-user"></i><small>Account</small></span><span><i class="ti ti-shopping-cart"></i><small>Cart</small></span></div>' +
                '</div>' + previewCategoryNavHtml('desktop', context) + '</header>';
        }

        function previewFooterHtml() {
            const settings = cfg.storefront;
            const description = settings.shortDescription || "Your neighborhood's fastest delivery service. We bring everything you need, right to your doorstep in minutes.";
            const socialIcons = [
                [settings.facebookLink, 'ti-brand-facebook'],
                [settings.instagramLink, 'ti-brand-instagram'],
                [settings.youtubeLink, 'ti-brand-youtube'],
                [settings.xLink, 'ti-brand-x']
            ].filter(social => social[0]).map(social => '<i class="ti ' + social[1] + '"></i>').join('');
            return '<footer class="hb-storefront-footer"><div class="hb-storefront-footer-grid">' +
                '<div class="hb-storefront-company">' + previewLogoHtml() + '<p>' + esc(description) + '</p>' +
                (settings.supportNumber ? '<span><i class="ti ti-phone"></i>' + esc(settings.supportNumber) + '</span>' : '') +
                (settings.supportEmail ? '<span><i class="ti ti-mail"></i>' + esc(settings.supportEmail) + '</span>' : '') + '</div>' +
                '<div><strong>QUICK LINKS</strong><span>About Us</span><span>FAQs</span><span>Stores</span><span>Become a Seller</span></div>' +
                '<div><strong>POLICIES</strong><span>Privacy Policy</span><span>Terms &amp; Conditions</span><span>Shipping Policy</span><span>Return &amp; Refund Policy</span></div>' +
                '<div><strong>FOLLOW US</strong>' + (socialIcons ? '<div class="hb-storefront-socials">' + socialIcons + '</div>' : '') + '<span><i class="ti ti-package"></i>Quality</span><span><i class="ti ti-shield-check"></i>Secure</span><span><i class="ti ti-rosette-discount-check"></i>Trusted</span></div>' +
                '</div><div class="hb-storefront-footer-bottom"><span>&copy; ' + new Date().getFullYear() + ' ' + esc(settings.siteCopyright || settings.siteName || 'Hypercommerce') + '</span><span>Powered by <b>Infinitietech</b></span></div></footer>';
        }

        function previewBottomNavigationHtml() {
            return '<nav class="hb-storefront-bottom-nav" aria-label="App navigation">' +
                '<button type="button" class="active"><i class="ti ti-home-filled"></i><small>Home</small></button>' +
                '<button type="button"><i class="ti ti-category-2"></i><small>Categories</small></button>' +
                '<button type="button" class="hb-app-bag-nav"><i class="ti ti-shopping-bag"></i><small>Bag</small></button>' +
                '<button type="button"><i class="ti ti-user"></i><small>Account</small></button></nav>';
        }

        function previewSearchPlaceholder(context) {
            const active = context && context.activeCategory ? context.activeCategory : {};
            const labels = active.search_labels || active.searchLabels || cfg.homeGeneral.searchLabels || [];
            const label = Array.isArray(labels) ? labels.find(value => String(value || '').trim()) : '';
            if (label) return 'Search "' + label + '"';
            const title = active.id === '' ? '' : active.title;
            return title ? 'Search "' + title + '"' : 'Search products';
        }

        function previewAppStatusHtml(device) {
            const time = new Date().toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
            const network = device === 'iphone'
                ? '<i class="ti ti-antenna-bars-5"></i><i class="ti ti-wifi"></i><span class="hb-app-status-battery"><i></i></span>'
                : '<i class="ti ti-volume-3"></i><b>5G</b><i class="ti ti-antenna-bars-5"></i><span class="hb-app-status-battery"><i></i></span>';
            return '<div class="hb-app-system-status hb-app-system-status-' + esc(device) + '" aria-hidden="true"><strong>' + esc(time) + '</strong><span>' + network + '</span></div>';
        }

        function previewAppHeaderHtml(device) {
            const context = previewCategoryContext('app');
            const deliveryLocation = cfg.storefront.address || 'Delivery location';
            return '<header class="hb-app-preview-header" style="' + previewCategoryHeaderStyle('app', context) + '">' +
                categoryBackgroundMediaHtml(context.activeCategory, 'app', context.appearance) +
                previewAppStatusHtml(device) +
                '<div class="hb-app-location-row"><button type="button"><i class="ti ti-map-pin-filled"></i><span>' + esc(deliveryLocation) + '</span><i class="ti ti-chevron-down"></i></button>' +
                '<button type="button" class="hb-app-notification" aria-label="Notifications"><i class="ti ti-bell-filled"></i></button></div>' +
                '<div class="hb-app-search"><i class="ti ti-search"></i><span>' + esc(previewSearchPlaceholder(context)) + '</span><i class="ti ti-pencil"></i></div>' +
                previewCategoryNavHtml('app', context) + '</header>';
        }

        function previewContentHtml(device) {
            const useMobile = PLATFORM === 'app' || device === 'mobile';
            const entries = marketOrderedSectionEntries(false);
            let sections = entries.map(entry => previewSectionHtml(entry.section, entry.index, useMobile)).filter(Boolean).join('');
            if (previewMarketId && marketCatalogLoading) {
                sections = '<div class="hb-live-empty hb-market-preview-loading"><i class="ti ti-loader-2"></i><strong>Loading market preview</strong><small>Checking products available in ' + esc(previewMarketLabel) + '…</small></div>';
            } else if (!sections) {
                sections = '<div class="hb-live-empty"><i class="ti ti-layout-dashboard"></i><strong>' + (previewMarketId ? 'No available sections for this market' : 'No sections yet') + '</strong><small>' + (previewMarketId ? 'Visible product sections are hidden when this market has no available products.' : 'Add a section to start building the preview.') + '</small></div>';
            }
            if (PLATFORM !== 'web') {
                return '<div class="hb-live-storefront hb-live-storefront-app">' + previewAppHeaderHtml(device) +
                    '<div class="hb-live-preview-content hb-app-preview-content">' + sections + '</div>' +
                    previewBottomNavigationHtml() + '</div>';
            }
            return '<div class="hb-live-storefront hb-live-storefront-web">' + previewHeaderHtml(device) +
                '<main class="hb-storefront-main"><div class="hb-live-preview-content">' + sections + '</div></main>' +
                previewFooterHtml() + '</div>';
        }

        function appDeviceHtml(device, content) {
            const assets = {
                android: cfg.deviceAssets.android || '/assets/images/android-mockup.png',
                iphone: cfg.deviceAssets.iphone || '/assets/images/iphone-mockup.png'
            };
            return '<div class="hb-device-stage hb-device-stage-app" tabindex="0" aria-label="Interactive app preview canvas. On a mobile screen, use two fingers to move and zoom." style="--hb-preview-scale:' + (previewZoom / 100) + '">' +
                '<div class="hb-preview-canvas-note" aria-hidden="true"><span class="hb-preview-drag-badge"><i class="ti ti-hand-finger"></i> Use two fingers to move &amp; zoom</span><small>One finger scrolls the storefront normally.</small></div>' +
                '<div class="hb-app-device hb-app-device-' + device + '" style="--hb-preview-pan-x:' + previewPan.x + 'px;--hb-preview-pan-y:' + previewPan.y + 'px">' +
                '<div class="hb-app-screen">' + content + '</div>' +
                '<img class="hb-device-overlay" src="' + assets[device] + '" alt="' + esc(device) + ' preview frame">' +
                '</div></div>';
        }

        function webDeviceHtml(device, content) {
            return '<div class="hb-device-stage hb-device-stage-web" style="--hb-preview-scale:' + (previewZoom / 100) + '"><div class="hb-web-device hb-web-device-' + device + '">' +
                '<div class="hb-browser-bar"><span></span><span></span><span></span><div><i class="ti ti-lock"></i> store.example.com</div></div>' +
                '<div class="hb-web-screen">' + content + '</div></div></div>';
        }

        function materializeCatalogItem(item) {
            if (!item) return null;
            return {
                id: null,
                _ui_key: newUiKey('item'),
                purpose: 'display',
                itemable_type: item.itemable_type || null,
                itemable_id: item.itemable_id || null,
                itemable_label: item.itemable_label || null,
                itemable_image: item.itemable_image || null,
                itemable_meta: item.itemable_meta || {},
                title: item.title || '',
                link_url: item.link_url || '',
                image: item.image || null,
                mobile_image: item.mobile_image || null,
                config: item.config || {},
            };
        }

        function initPreviewCarousels() {
            if (root.closest('.d-none')) return;

            previewEl.querySelectorAll('.hb-native-preview-carousel').forEach(carousel => {
                const track = carousel.querySelector('.hb-product-rail');
                if (!track) return;
                const scroll = direction => track.scrollBy({
                    left: direction * Math.max(80, track.clientWidth * .8),
                    behavior: 'smooth',
                });
                carousel.querySelector('.hb-preview-slide-prev')?.addEventListener('click', () => scroll(-1));
                carousel.querySelector('.hb-preview-slide-next')?.addEventListener('click', () => scroll(1));
            });

            if (!window.Swiper) return;

            previewEl.querySelectorAll('.hb-preview-carousel.swiper').forEach(carousel => {
                const track = carousel.querySelector('.swiper-wrapper');
                const showButtons = carousel.dataset.showButtons === '1';
                const showBullets = carousel.dataset.showBullets === '1';
                const autoScroll = Math.max(0, Number(carousel.dataset.autoScroll) || 0);
                const slideGap = track ? Math.max(0, parseFloat(window.getComputedStyle(track).gap) || 0) : 0;
                if (track) track.style.gap = '0px';
                const previous = carousel.querySelector('.hb-preview-slide-prev');
                const next = carousel.querySelector('.hb-preview-slide-next');
                const pagination = carousel.querySelector('.swiper-pagination');

                const swiperOptions = {
                    slidesPerView: 'auto',
                    spaceBetween: slideGap,
                    speed: 420,
                    watchOverflow: true,
                    grabCursor: cleanPreview,
                    allowTouchMove: true,
                    simulateTouch: true,
                };
                if (showButtons && previous && next) {
                    swiperOptions.navigation = {prevEl: previous, nextEl: next};
                }
                if (showBullets && pagination) {
                    swiperOptions.pagination = {
                        el: pagination,
                        clickable: true,
                        bulletClass: 'hb-preview-carousel-dot',
                        bulletActiveClass: 'active',
                        renderBullet: (index, className) => '<button type="button" class="' + className + '" aria-label="Go to slide ' + (index + 1) + '"></button>',
                    };
                }
                if (autoScroll) {
                    swiperOptions.autoplay = {
                        delay: autoScroll * 1000,
                        disableOnInteraction: false,
                        pauseOnMouseEnter: true,
                    };
                }

                try {
                    const swiper = new window.Swiper(carousel, swiperOptions);
                    previewSwiperInstances.push(swiper);
                } catch (error) {
                    console.error('Home builder Swiper initialization failed', error);
                }
            });
        }

        function createPreviewSortable(element, options, scrollable) {
            if (scrollable) {
                if (window.HyperScrollSort) {
                    return window.HyperScrollSort.create(element, options, {scrollable: true, holdDelay: 900});
                }
                return window.Sortable.create(element, Object.assign({}, options, {
                    delay: 900,
                    delayOnTouchOnly: false,
                    forceFallback: true,
                    fallbackTolerance: 7,
                    touchStartThreshold: 7,
                }));
            }
            return window.Sortable.create(element, options);
        }

        function initPreviewItemSortables() {
            if (!cfg.canEdit || !window.Sortable) return;
            const handleOnly = window.matchMedia('(max-width: 767.98px)').matches;

            previewEl.querySelectorAll('.hb-preview-sortable-items').forEach(track => {
                const isPlayImageRow = track.dataset.previewRowIndex !== undefined;
                const scrollable = track.classList.contains('hb-product-rail') ||
                    track.classList.contains('hb-live-play-row-scroll') ||
                    Boolean(track.closest('.hb-preview-carousel'));
                createPreviewSortable(track, {
                    group: isPlayImageRow ? {name: 'hb-preview-play-items-' + track.dataset.previewItems, pull: true, put: true} : undefined,
                    handle: scrollable ? '.hb-preview-sort-item' : (handleOnly ? '.hb-preview-item-handle' : '.hb-preview-sort-item'),
                    filter: 'button:not(.hb-preview-item-handle), a, input, select, textarea',
                    preventOnFilter: false,
                    touchStartThreshold: 5,
                    animation: 150,
                    ghostClass: 'hb-ghost',
                    onEnd: () => {
                        const sectionIndex = Number(track.dataset.previewItems);
                        const section = state.sections[sectionIndex];
                        if (!section) return;

                        const orderedIndices = Array.from(track.children)
                            .map(child => Number(child.dataset.previewItemIndex))
                            .filter(Number.isFinite);

                        if (isPlayImageRow) {
                            const rowCount = Math.max(
                                Array.isArray(section.config.rows) ? section.config.rows.length : 0,
                                ...Array.from(previewEl.querySelectorAll('[data-preview-section="' + sectionIndex + '"] [data-preview-row-index]')).map(row => Number(row.dataset.previewRowIndex) + 1)
                            );
                            const rows = Array.from({length: rowCount}, () => []);
                            previewEl.querySelectorAll('[data-preview-section="' + sectionIndex + '"] [data-preview-row-index]').forEach(rowTrack => {
                                const targetRow = Number(rowTrack.dataset.previewRowIndex);
                                Array.from(rowTrack.children).forEach(child => {
                                    const originalIndex = Number(child.dataset.previewItemIndex);
                                    const item = section.items[originalIndex];
                                    if (item) rows[targetRow].push(item);
                                });
                            });
                            section.items = rebuildItemsFromRows(rows);
                        } else if (track.dataset.previewItemsSource === 'catalog') {
                            const kind = track.dataset.previewItemsKind;
                            const catalog = previewCatalogItems(kind.charAt(0).toUpperCase() + kind.slice(1), section);
                            section.items = orderedIndices.map(index => materializeCatalogItem(catalog[index])).filter(Boolean);
                            section.config.source = 'custom';
                        } else {
                            const positions = section.items.reduce((result, item, index) => {
                                if (item.purpose === 'display') result.push(index);
                                return result;
                            }, []);
                            const reordered = orderedIndices.map(index => section.items[index]).filter(Boolean);
                            positions.forEach((position, index) => {
                                if (reordered[index]) section.items[position] = reordered[index];
                            });
                        }

                        section.items.forEach((item, index) => { item.sort_order = index; });
                        sel = sectionIndex;
                        recordStateChange();
                        renderSections();
                        renderEditor();
                        renderPreview();
                    }
                }, scrollable);
            });

        }

        function reorderIndexedPreviewState(values, order) {
            if (!values) return values;
            const reordered = {};
            order.forEach((oldIndex, newIndex) => {
                if (values[oldIndex] !== undefined) reordered[newIndex] = values[oldIndex];
            });
            return reordered;
        }

        function applyPlayImageRowOrder(section, order) {
            const oldConfigs = Array.isArray(section.config.rows) ? section.config.rows : [];
            const oldGroups = [];
            section.items.forEach(item => {
                const rowIndex = Number(item.config && item.config.row_index !== undefined ? item.config.row_index : 0);
                oldGroups[rowIndex] = oldGroups[rowIndex] || [];
                oldGroups[rowIndex].push(item);
            });
            section.config.rows = order.map(oldIndex => oldConfigs[oldIndex] || defaultRowConfig());
            section.items = rebuildItemsFromRows(order.map(oldIndex => oldGroups[oldIndex] || []));
            section._rowBackgroundFiles = reorderIndexedPreviewState(section._rowBackgroundFiles, order);
            section._rowBackgroundPreview = reorderIndexedPreviewState(section._rowBackgroundPreview, order);
        }

        function initPreviewRowSortables() {
            if (!cfg.canEdit || !window.Sortable) return;
            previewEl.querySelectorAll('[data-preview-rows]').forEach(rowsEl => {
                window.Sortable.create(rowsEl, {
                    handle: '.hb-preview-row-handle',
                    animation: 150,
                    ghostClass: 'hb-ghost',
                    onEnd: () => {
                        const sectionIndex = Number(rowsEl.dataset.previewRows);
                        const section = state.sections[sectionIndex];
                        if (!section) return;
                        const order = Array.from(rowsEl.children).map(row => Number(row.dataset.previewRow));
                        applyPlayImageRowOrder(section, order);
                        sel = sectionIndex;
                        recordStateChange();
                        renderSections();
                        renderEditor();
                    }
                });
            });

            previewEl.querySelectorAll('[data-preview-row-action]').forEach(button => button.addEventListener('click', event => {
                event.stopPropagation();
                const sectionEl = button.closest('[data-preview-section]');
                const rowEl = button.closest('[data-preview-row]');
                const sectionIndex = sectionEl ? Number(sectionEl.dataset.previewSection) : -1;
                const rowIndex = rowEl ? Number(rowEl.dataset.previewRow) : -1;
                const section = state.sections[sectionIndex];
                const rowCount = section && Array.isArray(section.config.rows) ? section.config.rows.length : 0;
                const targetIndex = rowIndex + (button.dataset.previewRowAction === 'up' ? -1 : 1);
                if (!section || targetIndex < 0 || targetIndex >= rowCount) return;
                const order = Array.from({length: rowCount}, (_, index) => index);
                const moved = order.splice(rowIndex, 1)[0];
                order.splice(targetIndex, 0, moved);
                applyPlayImageRowOrder(section, order);
                sel = sectionIndex;
                recordStateChange();
                renderSections();
                renderEditor();
            }));
        }

        function focusPlayImageRow(sectionIndex, rowIndex) {
            const section = state.sections[sectionIndex];
            if (!section || section.type !== 'play_image') return;

            const rows = Array.isArray(section.config.rows) ? section.config.rows : [];
            const itemRowCount = (section.items || []).reduce((count, item) => {
                const index = Number(item.config && item.config.row_index !== undefined ? item.config.row_index : 0);
                return Math.max(count, index + 1);
            }, 0);
            const rowCount = Math.max(rows.length, itemRowCount);
            Array.from({length: rowCount}, (_, index) => rows[index] || defaultRowConfig()).forEach((row, index) => {
                const rowKey = row.media_key || ('row-' + index);
                disclosureState.set(sectionUiKey(section) + ':row:' + rowKey, index === rowIndex);
            });

            sel = sectionIndex;
            editorTab = 'style';
            focusedPlayRow = {sectionKey: sectionUiKey(section), rowIndex};
            renderSections();
            renderEditor();

            window.requestAnimationFrame(() => {
                const rowPanel = editorEl.querySelector('.hb-row-panel[data-row-idx="' + rowIndex + '"]');
                if (!rowPanel) return;
                rowPanel.scrollIntoView({behavior: 'smooth', block: 'nearest'});
            });
        }

        function capturePreviewViewState() {
            if (!previewEl || !previewEl.children.length) return null;
            const stage = previewEl.querySelector('.hb-device-stage');
            const storefront = previewEl.querySelector('.hb-live-storefront');
            const contentScroller = storefront && storefront.classList.contains('hb-live-storefront-app')
                ? storefront.querySelector('.hb-live-preview-content')
                : storefront;
            const tracks = {};
            previewEl.querySelectorAll('[data-preview-track-key]').forEach(track => {
                const carousel = track.closest('.hb-preview-carousel.swiper');
                tracks[track.dataset.previewTrackKey] = {
                    left: track.scrollLeft,
                    top: track.scrollTop,
                    slide: carousel && carousel.swiper ? carousel.swiper.activeIndex : null,
                };
            });
            return {
                stageLeft: stage ? stage.scrollLeft : 0,
                stageTop: stage ? stage.scrollTop : 0,
                storefrontTop: contentScroller ? contentScroller.scrollTop : 0,
                tracks,
            };
        }

        function restorePreviewViewState(viewState) {
            if (!viewState) return;
            const restore = () => {
                const stage = previewEl.querySelector('.hb-device-stage');
                const storefront = previewEl.querySelector('.hb-live-storefront');
                const contentScroller = storefront && storefront.classList.contains('hb-live-storefront-app')
                    ? storefront.querySelector('.hb-live-preview-content')
                    : storefront;
                if (stage) {
                    stage.scrollLeft = viewState.stageLeft;
                    stage.scrollTop = viewState.stageTop;
                }
                if (contentScroller) contentScroller.scrollTop = viewState.storefrontTop;
                previewEl.querySelectorAll('[data-preview-track-key]').forEach(track => {
                    const position = viewState.tracks[track.dataset.previewTrackKey];
                    if (!position) return;
                    const carousel = track.closest('.hb-preview-carousel.swiper');
                    if (carousel && carousel.swiper && position.slide !== null) {
                        carousel.swiper.slideTo(position.slide, 0, false);
                        return;
                    }
                    track.scrollLeft = position.left;
                    track.scrollTop = position.top;
                });
            };
            restore();
            window.requestAnimationFrame(restore);
        }

        function copySectionDesign(section) {
            navigator.clipboard.writeText(JSON.stringify(section))
                .then(() => Toast.fire({icon: 'success', title: 'Section design copied to clipboard!'}))
                .catch(() => Toast.fire({icon: 'error', title: 'Failed to copy section design'}));
        }

        function initPreviewSectionActions() {
            previewEl.querySelectorAll('[data-preview-action]').forEach(button => button.addEventListener('click', event => {
                event.stopPropagation();
                const sectionEl = button.closest('[data-preview-section]');
                const sectionIndex = sectionEl ? Number(sectionEl.dataset.previewSection) : -1;
                const section = state.sections[sectionIndex];
                if (!section) return;

                const action = button.dataset.previewAction;
                if (action === 'edit') {
                    sel = sectionIndex;
                    editorTab = 'content';
                    focusedPlayRow = null;
                    renderSections();
                    renderEditor();
                    return;
                }
                if (action === 'copy') {
                    copySectionDesign(section);
                    return;
                }
                if (action === 'visibility') {
                    section.is_active = !section.is_active;
                    sel = sectionIndex;
                }
                if (action === 'up' || action === 'down') {
                    if (previewMarketId) {
                        const order = marketOrderedSectionEntries(false).map(entry => entry.index);
                        const currentPosition = order.indexOf(sectionIndex);
                        const targetPosition = currentPosition + (action === 'up' ? -1 : 1);
                        if (currentPosition < 0 || targetPosition < 0 || targetPosition >= order.length) return;
                        const movedIndex = order.splice(currentPosition, 1)[0];
                        order.splice(targetPosition, 0, movedIndex);
                        applyPreviewMarketOrder(order);
                        sel = sectionIndex;
                        recordStateChange();
                        renderSections();
                        renderEditor();
                        return;
                    }
                    const targetIndex = sectionIndex + (action === 'up' ? -1 : 1);
                    if (targetIndex < 0 || targetIndex >= state.sections.length) return;
                    state.sections.splice(sectionIndex, 1);
                    state.sections.splice(targetIndex, 0, section);
                    sel = targetIndex;
                }
                if (action === 'delete') {
                    ConfirmModal.open(L('removeConfirm', 'Remove this section?'), () => {
                        state.sections.splice(sectionIndex, 1);
                        sel = Math.min(sectionIndex, state.sections.length - 1);
                        recordStateChange();
                        renderSections();
                        renderEditor();
                    });
                    return;
                }

                recordStateChange();
                renderSections();
                renderEditor();
            }));
        }

        function initPreviewCategoryNavigation() {
            previewEl.querySelectorAll('[data-preview-navbar]').forEach(button => button.addEventListener('click', event => {
                event.stopPropagation();
                const navbarId = button.dataset.previewNavbar;
                if (headerMode) {
                    selectHeaderTarget(navbarId ? Number(navbarId) : 'all');
                    return;
                }
                const nextScope = navbarId ? 'navbar' : 'global';
                const nextId = navbarId ? Number(navbarId) : null;
                if (state.scope_type === nextScope && state.scope_id === nextId) return;

                requestScopeChange(nextScope, nextId);
            }));
        }

        function initHeaderNavbarSortable() {
            if (!headerMode || cleanPreview || !cfg.canEdit || !window.Sortable) return;
            const navbar = previewEl.querySelector('.hb-storefront-categories');
            if (!navbar) return;
            createPreviewSortable(navbar, {
                animation: 180,
                draggable: 'button[data-preview-navbar]:not([data-preview-navbar=""])',
                ghostClass: 'hb-navbar-sort-ghost',
                onEnd: () => {
                    const itemIds = Array.from(navbar.querySelectorAll('[data-preview-navbar]'))
                        .map(item => Number(item.dataset.previewNavbar))
                        .filter(Boolean);
                    const sortKey = PLATFORM === 'web' ? 'web_sort_order' : 'app_sort_order';
                    const position = new Map(itemIds.map((id, index) => [id, index + 1]));
                    cfg.homeNavbarItems.forEach(item => {
                        if (position.has(Number(item.id))) item[sortKey] = position.get(Number(item.id));
                    });
                    document.dispatchEvent(new CustomEvent('home-navbar:integrated-order', {
                        detail: {owner: root.id, platform: PLATFORM, itemIds},
                    }));
                },
            }, true);
        }

        function initPreviewStickyHeader() {
            const storefront = previewEl.querySelector('.hb-live-storefront');
            const header = storefront && storefront.querySelector('.hb-storefront-header, .hb-app-preview-header');
            const scroller = storefront && storefront.classList.contains('hb-live-storefront-app')
                ? storefront.querySelector('.hb-live-preview-content')
                : storefront;
            if (!storefront || !header || !scroller) return;
            const webPreview = storefront.classList.contains('hb-live-storefront-web');
            let stuck = header.classList.contains('is-stuck');
            let frame = null;
            const update = () => {
                frame = null;
                const scrollTop = Math.max(0, scroller.scrollTop);
                const next = webPreview
                    ? (stuck ? scrollTop > 4 : scrollTop >= 32)
                    : scrollTop > 12;
                if (next === stuck) return;
                stuck = next;
                header.classList.toggle('is-stuck', stuck);
            };
            const scheduleUpdate = () => {
                if (frame !== null) return;
                frame = window.requestAnimationFrame(update);
            };
            scroller.addEventListener('scroll', scheduleUpdate, {passive: true});
            update();
        }

        function initLazySectionObserver() {
            if (sectionPreviewObserver) sectionPreviewObserver.disconnect();
            if (previewTargetLoading) return;
            const placeholders = Array.from(previewEl.querySelectorAll('[data-section-load-page]'));
            if (!placeholders.length) return;
            const storefront = previewEl.querySelector('.hb-live-storefront');
            const scroller = storefront && storefront.classList.contains('hb-live-storefront-app')
                ? storefront.querySelector('.hb-live-preview-content')
                : storefront;
            if (!window.IntersectionObserver) {
                loadSectionPage(placeholders[0].dataset.sectionLoadPage).catch(() => {});
                return;
            }
            sectionPreviewObserver = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) return;
                    sectionPreviewObserver.unobserve(entry.target);
                    loadSectionPage(entry.target.dataset.sectionLoadPage).catch(() => {});
                });
            }, {root: scroller || null, rootMargin: '320px 0px', threshold: 0.01});
            placeholders.forEach(placeholder => sectionPreviewObserver.observe(placeholder));
        }

        function scrollPreviewToSection(sectionIndex) {
            const target = previewEl.querySelector('[data-preview-section="' + sectionIndex + '"]');
            const storefront = previewEl.querySelector('.hb-live-storefront');
            const scroller = storefront && storefront.classList.contains('hb-live-storefront-app')
                ? storefront.querySelector('.hb-live-preview-content')
                : storefront;
            if (!target || !scroller) return;

            const targetRect = target.getBoundingClientRect();
            const scrollerRect = scroller.getBoundingClientRect();
            const header = storefront.querySelector('.hb-app-preview-header, .hb-storefront-header');
            const stickyOffset = header && PLATFORM === 'web' ? header.getBoundingClientRect().height : 0;
            const top = Math.max(0, scroller.scrollTop + targetRect.top - scrollerRect.top - stickyOffset - 8);
            scroller.scrollTo({top, behavior: 'smooth'});
        }

        function isFullscreenEditor() {
            return document.fullscreenElement === root || root.classList.contains('hb-is-fullscreen');
        }

        async function toggleFullscreenEditor() {
            if (document.fullscreenElement === root) {
                await document.exitFullscreen();
                return;
            }
            if (root.classList.contains('hb-is-fullscreen')) {
                root.classList.remove('hb-is-fullscreen');
                renderPreview();
                return;
            }
            try {
                if (root.requestFullscreen) await root.requestFullscreen();
                else root.classList.add('hb-is-fullscreen');
            } catch (error) {
                root.classList.add('hb-is-fullscreen');
            }
            renderPreview();
        }

        function applyPreviewCanvasTransform() {
            const stage = previewEl.querySelector('.hb-device-stage');
            const device = stage && stage.querySelector(':scope > .hb-app-device, :scope > .hb-web-device');
            if (!stage || !device) return;

            stage.style.setProperty('--hb-preview-scale', previewZoom / 100);
            device.style.setProperty('--hb-preview-pan-x', previewPan.x + 'px');
            device.style.setProperty('--hb-preview-pan-y', previewPan.y + 'px');

            const zoomValue = previewEl.querySelector('[data-preview-zoom-reset]');
            if (zoomValue) zoomValue.textContent = previewZoom + '%';
            previewEl.querySelectorAll('[data-preview-zoom]').forEach(button => {
                const next = previewZoom + Number(button.dataset.previewZoom);
                button.disabled = next < 25 || next > 200;
            });
        }

        function setPreviewZoom(nextZoom, focalPoint) {
            const previousScale = previewZoom / 100;
            const normalizedZoom = Math.min(200, Math.max(25, Math.round(nextZoom)));
            const nextScale = normalizedZoom / 100;
            const stage = PLATFORM === 'app' ? previewEl.querySelector('.hb-device-stage-app') : null;

            if (stage && focalPoint && previousScale !== nextScale) {
                const rect = stage.getBoundingClientRect();
                const stageCenterX = rect.left + rect.width / 2;
                const stageCenterY = rect.top + rect.height / 2;
                const anchorX = (focalPoint.x - stageCenterX - previewPan.x) / previousScale;
                const anchorY = (focalPoint.y - stageCenterY - previewPan.y) / previousScale;
                previewPan.x = focalPoint.x - stageCenterX - anchorX * nextScale;
                previewPan.y = focalPoint.y - stageCenterY - anchorY * nextScale;
            }

            previewZoom = normalizedZoom;
            applyPreviewCanvasTransform();
        }

        function initPreviewCanvasInteractions() {
            if (PLATFORM !== 'app') return;
            const stage = previewEl.querySelector('.hb-device-stage');
            if (!stage) return;

            const pointers = new Map();
            const mobileResponsive = window.matchMedia('(max-width: 767.98px)').matches;
            let pan = null;
            let pinch = null;
            let spacePressed = false;

            const pointerDistance = (first, second) => Math.hypot(second.x - first.x, second.y - first.y);
            const pointerCenter = (first, second) => ({x: (first.x + second.x) / 2, y: (first.y + second.y) / 2});
            const capturePointer = pointerId => {
                try {
                    if (stage.setPointerCapture) stage.setPointerCapture(pointerId);
                } catch (error) {
                    // The browser may already have assigned this touch to a nested scroller.
                }
            };
            const releasePointer = pointerId => {
                try {
                    if (stage.hasPointerCapture && stage.hasPointerCapture(pointerId)) stage.releasePointerCapture(pointerId);
                } catch (error) {
                    // Pointer capture is already released.
                }
            };
            const beginPinch = () => {
                const active = Array.from(pointers.values()).slice(0, 2);
                if (active.length < 2) return;
                const center = pointerCenter(active[0], active[1]);
                const rect = stage.getBoundingClientRect();
                const scale = previewZoom / 100;
                pinch = {
                    startDistance: Math.max(1, pointerDistance(active[0], active[1])),
                    startZoom: previewZoom,
                    stageCenterX: rect.left + rect.width / 2,
                    stageCenterY: rect.top + rect.height / 2,
                    anchorX: (center.x - (rect.left + rect.width / 2) - previewPan.x) / scale,
                    anchorY: (center.y - (rect.top + rect.height / 2) - previewPan.y) / scale,
                };
                pan = null;
                active.forEach(pointer => capturePointer(pointer.id));
                stage.classList.add('is-pinching');
                stage.classList.remove('is-panning');
            };
            const stopGesture = event => {
                pointers.delete(event.pointerId);
                releasePointer(event.pointerId);

                if (pinch && pointers.size < 2) {
                    pinch = null;
                    stage.classList.remove('is-pinching');
                }
                if (pan && pan.pointerId === event.pointerId) pan = null;
                if (!pan) stage.classList.remove('is-panning');

                if (!pinch && pointers.size === 1) {
                    const remaining = Array.from(pointers.values())[0];
                    if (remaining.canPan) {
                        pan = {pointerId: remaining.id, x: remaining.x, y: remaining.y};
                        capturePointer(remaining.id);
                        stage.classList.add('is-panning');
                    }
                }
            };

            stage.addEventListener('pointerdown', event => {
                if (event.pointerType !== 'touch' && event.button !== 0 && event.button !== 1) return;
                const insideStorefront = Boolean(event.target.closest('.hb-app-screen'));
                const panHandle = Boolean(event.target.closest('[data-preview-pan-handle]'));
                const interactive = Boolean(event.target.closest('button, a, input, select, textarea, [contenteditable="true"]'));
                const requestedPan = panHandle || event.button === 1 || spacePressed || (!insideStorefront && !interactive);
                const canPan = event.pointerType === 'touch' && mobileResponsive ? false : requestedPan;
                if (event.pointerType !== 'touch' && !canPan) return;
                pointers.set(event.pointerId, {id: event.pointerId, x: event.clientX, y: event.clientY, canPan});

                if (pointers.size >= 2) {
                    beginPinch();
                    event.preventDefault();
                    return;
                }
                if (!canPan) return;

                stage.focus({preventScroll: true});
                pan = {pointerId: event.pointerId, x: event.clientX, y: event.clientY};
                capturePointer(event.pointerId);
                stage.classList.add('is-panning');
                event.preventDefault();
            });

            stage.addEventListener('pointermove', event => {
                const pointer = pointers.get(event.pointerId);
                if (pointer) {
                    pointer.x = event.clientX;
                    pointer.y = event.clientY;
                }

                if (pinch && pointers.size >= 2) {
                    const active = Array.from(pointers.values()).slice(0, 2);
                    const distance = pointerDistance(active[0], active[1]);
                    const center = pointerCenter(active[0], active[1]);
                    previewZoom = Math.min(200, Math.max(25, Math.round(pinch.startZoom * distance / pinch.startDistance)));
                    const scale = previewZoom / 100;
                    previewPan.x = center.x - pinch.stageCenterX - pinch.anchorX * scale;
                    previewPan.y = center.y - pinch.stageCenterY - pinch.anchorY * scale;
                    applyPreviewCanvasTransform();
                    event.preventDefault();
                    return;
                }

                if (!pan || event.pointerId !== pan.pointerId) return;
                previewPan.x += event.clientX - pan.x;
                previewPan.y += event.clientY - pan.y;
                pan.x = event.clientX;
                pan.y = event.clientY;
                applyPreviewCanvasTransform();
                event.preventDefault();
            });
            stage.addEventListener('pointerup', stopGesture);
            stage.addEventListener('pointercancel', stopGesture);
            stage.addEventListener('lostpointercapture', event => {
                if (pointers.has(event.pointerId)) stopGesture(event);
            });

            stage.addEventListener('wheel', event => {
                if (!event.ctrlKey && !event.metaKey) return;
                event.preventDefault();
                setPreviewZoom(previewZoom + (event.deltaY < 0 ? 5 : -5), {x: event.clientX, y: event.clientY});
            }, {passive: false});

            stage.addEventListener('dblclick', event => {
                if (event.target.closest('.hb-app-screen') && !event.target.closest('[data-preview-pan-handle]')) return;
                previewPan.x = 0;
                previewPan.y = 0;
                setPreviewZoom(100);
            });

            stage.addEventListener('keydown', event => {
                if (event.code === 'Space') {
                    spacePressed = true;
                    stage.classList.add('is-pan-ready');
                    event.preventDefault();
                    return;
                }
                if (event.key === '+' || event.key === '=') {
                    setPreviewZoom(previewZoom + 10);
                    event.preventDefault();
                } else if (event.key === '-' || event.key === '_') {
                    setPreviewZoom(previewZoom - 10);
                    event.preventDefault();
                } else if (event.key === '0') {
                    previewPan.x = 0;
                    previewPan.y = 0;
                    setPreviewZoom(100);
                    event.preventDefault();
                }
            });
            stage.addEventListener('keyup', event => {
                if (event.code !== 'Space') return;
                spacePressed = false;
                stage.classList.remove('is-pan-ready');
            });
            stage.addEventListener('blur', () => {
                spacePressed = false;
                stage.classList.remove('is-pan-ready');
            });
        }

        function renderPreview() {
            if (!previewEl) return;
            if (previewRenderTimer) {
                clearTimeout(previewRenderTimer);
                previewRenderTimer = null;
            }
            const viewState = cachedPreviewViewState || (resetPreviewViewOnRender ? null : capturePreviewViewState());
            cachedPreviewViewState = null;
            resetPreviewViewOnRender = false;
            previewSwiperInstances.forEach(swiper => {
                if (!swiper || swiper.destroyed || !swiper.el || typeof swiper.destroy !== 'function') return;
                try {
                    swiper.destroy(true, true);
                } catch (error) {
                    console.warn('Home builder Swiper cleanup skipped', error);
                }
            });
            previewSwiperInstances = [];
            const devices = PLATFORM === 'web'
                ? [['desktop', 'ti-device-desktop', 'Desktop'], ['tablet', 'ti-device-tablet', 'Tablet'], ['mobile', 'ti-device-mobile', 'Mobile']]
                : [['android', 'ti-brand-android', 'Android'], ['iphone', 'ti-brand-apple', 'iPhone']];
            if (!devices.some(device => device[0] === previewDevice)) previewDevice = devices[0][0];
            previewEl.classList.toggle('hb-clean-preview', cleanPreview);
            previewEl.classList.toggle('hb-preview-is-loading', previewTargetLoading);
            root.classList.toggle('hb-header-editing', headerMode && !cleanPreview);
            root.classList.toggle('hb-header-previewing', headerMode && cleanPreview);
            const fullscreen = isFullscreenEditor();
            const marketHint = '<div class="hb-preview-market-hint"><i class="ti ti-info-circle"></i><span>If the expected order or content is missing, choose the market you are viewing from the market filter above.</span></div>';
            const previewControls = '<div class="hb-device-tabs">' + devices.map(device =>
                '<button type="button" class="hb-device-tab' + (previewDevice === device[0] ? ' active' : '') + '" data-preview-device="' + device[0] + '"><i class="ti ' + device[1] + '"></i><span>' + device[2] + '</span></button>'
            ).join('') + '</div>' + marketHint + '<div class="hb-preview-toolbar-actions"><div class="hb-zoom-controls" aria-label="Preview zoom">' +
                '<button type="button" data-preview-zoom="-10" aria-label="Zoom out"' + (previewZoom <= 25 ? ' disabled' : '') + '><i class="ti ti-minus"></i></button>' +
                '<button type="button" class="hb-zoom-value" data-preview-zoom-reset title="Reset zoom">' + previewZoom + '%</button>' +
                '<button type="button" data-preview-zoom="10" aria-label="Zoom in"' + (previewZoom >= 200 ? ' disabled' : '') + '><i class="ti ti-plus"></i></button>' +
                '</div><span class="hb-preview-status"><i></i>' + (cleanPreview ? 'Preview' : 'Live') + '</span>' +
                '<button type="button" class="hb-fullscreen-toggle" data-preview-fullscreen title="' + (fullscreen ? 'Exit full screen' : 'Full screen editor') + '" aria-label="' + (fullscreen ? 'Exit full screen' : 'Full screen editor') + '"><i class="ti ' + (fullscreen ? 'ti-minimize' : 'ti-maximize') + '"></i></button>' +
                '<button type="button" class="btn btn-sm ' + (cleanPreview ? 'btn-outline-secondary' : 'btn-primary') + ' hb-clean-preview-toggle" data-preview-clean><i class="ti ' + (cleanPreview ? 'ti-pencil' : 'ti-eye') + ' me-1"></i>' + (cleanPreview ? 'Back to edit' : 'Show Preview') + '</button></div>';
            const toolbar = '<div class="hb-preview-toolbar' + (cleanPreview ? ' hb-clean-preview-toolbar' : '') + '">' + previewControls + '</div>';
            const loadingOverlay = previewTargetLoading
                ? '<div class="hb-preview-loading-overlay" role="status"><span><i class="ti ti-loader-2"></i></span><strong>Preparing preview</strong><small>' + esc(previewTargetLoadingLabel || 'Loading section content…') + '</small></div>'
                : '';
            const content = previewContentHtml(previewDevice);
            previewEl.innerHTML = toolbar + loadingOverlay + (PLATFORM === 'web' ? webDeviceHtml(previewDevice, content) : appDeviceHtml(previewDevice, content));

            const addNavbarButton = previewEl.querySelector('[data-add-navbar]');
            if (addNavbarButton) addNavbarButton.addEventListener('click', event => {
                event.stopPropagation();
                document.dispatchEvent(new CustomEvent('home-navbar:integrated-add-focus', {
                    detail: {owner: root.id},
                }));
            });

            const cleanToggle = previewEl.querySelector('[data-preview-clean]');
            if (cleanToggle) cleanToggle.addEventListener('click', () => {
                cleanPreview = !cleanPreview;
                renderPreview();
            });
            const fullscreenToggle = previewEl.querySelector('[data-preview-fullscreen]');
            if (fullscreenToggle) fullscreenToggle.addEventListener('click', toggleFullscreenEditor);

            previewEl.querySelectorAll('[data-preview-device]').forEach(button => button.addEventListener('click', () => {
                previewDevice = button.dataset.previewDevice;
                previewPan.x = 0;
                previewPan.y = 0;
                renderPreview();
            }));
            previewEl.querySelectorAll('[data-preview-zoom]').forEach(button => button.addEventListener('click', () => {
                setPreviewZoom(previewZoom + Number(button.dataset.previewZoom));
            }));
            const zoomReset = previewEl.querySelector('[data-preview-zoom-reset]');
            if (zoomReset) zoomReset.addEventListener('click', () => {
                previewPan.x = 0;
                previewPan.y = 0;
                setPreviewZoom(100);
            });
            initPreviewCarousels();
            initPreviewCanvasInteractions();
            initPreviewCategoryNavigation();
            initHeaderNavbarSortable();
            initPreviewStickyHeader();
            initLazySectionObserver();
            if (!cleanPreview) {
                initPreviewItemSortables();
                initPreviewRowSortables();
                initPreviewSectionActions();
                previewEl.querySelectorAll('[data-preview-section]').forEach(section => section.addEventListener('click', event => {
                    if (event.target.closest('.hb-live-section-handle, .hb-preview-item-handle, .hb-preview-slide')) return;
                    const sectionIndex = Number(section.dataset.previewSection);
                    previewEl.querySelectorAll('[data-preview-section]').forEach(candidate => {
                        const isCurrent = Number(candidate.dataset.previewSection) === sectionIndex;
                        candidate.classList.toggle('active', isCurrent);
                        candidate.classList.toggle('is-toolbar-visible', isCurrent);
                    });
                    const row = event.target.closest('[data-preview-row]');
                    if (row && state.sections[sectionIndex] && state.sections[sectionIndex].type === 'play_image') {
                        event.stopPropagation();
                        focusPlayImageRow(sectionIndex, Number(row.dataset.previewRow));
                        return;
                    }
                    sel = sectionIndex;
                    editorTab = 'content';
                    focusedPlayRow = null;
                    renderSections();
                    renderEditor();
                    ensureSectionLoaded(sectionIndex).catch(() => {});
                }));
            }
            const previewSections = previewEl.querySelector('.hb-live-preview-content');
            if (!cleanPreview && previewSections && cfg.canEdit && window.Sortable && state.sections.length) {
                const handleOnly = window.matchMedia('(max-width: 991.98px)').matches;
                window.Sortable.create(previewSections, {
                    handle: handleOnly ? '.hb-live-section-handle' : '.hb-live-section',
                    filter: 'button:not(.hb-live-section-handle), a, input, select, textarea, .hb-preview-sortable-items, .hb-live-play-row-toolbar',
                    preventOnFilter: false,
                    animation: 150,
                    ghostClass: 'hb-ghost',
                    onEnd: event => {
                        if (previewMarketId) {
                            const visibleOrder = Array.from(previewSections.querySelectorAll(':scope > [data-preview-section]'))
                                .map(section => Number(section.dataset.previewSection));
                            applyPreviewMarketOrder(visibleOrder);
                            recordStateChange();
                            renderSections();
                            renderEditor();
                            return;
                        }
                        const selected = state.sections[sel];
                        const moved = state.sections.splice(event.oldIndex, 1)[0];
                        state.sections.splice(event.newIndex, 0, moved);
                        sel = state.sections.indexOf(selected);
                        recordStateChange();
                        renderSections();
                        renderEditor();
                        renderPreview();
                    }
                });
            }
            restorePreviewViewState(viewState);
            if (pendingPreviewSectionScroll !== null) {
                const sectionIndex = pendingPreviewSectionScroll;
                pendingPreviewSectionScroll = null;
                window.requestAnimationFrame(() => scrollPreviewToSection(sectionIndex));
            }
        }

        /* ---------- editor ---------- */
        function seg(field, options, current) {
            const name = 'hbseg-' + (segCounter++);
            return '<div class="form-selectgroup form-selectgroup-pills hb-seg" data-seg="' + field + '">' +
                options.map(o => '<label class="form-selectgroup-item"><input type="radio" name="' + name + '" value="' + o[0] + '" class="form-selectgroup-input"' + (o[0] === current ? ' checked' : '') + '><span class="form-selectgroup-label">' + esc(o[1]) + '</span></label>').join('') +
                '</div>';
        }

        function preset(field, options, current) {
            return '<div class="hb-preset" data-preset="' + field + '">' +
                options.map(o => {
                    const val = Array.isArray(o) ? o[0] : (o.val || o.value);
                    const lbl = Array.isArray(o) ? o[1] : (o.label || val);
                    const img = Array.isArray(o) ? o[2] : (o.image || o.imagePath || o.image_path || o.img || o.src);
                    const icon = Array.isArray(o) ? o[3] : o.icon;
                    const activeClass = (val === current ? ' active' : '');

                    let thumbContent = '';
                    if (img) {
                        thumbContent = '<img src="' + esc(img) + '" alt="' + esc(lbl) + '" class="hb-preset-img">';
                    } else if (icon) {
                        const iconClass = icon.startsWith('ti') ? icon : ('ti-' + icon);
                        thumbContent = '<i class="ti ' + esc(iconClass) + ' hb-preset-icon"></i>';
                    }

                    return '<div class="hb-preset-card' + activeClass + '" data-val="' + esc(val) + '"><div class="hb-thumb">' + thumbContent + '</div>' + esc(lbl) + '</div>';
                }).join('') +
                '</div>';
        }

        function fieldTpl(label, inner) {
            return '<div class="hb-field"><label class="form-label">' + esc(label) + '</label>' + inner + '</div>';
        }

        function rangeFieldTpl(label, configKey, value, min, max) {
            const name = 'hb-range-' + configKey;
            const currentVal = value !== undefined && value !== null ? value : 0;
            return '<div class="hb-field">' +
                '<label class="form-label d-flex justify-content-between">' +
                '<span>' + esc(label) + '</span>' +
                '<span class="badge bg-secondary-lt" id="' + name + '-val">' + currentVal + 'px</span>' +
                '</label>' +
                '<input type="range" class="form-range hb-config-range" data-config-key="' + configKey + '" id="' + name + '" min="' + min + '" max="' + max + '" value="' + currentVal + '">' +
                '</div>';
        }

        function imageSlot(kind, j, preview, nonImage) {
            let inner;
            if (nonImage) {
                inner = '<i class="ti ti-movie fs-1 ' + (preview ? 'text-success' : 'text-muted') + '"></i>';
            } else {
                inner = preview ? '<img src="' + esc(preview) + '">' : '<i class="ti ti-photo text-muted"></i>';
            }
            return '<button type="button" class="hb-img-slot" data-target-kind="' + kind + '"' + (j != null ? ' data-j="' + j + '"' : '') + '>' + inner + '</button>';
        }

        function rowBackgroundSlot(rowIndex, preview) {
            const inner = preview ? '<img src="' + esc(preview) + '">' : '<i class="ti ti-photo text-muted"></i>';
            return '<button type="button" class="hb-img-slot" data-target-kind="row_background" data-row-idx="' + rowIndex + '">' + inner + '</button>';
        }

        function sectionPreview(s, kind) {
            return (s._mediaPreview && s._mediaPreview[kind]) || s[MEDIA_FIELD[kind]] || null;
        }

        function itemPreview(it) {
            return it._imagePreview || it.image || null;
        }

        function itemMobilePreview(it) {
            return it._mobileImagePreview || it.mobile_image || null;
        }

        function renderEditor(options) {
            const deferPreview = options && options.deferPreview;
            const skipPreview = options && options.skipPreview;
            const skipHistory = options && options.skipHistory;
            if (headerMode) {
                mountHeaderEditor();
                if (!skipPreview) renderPreview();
                return;
            }
            editorEl.querySelectorAll('select').forEach(x => {
                if (x.tomselect) x.tomselect.destroy();
            });

            const s = state.sections[sel];
            if (!s) {
                editorEl.innerHTML = '<div class="hb-editor-empty text-muted">' + esc(L('selectSection', 'Select a section to edit')) + '</div>';
                if (!skipPreview) {
                    if (deferPreview) schedulePreviewRender(null, PREVIEW_RENDER_DEBOUNCE_MS);
                    else renderPreview();
                }
                return;
            }

            if (s._loaded === false) {
                editorEl.innerHTML = '<div class="hb-editor-empty hb-editor-loading"><i class="ti ti-loader-2"></i><strong>Loading section</strong><span>Fetching items and design settings…</span></div>';
                return;
            }

            if (!skipHistory) recordStateChange();

            if (sel !== lastSelIndex) {
                lastSelIndex = sel;
                editorTab = 'content';
            }

            const tabs = editorTabsFor(s);
            editorTab = tabs.some(tab => tab[0] === editorTab) ? editorTab : 'content';

            let h = '<div class="hb-editor-header">' +
                '<div class="hb-editor-identity">' +
                '<span class="hb-editor-icon"><i class="ti ' + ICON[s.type] + '"></i></span>' +
                '<div><h3 class="card-title m-0">' + esc(s.title || nameOf(s.type, TYPE)) + '</h3>' +
                '<div class="text-muted small">' + esc(nameOf(s.type, TYPE)) + ' · ' + esc(s.is_active ? L('enabled', 'Enabled') : L('disabled', 'Disabled')) + '</div></div>' +
                '</div>' +
                '<div class="hb-editor-actions">' +
                '<button type="button" class="btn btn-icon btn-xs btn-ghost-secondary" id="hb-undo-btn" title="Undo change"><i class="ti ti-rotate-2" style="font-size:14px;"></i></button>' +
                '<button type="button" class="btn btn-icon btn-xs btn-ghost-secondary" id="hb-redo-btn" title="Redo change"><i class="ti ti-rotate-clockwise-2" style="font-size:14px;"></i></button>' +
                '<button type="button" class="btn btn-icon btn-xs btn-primary" id="hb-copy-design-btn" title="Copy Section Design" aria-label="Copy section design"><i class="ti ti-copy"></i></button>' +
                '<button type="button" class="btn btn-icon btn-xs btn-ghost-danger ms-1" id="hb-del-section" title="Delete section"' + (cfg.canEdit ? '' : ' disabled') + '><i class="ti ti-trash"></i></button>' +
                '</div></div>' +
                '<div class="hb-editor-tabs hb-editor-tabs-' + tabs.length + '" role="tablist">' + tabs.map(tab =>
                    '<button type="button" class="hb-editor-tab' + (editorTab === tab[0] ? ' active' : '') + '" data-editor-tab="' + tab[0] + '" role="tab" aria-selected="' + (editorTab === tab[0] ? 'true' : 'false') + '">' +
                    '<i class="ti ' + tab[1] + '"></i><span>' + esc(tab[2]) + '</span></button>'
                ).join('') + '</div>' +
                '<div class="hb-editor-panel" role="tabpanel">' + editorTabBody(s, editorTab) + '</div>';

            editorEl.innerHTML = h;
            updateHistoryButtons();
            wireEditor(s);
            if (!skipPreview) {
                if (deferPreview) schedulePreviewRender(null, PREVIEW_RENDER_DEBOUNCE_MS);
                else renderPreview();
            }
        }

        function editorTabsFor(s) {
            const tabs = [
                ['content', 'ti-box', L('tabContent', 'Content')]
            ];
            if (s.type === 'hero' || s.type === 'products' || s.type === 'categories' || s.type === 'brands') {
                tabs.push(['layout', 'ti-layout-grid', L('tabLayout', 'Layout')]);
            }
            if ((s.type === 'hero' && s.config.layout === 'items' && s.style === 'image_title') || s.type === 'products' || s.type === 'categories' || s.type === 'play_image') {
                tabs.push(['style', 'ti-palette', L('tabStyle', 'Style')]);
            }
            tabs.push(['visibility', 'ti-eye', L('tabVisibility', 'Visibility')]);
            return tabs;
        }

        function editorTabBody(s, tab) {
            if (tab === 'content') return contentBody(s);
            if (tab === 'layout') return layoutBody(s);
            if (tab === 'style') return styleBody(s);
            return visibilityBody(s);
        }

        function emptyTab() {
            return '<div class="hb-tab-empty"><i class="ti ti-adjustments-off"></i><span>' + esc(L('noSettings', 'No settings are available for this section.')) + '</span></div>';
        }

        function productPreviewCount(section) {
            const resolvedSections = cfg.previewCatalog.section_products || {};
            const sectionKey = sectionUiKey(section);
            if (Object.prototype.hasOwnProperty.call(resolvedSections, sectionKey)) {
                return Array.isArray(resolvedSections[sectionKey]) ? resolvedSections[sectionKey].length : 0;
            }
            if (section.config.source === 'custom') {
                return (section.items || []).filter(item => item.purpose === 'display' && item.itemable_type === 'product').length;
            }
            return previewCatalogItems('Product', section).length;
        }

        function productCountSummary(section) {
            if (marketCatalogLoading) {
                return '<div class="hb-product-count-summary is-loading"><i class="ti ti-loader-2"></i><span>Checking products showing…</span></div>';
            }
            const count = productPreviewCount(section);
            return '<div class="hb-product-count-summary"><i class="ti ti-package"></i><span>Products showing</span><strong>' + count + '</strong></div>';
        }

        function updateProductCountSummary() {
            const current = editorEl.querySelector('.hb-product-count-summary');
            const section = state.sections[sel];
            if (!current || !section || section.type !== 'products') return;
            const wrapper = document.createElement('div');
            wrapper.innerHTML = productCountSummary(section);
            current.replaceWith(wrapper.firstElementChild);
        }

        function sectionTitleField(s) {
            if (s.type === 'hero') return '';
            return '<div class="hb-field hb-title-field"><div class="d-flex align-items-center justify-content-between gap-2 mb-2"><label class="form-label mb-0">' + esc(L('sectionTitle', 'Section title')) + '</label>' +
                '<label class="form-check form-switch m-0"><input class="form-check-input" id="hb-show-title" type="checkbox"' + (s.show_title ? ' checked' : '') + '><span class="form-check-label">Show</span></label></div>' +
                '<input type="text" class="form-control" id="hb-title" value="' + esc(s.title) + '" placeholder="' + esc(L('optionalHeading', 'Internal section name')) + '">' +
                '<div class="form-hint">Keep a title for identifying the section, then hide it from the storefront when needed.</div></div>';
        }

        function contentBody(s) {
            let h = sectionTitleField(s);
            if (s.type === 'hero') {
                if (s.config.layout === 'items') {
                    h += fieldTpl('Items', seg('item_source', ITEM_SOURCE, s.config.item_source)) + heroItemsEditor(s);
                }
                return h;
            }
            if (s.type === 'products') {
                h += fieldTpl('Product list', seg('source', PRODUCT_SOURCE, s.config.source));
                h += s.config.source === 'custom'
                    ? fieldTpl(L('selectedProducts', 'Selected products'), '<select class="form-select hb-entity" data-entity="product" data-purpose="display" multiple></select>')
                    : fieldTpl(L('limitCategories', 'Categories (required)'), '<select class="form-select hb-entity" data-entity="category" data-purpose="filter" multiple></select>');
                return h + productCountSummary(s);
            }
            if (s.type === 'categories') {
                return h + (sourceModes.length > 1 ? fieldTpl('Source', seg('source', sourceModes, s.config.source)) : '') +
                    (s.config.source === 'custom' ? fieldTpl('Categories', '<select class="form-select hb-entity" data-entity="category" data-purpose="display" multiple></select>') + customSelectionItemsEditor(s, 'category') : '');
            }
            if (s.type === 'brands') {
                return h + (sourceModes.length > 1 ? fieldTpl('Source', seg('source', sourceModes, s.config.source)) : '') +
                    (s.config.source === 'custom' ? fieldTpl('Brands', '<select class="form-select hb-entity" data-entity="brand" data-purpose="display" multiple></select>') + customSelectionItemsEditor(s, 'brand') : '');
            }
            if (s.type === 'play_image') {
                return h + playImageRowsEditor(s, false);
            }
            return h || emptyTab();
        }

        function layoutBody(s) {
            if (s.type === 'hero') {
                let h = fieldTpl(L('background', 'Background'), seg('media_type', HERO_MEDIA, s.config.media_type)) +
                    fieldTpl(s.config.media_type === 'lottie' ? 'Animation file (JSON)' : 'Background image', imageSlot('hero_media', null, sectionPreview(s, 'hero_media'), s.config.media_type === 'lottie'));
                h += fieldTpl('Layout', seg('layout', HERO_LAYOUT, s.config.layout));
                if (s.config.layout === 'items') h += fieldTpl('Item style', preset('style', STYLES.hero, s.style));
                return h;
            }
            if (s.type === 'products') return fieldTpl('Scroll', preset('orientation', ORIENTATION, s.config.orientation));
            if (s.type === 'categories') return fieldTpl('Style', preset('style', categoryStyles, s.style));
            if (s.type === 'brands') return fieldTpl('Style', preset('style', STYLES.brands, s.style));
            return emptyTab();
        }

        function styleBody(s) {
            if (s.type === 'hero' && s.config.layout === 'items' && s.style === 'image_title') {
                return fieldTpl('Card background', seg('card_bg_type', [['color', 'Color'], ['image', 'Image']], s.config.card_bg_type)) +
                    (s.config.card_bg_type === 'image'
                        ? fieldTpl('Card background image', imageSlot('card_background', null, sectionPreview(s, 'card_background')))
                        : '<div class="hb-field"><input type="color" class="form-control form-control-color" style="max-width:60px" id="hb-cardcolor" value="' + esc(s.config.card_bg_color || '#F1EFE8') + '" title="' + esc(L('background', 'Background')) + '"></div>') +
                    fieldTpl('Card title color', '<input type="color" class="form-control form-control-color" style="max-width:60px" id="hb-cardtitlecolor" value="' + esc(s.config.card_title_color || '#222222') + '" title="Card title color">');
            }
            if (s.type === 'products' || s.type === 'categories') {
                const hideBgImage = s.type === 'products' && s.config.orientation === 'vertical';
                if (hideBgImage && s.config.background_type === 'image') s.config.background_type = 'none';
                const bgOptions = hideBgImage ? BACKGROUND.filter(o => o[0] !== 'image') : BACKGROUND;
                let h = s.type === 'products'
                    ? fieldTpl('Product card', preset('style', productStyles, s.style || (PLATFORM === 'app' ? 'quick_add' : 'standard')))
                    : '';
                h += fieldTpl('Background', seg('background_type', bgOptions, s.config.background_type || 'none'));
                if (s.config.background_type === 'color') h += '<div class="hb-field"><input type="color" class="form-control form-control-color" style="max-width:60px" id="hb-bgcolor" value="' + esc(s.config.background_color || '#FFFFFF') + '" title="' + esc(L('background', 'Background')) + '"></div>';
                if (s.config.background_type === 'image') h += fieldTpl('Background image', imageSlot('background', null, sectionPreview(s, 'background')));
                return h;
            }
            if (s.type === 'play_image') return playImageRowsEditor(s, true);
            return emptyTab();
        }

        function visibilityBody(s) {
            return '<div class="hb-visibility-row"><div><strong>' + esc(L('sectionEnabled', 'Section enabled')) + '</strong><div class="text-muted small">' + esc(s.is_active ? L('enabled', 'Enabled') : L('disabled', 'Disabled')) + '</div></div>' +
                '<label class="form-check form-switch m-0"><input class="form-check-input" id="hb-section-enabled" type="checkbox"' + (s.is_active ? ' checked' : '') + (cfg.canEdit ? '' : ' disabled') + '></label></div>' +
                fieldTpl(L('markets', 'Markets'), '<select class="form-select hb-markets" multiple></select>');
        }

        function heroItemsEditor(s) {
            const rows = s.items.map((it, j) => {
                const itemKey = itemUiKey(it);
                const disclosure = disclosureInfo(s, 'hero-item', itemKey, false);
                const collapseId = 'hb-hero-' + sectionUiKey(s) + '-' + itemKey;
                return '<div class="hb-item-panel" data-j="' + j + '">' +
                '<div class="hb-item-collapse-head hb-disclosure-trigger" data-bs-toggle="collapse" data-bs-target="#' + collapseId + '" data-disclosure-key="' + disclosure.key + '" aria-expanded="' + disclosure.expanded + '">' +
                '<span><i class="ti ti-chevron-right me-2 hb-disclosure-chevron"></i>' + esc(it.itemable_label || it.title || ('Item #' + (j + 1))) + '</span>' +
                '<button type="button" class="btn btn-sm btn-ghost-danger hb-item-remove"><i class="ti ti-x"></i></button></div>' +
                '<div id="' + collapseId + '" class="collapse' + disclosure.show + '" data-disclosure-key="' + disclosure.key + '"><div class="hb-item-panel-body hb-item-row mb-0">' +
                imageSlot('item', j, itemPreview(it)) +
                '<select class="form-select hb-hero-item" data-entity="' + SRC_TO_KEY[s.config.item_source] + '"></select>' +
                '<input type="text" class="form-control hb-item-title" value="' + esc(it.title) + '" placeholder="Item title" style="max-width:180px">' +
                '</div></div></div>';
            }).join('');
            const canAdd = s.items.length < 6;
            return fieldTpl(L('selectItems', 'Select items') + ' (' + s.items.length + '/6)', '<div class="hb-items-stack">' + rows + '</div>' +
                '<button type="button" class="btn btn-sm btn-outline-primary hb-add-item"' + (canAdd ? '' : ' disabled') + '><i class="ti ti-plus me-1"></i>' + esc(L('addItem', 'Add item')) + (canAdd ? '' : ' (max 6)') + '</button>');
        }

        function customSelectionItemsEditor(s, type) {
            const items = s.items.filter(item => item.itemable_type === type);
            if (!items.length) return '';

            return fieldTpl('Custom images', '<div class="text-muted small mb-2">Optional. The module image is used when no custom image is uploaded.</div><div class="hb-items-stack">' + items.map(item => {
                const j = s.items.indexOf(item);
                const preview = itemPreview(item) || item.itemable_image;
                const itemKey = itemUiKey(item);
                const disclosure = disclosureInfo(s, 'custom-item', itemKey, false);
                const collapseId = 'hb-custom-' + sectionUiKey(s) + '-' + itemKey;
                const resetButton = itemPreview(item)
                    ? '<button type="button" class="btn btn-sm btn-outline-secondary hb-use-module-image" data-j="' + j + '">Use module image</button>'
                    : '';
                return '<div class="hb-item-panel" data-j="' + j + '">' +
                    '<div class="hb-item-collapse-head hb-disclosure-trigger" data-bs-toggle="collapse" data-bs-target="#' + collapseId + '" data-disclosure-key="' + disclosure.key + '" aria-expanded="' + disclosure.expanded + '">' +
                    '<span><i class="ti ti-chevron-right me-2 hb-disclosure-chevron"></i>' + esc(item.itemable_label || ('Item #' + (j + 1))) + '</span></div>' +
                    '<div id="' + collapseId + '" class="collapse' + disclosure.show + '" data-disclosure-key="' + disclosure.key + '"><div class="hb-item-panel-body d-flex align-items-center gap-3">' +
                    imageSlot('item', j, preview) +
                    '<div class="flex-fill"><div class="fw-medium">Custom image</div><div class="text-muted small mb-2">Leave empty to use the module image.</div>' + resetButton + '</div>' +
                    '</div></div></div>';
            }).join('') + '</div>');
        }

        function playImagePreviewGrid(s) {
            if ((!s.items || !s.items.length) && !(Array.isArray(s.config.rows) && s.config.rows.length)) {
                return '<div class="mb-3 p-3 text-center text-muted border border-dashed rounded">No items added yet. Click "Add New Row" below.</div>';
            }

            // Load lottie player script dynamically in admin panel if layout has lottie items
            if (s.items.some(it => it.config && it.config.media_type === 'lottie')) {
                ensureLottiePlayer();
            }

            s.preview_mode = s.preview_mode || PLATFORM;
            const isMobileLayout = s.preview_mode === 'app';

            const desktopActive = !isMobileLayout ? 'active' : '';
            const mobileActive = isMobileLayout ? 'active' : '';

            const toggleHtml = '<div class="btn-group btn-group-sm ms-2 hb-preview-toggle-group" role="group">' +
                '<button type="button" class="btn btn-outline-secondary ' + desktopActive + '" data-mode="web"><i class="ti ti-device-desktop me-1"></i>Desktop view</button>' +
                '<button type="button" class="btn btn-outline-secondary ' + mobileActive + '" data-mode="app"><i class="ti ti-device-mobile me-1"></i>Mobile view</button>' +
                '</div>';

            // Group items into rows
            const groupedRows = [];
            s.items.forEach(it => {
                it.config = it.config || {};
                const rIdx = it.config.row_index !== undefined ? parseInt(it.config.row_index, 10) : 0;
                if (!groupedRows[rIdx]) {
                    groupedRows[rIdx] = [];
                }
                groupedRows[rIdx].push(it);
            });
            const rowCount = Math.max(groupedRows.length, Array.isArray(s.config.rows) ? s.config.rows.length : 0);
            const activeRows = Array.from({length: rowCount}, (_, rowIndex) => groupedRows[rowIndex] || []);

            const previewRowsHtml = activeRows.map((rowItems, rIdx) => {
                s.config.rows = s.config.rows || {};
                s.config.rows[rIdx] = s.config.rows[rIdx] || defaultRowConfig();
                const rCfg = s.config.rows[rIdx];

                const signedGap = Number(rCfg.grid_gap) || 0;
                const gap = Math.max(0, signedGap) + 'px';
                const boxSpacing = rowBoxSpacing(rCfg);
                const radius = (rCfg.border_radius !== undefined ? rCfg.border_radius : 0) + 'px';
                const hoverEffect = rCfg.hover_effect || 'none';
                const rowBackgroundImage = (s._rowBackgroundPreview && s._rowBackgroundPreview[rIdx]) || rCfg.background_image;
                let rowBackgroundStyle = '';
                if (rCfg.background_type === 'color') {
                    rowBackgroundStyle = 'background-color:' + esc(rCfg.background_color || '#ffffff') + ';';
                } else if (rCfg.background_type === 'image' && rowBackgroundImage) {
                    rowBackgroundStyle = 'background-image:url(&quot;' + esc(rowBackgroundImage) + '&quot;);background-size:cover;background-position:center;';
                }

                let rowStyle = '';
                if (rCfg.container_width === 'fluid') {
                    rowStyle = 'max-width: 96%; margin: 0 auto;';
                } else if (rCfg.container_width === 'full') {
                    rowStyle = 'max-width: 100%; margin: 0;';
                } else {
                    rowStyle = 'max-width: 90%; margin: 0 auto;';
                }

                if (rCfg.style === 'scroll') {
                    // Render Row as a scroll slider!
                    const itemsToShow = parseFloat(isMobileLayout
                        ? (rCfg.items_to_show_mobile !== undefined ? rCfg.items_to_show_mobile : 1)
                        : (rCfg.items_to_show !== undefined ? rCfg.items_to_show : 1)
                    );
                    const itemWidth = 'calc(' + (100 / itemsToShow) + '% - (' + gap + ' * ' + (itemsToShow - 1) + ' / ' + itemsToShow + '))';

                    const cards = rowItems.map((it, idx) => {
                        const origIndex = s.items.indexOf(it);
                        const cssAspectRatio = playItemAspectRatio(it.config);
                        const customSize = playItemCustomSize(it.config);
                        const isOriginal = !cssAspectRatio && !customSize;
                        const objectFit = playItemObjectFit(it.config);
                        const objectPosition = playItemObjectPosition(it.config);
                        let imgHtml = '';
                        const isLottie = it.config.media_type === 'lottie';
                        if (isLottie) {
                            const previewImg = isMobileLayout ? (itemMobilePreview(it) || itemPreview(it)) : (itemPreview(it) || itemMobilePreview(it));
                            const w = getLottieSize(it.config.lottie_width, '100%');
                            const h = getLottieSize(it.config.lottie_height, '150px');
                            imgHtml = previewImg
                                ? '<lottie-player src="' + esc(previewImg) + '" background="transparent" speed="1" style="width:' + w + '; height:' + h + '; display:block; margin:0 auto; border-radius:' + radius + ';" loop autoplay></lottie-player>'
                                : '<div class="d-flex align-items-center justify-content-center bg-light text-muted" style="width:100%; height:150px; border-radius:' + radius + '; border: 1px solid #e2e8f0;"><i class="ti ti-movie fs-2 me-1"></i>Lottie Uploaded</div>';
                        } else {
                            const previewImg = isMobileLayout ? (itemMobilePreview(it) || itemPreview(it)) : (itemPreview(it) || itemMobilePreview(it));
                            const imgStyle = isOriginal
                                ? 'width:100%; height:auto; display:block; object-fit:' + objectFit + '; object-position:' + objectPosition + '; border-radius:' + radius + ';'
                                : 'width:100%; height:100%; object-fit:' + objectFit + '; object-position:' + objectPosition + '; border-radius:' + radius + ';';

                            imgHtml = previewImg
                                ? '<img src="' + esc(previewImg) + '" style="' + imgStyle + '">'
                                : '<div class="d-flex align-items-center justify-content-center bg-light text-muted" style="width:100%; height:100%; min-height:80px; border-radius:' + radius + '; border: 1px solid #e2e8f0;"><i class="ti ti-photo fs-2"></i></div>';
                        }

                        const cardWrapperStyle = isLottie
                            ? 'position:relative;width:100%;text-align:center;'
                            : 'position:relative;' + playItemFrameStyle(it.config);
                        const hoverClass = hoverEffect !== 'none' ? ' hover-' + hoverEffect : '';

                        return '<div class="hb-preview-card flex-shrink-0' + hoverClass + '" data-orig-idx="' + origIndex + '" style="width: ' + itemWidth + '; position:relative; cursor:move;">' +
                            '<div style="' + cardWrapperStyle + '">' +
                            imgHtml +
                            '</div></div>';
                    }).join('');

                    const bulletsHtml = parseBool(rCfg.show_bullets, false)
                        ? '<div class="d-flex justify-content-center gap-1 mt-2">' +
                          rowItems.map((_, i) => '<span class="badge badge-empty bg-secondary" style="width: 6px; height: 6px; border-radius: 50%; padding: 0;"></span>').join('') +
                          '</div>'
                        : '';

                    const navButtonsHtml = PLATFORM !== 'app' && parseBool(rCfg.show_scroll_buttons, true)
                        ? '<button type="button" class="btn btn-icon btn-sm btn-outline-secondary position-absolute start-0 top-50 translate-middle-y z-3 ms-2" style="border-radius: 50%; opacity: 0.8; width: 28px; height: 28px; padding: 0; background: var(--tblr-bg-surface);"><i class="ti ti-chevron-left"></i></button>' +
                          '<button type="button" class="btn btn-icon btn-sm btn-outline-secondary position-absolute end-0 top-50 translate-middle-y z-3 me-2" style="border-radius: 50%; opacity: 0.8; width: 28px; height: 28px; padding: 0; background: var(--tblr-bg-surface);"><i class="ti ti-chevron-right"></i></button>'
                        : '';

                    return '<div class="hb-preview-row mb-3 pb-2" data-row-idx="' + rIdx + '" style="position:relative; margin:' + boxSpacing.margin + '; border-bottom: 1px dashed var(--tblr-border-color);">' +
                        '<div class="text-muted small mb-1 d-flex justify-content-between align-items-center">' +
                        '<span class="d-flex align-items-center gap-1"><button type="button" class="btn btn-icon btn-xs btn-ghost-secondary hb-editor-preview-row-handle" title="Drag row"><i class="ti ti-grip-horizontal"></i></button>Row #' + (rIdx + 1) + ' (Slider Layout)</span>' +
                        '<button type="button" class="btn btn-xs btn-ghost-danger hb-remove-row-btn" data-row-idx="' + rIdx + '" title="Delete row and all its items"><i class="ti ti-trash"></i> Delete Row</button>' +
                        '</div>' +
                        '<div class="position-relative w-full hb-preview-row-scroll-container" style="padding: ' + boxSpacing.padding + '; border: 1px solid var(--tblr-border-color); border-radius: 8px; ' + rowBackgroundStyle + rowStyle + '">' +
                        navButtonsHtml +
                        '<div class="hb-preview-grid-container d-flex flex-nowrap" style="overflow-x: auto; scrollbar-width: none; gap: ' + gap + '; --hb-negative-gap:' + Math.min(0, signedGap) + 'px;">' +
                        cards +
                        '</div>' +
                        bulletsHtml +
                        '</div></div>';
                }

                // Grid mode
                const computedLayout = calculateSpansJS(rowItems, isMobileLayout);
                const cards = computedLayout.map((layoutItem) => {
                    const it = layoutItem.item;
                    const span = layoutItem.span;
                    const colsMobile = it.config.cols_mobile || 'auto';
                    const colsDesktop = it.config.cols || 'auto';
                    const origIndex = s.items.indexOf(it);

                    const cssAspectRatio = playItemAspectRatio(it.config);
                    const customSize = playItemCustomSize(it.config);
                    const isOriginal = !cssAspectRatio && !customSize;
                    const objectFit = playItemObjectFit(it.config);
                    const objectPosition = playItemObjectPosition(it.config);
                    const isLottie = it.config.media_type === 'lottie';
                    let gridColumnStyle = 'grid-column: span ' + span + ';';
                    if (!isOriginal && !isLottie) {
                        gridColumnStyle += ' aspect-ratio: ' + cssAspectRatio + ';';
                    }

                    let imgHtml = '';
                    if (isLottie) {
                        const previewImg = isMobileLayout ? (itemMobilePreview(it) || itemPreview(it)) : (itemPreview(it) || itemMobilePreview(it));
                        const w = getLottieSize(it.config.lottie_width, '100%');
                        const h = getLottieSize(it.config.lottie_height, '150px');
                        imgHtml = previewImg
                            ? '<lottie-player src="' + esc(previewImg) + '" background="transparent" speed="1" style="width:' + w + '; height:' + h + '; display:block; margin:0 auto; border-radius:' + radius + ';" loop autoplay></lottie-player>'
                            : '<div class="d-flex align-items-center justify-content-center bg-light text-muted" style="width:100%; height:150px; border-radius:' + radius + '; border: 1px solid #e2e8f0;"><i class="ti ti-movie fs-2 me-1"></i>Lottie Uploaded</div>';
                    } else {
                        const previewImg = isMobileLayout ? (itemMobilePreview(it) || itemPreview(it)) : (itemPreview(it) || itemMobilePreview(it));
                        const imgStyle = isOriginal
                            ? 'width:100%; height:100%; display:block; object-fit:' + objectFit + '; object-position:' + objectPosition + '; border-radius:' + radius + ';'
                            : 'width:100%; height:100%; object-fit:' + objectFit + '; object-position:' + objectPosition + '; border-radius:' + radius + ';';

                        imgHtml = previewImg
                            ? '<img src="' + esc(previewImg) + '" style="' + imgStyle + '">'
                            : '<div class="d-flex align-items-center justify-content-center bg-light text-muted" style="width:100%; height:100%; min-height:80px; border-radius:' + radius + '; border: 1px solid #e2e8f0;"><i class="ti ti-photo fs-2"></i></div>';
                    }

                    const cardWrapperStyle = isLottie
                        ? 'position:relative;width:100%;text-align:center;'
                        : 'position:relative;' + playItemFrameStyle(it.config) + (isOriginal ? 'height:100%;' : '');
                    const hoverClass = hoverEffect !== 'none' ? ' hover-' + hoverEffect : '';

                    return '<div class="hb-preview-card' + hoverClass + '" data-orig-idx="' + origIndex + '" style="' + gridColumnStyle + ' position:relative; cursor:move;" title="Drag to reorder within/between rows">' +
                        '<div style="' + cardWrapperStyle + '">' +
                        imgHtml +
                        '<div class="badge bg-dark text-white px-2 py-1" style="position:absolute; bottom:4px; right:4px; font-size:10px; opacity:0.85; pointer-events:none;">' +
                        'D:' + colsDesktop + ' | M:' + colsMobile +
                        '</div></div></div>';
                }).join('');

                return '<div class="hb-preview-row mb-3 pb-2" data-row-idx="' + rIdx + '" style="position:relative; margin:' + boxSpacing.margin + '; border-bottom: 1px dashed var(--tblr-border-color);">' +
                    '<div class="text-muted small mb-1 d-flex justify-content-between align-items-center">' +
                    '<span class="d-flex align-items-center gap-1"><button type="button" class="btn btn-icon btn-xs btn-ghost-secondary hb-editor-preview-row-handle" title="Drag row"><i class="ti ti-grip-horizontal"></i></button>Row #' + (rIdx + 1) + ' (Grid Layout)</span>' +
                    '<button type="button" class="btn btn-xs btn-ghost-danger hb-remove-row-btn" data-row-idx="' + rIdx + '" title="Delete row and all its items"><i class="ti ti-trash"></i> Delete Row</button>' +
                    '</div>' +
                    '<div class="hb-preview-grid-container d-grid" style="grid-template-columns: repeat(12, 1fr); gap: ' + gap + '; padding: ' + boxSpacing.padding + '; --hb-negative-gap:' + Math.min(0, signedGap) + 'px; border: 1px solid var(--tblr-border-color); border-radius: 8px; min-height: 100px; ' + rowBackgroundStyle + rowStyle + '">' +
                    cards +
                    '</div></div>';
            }).join('');

            const previewTitle = isMobileLayout ? 'Live Grid Preview (Mobile view)' : 'Live Grid Preview (Desktop view)';
            const previewDisclosure = disclosureInfo(s, 'preview', 'main', true);
            const previewCollapseId = 'hb-preview-' + PLATFORM + '-' + sectionUiKey(s);

            return '<div class="hb-field hb-preview-panel">' +
                '<div class="form-label d-flex align-items-center justify-content-between hb-preview-collapse-head hb-disclosure-trigger" data-bs-toggle="collapse" data-bs-target="#' + previewCollapseId + '" data-disclosure-key="' + previewDisclosure.key + '" aria-expanded="' + previewDisclosure.expanded + '">' +
                '<div class="d-flex align-items-center"><span>' + esc(previewTitle) + '</span>' + toggleHtml + '</div>' +
                '<small class="text-muted"><i class="ti ti-chevron-right me-1 hb-disclosure-chevron"></i>Drag items to reorder</small>' +
                '</div>' +
                '<div id="' + previewCollapseId + '" class="collapse' + previewDisclosure.show + '" data-disclosure-key="' + previewDisclosure.key + '">' +
                '<div class="hb-editor-preview-rows">' + previewRowsHtml + '</div>' +
                '</div></div>';
        }

        function playImageRowsEditor(s, styleOnly) {
            // Group items into rows
            const groupedRows = [];
            s.items.forEach(it => {
                it.config = it.config || {};
                const rIdx = it.config.row_index !== undefined ? parseInt(it.config.row_index, 10) : 0;
                if (!groupedRows[rIdx]) {
                    groupedRows[rIdx] = [];
                }
                groupedRows[rIdx].push(it);
            });
            const rowCount = Math.max(groupedRows.length, Array.isArray(s.config.rows) ? s.config.rows.length : 0);
            const activeRows = Array.from({length: rowCount}, (_, rowIndex) => groupedRows[rowIndex] || []);

            if (activeRows.length === 0) {
                return styleOnly
                    ? fieldTpl('Row styling', '<div class="text-center p-3 border border-dashed rounded text-muted">Add a row from the Content tab to configure its styling.</div>')
                    : fieldTpl('Items', '<div class="text-center p-3 border border-dashed rounded text-muted mb-2">No rows. Click "Add New Row" to start building your grid.</div>' +
                        '<button type="button" class="btn btn-sm btn-outline-primary hb-add-row"><i class="ti ti-plus me-1"></i>Add New Row</button>');
            }

            const rowsHtml = activeRows.map((rowItems, rIdx) => {
                s.config.rows = s.config.rows || {};
                s.config.rows[rIdx] = s.config.rows[rIdx] || defaultRowConfig();
                const rCfg = s.config.rows[rIdx];

                const itemsHtml = styleOnly ? '' : rowItems.map(it => {
                    const j = s.items.indexOf(it);
                    const cols = it.config.cols || 'auto';
                    const colsMobile = it.config.cols_mobile || 'auto';
                    const aspectRatio = it.config.aspect_ratio || 'original';
                    const aspectWidthUnit = it.config.aspect_width_unit || '%';
                    const aspectHeightUnit = it.config.aspect_height_unit || 'px';
                    const altText = it.config.alt || '';
                    const linkType = it.link_type || (it.link_url ? 'custom' : (it.itemable_type || 'none'));

                    const colsOptions = ['auto', 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12].map(c =>
                        '<option value="' + c + '"' + (String(c) === String(cols) ? ' selected' : '') + '>' + (c === 'auto' ? 'Auto' : c + ' Columns') + '</option>'
                    ).join('');

                    const colsMobileOptions = ['auto', 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12].map(c =>
                        '<option value="' + c + '"' + (String(c) === String(colsMobile) ? ' selected' : '') + '>' + (c === 'auto' ? 'Auto' : c + ' Columns') + '</option>'
                    ).join('');

                    const aspectOptions = [
                        ['original', 'Original'],
                        ['1:1', 'Square (1:1)'],
                        ['16:9', 'Widescreen (16:9)'],
                        ['4:3', 'Standard (4:3)'],
                        ['3:2', 'Classic (3:2)'],
                        ['2:3', 'Portrait (2:3)'],
                        ['custom', 'Custom width / height']
                    ].map(o => '<option value="' + o[0] + '"' + (o[0] === aspectRatio ? ' selected' : '') + '>' + o[1] + '</option>').join('');

                    const sizeUnitOptions = value => [
                        ['px', 'px'],
                        ['%', '%'],
                        ['rem', 'rem'],
                        ['em', 'em'],
                        ['vw', 'vw'],
                        ['vh', 'vh']
                    ].map(o => '<option value="' + o[0] + '"' + (o[0] === value ? ' selected' : '') + '>' + o[1] + '</option>').join('');

                    const objectFit = playItemObjectFit(it.config);
                    const objectPosition = playItemObjectPosition(it.config);
                    const objectFitOptions = [
                        ['cover', 'Cover frame (may crop)'],
                        ['contain', 'Contain full image'],
                        ['fill', 'Stretch to fill'],
                        ['scale-down', 'Scale down only'],
                        ['none', 'Original image size']
                    ].map(o => '<option value="' + o[0] + '"' + (o[0] === objectFit ? ' selected' : '') + '>' + o[1] + '</option>').join('');
                    const objectPositionOptions = [
                        ['center', 'Center'],
                        ['top', 'Top'],
                        ['right', 'Right'],
                        ['bottom', 'Bottom'],
                        ['left', 'Left']
                    ].map(o => '<option value="' + o[0] + '"' + (o[0] === objectPosition ? ' selected' : '') + '>' + o[1] + '</option>').join('');

                    const mediaType = it.config.media_type || 'image';
                    const itemKey = itemUiKey(it);
                    const itemDisclosure = disclosureInfo(s, 'play-item', itemKey, false);
                    const itemCollapseId = 'hb-play-' + sectionUiKey(s) + '-' + itemKey;
                    const mediaTypeOptions = [
                        ['image', 'Image'],
                        ['lottie', 'Lottie']
                    ];

                    const mediaTypeSelector = '<div class="mb-2">' +
                        '<label class="form-label small text-muted mb-1">Media Type</label>' +
                        seg('__mediatype', mediaTypeOptions, mediaType).replace('data-seg="__mediatype"', 'data-item-mediatype="' + j + '"') +
                        '</div>';

                    let mediaUploadSlot = '';
                    if (mediaType === 'lottie') {
                        mediaUploadSlot = '<div class="d-flex flex-column gap-1" style="min-width: 80px;">' +
                            '<span class="small text-muted" style="font-size: 10px;">Lottie JSON</span>' +
                            imageSlot('item_lottie', j, it.image || it._imagePreview, true) +
                            '</div>';
                    } else {
                        mediaUploadSlot = '<div class="d-flex flex-column gap-1" style="min-width: 80px;">' +
                            '<span class="small text-muted" style="font-size: 10px;">Desktop</span>' +
                            imageSlot('item', j, itemPreview(it)) +
                            '<span class="small text-muted" style="font-size: 10px;">Mobile</span>' +
                            imageSlot('item_mobile', j, itemMobilePreview(it)) +
                            '</div>';
                    }

                    // Only show column selectors if the row style is Grid
                    const colsSelectorHtml = rCfg.style === 'grid'
                        ? '<div class="row g-2 mb-2">' +
                          '<div class="col-md-6">' +
                          '<label class="form-label small text-muted mb-1">Desktop Width</label>' +
                          '<select class="form-select form-select-sm hb-grid-cols">' + colsOptions + '</select>' +
                          '</div>' +
                          '<div class="col-md-6">' +
                          '<label class="form-label small text-muted mb-1">Mobile Width</label>' +
                          '<select class="form-select form-select-sm hb-grid-cols-mobile">' + colsMobileOptions + '</select>' +
                          '</div></div>'
                        : '';

                    const aspectOrLottieSizeHtml = mediaType === 'lottie'
                        ? '<div class="row g-2 mb-2">' +
                          '<div class="col-md-6">' +
                          '<label class="form-label small text-muted mb-1">Lottie Width</label>' +
                          '<input type="text" class="form-control form-control-sm hb-grid-lottie-width" value="' + esc(it.config.lottie_width || '100%') + '" placeholder="e.g. 100% or 150px">' +
                          '</div>' +
                          '<div class="col-md-6">' +
                          '<label class="form-label small text-muted mb-1">Lottie Height</label>' +
                          '<input type="text" class="form-control form-control-sm hb-grid-lottie-height" value="' + esc(it.config.lottie_height || '150px') + '" placeholder="e.g. 150px">' +
                          '</div></div>'
                        : '<div class="row g-2 mb-2">' +
                          '<div class="col-md-6">' +
                          '<label class="form-label small text-muted mb-1">Aspect Ratio</label>' +
                          '<select class="form-select form-select-sm hb-grid-aspect">' + aspectOptions + '</select>' +
                          '</div>' +
                          '<div class="col-md-6">' +
                          '<label class="form-label small text-muted mb-1">SEO Alt Text</label>' +
                          '<input type="text" class="form-control form-control-sm hb-grid-alt" value="' + esc(altText) + '" placeholder="SEO Alt Text">' +
                          '</div></div>' +
                          (aspectRatio === 'custom'
                              ? '<div class="hb-custom-aspect mb-2"><label class="form-label small text-muted mb-1">Custom frame size</label>' +
                                '<div class="row g-2"><div class="col-6"><label class="form-label small mb-1">Width</label>' +
                                '<div class="input-group input-group-sm"><input type="number" class="form-control hb-grid-aspect-width" min="0.1" max="10000" step="0.1" value="' + esc(it.config.aspect_width || 100) + '" aria-label="Custom image width">' +
                                '<select class="form-select hb-grid-aspect-unit" data-size-key="aspect_width_unit" aria-label="Width unit">' + sizeUnitOptions(aspectWidthUnit) + '</select></div></div>' +
                                '<div class="col-6"><label class="form-label small mb-1">Height</label>' +
                                '<div class="input-group input-group-sm"><input type="number" class="form-control hb-grid-aspect-height" min="0.1" max="10000" step="0.1" value="' + esc(it.config.aspect_height || 300) + '" aria-label="Custom image height">' +
                                '<select class="form-select hb-grid-aspect-unit" data-size-key="aspect_height_unit" aria-label="Height unit">' + sizeUnitOptions(aspectHeightUnit) + '</select></div></div></div>' +
                                '<div class="form-hint">Sets the actual image frame. A responsive starting point is 100% width and 300px height.</div></div>'
                              : '') +
                          '<div class="row g-2 mb-2"><div class="col-md-6">' +
                          '<label class="form-label small text-muted mb-1">Image Fit</label>' +
                          '<select class="form-select form-select-sm hb-grid-object-fit">' + objectFitOptions + '</select></div>' +
                          '<div class="col-md-6"><label class="form-label small text-muted mb-1">Image Focus</label>' +
                          '<select class="form-select form-select-sm hb-grid-object-position">' + objectPositionOptions + '</select></div></div>';

                    return '<div class="hb-item-panel hb-play-image-item" data-j="' + j + '">' +
                        '<div class="hb-item-collapse-head hb-disclosure-trigger" data-bs-toggle="collapse" data-bs-target="#' + itemCollapseId + '" data-disclosure-key="' + itemDisclosure.key + '" aria-expanded="' + itemDisclosure.expanded + '">' +
                        '<span><i class="ti ti-chevron-right me-2 hb-disclosure-chevron"></i>' + esc(it.title || ('Item #' + (j + 1))) + '<small class="hb-item-summary">' + esc(mediaType === 'lottie' ? 'Animation' : 'Image') + '</small></span>' +
                        '<button type="button" class="btn btn-sm btn-ghost-danger hb-item-remove"><i class="ti ti-x"></i></button></div>' +
                        '<div id="' + itemCollapseId + '" class="collapse' + itemDisclosure.show + '" data-disclosure-key="' + itemDisclosure.key + '"><div class="hb-item-panel-body">' +
                        mediaUploadSlot +
                        '<div class="flex-fill">' +
                        mediaTypeSelector +
                        colsSelectorHtml +
                        aspectOrLottieSizeHtml +
                        '<div class="mb-2">' +
                        '<label class="form-label small text-muted mb-1">Action Link</label>' +
                        seg('__linktype', GRID_LINK_TYPES, linkType).replace('data-seg="__linktype"', 'data-item-linktype="' + j + '"') +
                        '</div>' +
                        '<div class="mt-2">' +
                        (linkType === 'none' ? '' :
                         (linkType === 'custom'
                            ? '<input type="url" class="form-control form-control-sm hb-item-link-url" value="' + esc(it.link_url) + '" placeholder="https://…">'
                            : '<select class="form-select form-select-sm hb-item-link-entity" data-entity="' + linkType + '"></select>')) +
                        '</div></div></div></div></div>';
                }).join('');

                const rowStylePreset = '<select class="form-select form-select-sm hb-row-config-preset" data-row-idx="' + rIdx + '" data-config-key="style">' +
                    '<option value="grid"' + (rCfg.style === 'grid' ? ' selected' : '') + '>Grid Layout</option>' +
                    '<option value="scroll"' + (rCfg.style === 'scroll' ? ' selected' : '') + '>Scroll/Slider Layout</option>' +
                    '</select>';

                const containerWidthSelect = '<select class="form-select form-select-sm hb-row-config-select" data-row-idx="' + rIdx + '" data-config-key="container_width">' +
                    '<option value="container"' + (rCfg.container_width === 'container' ? ' selected' : '') + '>Default Container</option>' +
                    '<option value="fluid"' + (rCfg.container_width === 'fluid' ? ' selected' : '') + '>Fluid Width</option>' +
                    '<option value="full"' + (rCfg.container_width === 'full' ? ' selected' : '') + '>Full Width</option>' +
                    '</select>';

                const hoverSelect = '<select class="form-select form-select-sm hb-row-config-select" data-row-idx="' + rIdx + '" data-config-key="hover_effect">' +
                    '<option value="none"' + (rCfg.hover_effect === 'none' ? ' selected' : '') + '>None</option>' +
                    '<option value="zoom"' + (rCfg.hover_effect === 'zoom' ? ' selected' : '') + '>Zoom image</option>' +
                    '<option value="lift"' + (rCfg.hover_effect === 'lift' ? ' selected' : '') + '>Lift card</option>' +
                    '<option value="opacity"' + (rCfg.hover_effect === 'opacity' ? ' selected' : '') + '>Highlight overlay</option>' +
                    '</select>';

                const backgroundTypeSelect = '<select class="form-select form-select-sm hb-row-config-select" data-row-idx="' + rIdx + '" data-config-key="background_type">' +
                    '<option value="none"' + ((rCfg.background_type || 'none') === 'none' ? ' selected' : '') + '>None</option>' +
                    '<option value="color"' + (rCfg.background_type === 'color' ? ' selected' : '') + '>Color</option>' +
                    '<option value="image"' + (rCfg.background_type === 'image' ? ' selected' : '') + '>Image</option>' +
                    '</select>';

                const rowBackgroundPreview = (s._rowBackgroundPreview && s._rowBackgroundPreview[rIdx]) || rCfg.background_image;
                const backgroundControl = rCfg.background_type === 'color'
                    ? '<input type="color" class="form-control form-control-color hb-row-background-color" data-row-idx="' + rIdx + '" value="' + esc(rCfg.background_color || '#ffffff') + '">'
                    : (rCfg.background_type === 'image' ? rowBackgroundSlot(rIdx, rowBackgroundPreview) : '<span class="text-muted small">No background</span>');

                const paddingInputsHtml = paddingSides(rCfg).map(padding =>
                    '<div class="col-6 col-md-3"><label class="form-label text-muted mb-1">' + padding.side.charAt(0).toUpperCase() + padding.side.slice(1) + '</label>' +
                    '<div class="input-group input-group-sm"><input type="number" class="form-control hb-row-padding-input" data-row-idx="' + rIdx + '" data-padding-side="' + padding.side + '" value="' + padding.value + '">' +
                    '<span class="input-group-text">px</span></div></div>'
                ).join('');

                let scrollConfigsHtml = '';
                if (rCfg.style === 'scroll') {
                    const scrollButtonsConfigHtml = PLATFORM === 'app' ? '' :
                        '<div class="col-md-6">' +
                        '<label class="form-check form-switch mt-2">' +
                        '<input class="form-check-input hb-row-config-switch" type="checkbox" data-row-idx="' + rIdx + '" data-config-key="show_scroll_buttons"' + (parseBool(rCfg.show_scroll_buttons, true) ? ' checked' : '') + '>' +
                        '<span class="form-check-label small text-muted">Scroll buttons</span>' +
                        '</label>' +
                        '</div>';
                    scrollConfigsHtml = '<div class="row g-2 mb-2">' +
                        '<div class="col-12">' +
                        '<label class="form-label small text-muted mb-1 d-flex justify-content-between"><span>Desktop visible items</span><span id="hb-row-items-val-' + rIdx + '" class="hb-range-value">' + rCfg.items_to_show + '</span></label>' +
                        '<input type="range" class="form-range form-range-sm hb-row-config-range" data-row-idx="' + rIdx + '" data-config-key="items_to_show" min="1" max="10" step="0.1" value="' + rCfg.items_to_show + '">' +
                        '</div>' +
                        '<div class="col-12">' +
                        '<label class="form-label small text-muted mb-1 d-flex justify-content-between"><span>Mobile visible items</span><span id="hb-row-items-mobile-val-' + rIdx + '" class="hb-range-value">' + rCfg.items_to_show_mobile + '</span></label>' +
                        '<input type="range" class="form-range form-range-sm hb-row-config-range" data-row-idx="' + rIdx + '" data-config-key="items_to_show_mobile" min="1" max="5" step="0.1" value="' + rCfg.items_to_show_mobile + '">' +
                        '</div></div>' +
                        '<div class="row g-2 mb-2">' +
                        scrollButtonsConfigHtml +
                        '<div class="' + (PLATFORM === 'app' ? 'col-12' : 'col-md-6') + '">' +
                        '<label class="form-check form-switch mt-2">' +
                        '<input class="form-check-input hb-row-config-switch" type="checkbox" data-row-idx="' + rIdx + '" data-config-key="show_bullets"' + (parseBool(rCfg.show_bullets, true) ? ' checked' : '') + '>' +
                        '<span class="form-check-label small text-muted">Bullets</span>' +
                        '</label>' +
                        '</div>' +
                        '<div class="col-md-12">' +
                        '<label class="form-check form-switch mt-2">' +
                        '<input class="form-check-input hb-row-config-switch" type="checkbox" data-row-idx="' + rIdx + '" data-config-key="auto_scroll"' + (parseBool(rCfg.auto_scroll, false) ? ' checked' : '') + '>' +
                        '<span class="form-check-label small text-muted">Auto scroll</span>' +
                        '</label>' +
                        '</div>' +
                        '</div>' +
                        '<div class="mb-2" id="hb-row-scroll-interval-container-' + rIdx + '" style="' + (parseBool(rCfg.auto_scroll, false) ? '' : 'display: none;') + '">' +
                        '<label class="form-label small text-muted mb-1">Auto scroll delay (Seconds)</label>' +
                        '<input type="number" class="form-control form-control-sm hb-row-config-number" data-row-idx="' + rIdx + '" data-config-key="auto_scroll_time" min="1" max="30" value="' + (rCfg.auto_scroll_time || 3) + '">' +
                        '</div>';
                }

                const configFormHtml = '<div class="card card-sm mb-3 hb-row-settings-card">' +
                    '<div class="card-body p-2">' +
                    '<div class="row g-2 mb-2">' +
                    '<div class="col-12">' +
                    '<label class="form-label small text-muted mb-1">Layout Style</label>' +
                    rowStylePreset +
                    '</div>' +
                    '<div class="col-12">' +
                    '<label class="form-label small text-muted mb-1">Container Width</label>' +
                    containerWidthSelect +
                    '</div>' +
                    '<div class="col-12">' +
                    '<label class="form-label small text-muted mb-1">Hover Effect</label>' +
                    hoverSelect +
                    '</div></div>' +
                    '<div class="row g-2 mb-3 align-items-end">' +
                    '<div class="col-12"><label class="form-label small text-muted mb-1">Row Background</label>' + backgroundTypeSelect + '</div>' +
                    '<div class="col-12"><label class="form-label small text-muted mb-1">Background Value</label><div class="hb-row-background-control">' + backgroundControl + '</div></div>' +
                    '</div>' +
                    '<div class="row g-2 mb-2">' +
                    '<div class="col-12">' +
                    '<label class="form-label small text-muted mb-1 d-flex justify-content-between"><span>Outer Spacing</span><span id="hb-row-range-outer_padding-val-' + rIdx + '" class="hb-range-value"><strong>' + (rCfg.outer_padding_custom ? 'Custom' : rCfg.outer_padding) + '</strong>' + (rCfg.outer_padding_custom ? '' : '<small>px</small>') + '</span></label>' +
                    '<input type="range" class="form-range form-range-sm hb-row-config-range" data-row-idx="' + rIdx + '" data-config-key="outer_padding" min="-100" max="100" value="' + rCfg.outer_padding + '">' +
                    '<div class="form-hint">Positive values add space inside. Negative values pull the row outward.</div>' +
                    '</div></div>' +
                    '<div class="row g-2 mb-3 hb-padding-sides">' + paddingInputsHtml + '</div>' +
                    '<div class="row g-2 mb-2">' +
                    '<div class="col-12">' +
                    '<label class="form-label small text-muted mb-1 d-flex justify-content-between"><span>Inner Spacing</span><span id="hb-row-range-grid_gap-val-' + rIdx + '" class="hb-range-value"><strong>' + rCfg.grid_gap + '</strong><small>px</small></span></label>' +
                    '<input type="range" class="form-range form-range-sm hb-row-config-range" data-row-idx="' + rIdx + '" data-config-key="grid_gap" min="-100" max="100" value="' + rCfg.grid_gap + '">' +
                    '<div class="form-hint">Negative values overlap neighbouring items.</div>' +
                    '</div>' +
                    '<div class="col-12">' +
                    '<label class="form-label small text-muted mb-1 d-flex justify-content-between"><span>Border Radius</span><span id="hb-row-range-border_radius-val-' + rIdx + '" class="hb-range-value"><strong>' + rCfg.border_radius + '</strong><small>px</small></span></label>' +
                    '<input type="range" class="form-range form-range-sm hb-row-config-range" data-row-idx="' + rIdx + '" data-config-key="border_radius" min="0" max="50" value="' + rCfg.border_radius + '">' +
                    '</div></div>' +
                    scrollConfigsHtml +
                    '</div></div>';

                const rowKey = rCfg.media_key || ('row-' + rIdx);
                const rowDisclosure = disclosureInfo(s, 'row', rowKey, true);
                const rowCollapseId = 'hb-row-' + sectionUiKey(s) + '-' + rowKey;
                const isFocusedRow = focusedPlayRow && focusedPlayRow.sectionKey === sectionUiKey(s) && focusedPlayRow.rowIndex === rIdx;

                return '<div class="hb-row-panel' + (isFocusedRow ? ' is-focused' : '') + '" data-row-idx="' + rIdx + '">' +
                    '<div class="hb-row-panel-head hb-disclosure-trigger" data-bs-toggle="collapse" data-bs-target="#' + rowCollapseId + '" data-disclosure-key="' + rowDisclosure.key + '" aria-expanded="' + rowDisclosure.expanded + '">' +
                    '<div><strong><i class="ti ti-chevron-right me-2 hb-disclosure-chevron"></i>Row ' + (rIdx + 1) + '</strong><span class="hb-row-summary">' + rowItems.length + ' item' + (rowItems.length === 1 ? '' : 's') + ' · ' + (rCfg.style === 'scroll' ? 'Slider' : 'Grid') + '</span></div>' +
                    (styleOnly ? '' : '<button type="button" class="btn btn-xs btn-ghost-danger hb-remove-row-btn" data-row-idx="' + rIdx + '" title="Delete row and all its items"><i class="ti ti-trash"></i> Delete Row</button>') +
                    '</div>' +
                    '<div id="' + rowCollapseId + '" class="collapse' + rowDisclosure.show + '" data-disclosure-key="' + rowDisclosure.key + '"><div class="hb-row-panel-body">' +
                    (styleOnly
                        ? configFormHtml
                        : '<div class="hb-items-stack">' + itemsHtml + '</div>' +
                          '<button type="button" class="btn btn-sm btn-outline-primary w-100 hb-add-item-to-row" data-row-idx="' + rIdx + '"><i class="ti ti-plus me-1"></i>Add item</button>') +
                    '</div></div></div>';
            }).join('');

            return fieldTpl(styleOnly ? 'Row styling' : 'Items',
                '<div class="hb-play-row-list">' + rowsHtml + '</div>' +
                (styleOnly ? '' : '<div class="hb-add-row-wrap"><button type="button" class="btn btn-sm btn-outline-primary w-100 hb-add-row"><i class="ti ti-plus me-1"></i>Add row</button></div>'));
        }

        /* ---------- editor wiring ---------- */
        function wireEditor(s) {
            editorEl.querySelectorAll('.hb-disclosure-trigger').forEach(trigger => trigger.addEventListener('click', () => {
                const target = editorEl.querySelector(trigger.dataset.bsTarget);
                disclosureState.set(trigger.dataset.disclosureKey, !(target && target.classList.contains('show')));
            }));
            editorEl.querySelectorAll('.collapse[data-disclosure-key]').forEach(collapse => {
                collapse.addEventListener('shown.bs.collapse', () => disclosureState.set(collapse.dataset.disclosureKey, true));
                collapse.addEventListener('hidden.bs.collapse', () => disclosureState.set(collapse.dataset.disclosureKey, false));
            });

            editorEl.querySelectorAll('[data-editor-tab]').forEach(tab => tab.addEventListener('click', () => {
                editorTab = tab.dataset.editorTab;
                renderEditor({skipPreview: true, skipHistory: true});
            }));

            const sectionEnabled = editorEl.querySelector('#hb-section-enabled');
            if (sectionEnabled) sectionEnabled.addEventListener('change', () => {
                s.is_active = sectionEnabled.checked;
                renderSections();
                renderEditor();
            });

            // Undo/Redo listeners
            const undoBtn = editorEl.querySelector('#hb-undo-btn');
            if (undoBtn) undoBtn.addEventListener('click', () => undo());

            const redoBtn = editorEl.querySelector('#hb-redo-btn');
            if (redoBtn) redoBtn.addEventListener('click', () => redo());

            // Copy/Paste design listeners
            const copyBtn = editorEl.querySelector('#hb-copy-design-btn');
            if (copyBtn) {
                copyBtn.addEventListener('click', () => {
                    const serialized = JSON.stringify(s);
                    navigator.clipboard.writeText(serialized)
                        .then(() => Toast.fire({
                            icon: 'success',
                            title: 'Section design copied to clipboard!'
                        }))
                        .catch(() => Toast.fire({
                            icon: 'error',
                            title: 'Failed to copy section design'
                        }));
                });
            }



            editorEl.querySelectorAll('[data-seg]').forEach(g => {
                const fld = g.dataset.seg;
                g.querySelectorAll('input.form-selectgroup-input').forEach(inp => inp.addEventListener('change', () => {
                    if (!inp.checked) return;
                    if (fld === 'item_source') {
                        const previousSource = s.config.item_source || 'categories';
                        s._heroItemsBySource = s._heroItemsBySource || {};
                        s._heroItemsBySource[previousSource] = s.items;
                        s.config.item_source = inp.value;
                        s.items = s._heroItemsBySource[inp.value] || [];
                        s._heroItemsBySource[inp.value] = s.items;
                    } else if (fld in s) {
                        s[fld] = inp.value;
                    } else {
                        s.config[fld] = inp.value;
                    }
                    if (fld === 'source' || fld === 'item_source') scheduleMarketCatalogRefresh();
                    renderSections();
                    renderEditor();
                }));
            });
            editorEl.querySelectorAll('[data-item-mediatype]').forEach(g => {
                const j = +g.dataset.itemMediatype;
                g.querySelectorAll('input.form-selectgroup-input').forEach(inp => inp.addEventListener('change', () => {
                    if (!inp.checked) return;
                    s.items[j].config.media_type = inp.value;
                    renderSections();
                    renderEditor();
                }));
            });
            editorEl.querySelectorAll('[data-preset]').forEach(g => {
                const fld = g.dataset.preset;
                g.querySelectorAll('.hb-preset-card').forEach(c => c.addEventListener('click', () => {
                    if (fld in s) s[fld] = c.dataset.val; else s.config[fld] = c.dataset.val;
                    renderSections();
                    renderEditor();
                }));
            });
            bindInput('#hb-title', v => {
                s.title = v;
                updateRowTitle();
            });
            const showTitle = editorEl.querySelector('#hb-show-title');
            if (showTitle) showTitle.addEventListener('change', () => {
                s.show_title = showTitle.checked;
                renderPreview();
            });
            bindInput('#hb-cardcolor', v => {
                s.config.card_bg_color = v;
            });
            bindInput('#hb-cardtitlecolor', v => {
                s.config.card_title_color = v;
            });
            bindInput('#hb-bgcolor', v => {
                s.config.background_color = v;
            });

            const del = editorEl.querySelector('#hb-del-section');
            if (del) del.addEventListener('click', () => ConfirmModal.open(L('removeConfirm', 'Remove this section?'), () => {
                state.sections.splice(sel, 1);
                sel = Math.min(sel, state.sections.length - 1);
                renderSections();
                renderEditor();
            }));

            editorEl.querySelectorAll('.hb-add-item').forEach(b => b.addEventListener('click', () => {
                if (s.type === 'hero' && s.items.length >= 6) return;
                s.items.push({
                    id: null,
                    purpose: 'display',
                    itemable_type: s.type === 'hero' ? SRC_TO_KEY[s.config.item_source] : null,
                    itemable_id: null,
                    itemable_label: null,
                    title: '',
                    link_url: '',
                    image: null,
                    mobile_image: null
                });
                renderEditor();
            }));
            editorEl.querySelectorAll('.hb-item-remove').forEach(b => b.addEventListener('click', event => {
                event.stopPropagation();
                const j = +b.closest('[data-j]').dataset.j;
                const removedProduct = s.items[j] && s.items[j].itemable_type === 'product';
                s.items.splice(j, 1);
                if (removedProduct) scheduleMarketCatalogRefresh();
                renderEditor();
            }));
            editorEl.querySelectorAll('.hb-item-title').forEach(inp => inp.addEventListener('input', () => {
                const j = +inp.closest('[data-j]').dataset.j;
                s.items[j].title = inp.value;
            }));
            editorEl.querySelectorAll('.hb-item-link-url').forEach(inp => inp.addEventListener('input', () => {
                const j = +inp.closest('[data-j]').dataset.j;
                s.items[j].link_url = inp.value;
                s.items[j].itemable_type = null;
                s.items[j].itemable_id = null;
            }));
            editorEl.querySelectorAll('[data-item-linktype]').forEach(g => g.querySelectorAll('input.form-selectgroup-input').forEach(inp => inp.addEventListener('change', () => {
                if (!inp.checked) return;
                const j = +g.dataset.itemLinktype;
                const it = s.items[j];
                it.link_type = inp.value;
                if (inp.value === 'none') {
                    it.itemable_type = null;
                    it.itemable_id = null;
                    it.link_url = '';
                } else if (inp.value === 'custom') {
                    it.itemable_type = null;
                    it.itemable_id = null;
                } else {
                    it.itemable_type = inp.value;
                    it.link_url = '';
                }
                renderEditor();
            })));
            editorEl.querySelectorAll('.hb-img-slot').forEach(btn => btn.addEventListener('click', () => {
                openImage(s, {
                    kind: btn.dataset.targetKind,
                    j: btn.dataset.j != null ? +btn.dataset.j : null,
                    r: btn.dataset.rowIdx != null ? +btn.dataset.rowIdx : null
                });
            }));

            editorEl.querySelectorAll('.hb-use-module-image').forEach(button => button.addEventListener('click', () => {
                const j = +button.dataset.j;
                const item = s.items[j];
                item.image = null;
                item._imageFile = null;
                item._imagePreview = null;
                item._removeImage = true;
                renderEditor();
            }));

            editorEl.querySelectorAll('.hb-row-background-color').forEach(input => input.addEventListener('input', () => {
                const rIdx = +input.dataset.rowIdx;
                s.config.rows[rIdx].background_color = input.value;
                const rowEl = editorEl.querySelector('.hb-preview-row[data-row-idx="' + rIdx + '"]');
                const container = rowEl && (rowEl.querySelector('.hb-preview-row-scroll-container') || rowEl.querySelector('.hb-preview-grid-container'));
                if (container) container.style.backgroundColor = input.value;
            }));
            editorEl.querySelectorAll('.hb-row-background-color').forEach(input => input.addEventListener('change', recordStateChange));

            // play_image grid item config handlers
            editorEl.querySelectorAll('.hb-grid-cols').forEach(selEl => selEl.addEventListener('change', () => {
                const j = +selEl.closest('[data-j]').dataset.j;
                s.items[j].config = s.items[j].config || {};
                s.items[j].config.cols = selEl.value;
                renderEditor();
            }));
            editorEl.querySelectorAll('.hb-grid-cols-mobile').forEach(selEl => selEl.addEventListener('change', () => {
                const j = +selEl.closest('[data-j]').dataset.j;
                s.items[j].config = s.items[j].config || {};
                s.items[j].config.cols_mobile = selEl.value;
                renderEditor();
            }));
            editorEl.querySelectorAll('.hb-grid-aspect').forEach(selEl => selEl.addEventListener('change', () => {
                const j = +selEl.closest('[data-j]').dataset.j;
                s.items[j].config = s.items[j].config || {};
                s.items[j].config.aspect_ratio = selEl.value;
                if (selEl.value === 'custom') {
                    if (!s.items[j].config.aspect_width_unit && !s.items[j].config.aspect_height_unit) {
                        s.items[j].config.aspect_width = 100;
                        s.items[j].config.aspect_height = 300;
                    }
                    s.items[j].config.aspect_width_unit = s.items[j].config.aspect_width_unit || '%';
                    s.items[j].config.aspect_height_unit = s.items[j].config.aspect_height_unit || 'px';
                }
                renderEditor();
            }));
            editorEl.querySelectorAll('.hb-grid-aspect-width, .hb-grid-aspect-height').forEach(inp => {
                const key = inp.classList.contains('hb-grid-aspect-width') ? 'aspect_width' : 'aspect_height';
                inp.addEventListener('input', () => {
                    const j = +inp.closest('[data-j]').dataset.j;
                    s.items[j].config = s.items[j].config || {};
                    s.items[j].config[key] = Math.max(.1, Math.min(10000, Number(inp.value) || 1));
                });
                inp.addEventListener('change', recordStateChange);
            });
            editorEl.querySelectorAll('.hb-grid-aspect-unit').forEach(selEl => selEl.addEventListener('change', () => {
                const j = +selEl.closest('[data-j]').dataset.j;
                s.items[j].config = s.items[j].config || {};
                s.items[j].config[selEl.dataset.sizeKey] = selEl.value;
                recordStateChange();
            }));
            editorEl.querySelectorAll('.hb-grid-object-fit').forEach(selEl => selEl.addEventListener('change', () => {
                const j = +selEl.closest('[data-j]').dataset.j;
                s.items[j].config = s.items[j].config || {};
                s.items[j].config.object_fit = selEl.value;
                recordStateChange();
            }));
            editorEl.querySelectorAll('.hb-grid-object-position').forEach(selEl => selEl.addEventListener('change', () => {
                const j = +selEl.closest('[data-j]').dataset.j;
                s.items[j].config = s.items[j].config || {};
                s.items[j].config.object_position = selEl.value;
                recordStateChange();
            }));
            editorEl.querySelectorAll('.hb-grid-alt').forEach(inp => inp.addEventListener('input', () => {
                const j = +inp.closest('[data-j]').dataset.j;
                s.items[j].config = s.items[j].config || {};
                s.items[j].config.alt = inp.value;
            }));
            editorEl.querySelectorAll('.hb-grid-lottie-width').forEach(inp => inp.addEventListener('input', () => {
                const j = +inp.closest('[data-j]').dataset.j;
                s.items[j].config = s.items[j].config || {};
                s.items[j].config.lottie_width = inp.value;
            }));
            editorEl.querySelectorAll('.hb-grid-lottie-height').forEach(inp => inp.addEventListener('input', () => {
                const j = +inp.closest('[data-j]').dataset.j;
                s.items[j].config = s.items[j].config || {};
                s.items[j].config.lottie_height = inp.value;
            }));

            editorEl.querySelectorAll('.hb-config-text').forEach(inp => {
                const key = inp.dataset.configKey;
                inp.addEventListener('input', () => {
                    s.config[key] = inp.value;
                    const val = inp.value;
                    let padding = (s.config.outer_padding !== undefined ? s.config.outer_padding : 0) + 'px';
                    if (val) {
                        padding = val.split('/').map(v => v.trim() + (v.trim().endsWith('px') || v.trim().endsWith('%') ? '' : 'px')).join(' ');
                    }
                    editorEl.querySelectorAll('.hb-preview-grid-container, .hb-field > div.position-relative').forEach(previewGrid => {
                        previewGrid.style.padding = padding;
                    });
                });
                inp.addEventListener('change', recordStateChange);
            });
            // play_image grid config range sliders (padding, gap, radius)
            editorEl.querySelectorAll('.hb-config-range').forEach(el => {
                const key = el.dataset.configKey;
                el.addEventListener('input', () => {
                    const val = parseInt(el.value, 10);
                    s.config[key] = val;
                    const valBadge = editorEl.querySelector('#hb-range-' + key + '-val');
                    if (valBadge) valBadge.textContent = val + 'px';

                    if (key === 'outer_padding') {
                        if (!s.config.outer_padding_custom) {
                            editorEl.querySelectorAll('.hb-preview-grid-container, .hb-field > div.position-relative').forEach(pGrid => {
                                pGrid.style.padding = val + 'px';
                            });
                        }
                    } else if (key === 'grid_gap') {
                        editorEl.querySelectorAll('.hb-preview-grid-container, #hb-scroll-preview').forEach(gGrid => {
                            gGrid.style.gap = val + 'px';
                        });
                    } else if (key === 'border_radius') {
                        const radiusVal = val + 'px';
                        editorEl.querySelectorAll('.hb-preview-card img, .hb-preview-card .bg-light').forEach(cardEl => {
                            cardEl.style.borderRadius = radiusVal;
                        });
                    }
                });
                el.addEventListener('change', () => {
                    recordStateChange();
                });
            });

            // play_image preview mode toggler
            editorEl.querySelectorAll('.hb-preview-toggle-group button').forEach(btn => btn.addEventListener('click', event => {
                event.stopPropagation();
                s.preview_mode = btn.dataset.mode;
                renderEditor({skipPreview: true, skipHistory: true});
            }));

            // play_image row and item manipulation
            editorEl.querySelectorAll('.hb-add-row').forEach(btn => btn.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                let maxRowIdx = Array.isArray(s.config.rows) ? s.config.rows.length - 1 : -1;
                s.items.forEach(it => {
                    const rIdx = it.config.row_index !== undefined ? parseInt(it.config.row_index, 10) : 0;
                    if (rIdx > maxRowIdx) maxRowIdx = rIdx;
                });
                const newRowIdx = maxRowIdx + 1;
                s.config.rows = s.config.rows || [];
                s.config.rows[newRowIdx] = defaultRowConfig();
                s.items.push({
                    title: '',
                    image: '',
                    mobile_image: '',
                    link_type: 'none',
                    link_url: '',
                    itemable_type: null,
                    itemable_id: null,
                    sort_order: s.items.length,
                    config: {
                        row_index: newRowIdx,
                        cols: 'auto',
                        cols_mobile: 'auto',
                        aspect_ratio: 'original',
                        aspect_width: 100,
                        aspect_width_unit: '%',
                        aspect_height: 300,
                        aspect_height_unit: 'px',
                        object_fit: 'cover',
                        object_position: 'center',
                        alt: ''
                    }
                });
                const newRowConfig = s.config.rows[newRowIdx];
                const newRowKey = newRowConfig.media_key || ('row-' + newRowIdx);
                disclosureState.set(sectionUiKey(s) + ':row:' + newRowKey, true);
                focusedPlayRow = {sectionKey: sectionUiKey(s), rowIndex: newRowIdx};
                renderEditor();
            }));

            editorEl.querySelectorAll('.hb-add-item-to-row').forEach(btn => btn.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                const rIdx = parseInt(btn.dataset.rowIdx, 10);
                s.items.push({
                    title: '',
                    image: '',
                    mobile_image: '',
                    link_type: 'none',
                    link_url: '',
                    itemable_type: null,
                    itemable_id: null,
                    sort_order: s.items.length,
                    config: {
                        row_index: rIdx,
                        cols: 'auto',
                        cols_mobile: 'auto',
                        aspect_ratio: 'original',
                        aspect_width: 100,
                        aspect_width_unit: '%',
                        aspect_height: 300,
                        aspect_height_unit: 'px',
                        object_fit: 'cover',
                        object_position: 'center',
                        alt: ''
                    }
                });
                const rowConfig = (s.config.rows && s.config.rows[rIdx]) || defaultRowConfig();
                const rowKey = rowConfig.media_key || ('row-' + rIdx);
                disclosureState.set(sectionUiKey(s) + ':row:' + rowKey, true);
                focusedPlayRow = {sectionKey: sectionUiKey(s), rowIndex: rIdx};
                renderEditor();
            }));

            editorEl.querySelectorAll('.hb-remove-row-btn').forEach(btn => btn.addEventListener('click', event => {
                event.stopPropagation();
                const rIdx = parseInt(btn.dataset.rowIdx, 10);
                ConfirmModal.open('Delete Row #' + (rIdx + 1) + ' and all of its items?', () => {
                    s.items = s.items.filter(it => {
                        const itemRowIdx = it.config.row_index !== undefined ? parseInt(it.config.row_index, 10) : 0;
                        return itemRowIdx !== rIdx;
                    });
                    s.items.forEach(it => {
                        const itemRowIdx = it.config.row_index !== undefined ? parseInt(it.config.row_index, 10) : 0;
                        if (itemRowIdx > rIdx) {
                            it.config.row_index = itemRowIdx - 1;
                        }
                    });
                    if (Array.isArray(s.config.rows)) s.config.rows.splice(rIdx, 1);
                    if (s._rowBackgroundFiles) {
                        const files = s._rowBackgroundFiles;
                        s._rowBackgroundFiles = {};
                        Object.keys(files).forEach(key => {
                            const index = Number(key);
                            if (index < rIdx) s._rowBackgroundFiles[index] = files[index];
                            if (index > rIdx) s._rowBackgroundFiles[index - 1] = files[index];
                        });
                    }
                    if (s._rowBackgroundPreview) {
                        const previews = s._rowBackgroundPreview;
                        s._rowBackgroundPreview = {};
                        Object.keys(previews).forEach(key => {
                            const index = Number(key);
                            if (index < rIdx) s._rowBackgroundPreview[index] = previews[index];
                            if (index > rIdx) s._rowBackgroundPreview[index - 1] = previews[index];
                        });
                    }
                    renderEditor();
                });
            }));

            // Sortable preview grid (single unified grid container)
            // Sortable preview grids inside each row container
            editorEl.querySelectorAll('.hb-preview-grid-container').forEach(previewGrid => {
                if (cfg.canEdit && window.Sortable) {
                    createPreviewSortable(previewGrid, {
                        group: 'play-image-cards',
                        animation: 150,
                        ghostClass: 'hb-ghost',
                        onEnd: function () {
                            const rows = [];
                            editorEl.querySelectorAll('.hb-preview-row').forEach((rowEl, rIdx) => {
                                const rowItems = [];
                                rowEl.querySelectorAll('.hb-preview-card').forEach(cardEl => {
                                    const origIdx = parseInt(cardEl.dataset.origIdx, 10);
                                    rowItems.push(s.items[origIdx]);
                                });
                                rows.push(rowItems);
                            });
                            s.items = rebuildItemsFromRows(rows);
                            renderEditor();
                        }
                    }, Boolean(previewGrid.closest('.hb-preview-row-scroll-container')));
                }
            });

            const editorPreviewRows = editorEl.querySelector('.hb-editor-preview-rows');
            if (editorPreviewRows && cfg.canEdit && window.Sortable) {
                window.Sortable.create(editorPreviewRows, {
                    handle: '.hb-editor-preview-row-handle',
                    animation: 150,
                    ghostClass: 'hb-ghost',
                    onEnd: () => {
                        const order = Array.from(editorPreviewRows.children).map(row => Number(row.dataset.rowIdx));
                        applyPlayImageRowOrder(s, order);
                        recordStateChange();
                        renderSections();
                        renderEditor();
                    }
                });
            }

            // Row config style presets and hover selects
            editorEl.querySelectorAll('.hb-row-config-preset, .hb-row-config-select').forEach(el => {
                const rIdx = +el.dataset.rowIdx;
                const key = el.dataset.configKey;
                el.addEventListener('change', () => {
                    s.config.rows = s.config.rows || {};
                    s.config.rows[rIdx] = s.config.rows[rIdx] || {};
                    s.config.rows[rIdx][key] = el.value;
                    renderEditor();
                });
            });

            // Row config range sliders (padding, gap, radius, itemsToShow)
            editorEl.querySelectorAll('.hb-row-config-range').forEach(el => {
                const rIdx = +el.dataset.rowIdx;
                const key = el.dataset.configKey;
                el.addEventListener('input', () => {
                    const val = parseFloat(el.value);
                    s.config.rows = s.config.rows || {};
                    s.config.rows[rIdx] = s.config.rows[rIdx] || {};
                    s.config.rows[rIdx][key] = val;

                    let badgeId = '';
                    if (key === 'outer_padding' || key === 'grid_gap' || key === 'border_radius') {
                        badgeId = '#hb-row-range-' + key + '-val-' + rIdx;
                        const badge = editorEl.querySelector(badgeId);
                        if (badge) badge.innerHTML = '<strong>' + val + '</strong><small>px</small>';
                    } else if (key === 'items_to_show') {
                        badgeId = '#hb-row-items-val-' + rIdx;
                        const badge = editorEl.querySelector(badgeId);
                        if (badge) badge.textContent = val;
                    } else if (key === 'items_to_show_mobile') {
                        badgeId = '#hb-row-items-mobile-val-' + rIdx;
                        const badge = editorEl.querySelector(badgeId);
                        if (badge) badge.textContent = val;
                    }

                    if (key === 'outer_padding') {
                        s.config.rows[rIdx].outer_padding_custom = '';
                        const rowPanel = el.closest('.hb-row-panel');
                        if (rowPanel) {
                            rowPanel.querySelectorAll('.hb-row-padding-input').forEach(sideInput => {
                                sideInput.value = val;
                            });
                        }
                    }

                    // Live preview styling updates for row elements
                    const rowEl = editorEl.querySelector('.hb-preview-row[data-row-idx="' + rIdx + '"]');
                    if (rowEl) {
                        const previewGrid = rowEl.querySelector('.hb-preview-grid-container');
                        const paddingContainer = rowEl.querySelector('.hb-preview-row-scroll-container') || previewGrid;
                        if (key === 'outer_padding') {
                            const spacing = rowBoxSpacing(s.config.rows[rIdx]);
                            paddingContainer.style.padding = spacing.padding;
                            rowEl.style.margin = spacing.margin;
                        } else if (key === 'grid_gap') {
                            previewGrid.style.gap = Math.max(0, val) + 'px';
                            previewGrid.style.setProperty('--hb-negative-gap', Math.min(0, val) + 'px');
                        } else if (key === 'border_radius') {
                            paddingContainer.querySelectorAll('.hb-preview-card img, .hb-preview-card .bg-light').forEach(cardEl => {
                                cardEl.style.borderRadius = val + 'px';
                            });
                        }
                    }
                });
                el.addEventListener('change', recordStateChange);
            });

            // Row config custom padding text inputs
            editorEl.querySelectorAll('.hb-row-config-text').forEach(inp => {
                const rIdx = +inp.dataset.rowIdx;
                const key = inp.dataset.configKey;
                inp.addEventListener('input', () => {
                    s.config.rows = s.config.rows || {};
                    s.config.rows[rIdx] = s.config.rows[rIdx] || {};
                    s.config.rows[rIdx][key] = inp.value;

                    const rowEl = editorEl.querySelector('.hb-preview-row[data-row-idx="' + rIdx + '"]');
                    if (rowEl) {
                        const previewGrid = rowEl.querySelector('.hb-preview-grid-container');
                        const paddingContainer = rowEl.querySelector('.hb-preview-row-scroll-container') || previewGrid;
                        const spacing = rowBoxSpacing(s.config.rows[rIdx]);
                        paddingContainer.style.padding = spacing.padding;
                        rowEl.style.margin = spacing.margin;
                    }
                });
                inp.addEventListener('change', recordStateChange);
            });

            editorEl.querySelectorAll('.hb-row-padding-input').forEach(input => input.addEventListener('input', () => {
                const rIdx = +input.dataset.rowIdx;
                const rowEditor = input.closest('.hb-row-panel');
                const values = ['top', 'right', 'bottom', 'left'].map(side => {
                    const sideInput = rowEditor.querySelector('[data-padding-side="' + side + '"]');
                    return Number(sideInput.value) || 0;
                });
                const uniform = values.every(value => value === values[0]);
                const paddingSummary = uniform
                    ? values[0]
                    : values.reduce((current, value) => Math.abs(value) > Math.abs(current) ? value : current, 0);
                s.config.rows[rIdx].outer_padding = paddingSummary;
                s.config.rows[rIdx].outer_padding_custom = uniform ? '' : values.join('/');

                const paddingRange = rowEditor.querySelector('.hb-row-config-range[data-config-key="outer_padding"]');
                if (paddingRange) paddingRange.value = paddingSummary;
                const paddingBadge = editorEl.querySelector('#hb-row-range-outer_padding-val-' + rIdx);
                if (paddingBadge) paddingBadge.innerHTML = uniform
                    ? '<strong>' + paddingSummary + '</strong><small>px</small>'
                    : '<strong>Custom</strong>';

                const rowEl = editorEl.querySelector('.hb-preview-row[data-row-idx="' + rIdx + '"]');
                const previewGrid = rowEl && rowEl.querySelector('.hb-preview-grid-container');
                const paddingContainer = rowEl && (rowEl.querySelector('.hb-preview-row-scroll-container') || previewGrid);
                if (paddingContainer) {
                    const spacing = rowBoxSpacing(s.config.rows[rIdx]);
                    paddingContainer.style.padding = spacing.padding;
                    rowEl.style.margin = spacing.margin;
                }
            }));
            editorEl.querySelectorAll('.hb-row-padding-input').forEach(input => input.addEventListener('change', recordStateChange));

            // Row config switches
            editorEl.querySelectorAll('.hb-row-config-switch').forEach(el => {
                const rIdx = +el.dataset.rowIdx;
                const key = el.dataset.configKey;
                el.addEventListener('change', () => {
                    s.config.rows = s.config.rows || {};
                    s.config.rows[rIdx] = s.config.rows[rIdx] || {};
                    s.config.rows[rIdx][key] = el.checked ? 1 : 0;
                    renderEditor();
                });
            });

            // Row config auto scroll delays
            editorEl.querySelectorAll('.hb-row-config-number').forEach(el => {
                const rIdx = +el.dataset.rowIdx;
                const key = el.dataset.configKey;
                el.addEventListener('input', () => {
                    const val = parseInt(el.value, 10);
                    s.config.rows = s.config.rows || {};
                    s.config.rows[rIdx] = s.config.rows[rIdx] || {};
                    s.config.rows[rIdx][key] = val || 3;
                });
                el.addEventListener('change', recordStateChange);
            });

            initEditorSelects(s);
        }

        function bindInput(selector, cb) {
            const el = editorEl.querySelector(selector);
            if (el) {
                el.addEventListener('input', () => cb(el.value));
                el.addEventListener('change', () => recordStateChange());
            }
        }

        function updateRowTitle() {
            const row = sectionsEl.querySelector('.hb-row.active .hb-row-title > div');
            if (row) row.textContent = state.sections[sel].title || nameOf(state.sections[sel].type, TYPE);
        }

        /* ---------- TomSelect pickers ---------- */
        function entityCfg(key) {
            if (key === 'brand') return {url: cfg.brandSearch, param: 'q'};
            if (key === 'category') return {url: cfg.categorySearch, param: 'search'};
            return {url: cfg.productSearch, param: 'search'};
        }

        function remoteOpts(key, multiple) {
            const ec = entityCfg(key);
            return {
                valueField: 'value', labelField: 'text', searchField: 'text',
                maxItems: multiple ? null : 1, placeholder: 'Search…',
                dropdownParent: 'body', dropdownClass: 'ts-dropdown hb-home-builder-dropdown',
                load: function (query, cb) {
                    if (!query.length) return cb();
                    const u = new URL(ec.url, window.location.origin);
                    u.searchParams.set(ec.param, query);
                    fetch(u.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json'}})
                        .then(r => r.json())
                        .then(rows => cb((rows || []).map(r => ({
                            value: String(r.value != null ? r.value : r.id),
                            text: r.text || r.title || r.name || ('#' + (r.value || r.id)),
                            image: r.image || r.image_url || r.logo || null,
                            meta: r.preview || {}
                        }))))
                        .catch(() => cb());
                },
            };
        }

        function initEditorSelects(s) {
            editorEl.querySelectorAll('select.hb-entity').forEach(elx => {
                const key = elx.dataset.entity, purpose = elx.dataset.purpose;
                const ts = new TomSelect(elx, remoteOpts(key, true));
                s.items.filter(it => it.itemable_type === key).forEach(it => {
                    if (it.itemable_id) ts.addOption({
                        value: String(it.itemable_id),
                        text: it.itemable_label || ('#' + it.itemable_id),
                        image: it.itemable_image || null,
                        meta: it.itemable_meta || {}
                    });
                });
                ts.setValue(s.items.filter(it => it.itemable_type === key).map(it => String(it.itemable_id)), true);
                ts.on('change', () => {
                    const vals = ts.getValue();
                    const arr = Array.isArray(vals) ? vals : (vals ? [vals] : []);
                    const otherItems = s.items.filter(item => item.itemable_type !== key || item.purpose !== purpose);
                    const selectedItems = arr.map(v => {
                        const existing = s.items.find(item => item.itemable_type === key && item.purpose === purpose && Number(item.itemable_id) === Number(v));
                        if (existing) return existing;
                        return {
                            id: null,
                            purpose,
                            itemable_type: key,
                            itemable_id: +v,
                            itemable_label: (ts.options[v] || {}).text || null,
                            itemable_image: (ts.options[v] || {}).image || null,
                            itemable_meta: (ts.options[v] || {}).meta || {},
                            title: '',
                            link_url: '',
                            image: null,
                            mobile_image: null
                        };
                    });
                    s.items = otherItems.concat(selectedItems);
                    if (key === 'product' || (s.type === 'products' && key === 'category' && purpose === 'filter')) {
                        scheduleMarketCatalogRefresh();
                    }
                    renderEditor();
                });
            });
            editorEl.querySelectorAll('select.hb-hero-item').forEach(elx => {
                const j = +elx.closest('[data-j]').dataset.j;
                const it = s.items[j];
                const key = elx.dataset.entity;
                const ts = new TomSelect(elx, remoteOpts(key, false));
                if (it.itemable_id) {
                    ts.addOption({value: String(it.itemable_id), text: it.itemable_label || ('#' + it.itemable_id), image: it.itemable_image || null, meta: it.itemable_meta || {}});
                    ts.setValue(String(it.itemable_id), true);
                }
                ts.on('change', () => {
                    const v = ts.getValue();
                    it.itemable_id = v ? +v : null;
                    it.itemable_label = (ts.options[v] || {}).text || null;
                    it.itemable_image = (ts.options[v] || {}).image || null;
                    it.itemable_meta = (ts.options[v] || {}).meta || {};
                    if (key === 'product') scheduleMarketCatalogRefresh();
                });
            });
            editorEl.querySelectorAll('select.hb-item-link-entity').forEach(elx => {
                const j = +elx.closest('[data-j]').dataset.j;
                const it = s.items[j];
                const key = elx.dataset.entity;
                const ts = new TomSelect(elx, remoteOpts(key, false));
                if (it.itemable_id && it.itemable_type === key) {
                    ts.addOption({value: String(it.itemable_id), text: it.itemable_label || ('#' + it.itemable_id), image: it.itemable_image || null, meta: it.itemable_meta || {}});
                    ts.setValue(String(it.itemable_id), true);
                }
                ts.on('change', () => {
                    const v = ts.getValue();
                    it.itemable_type = key;
                    it.itemable_id = v ? +v : null;
                    it.itemable_label = (ts.options[v] || {}).text || null;
                    it.itemable_image = (ts.options[v] || {}).image || null;
                    it.itemable_meta = (ts.options[v] || {}).meta || {};
                    it.link_url = '';
                });
            });
            editorEl.querySelectorAll('select.hb-markets').forEach(elx => {
                const ts = new TomSelect(elx, {
                    valueField: 'value', labelField: 'text', searchField: 'text',
                    placeholder: L('allMarkets', 'All markets'),
                    load: function (query, cb) {
                        const self = this;
                        const u = new URL(cfg.marketSearch, window.location.origin);
                        if (query) u.searchParams.set('search', query);
                        fetch(u.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json'}})
                            .then(r => r.json()).then(rows => {
                            (rows || []).forEach(row => {
                                if (self.options[row.value]) self.updateOption(row.value, row);
                            });
                            cb(rows || []);
                        }).catch(() => cb());
                    },
                    onInitialize: function () {
                        this.load('');
                    },
                });
                (s.market_ids || []).forEach(id => ts.addOption({value: String(id), text: '#' + id}));
                ts.setValue((s.market_ids || []).map(String), true);
                ts.on('change', () => {
                    const v = ts.getValue();
                    s.market_ids = (Array.isArray(v) ? v : []).map(Number);
                    renderSections();
                    renderPreview();
                });
            });
        }

        /* ---------- image modal trigger ---------- */
        function openImage(s, target) {
            if (!cfg.canEdit || !window.FilePond) return;
            const isLottie = (target.kind === 'hero_media' && s.config && s.config.media_type === 'lottie')
                || (target.kind === 'item_lottie');
            const allowCrop = s.type === 'play_image' && (target.kind === 'item' || target.kind === 'item_mobile');
            const existing = target.kind === 'row_background'
                ? ((s._rowBackgroundPreview && s._rowBackgroundPreview[target.r]) || s.config.rows[target.r].background_image)
                : target.kind === 'item'
                ? itemPreview(s.items[target.j])
                : target.kind === 'item_mobile'
                    ? itemMobilePreview(s.items[target.j])
                    : target.kind === 'item_lottie'
                        ? itemPreview(s.items[target.j])
                        : sectionPreview(s, target.kind);
            ImageModal.open({isLottie, existing, allowCrop}, (native) => {
                if (native) {
                    const url = URL.createObjectURL(native);
                    if (target.kind === 'row_background') {
                        s._rowBackgroundFiles = s._rowBackgroundFiles || {};
                        s._rowBackgroundPreview = s._rowBackgroundPreview || {};
                        s._rowBackgroundFiles[target.r] = native;
                        s._rowBackgroundPreview[target.r] = url;
                    } else if (target.kind === 'item' || target.kind === 'item_lottie') {
                        const it = s.items[target.j];
                        it._imageFile = native;
                        it._imagePreview = url;
                        it._removeImage = false;
                    } else if (target.kind === 'item_mobile') {
                        const it = s.items[target.j];
                        it._mobileImageFile = native;
                        it._mobileImagePreview = url;
                    } else {
                        s._mediaFiles = s._mediaFiles || {};
                        s._mediaPreview = s._mediaPreview || {};
                        s._mediaFiles[target.kind] = native;
                        s._mediaPreview[target.kind] = url;
                    }
                }
                renderEditor();
            });
        }

        /* ---------- save (multipart: structure + staged images) ---------- */
        function appendFormData(fd, data, parent) {
            if (data === null || data === undefined) return;
            if (Array.isArray(data)) {
                data.forEach((v, i) => appendFormData(fd, v, parent + '[' + i + ']'));
            } else if (typeof data === 'object') {
                Object.keys(data).forEach(k => appendFormData(fd, data[k], parent ? parent + '[' + k + ']' : k));
            } else {
                fd.append(parent, data === true ? '1' : data === false ? '0' : data);
            }
        }

        function serialize() {
            return {
                platform: state.platform, scope_type: state.scope_type, scope_id: state.scope_id, is_active: state.is_active,
                sections: state.sections.map(serializeSection),
            };
        }

        function serializeSection(s, index) {
            return {
                id: s.id, type: s.type, title: s.title || null, show_title: s.show_title, style: s.style || null,
                config: s.config || {}, sort_order: index, is_active: s.is_active, market_ids: s.market_ids || [],
                market_positions: s.market_positions || [],
                background_image: s.background_image || null,
                hero_media: s.hero_media || null,
                card_background_image: s.card_background_image || null,
                items: (s.items || []).map((it, itemIndex) => ({
                    id: it.id, purpose: it.purpose || 'display',
                    itemable_type: it.itemable_type || null, itemable_id: it.itemable_id || null,
                    title: it.title || null, link_url: it.link_url || null, sort_order: itemIndex,
                    config: it.config || {},
                    image: it.image || null,
                    mobile_image: it.mobile_image || null,
                })),
            };
        }

        function sectionSavedSignature(section) {
            const serialized = serializeSection(section, 0);
            delete serialized.sort_order;
            return JSON.stringify(serialized);
        }

        function captureSavedSectionSignatures() {
            savedSectionSignatures.clear();
            state.sections.forEach(section => savedSectionSignatures.set(sectionUiKey(section), sectionSavedSignature(section)));
        }

        function sectionIsModified(section) {
            const saved = savedSectionSignatures.get(sectionUiKey(section));
            return saved === undefined || saved !== sectionSavedSignature(section) || sectionHasPendingMediaChanges(section);
        }

        function refreshSectionModifiedIndicators(sectionIndex) {
            if (!sectionsEl) return;
            const rows = Number.isInteger(sectionIndex)
                ? sectionsEl.querySelectorAll('.hb-row[data-i="' + sectionIndex + '"]')
                : sectionsEl.querySelectorAll('.hb-row[data-i]');
            rows.forEach(row => {
                const section = state.sections[Number(row.dataset.i)];
                const title = row.querySelector('.hb-row-title');
                const existing = title && title.querySelector('.hb-section-modified-label');
                const modified = section && sectionIsModified(section);
                if (modified && title && !existing) {
                    title.insertAdjacentHTML('beforeend', '<span class="hb-section-modified-label"><i class="ti ti-pencil"></i>Modified</span>');
                } else if (!modified && existing) {
                    existing.remove();
                }
            });
        }

        function collectIssues() {
            const issues = [];
            state.sections.forEach((section, index) => {
                if (section._loaded === false) return;
                const items = section.items || [];
                const withTarget = items.filter(item => item.itemable_id);
                const sectionName = section.title || nameOf(section.type, TYPE) || ('Section ' + (index + 1));
                const add = (severity, message, tab, rowIndex, category) => issues.push({
                    severity,
                    index,
                    message,
                    tab: tab || 'content',
                    rowIndex,
                    category: category || 'layout',
                    sectionName,
                });

                if (section.is_active && section.type !== 'hero' && section.show_title && !String(section.title || '').trim()) {
                    add('warning', 'Add a visible section heading or turn off “Show title”.', 'content', undefined, 'accessibility');
                }

                if (section.type === 'hero') {
                    if (section.config.layout === 'items' && withTarget.length < 1) {
                        add('error', L('heroItemsRequired', 'Select at least one item for the hero section.'), 'content');
                    }
                    if (section.is_active && !sectionPreview(section, 'hero_media')) {
                        add('warning', 'Add the hero background image or Lottie file.', 'layout');
                    }
                    if (section.config.card_bg_type === 'image' && !sectionPreview(section, 'card_background')) {
                        add('warning', 'Add the selected hero card background image.', 'style');
                    }
                    if (section.is_active && section.config.layout === 'items' && section.style === 'image_title' && section.config.card_bg_type !== 'image') {
                        const ratio = contrastRatio(section.config.card_title_color || '#222222', section.config.card_bg_color || '#F1EFE8');
                        if (ratio !== null && ratio < 4.5) {
                            add('warning', 'Hero card title contrast is ' + ratio.toFixed(2) + ':1; use colors with at least 4.5:1 contrast.', 'style', undefined, 'accessibility');
                        }
                    }
                }

                if (section.type === 'products' && withTarget.length < 1) {
                    add('error', L('productsTargetRequired', 'Select at least one category or product for the products section.'), 'content');
                }
                if ((section.type === 'categories' || section.type === 'brands') && section.config.source === 'custom' && withTarget.length < 1) {
                    add('error', L('customItemsRequired', 'Select at least one item for a custom selection.'), 'content');
                }
                if (section.is_active && section.config.background_type === 'image' && !sectionPreview(section, 'background')) {
                    add('warning', 'Add the selected section background image.', 'style');
                }
                if (previewMarketId && section.is_active && sectionVisibleInPreviewMarket(section) && !marketCatalogLoading && !marketCatalogError && !sectionHasMarketProducts(section)) {
                    add('warning', 'No products are available for this section in ' + previewMarketLabel + '.', 'content');
                }

                if (section.type === 'play_image') {
                    if (!items.length) add('warning', 'Add at least one Play Image item.', 'content');
                    items.forEach((item, itemIndex) => {
                        const mediaType = (item.config && item.config.media_type) || 'image';
                        if (!itemPreview(item) && !itemMobilePreview(item)) {
                            add('warning', 'Item ' + (itemIndex + 1) + ' is missing its ' + (mediaType === 'lottie' ? 'Lottie file.' : 'image.'), 'content');
                        }
                        if (mediaType === 'image' && !(item.config && String(item.config.alt || '').trim())) {
                            add('warning', 'Item ' + (itemIndex + 1) + ' is missing accessible alt text.', 'content', undefined, 'accessibility');
                        }
                        if (item.link_type === 'custom' && !String(item.link_url || '').trim()) {
                            add('warning', 'Item ' + (itemIndex + 1) + ' needs an action URL.', 'content');
                        } else if (item.link_url && !/^https?:\/\//i.test(item.link_url) && !/^\//.test(item.link_url)) {
                            add('warning', 'Item ' + (itemIndex + 1) + ' has an invalid action URL.', 'content');
                        }
                        if (item.config && item.config.aspect_ratio === 'custom' && (!(Number(item.config.aspect_width) > 0) || !(Number(item.config.aspect_height) > 0))) {
                            add('warning', 'Item ' + (itemIndex + 1) + ' needs valid custom width and height values.', 'content');
                        }
                    });
                    const rows = Array.isArray(section.config.rows) ? section.config.rows : Object.values(section.config.rows || {});
                    rows.forEach((row, rowIndex) => {
                        const rowBackground = (section._rowBackgroundPreview && section._rowBackgroundPreview[rowIndex]) || row.background_image;
                        if (row.background_type === 'image' && !rowBackground) {
                            add('warning', 'Row ' + (rowIndex + 1) + ' is missing its background image.', 'style', rowIndex);
                        }
                        if (section.is_active && row.style === 'scroll' && parseBool(row.auto_scroll, false)) {
                            add('warning', 'Row ' + (rowIndex + 1) + ' rotates automatically without a pause control. Disable auto scroll for accessible playback.', 'style', rowIndex, 'accessibility');
                        }
                    });
                }
            });
            return issues;
        }

        function renderIssues() {
            if (!issuesToggleEl || !issuesPanelEl) return;
            const issues = collectIssues();
            const errorCount = issues.filter(issue => issue.severity === 'error').length;
            const warningCount = issues.length - errorCount;
            const accessibilityCount = issues.filter(issue => issue.category === 'accessibility').length;
            issuesToggleEl.classList.toggle('has-errors', errorCount > 0);
            issuesToggleEl.classList.toggle('has-warnings', errorCount === 0 && warningCount > 0);
            issuesToggleEl.classList.toggle('is-ready', issues.length === 0);
            if (issuesLabelEl) issuesLabelEl.textContent = issues.length ? 'Issues' : 'Ready';
            if (issuesCountEl) issuesCountEl.textContent = String(issues.length);
            issuesPanelEl.hidden = !issuesOpen;
            issuesToggleEl.setAttribute('aria-expanded', issuesOpen ? 'true' : 'false');

            if (!issues.length) {
                issuesPanelEl.innerHTML = '<div class="d-flex align-items-center gap-2 text-success"><i class="ti ti-circle-check"></i><strong>No layout or accessibility issues found.</strong></div>';
                return;
            }

            issuesPanelEl.innerHTML = '<div class="hb-issues-panel-head"><div><strong>Layout checks</strong><div class="text-muted small">' + errorCount + ' blocking · ' + warningCount + ' warning' + (warningCount === 1 ? '' : 's') + ' · ' + accessibilityCount + ' accessibility</div></div></div>' +
                '<div class="hb-issues-list">' + issues.map((issue, issueIndex) =>
                    '<button type="button" class="hb-issue-item is-' + issue.severity + '" data-issue-index="' + issueIndex + '">' +
                    '<i class="ti ' + (issue.category === 'accessibility' ? 'ti-accessible' : (issue.severity === 'error' ? 'ti-circle-x' : 'ti-alert-triangle')) + '"></i>' +
                    '<span><strong>' + esc(issue.sectionName) + (issue.category === 'accessibility' ? '<em class="hb-issue-kind">Accessibility</em>' : '') + '</strong><span>' + esc(issue.message) + '</span></span></button>'
                ).join('') + '</div>';
            issuesPanelEl.querySelectorAll('[data-issue-index]').forEach(button => button.addEventListener('click', () => {
                const issue = issues[Number(button.dataset.issueIndex)];
                if (!issue) return;
                sel = issue.index;
                editorTab = issue.tab;
                pendingPreviewSectionScroll = issue.index;
                focusedPlayRow = issue.rowIndex === undefined
                    ? null
                    : {sectionKey: sectionUiKey(state.sections[issue.index]), rowIndex: issue.rowIndex};
                if (issue.rowIndex !== undefined) {
                    const section = state.sections[issue.index];
                    const row = (section.config.rows || [])[issue.rowIndex] || defaultRowConfig();
                    const rowKey = row.media_key || ('row-' + issue.rowIndex);
                    disclosureState.set(sectionUiKey(section) + ':row:' + rowKey, true);
                }
                issuesOpen = false;
                renderSections();
                renderEditor();
            }));
        }

        function validateBeforeSave() {
            return collectIssues().find(issue => issue.severity === 'error') || null;
        }

        function save() {
            if (headerMode) {
                setSaveStatus('loading', 'Saving header…');
                document.dispatchEvent(new CustomEvent('home-navbar:integrated-save', {
                    detail: {owner: root.id, platform: PLATFORM},
                }));
                return;
            }
            if (state.sections.some(section => section._loaded === false)) {
                setSaveStatus('loading', 'Preparing sections…');
                ensureAllSectionsLoaded()
                    .then(saveHydratedLayout)
                    .catch(() => setSaveStatus('error', 'Load failed · unsaved'));
                return;
            }
            saveHydratedLayout();
        }

        function saveHydratedLayout() {
            const problem = validateBeforeSave();
            if (problem) {
                sel = problem.index;
                editorTab = problem.tab || 'content';
                renderSections();
                renderEditor();
                Toast.fire({icon: 'error', title: problem.message});
                return;
            }
            const fd = new FormData();
            setSaveStatus('loading', 'Saving…');
            appendFormData(fd, serialize(), '');
            state.sections.forEach((s, i) => {
                if (s._mediaFiles) Object.keys(s._mediaFiles).forEach(kind => {
                    if (s._mediaFiles[kind]) fd.append('media[' + i + '][' + kind + ']', s._mediaFiles[kind]);
                });
                (s.items || []).forEach((it, j) => {
                    if (it._imageFile) fd.append('item_media[' + i + '][' + j + ']', it._imageFile);
                    if (it._mobileImageFile) fd.append('item_mobile_media[' + i + '][' + j + ']', it._mobileImageFile);
                    if (it._removeImage) fd.append('remove_item_media[' + i + '][' + j + ']', '1');
                });
                if (s._rowBackgroundFiles) Object.keys(s._rowBackgroundFiles).forEach(r => {
                    if (s._rowBackgroundFiles[r]) fd.append('row_media[' + i + '][' + r + ']', s._rowBackgroundFiles[r]);
                });
            });
            api('post', cfg.saveUrl, fd).then(r => {
                const d = r.data.data || {};
                const curSel = sel;
                const previousSections = state.sections;
                state.sections = (d.sections || []).map((section, index) => normalizeSectionKeepingUi(section, previousSections[index]));
                state.sections.forEach(section => { section._loaded = true; });
                sectionTotal = state.sections.length;
                sectionLastPage = Math.max(1, Math.ceil(sectionTotal / SECTION_PAGE_SIZE));
                loadedSectionPages.clear();
                for (let page = 1; page <= sectionLastPage; page++) loadedSectionPages.add(page);
                sel = Math.min(curSel, state.sections.length - 1);
                markSavedState();
                renderSections();
                renderEditor();
                Toast.fire({icon: 'success', title: r.data.message});
                finishSaveAction();
            }).catch(err => {
                afterSaveAction = null;
                setSaveStatus('error', 'Save failed · unsaved');
                Toast.fire({
                    icon: 'error',
                    title: err.response?.data?.message || 'Save failed'
                });
            });
        }

        /* ---------- type picker + scope + init ---------- */
        function renderTypePicker() {
            typePickerEl.innerHTML = TYPES.map(t => '<button type="button" class="btn btn-sm btn-outline-primary text-start" data-add="' + t[0] + '"><i class="ti ' + ICON[t[0]] + ' me-1"></i>' + esc(t[1]) + '</button>').join('');
            typePickerEl.querySelectorAll('[data-add]').forEach(b => b.addEventListener('click', () => {
                const newSection = defaultSection(b.dataset.add);
                if (state._addPosition === 'top') {
                    state.sections.unshift(newSection);
                    sel = 0;
                } else {
                    state.sections.push(newSection);
                    sel = state.sections.length - 1;
                }
                typePickerEl.classList.add('d-none');
                renderSections();
                renderEditor();
            }));
        }

        function updateScopeCategoryOverflow() {
            const slider = root.querySelector('[data-role="scope-category-slider"]');
            const track = root.querySelector('[data-role="scope-category-track"]');
            if (!slider || !track || slider.classList.contains('d-none')) return;
            const hasOverflow = track.scrollWidth > track.clientWidth + 2;
            slider.classList.toggle('has-overflow', hasOverflow);
            slider.querySelectorAll('[data-scope-category-scroll]').forEach(button => {
                button.hidden = !hasOverflow;
                const direction = Number(button.dataset.scopeCategoryScroll);
                button.disabled = direction < 0
                    ? track.scrollLeft <= 2
                    : track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
            });
        }

        function renderScopeCategories() {
            const slider = root.querySelector('[data-role="scope-category-slider"]');
            const track = root.querySelector('[data-role="scope-category-track"]');
            if (!slider || !track) return;

            const categories = navbarItemsForPlatform(PLATFORM);
            const globalActive = state.scope_type === 'global';
            track.innerHTML = '<button type="button" class="hb-scope-category-chip hb-scope-global-chip' + (globalActive ? ' active' : '') + '" data-scope-global aria-pressed="' + (globalActive ? 'true' : 'false') + '">' + esc(L('global', 'Global')) + '</button>' +
                (categories.length
                ? categories.map(category => {
                    const active = state.scope_type === 'navbar' && Number(state.scope_id) === Number(category.id);
                    return '<button type="button" class="hb-scope-category-chip' + (active ? ' active' : '') + '" data-scope-navbar="' + esc(category.id) + '" aria-pressed="' + (active ? 'true' : 'false') + '">' + esc(category.title || category.name || ('Navbar item #' + category.id)) + '</button>';
                }).join('')
                : '<span class="hb-scope-category-empty">No navbar items added</span>');

            const globalButton = track.querySelector('[data-scope-global]');
            if (globalButton) globalButton.addEventListener('click', () => {
                if (state.scope_type === 'global') return;
                requestScopeChange('global', null);
            });

            track.querySelectorAll('[data-scope-navbar]').forEach(button => button.addEventListener('click', () => {
                const navbarId = Number(button.dataset.scopeNavbar);
                if (!navbarId || (state.scope_type === 'navbar' && Number(state.scope_id) === navbarId)) return;
                requestScopeChange('navbar', navbarId);
            }));

            window.requestAnimationFrame(() => {
                updateScopeCategoryOverflow();
                const active = track.querySelector('.hb-scope-category-chip.active');
                if (active) active.scrollIntoView({behavior: 'smooth', block: 'nearest', inline: 'center'});
            });
        }

        function requestScopeChange(scopeType, scopeId) {
            if (headerMode) {
                const switchScope = () => {
                    leaveHeaderMode(true);
                    requestScopeChange(scopeType, scopeId);
                };
                if (!headerDirty) {
                    switchScope();
                    return;
                }
                ConfirmModal.open(
                    'Discard the unsaved header changes and open another homepage?',
                    switchScope,
                    {
                        title: 'Unsaved header changes',
                        confirmText: 'Discard changes',
                        confirmClass: 'btn-warning',
                        cancelText: 'Keep editing',
                        onSave: () => { afterSaveAction = switchScope; save(); },
                    },
                );
                return;
            }
            const changeScope = () => {
                cacheCurrentLayout();
                savedSignature = null;
                unsavedChanges = false;
                root.dataset.unsaved = '0';
                state.scope_type = scopeType;
                state.scope_id = scopeId;
                renderScopeCategories();
                resetPreviewViewOnRender = true;
                if (!restoreCachedLayout(scopeType, scopeId)) load();
            };
            if (!unsavedChanges) {
                changeScope();
                return;
            }
            ConfirmModal.open(
                'You have unsaved changes in this homepage. Discard them and open another homepage?',
                changeScope,
                {
                    title: 'Unsaved changes',
                    confirmText: 'Discard changes',
                    confirmClass: 'btn-warning',
                    cancelText: 'Keep editing',
                    onSave: () => { afterSaveAction = changeScope; save(); },
                },
            );
        }

        function updatePreviewMarketUrl() {
            if (!window.history || !window.history.replaceState) return;
            const url = new URL(window.location.href);
            if (previewMarketId) url.searchParams.set('home_market_id', previewMarketId);
            else url.searchParams.delete('home_market_id');
            window.history.replaceState({}, '', url.pathname + url.search + url.hash);
        }

        function setPreviewMarket(value, label, updateUrl, broadcast) {
            previewMarketId = Number(value) || null;
            previewMarketLabel = previewMarketId ? (label || ('Market #' + previewMarketId)) : 'All markets';

            if (previewMarketControl) {
                const controlValue = previewMarketId ? String(previewMarketId) : '';
                if (previewMarketId && !previewMarketControl.options[controlValue]) {
                    previewMarketControl.addOption({value: controlValue, text: previewMarketLabel});
                }
                if (String(previewMarketControl.getValue() || '') !== controlValue) {
                    previewMarketControl.setValue(controlValue, true);
                }
            } else if (previewMarketEl) {
                previewMarketEl.value = previewMarketId ? String(previewMarketId) : '';
            }

            if (updateUrl) updatePreviewMarketUrl();
            updateMarketViewControls();
            refreshMarketPreviewCatalog();

            if (broadcast) {
                document.dispatchEvent(new CustomEvent('home-builder:market-view-changed', {
                    detail: {source: PLATFORM, marketId: previewMarketId, label: previewMarketLabel},
                }));
            }
        }

        function fetchMarkets(query, ids) {
            const url = new URL(cfg.marketSearch, window.location.origin);
            if (query) url.searchParams.set('search', query);
            (ids || []).forEach(id => url.searchParams.append('find_in[]', id));
            return fetch(url.toString(), {
                headers: {'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json'},
            }).then(response => response.ok ? response.json() : Promise.reject(response));
        }

        function initMarketView() {
            if (!previewMarketEl) return;

            if (window.TomSelect) {
                previewMarketControl = new TomSelect(previewMarketEl, {
                    valueField: 'value',
                    labelField: 'text',
                    searchField: ['text', 'code', 'name'],
                    allowEmptyOption: true,
                    preload: true,
                    placeholder: 'Select a market homepage',
                    load: function (query, callback) {
                        fetchMarkets(query)
                            .then(rows => callback(rows || []))
                            .catch(() => callback());
                    },
                });

                if (previewMarketId) {
                    const marketId = String(previewMarketId);
                    previewMarketControl.addOption({value: marketId, text: previewMarketLabel});
                    previewMarketControl.setValue(marketId, true);
                    fetchMarkets('', [marketId]).then(rows => {
                        const market = (rows || [])[0];
                        if (!market) return;
                        previewMarketControl.updateOption(marketId, market);
                        previewMarketLabel = market.text;
                        updateMarketViewControls();
                    }).catch(() => {});
                }

                previewMarketControl.on('change', value => {
                    const option = previewMarketControl.options[value] || {};
                    setPreviewMarket(value, option.text, true, true);
                });
            } else {
                fetchMarkets('').then(rows => {
                    (rows || []).forEach(market => {
                        const option = document.createElement('option');
                        option.value = market.value;
                        option.textContent = market.text;
                        previewMarketEl.appendChild(option);
                    });
                    previewMarketEl.value = previewMarketId ? String(previewMarketId) : '';
                }).catch(() => {});
                previewMarketEl.addEventListener('change', () => {
                    const option = previewMarketEl.options[previewMarketEl.selectedIndex];
                    setPreviewMarket(previewMarketEl.value, option && option.textContent, true, true);
                });
            }

            if (resetMarketOrderEl) resetMarketOrderEl.addEventListener('click', () => {
                if (!previewMarketId) return;
                state.sections.forEach(section => {
                    section.market_positions = (section.market_positions || [])
                        .filter(position => Number(position.market_id) !== previewMarketId);
                });
                recordStateChange();
                renderSections();
                renderPreview();
            });

            document.addEventListener('home-builder:market-view-changed', event => {
                const detail = event.detail || {};
                if (detail.source === PLATFORM) return;
                setPreviewMarket(detail.marketId, detail.label, false, false);
            });

            updateMarketViewControls();
        }

        function initScope() {
            const slider = root.querySelector('[data-role="scope-category-slider"]');
            const track = root.querySelector('[data-role="scope-category-track"]');
            if (slider && track) {
                slider.querySelectorAll('[data-scope-category-scroll]').forEach(button => button.addEventListener('click', () => {
                    track.scrollBy({left: Number(button.dataset.scopeCategoryScroll) * Math.max(180, track.clientWidth * .72), behavior: 'smooth'});
                }));
                track.addEventListener('scroll', updateScopeCategoryOverflow, {passive: true});
                track.addEventListener('wheel', event => {
                    if (track.scrollWidth <= track.clientWidth || Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;
                    event.preventDefault();
                    track.scrollLeft += event.deltaY;
                }, {passive: false});
                window.addEventListener('resize', updateScopeCategoryOverflow);
            }

            renderScopeCategories();
            initMarketView();
        }

        function renderAddButtons() {
            // Find or create top add/paste buttons wrapper
            let topBtns = root.querySelector('#hb-top-buttons-wrapper');
            if (!topBtns) {
                topBtns = document.createElement('div');
                topBtns.id = 'hb-top-buttons-wrapper';
                topBtns.className = 'mb-3 d-flex flex-column gap-2';
                sectionsEl.parentNode.insertBefore(topBtns, sectionsEl);
            }

            // Find or create bottom add/paste buttons wrapper
            let bottomBtns = root.querySelector('#hb-bottom-buttons-wrapper');
            if (!bottomBtns) {
                bottomBtns = document.createElement('div');
                bottomBtns.id = 'hb-bottom-buttons-wrapper';
                bottomBtns.className = 'mt-3 d-flex flex-column gap-2';

                // Hide the static add-section button from PHP view
                if (addBtn) addBtn.style.display = 'none';

                sectionsEl.parentNode.insertBefore(bottomBtns, typePickerEl);
            }

            const hasSections = state.sections && state.sections.length > 0;

            // Toggle top buttons based on whether sections list is empty
            if (hasSections) {
                topBtns.innerHTML =
                    '<button type="button" class="btn btn-outline-primary w-100 hb-dashed hb-add-btn-trigger"' + (cfg.canEdit ? '' : ' disabled') + '><i class="ti ti-plus me-1"></i>Add Section</button>' +
                    '<button type="button" class="btn btn-outline-success w-100 hb-dashed hb-paste-btn-trigger"' + (cfg.canEdit ? '' : ' disabled') + '><i class="ti ti-clipboard me-1"></i>Paste Section Design</button>';
                topBtns.classList.remove('d-none');
            } else {
                topBtns.classList.add('d-none');
                topBtns.innerHTML = '';
            }

            // Bottom buttons are always shown
            bottomBtns.innerHTML =
                '<button type="button" class="btn btn-outline-primary w-100 hb-dashed hb-add-btn-trigger"' + (cfg.canEdit ? '' : ' disabled') + '><i class="ti ti-plus me-1"></i>Add Section</button>' +
                '<button type="button" class="btn btn-outline-success w-100 hb-dashed hb-paste-btn-trigger"' + (cfg.canEdit ? '' : ' disabled') + '><i class="ti ti-clipboard me-1"></i>Paste Section Design</button>';

            // Wire up event listeners
            root.querySelectorAll('.hb-add-btn-trigger').forEach(b => {
                b.onclick = () => {
                    const isTop = b.closest('#hb-top-buttons-wrapper') !== null;
                    state._addPosition = isTop ? 'top' : 'bottom';

                    if (isTop) {
                        sectionsEl.parentNode.insertBefore(typePickerEl, sectionsEl);
                    } else {
                        bottomBtns.parentNode.insertBefore(typePickerEl, bottomBtns.nextSibling);
                    }

                    renderTypePicker();
                    typePickerEl.classList.toggle('d-none');
                };
            });

            root.querySelectorAll('.hb-paste-btn-trigger').forEach(b => {
                b.onclick = () => {
                    const isTop = b.closest('#hb-top-buttons-wrapper') !== null;
                    state._addPosition = isTop ? 'top' : 'bottom';
                    PasteModal.open(txt => {
                        if (!txt) return;
                        try {
                            const imported = JSON.parse(txt);
                            if (!imported || typeof imported !== 'object' || !imported.type) {
                                throw new Error("Invalid section object");
                            }

                            // Create a new section populated with imported configurations
                            const newSec = defaultSection(imported.type);
                            newSec.title = imported.title || '';
                            newSec.style = imported.style || newSec.style;
                            newSec.config = imported.config || newSec.config;
                            newSec.items = imported.items || [];
                            if (imported.background_image !== undefined) newSec.background_image = imported.background_image;
                            if (imported.mobile_image !== undefined) newSec.mobile_image = imported.mobile_image;
                            if (imported.hero_media !== undefined) newSec.hero_media = imported.hero_media;
                            if (imported.card_background_image !== undefined) newSec.card_background_image = imported.card_background_image;

                            if (state._addPosition === 'top') {
                                state.sections.unshift(newSec);
                                sel = 0;
                            } else {
                                state.sections.push(newSec);
                                sel = state.sections.length - 1;
                            }

                            renderSections();
                            renderEditor();
                            Toast.fire({
                                icon: 'success',
                                title: 'New section pasted successfully!'
                            });
                        } catch (e) {
                            Toast.fire({
                                icon: 'error',
                                title: 'Invalid design JSON code'
                            });
                        }
                    });
                };
            });
        }

        function sectionTemplatePreview(template, miniature) {
            const type = template.type;
            const sizeClass = miniature ? ' is-miniature' : '';
            const line = '<span class="hb-template-sk-line"></span>';

            if (type === 'hero') {
                const itemCards = template.config && template.config.layout === 'items'
                    ? '<div class="hb-template-sk-hero-items"><span></span><span></span><span></span></div>'
                    : '';
                const mediaIcon = template.config && template.config.media_type === 'lottie'
                    ? '<i class="ti ti-player-play hb-template-sk-media-icon"></i>'
                    : '';
                return '<div class="hb-template-preview hb-template-preview-hero' + sizeClass + '">' + mediaIcon + '<div class="hb-template-sk-copy">' + line + line + '</div>' + itemCards + '</div>';
            }

            if (type === 'products') {
                const vertical = template.config && template.config.orientation === 'vertical';
                const count = vertical ? 6 : 5;
                return '<div class="hb-template-preview hb-template-preview-products ' + (vertical ? 'is-grid' : 'is-rail') + sizeClass + '"><div class="hb-template-sk-heading">' + line + '</div><div class="hb-template-sk-products">' +
                    Array.from({length: count}, () => '<span class="hb-template-sk-product"><i></i><b></b><small></small></span>').join('') +
                    '</div></div>';
            }

            if (type === 'categories') {
                const style = template.style || 'default';
                return '<div class="hb-template-preview hb-template-preview-categories is-' + esc(style) + sizeClass + '"><div class="hb-template-sk-heading">' + line + '</div><div class="hb-template-sk-categories">' +
                    Array.from({length: 6}, () => '<span><i></i><b></b></span>').join('') +
                    '</div></div>';
            }

            if (type === 'brands') {
                const withNames = template.style === 'image_title';
                return '<div class="hb-template-preview hb-template-preview-brands' + (withNames ? ' has-names' : '') + sizeClass + '"><div class="hb-template-sk-heading">' + line + '</div><div class="hb-template-sk-brands">' +
                    Array.from({length: 6}, () => '<span><i></i>' + (withNames ? '<b></b>' : '') + '</span>').join('') +
                    '</div></div>';
            }

            if (type === 'play_image') {
                const rows = (template.config && template.config.rows) || [{style: template.style || 'grid'}];
                const items = template.items || [];
                const rowsHtml = rows.map((row, rowIndex) => {
                    const rowItems = items.filter(item => Number((item.config || {}).row_index || 0) === rowIndex);
                    return '<div class="hb-template-sk-play-row is-' + esc(row.style || 'grid') + '">' + rowItems.map(item => {
                        const span = Math.max(1, Math.min(12, Number((item.config || {}).cols) || 12));
                        return '<span style="--hb-template-span:' + span + '"><i class="ti ti-photo"></i></span>';
                    }).join('') + '</div>';
                }).join('');
                return '<div class="hb-template-preview hb-template-preview-play' + sizeClass + '">' + rowsHtml + '</div>';
            }

            return '<div class="hb-template-preview' + sizeClass + '"><div class="hb-template-sk-heading">' + line + '</div></div>';
        }

        function templatePreview(entry) {
            if (entry.kind !== 'theme') return sectionTemplatePreview(entry, false);

            const sections = (entry.templateIds || [])
                .map(id => TEMPLATES.find(template => template.id === id))
                .filter(Boolean)
                .slice(0, 4);
            return '<div class="hb-template-preview hb-template-preview-theme">' + sections.map(template => sectionTemplatePreview(template, true)).join('') + '</div>';
        }

        function openTemplateLibrary() {
            const modal = document.getElementById('hb-templates-modal');
            if (!modal) return;

            const search = modal.querySelector('[data-role="template-search"]');
            const filters = modal.querySelector('[data-role="template-filters"]');
            let activeCategory = 'all';

            const categories = [
                ['all', 'All'],
                ['themes', 'Full pages'],
                ...TYPES.map(type => [type[0], type[1]])
            ].filter((entry, index, entries) => entries.findIndex(candidate => candidate[0] === entry[0]) === index);

            function renderLibrary() {
                const query = String(search.value || '').trim().toLowerCase();
                const cards = modal.querySelector('[data-role="template-cards"]');
                const empty = modal.querySelector('[data-role="template-empty"]');
                const entries = [
                    ...THEMES.map(theme => Object.assign({kind: 'theme', category: 'themes', icon: 'ti-layout-dashboard'}, theme)),
                    ...TEMPLATES.map(template => Object.assign({kind: 'section'}, template)),
                ].filter(entry => {
                    const matchesCategory = activeCategory === 'all' || entry.category === activeCategory;
                    const haystack = [entry.title, entry.description, entry.badge, entry.category].join(' ').toLowerCase();
                    return matchesCategory && (!query || haystack.includes(query));
                });

                cards.innerHTML = entries.map(entry => {
                    const isTheme = entry.kind === 'theme';
                    return '<div class="col-12 col-md-6 col-xl-4">' +
                        '<article class="card hb-template-card"><div class="card-body">' +
                        templatePreview(entry) +
                        '<div class="hb-template-card-head"><span class="hb-template-icon"><i class="ti ' + esc(entry.icon || ICON[entry.type] || 'ti-template') + '"></i></span>' +
                        '<div class="min-w-0"><span class="hb-template-kind">' + (isTheme ? 'Full homepage' : esc(nameOf(entry.type, TYPE))) + '</span>' +
                        '<h3 class="card-title mt-1 mb-0">' + esc(entry.title) + '</h3></div>' +
                        '<span class="badge bg-primary-lt ms-auto">' + esc(entry.badge || 'Template') + '</span></div>' +
                        '<p class="text-muted hb-template-description mb-0">' + esc(entry.description || '') + '</p>' +
                        '<button type="button" class="btn btn-outline-primary w-100" ' + (isTheme ? 'data-apply-theme' : 'data-insert-template') + '="' + esc(entry.id) + '">' +
                        '<i class="ti ' + (isTheme ? 'ti-layout-dashboard' : 'ti-plus') + ' me-1"></i>' + (isTheme ? 'Add page structure' : 'Add section') + '</button>' +
                        '</div></article></div>';
                }).join('');
                empty.classList.toggle('d-none', entries.length > 0);

                cards.querySelectorAll('[data-insert-template]').forEach(button => button.onclick = () => {
                    const template = TEMPLATES.find(item => item.id === button.dataset.insertTemplate);
                    if (!template) return;
                    insertTemplate(template);
                    hideTemplateLibrary(modal);
                });
                cards.querySelectorAll('[data-apply-theme]').forEach(button => button.onclick = () => {
                    const theme = THEMES.find(item => item.id === button.dataset.applyTheme);
                    if (!theme) return;
                    applyTheme(theme);
                    hideTemplateLibrary(modal);
                });
            }

            filters.innerHTML = categories.map(category => '<button type="button" class="nav-link' + (category[0] === activeCategory ? ' active' : '') + '" data-template-category="' + esc(category[0]) + '">' + esc(category[1]) + '</button>').join('');
            filters.querySelectorAll('[data-template-category]').forEach(button => button.onclick = () => {
                activeCategory = button.dataset.templateCategory;
                filters.querySelectorAll('[data-template-category]').forEach(candidate => candidate.classList.toggle('active', candidate === button));
                renderLibrary();
            });
            search.value = '';
            search.oninput = renderLibrary;
            renderLibrary();

            if (window.bootstrap && window.bootstrap.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modal).show();
            } else if (window.jQuery) {
                window.jQuery(modal).modal('show');
            }
        }

        function hideTemplateLibrary(modal) {
            if (window.bootstrap && window.bootstrap.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modal).hide();
            } else if (window.jQuery) {
                window.jQuery(modal).modal('hide');
            }
        }

        const templatesBtn = root.querySelector('[data-role="open-templates"]');
        if (templatesBtn) templatesBtn.addEventListener('click', openTemplateLibrary);

        const saveBtn = root.querySelector('[data-role="save"]');
        if (saveBtn) saveBtn.addEventListener('click', save);
        if (exitHeaderModeEl) exitHeaderModeEl.addEventListener('click', requestLeaveHeaderMode);
        if (newNavbarEl) newNavbarEl.addEventListener('click', () => {
            document.dispatchEvent(new CustomEvent('home-navbar:integrated-add-focus', {
                detail: {owner: root.id},
            }));
        });
        if (issuesToggleEl) issuesToggleEl.addEventListener('click', () => {
            issuesOpen = !issuesOpen;
            renderIssues();
        });

        document.addEventListener('home-navbar:appearance-saved', event => {
            const detail = event.detail || {};
            if (detail.target === 'all') {
                cfg.homeGeneral = detail.data || cfg.homeGeneral;
            } else {
                const category = cfg.homeNavbarItems.find(item => Number(item.id) === Number(detail.target));
                if (category) Object.assign(category, detail.data || {});
            }
            renderPreview();
        });

        document.addEventListener('home-navbar:integration-state', event => {
            const detail = event.detail || {};
            if (detail.owner !== root.id) return;
            headerDirty = detail.dirty === true;
            if (detail.data) {
                if (detail.target === 'all') {
                    cfg.homeGeneral = Object.assign({}, cfg.homeGeneral, detail.data);
                } else {
                    const item = cfg.homeNavbarItems.find(entry => Number(entry.id) === Number(detail.target));
                    if (item) Object.assign(item, detail.data);
                }
                renderPreview();
            }
            updateUnsavedState();
        });

        document.addEventListener('home-navbar:integrated-save-finished', event => {
            const detail = event.detail || {};
            if (detail.owner !== root.id) return;
            if (detail.success) {
                headerDirty = false;
                updateUnsavedState();
                finishSaveAction();
            } else {
                afterSaveAction = null;
                setSaveStatus('error', 'Save failed · unsaved');
            }
        });

        document.addEventListener('home-navbar:integration-reset', event => {
            const detail = event.detail || {};
            if (detail.owner !== root.id || !detail.data) return;
            if (detail.target === 'all') {
                cfg.homeGeneral = Object.assign({}, cfg.homeGeneral, detail.data);
            } else {
                const item = cfg.homeNavbarItems.find(entry => Number(entry.id) === Number(detail.target));
                if (item) Object.assign(item, detail.data);
            }
        });

        document.addEventListener('home-navbar:integration-selection', event => {
            const detail = event.detail || {};
            if (detail.owner !== root.id) return;
            headerTarget = detail.target === 'all' ? 'all' : Number(detail.target);
            headerDirty = false;
            renderPreview();
            updateUnsavedState();
        });

        document.addEventListener('home-navbar:integrated-create-intent', event => {
            const detail = event.detail || {};
            if (detail.owner !== root.id || !detail.title) return;
            const create = discard => document.dispatchEvent(new CustomEvent('home-navbar:integrated-create', {
                detail: {owner: root.id, title: detail.title, discard: discard === true},
            }));
            if (!headerDirty) {
                create(false);
                return;
            }
            ConfirmModal.open(
                'Save or discard the current header changes before creating a navbar item.',
                () => create(true),
                {
                    title: 'Unsaved header changes',
                    confirmText: 'Discard changes',
                    confirmClass: 'btn-warning',
                    cancelText: 'Keep editing',
                    onSave: () => { afterSaveAction = () => create(false); save(); },
                },
            );
        });

        document.addEventListener('home-navbar:items-saved', event => {
            const detail = event.detail || {};
            const categories = detail.categories || [];
            const deletedIds = new Set((detail.deleted_ids || []).map(Number));
            const incomingIds = new Set(categories.map(category => Number(category.id)));
            const retained = cfg.homeNavbarItems.filter(existing =>
                !incomingIds.has(Number(existing.id)) && !deletedIds.has(Number(existing.id))
            );
            cfg.homeNavbarItems = categories.map(category => Object.assign(
                {},
                cfg.homeNavbarItems.find(existing => Number(existing.id) === Number(category.id)) || {},
                category
            )).concat(retained);
            const availableItems = navbarItemsForPlatform(PLATFORM);
            if (state.scope_type === 'navbar' && !availableItems.some(category => Number(category.id) === Number(state.scope_id))) {
                const fallback = availableItems[0];
                requestScopeChange(fallback ? 'navbar' : 'global', fallback ? Number(fallback.id) : null);
            }
            renderScopeCategories();
            renderPreview();
        });

        document.addEventListener('home-builder:tab-activated', event => {
            if ((event.detail && event.detail.platform) !== PLATFORM) return;
            window.requestAnimationFrame(renderPreview);
        });

        document.addEventListener('home-builder:discard-for-tab-switch', event => {
            if ((event.detail && event.detail.platform) !== PLATFORM) return;
            if (headerMode) leaveHeaderMode(true);
            if (root.dataset.unsaved === '1') load();
        });

        document.addEventListener('home-builder:save-for-tab-switch', event => {
            const detail = event.detail || {};
            if (detail.platform !== PLATFORM) return;
            afterSaveAction = () => {
                if (headerMode) {
                    leaveHeaderMode(false);
                    if (root.dataset.unsaved === '1') {
                        afterSaveAction = typeof detail.done === 'function' ? detail.done : null;
                        save();
                        return;
                    }
                }
                if (typeof detail.done === 'function') detail.done();
            };
            save();
        });

        document.addEventListener('fullscreenchange', () => {
            root.classList.toggle('hb-is-fullscreen', document.fullscreenElement === root);
            renderPreview();
        });

        const mobileInteractionQuery = window.matchMedia('(max-width: 767.98px)');
        const refreshResponsiveInteractions = () => renderPreview();
        if (mobileInteractionQuery.addEventListener) mobileInteractionQuery.addEventListener('change', refreshResponsiveInteractions);
        else if (mobileInteractionQuery.addListener) mobileInteractionQuery.addListener(refreshResponsiveInteractions);

        function initKeyboardShortcuts() {
            document.addEventListener('keydown', event => {
                const tag = event.target ? event.target.tagName : '';
                if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || (event.target && event.target.isContentEditable)) return;

                const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
                const cmdOrCtrl = isMac ? event.metaKey : event.ctrlKey;

                if (cmdOrCtrl && !event.shiftKey && event.key.toLowerCase() === 'z') {
                    event.preventDefault();
                    undo();
                } else if ((cmdOrCtrl && event.key.toLowerCase() === 'y') || (cmdOrCtrl && event.shiftKey && event.key.toLowerCase() === 'z')) {
                    event.preventDefault();
                    redo();
                } else if (cmdOrCtrl && event.key.toLowerCase() === 's') {
                    event.preventDefault();
                    if (cfg.canEdit && unsavedChanges) {
                        const saveBtn = root.querySelector('[data-role="save"]');
                        if (saveBtn) saveBtn.click();
                    }
                } else if (event.key === 'Escape') {
                    if (headerMode && exitHeaderModeEl) exitHeaderModeEl.click();
                }
            });
        }

        initStructurePanel();
        initScope();
        initKeyboardShortcuts();
        load();
    }

    /* ---------- Header / App / Web workspace switcher ---------- */
    const tabs = document.getElementById('hb-tabs');
    if (tabs) {
        const panes = ['homecats', 'app', 'web'];
        const normalizePane = value => ['categories', 'home-categories', 'navbar', 'home-navbar'].includes(value)
            ? 'homecats'
            : (panes.includes(value) ? value : 'app');
        const activatePane = (pane, updateUrl) => {
            pane = normalizePane(pane);
            if (!panes.includes(pane)) pane = 'app';
            tabs.querySelectorAll('.nav-link').forEach(x => {
                const active = x.dataset.pane === pane;
                x.classList.toggle('active', active);
                x.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            panes.forEach(p => {
                const el = document.getElementById('hb-pane-' + p);
                if (el) el.classList.toggle('d-none', pane !== p);
            });
            document.dispatchEvent(new CustomEvent('home-builder:tab-activated', {detail: {platform: pane}}));
            if (updateUrl && window.history && window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.set('active_tab', pane);
                window.history.replaceState({}, '', url.pathname + url.search + url.hash);
            }
        };
        const params = new URLSearchParams(window.location.search);
        const hashPane = window.location.hash.replace(/^#hb-pane-/, '');
        const requestedPane = params.get('active_tab') || params.get('tab') || params.get('platform') || hashPane || 'app';
        tabs.querySelectorAll('[data-pane]').forEach(a => a.addEventListener('click', e => {
            e.preventDefault();
            const current = tabs.querySelector('[data-pane].active')?.dataset.pane;
            const next = normalizePane(a.dataset.pane);
            if (current === next) return;

            const headerManager = document.getElementById('home-categories-manager');
            if (current === 'homecats' && headerManager?.dataset.unsaved === '1') {
                ConfirmModal.open(
                    'Discard the unsaved header and navbar changes and switch to ' + next + '?',
                    () => document.dispatchEvent(new CustomEvent('home-navbar:direct-discard', {
                        detail: {done: () => activatePane(next, true)},
                    })),
                    {
                        title: 'Unsaved header changes',
                        confirmText: 'Discard and switch',
                        confirmClass: 'btn-warning',
                        cancelText: 'Keep editing',
                        onSave: () => document.dispatchEvent(new CustomEvent('home-navbar:direct-save', {
                            detail: {done: () => activatePane(next, true)},
                        })),
                    },
                );
                return;
            }

            const currentRoot = current ? document.querySelector('#hb-pane-' + current + ' [data-home-builder]') : null;
            if (!currentRoot || currentRoot.dataset.unsaved !== '1') {
                if (currentRoot) {
                    document.dispatchEvent(new CustomEvent('home-builder:discard-for-tab-switch', {detail: {platform: current}}));
                }
                activatePane(next, true);
                return;
            }
            ConfirmModal.open(
                'Discard the unsaved changes in the ' + current + ' layout and switch to ' + next + '?',
                () => {
                    document.dispatchEvent(new CustomEvent('home-builder:discard-for-tab-switch', {detail: {platform: current}}));
                    activatePane(next, true);
                },
                {
                    title: 'Unsaved changes',
                    confirmText: 'Discard and switch',
                    confirmClass: 'btn-warning',
                    cancelText: 'Keep editing',
                    onSave: () => document.dispatchEvent(new CustomEvent('home-builder:save-for-tab-switch', {
                        detail: {platform: current, done: () => activatePane(next, true)},
                    })),
                },
            );
        }));
        activatePane(requestedPane, false);
    }

    window.addEventListener('beforeunload', event => {
        if (!document.querySelector('[data-home-builder][data-unsaved="1"], #home-categories-manager[data-unsaved="1"]')) return;
        event.preventDefault();
        event.returnValue = '';
    });

    /* ---------- boot one builder per root ---------- */
    roots.forEach(initHomeBuilder);
})();
