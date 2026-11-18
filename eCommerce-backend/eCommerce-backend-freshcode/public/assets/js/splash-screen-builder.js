(function () {
    const tabs = Array.from(document.querySelectorAll('[data-splash-surface]'));
    let activeSurface = 'customer_app';
    const activeSlots = {customer_app: 'current', seller_app: 'current'};
    const selectedFiles = new WeakMap();
    let objectUrls = [];

    if (window.FilePond) {
        try {
            FilePond.registerPlugin(FilePondPluginImagePreview, FilePondPluginFileValidateType, FilePondPluginFileValidateSize);
        } catch (error) {
            void error;
        }
    }

    document.querySelectorAll('.splash-filepond').forEach((input) => {
        input.addEventListener('change', () => {
            if (input.files?.[0]) selectedFiles.set(input, input.files[0]);
            renderPreview();
        });
        if (window.FilePond) {
            const isVideo = input.name === 'background_video';
            const isLottie = input.name === 'overlay_lottie';
            const pond = FilePond.create(input, {
                allowMultiple: false,
                allowImagePreview: !isVideo && !isLottie,
                acceptedFileTypes: isVideo ? ['video/mp4', 'video/webm', 'video/quicktime'] : isLottie ? ['application/json', 'text/plain'] : ['image/jpeg', 'image/png', 'image/webp'],
                maxFileSize: input.dataset.filepondLimit || '10MB',
                credits: false,
                storeAsFile: true,
                fileValidateTypeDetectType: isLottie ? (source, type) => Promise.resolve(/\.json$/i.test(source.name || '') ? 'application/json' : type) : undefined,
            });
            pond.on('addfile', (error, item) => {
                if (!error && item?.file) selectedFiles.set(input, item.file);
                renderPreview();
            });
            pond.on('removefile', () => {
                selectedFiles.delete(input);
                renderPreview();
            });
        }
    });

    function activeForm() {
        return document.querySelector(`[data-splash-panel="${activeSurface}"] [data-surface="${activeSurface}"][data-slot="${activeSlots[activeSurface]}"]`);
    }

    function updateRangeOutput(input) {
        const output = input.closest('.range-field')?.querySelector('[data-range-output]');
        if (output) output.value = `${input.value}${input.dataset.rangeUnit || ''}`;
    }

    function selectSlot(surface, slot) {
        activeSlots[surface] = slot;
        document.querySelectorAll(`[data-splash-slot][data-surface="${surface}"]`).forEach((tab) => {
            const selected = tab.dataset.splashSlot === slot;
            tab.classList.toggle('active', selected);
            tab.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
        document.querySelectorAll(`[data-splash-form-surface="${surface}"]`).forEach((panel) => {
            panel.classList.toggle('d-none', panel.dataset.splashFormSlot !== slot);
        });
        renderPreview();
    }

    function toggleFields(form) {
        if (!form) return;
        const backgroundType = form.querySelector('[data-splash-background-type]')?.value;
        form.querySelectorAll('[data-splash-field="image"], [data-splash-field="video"], [data-splash-field="color"]').forEach((field) => {
            field.classList.toggle('d-none', field.dataset.splashField !== backgroundType);
        });
        const overlayEnabled = form.querySelector('[data-splash-overlay-toggle]')?.checked || false;
        const overlayFields = form.querySelector('[data-splash-overlay-fields]');
        const overlayType = form.querySelector('[data-splash-overlay-type]')?.value || 'image';
        overlayFields?.classList.toggle('d-none', !overlayEnabled);
        form.querySelector('[data-splash-overlay-value]').value = overlayEnabled ? '1' : '0';
        form.querySelectorAll('[data-splash-field="overlay-image"], [data-splash-field="overlay-lottie"]').forEach((field) => {
            field.classList.toggle('d-none', !overlayEnabled || field.dataset.splashField !== `overlay-${overlayType}`);
        });
        renderPreview();
    }

    function fileUrl(form, name) {
        const input = form.querySelector(`[name="${name}"]`);
        const pond = input && window.FilePond ? FilePond.find(input) : null;
        const file = selectedFiles.get(input) || pond?.getFile()?.file || input?.files?.[0];
        if (file) {
            const url = URL.createObjectURL(file);
            objectUrls.push(url);
            return url;
        }
        const mediaFields = {
            background_image: '[data-splash-field="image"]',
            background_video: '[data-splash-field="video"]',
            overlay_image: '[data-splash-field="overlay-image"]',
            overlay_lottie: '[data-splash-field="overlay-lottie"]',
        };
        const mediaField = input?.closest('[data-splash-field]') || form.querySelector(mediaFields[name]);
        const current = mediaField?.querySelector('.splash-current-media');
        const currentFileLink = mediaField?.querySelector('a[href]');
        return current?.currentSrc || current?.src || currentFileLink?.href || '';
    }

    function renderPreview() {
        const form = activeForm();
        const preview = document.querySelector(`[data-splash-panel="${activeSurface}"] [data-splash-preview]`);
        if (!form || !preview) return;
        objectUrls.forEach((url) => URL.revokeObjectURL(url));
        objectUrls = [];
        const type = form.querySelector('[data-splash-background-type]')?.value || 'color';
        const color = form.querySelector('[name="background_color"]')?.value || '#f8f8fc';
        const image = preview.querySelector('.splash-preview-background');
        const video = preview.querySelector('.splash-preview-video');
        const overlay = preview.querySelector('.splash-preview-overlay');
        const previewContent = preview.querySelector('.splash-phone-content');
        image.classList.add('d-none');
        video.classList.add('d-none');
        video.pause();
        video.removeAttribute('src');
        preview.style.backgroundColor = type === 'color' ? color : '#f8f8fc';
        preview.style.backgroundImage = 'none';

        if (type === 'image') {
            const src = fileUrl(form, 'background_image');
            if (src) { image.src = src; image.classList.remove('d-none'); }
        }
        if (type === 'video') {
            const src = fileUrl(form, 'background_video');
            if (src) { video.src = src; video.classList.remove('d-none'); video.play().catch(() => {}); }
        }

        overlay.replaceChildren();
        const enabled = form.querySelector('[data-splash-overlay-toggle]')?.checked || false;
        if (enabled) {
            const x = Number(form.querySelector('[name="overlay_horizontal_position"]')?.value || 50);
            const y = Number(form.querySelector('[name="overlay_vertical_position"]')?.value || 50);
            const size = Number(form.querySelector('[name="overlay_size"]')?.value || 40);
            const overlayWidth = previewContent.clientWidth * size / 100 * 2;
            const overlayHeight = previewContent.clientHeight * size / 100 * 2;
            overlay.style.left = `${x}%`;
            overlay.style.top = `${y}%`;
            overlay.style.width = `${size}%`;
            overlay.style.height = `${size}%`;
            const overlayType = form.querySelector('[data-splash-overlay-type]')?.value;
            const source = fileUrl(form, overlayType === 'lottie' ? 'overlay_lottie' : 'overlay_image');
            if (source && overlayType === 'lottie') {
                const player = document.createElement('lottie-player');
                player.setAttribute('src', source);
                player.setAttribute('background', 'transparent');
                player.setAttribute('loop', '');
                player.setAttribute('autoplay', '');
                player.style.width = `${overlayWidth}px`;
                player.style.height = `${overlayHeight}px`;
                overlay.append(player);
                loadLottiePlayer();
                overlay.classList.remove('d-none');
            } else if (source) {
                const overlayImage = document.createElement('img');
                overlayImage.src = source;
                overlayImage.alt = '';
                overlayImage.style.width = `${overlayWidth}px`;
                overlayImage.style.height = `${overlayHeight}px`;
                overlay.append(overlayImage);
                overlay.classList.remove('d-none');
            }
        } else {
            overlay.classList.add('d-none');
        }
    }

    function loadLottiePlayer() {
        if (customElements.get('lottie-player') || document.querySelector('[data-splash-lottie-player]')) return;
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js';
        script.dataset.splashLottiePlayer = 'true';
        document.head.append(script);
    }

    tabs.forEach((tab) => tab.addEventListener('click', () => {
        activeSurface = tab.dataset.splashSurface;
        tabs.forEach((item) => {
            const selected = item === tab;
            item.classList.toggle('active', selected);
            item.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
        document.querySelectorAll('[data-splash-panel]').forEach((panel) => panel.classList.toggle('d-none', panel.dataset.splashPanel !== activeSurface));
        renderPreview();
    }));

    document.querySelectorAll('[data-splash-slot]').forEach((tab) => tab.addEventListener('click', () => {
        selectSlot(tab.dataset.surface, tab.dataset.splashSlot);
    }));

    document.querySelectorAll('.splash-config-form').forEach((form) => {
        form.addEventListener('change', () => toggleFields(form));
        form.addEventListener('input', (event) => {
            if (event.target.matches('[data-range-input]')) updateRangeOutput(event.target);
            renderPreview();
        });
        form.addEventListener('reset', () => window.setTimeout(() => toggleFields(form), 0));
        form.querySelectorAll('[data-range-input]').forEach(updateRangeOutput);
        toggleFields(form);
    });
    document.addEventListener('resource:saved', (event) => {
        const form = event.detail?.form;
        if (!form?.matches('.splash-config-form')) return;
        const badge = form.querySelector('.card-header .badge');
        const saved = event.detail?.data?.data;
        if (badge) {
            badge.textContent = window.splashScreenLabels.configured;
            badge.classList.remove('bg-secondary-lt');
            badge.classList.add('bg-success-lt');
        }
        updateSavedMedia(form, 'background', saved?.background_image);
        updateSavedMedia(form, 'overlay', saved?.overlay_image, saved?.overlay_lottie);
    });

    function updateSavedMedia(form, kind, imageUrl, lottieUrl = null) {
        const fieldSelector = kind === 'background' ? '[data-splash-field="image"]' : '[data-splash-field="overlay-image"]';
        const field = form.querySelector(fieldSelector);
        const lottieField = form.querySelector('[data-splash-field="overlay-lottie"]');
        form.querySelector(`[data-splash-saved-media="${kind}"]`)?.remove();
        if (kind === 'overlay') lottieField?.querySelector('[data-splash-saved-media="overlay"]')?.remove();

        if (imageUrl && field) {
            const wrapper = document.createElement('div');
            wrapper.className = 'splash-saved-media mt-2';
            wrapper.dataset.splashSavedMedia = kind;
            const image = document.createElement('img');
            image.className = 'img-thumbnail splash-current-media';
            image.src = imageUrl;
            image.alt = window.splashScreenLabels.savedMediaAlt;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-outline-danger mt-2';
            remove.dataset.removeSplashMedia = kind;
            remove.textContent = kind === 'background' ? window.splashScreenLabels.removeBackgroundImage : window.splashScreenLabels.removeOverlayImage;
            wrapper.append(image, remove);
            field.append(wrapper);
        }

        if (lottieUrl && lottieField) {
            const wrapper = document.createElement('div');
            wrapper.className = 'splash-saved-media mt-2';
            wrapper.dataset.splashSavedMedia = kind;
            const link = document.createElement('a');
            link.href = lottieUrl;
            link.target = '_blank';
            link.rel = 'noopener';
            link.textContent = window.splashScreenLabels.currentFile;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-outline-danger mt-2';
            remove.dataset.removeSplashMedia = kind;
            remove.textContent = window.splashScreenLabels.removeOverlayMedia;
            wrapper.append(link, remove);
            lottieField.append(wrapper);
        }
    }

    document.addEventListener('click', async (event) => {
        const screenButton = event.target.closest('[data-remove-splash-screen]');
        if (screenButton) {
            if (!window.Swal || typeof window.Swal.fire !== 'function') {
                if (window.Toast?.fire) window.Toast.fire({icon: 'error', title: window.splashScreenLabels.confirmationUnavailable});
                return;
            }

            const result = await window.Swal.fire({
                icon: 'warning',
                title: window.splashScreenLabels.removeScreenTitle,
                text: window.splashScreenLabels.removeScreenConfirmation,
                showCancelButton: true,
                confirmButtonText: window.splashScreenLabels.removeScreenConfirm,
                cancelButtonText: window.splashScreenLabels.cancel,
                confirmButtonColor: '#d63939',
            });
            if (!result.isConfirmed) return;

            screenButton.disabled = true;
            try {
                const response = await window.axios.delete(screenButton.dataset.url, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                });
                window.Toast?.fire({icon: 'success', title: response.data?.message || window.splashScreenLabels.screenRemoved});
                window.setTimeout(() => window.location.reload(), 400);
            } catch (error) {
                screenButton.disabled = false;
                window.Toast?.fire({icon: 'error', title: error.response?.data?.message || window.splashScreenLabels.removeScreenFailed});
            }
            return;
        }

        const button = event.target.closest('[data-remove-splash-media]');
        if (!button) return;
        if (!window.Swal || typeof window.Swal.fire !== 'function') {
            if (window.Toast?.fire) window.Toast.fire({icon: 'error', title: window.splashScreenLabels.confirmationUnavailable});
            return;
        }

        const result = await window.Swal.fire({
            icon: 'warning',
            title: button.textContent.trim(),
            text: window.splashScreenLabels.removeMediaConfirmation,
            showCancelButton: true,
            confirmButtonText: window.splashScreenLabels.removeMediaConfirm,
            cancelButtonText: window.splashScreenLabels.cancel,
            confirmButtonColor: '#d63939',
        });
        if (!result.isConfirmed) return;

        const form = button.closest('.splash-config-form');
        const kind = button.dataset.removeSplashMedia;
        form.querySelector(`[data-splash-saved-media="${kind}"]`)?.remove();

        const inputName = kind === 'background'
            ? 'background_image'
            : form.querySelector('[data-splash-overlay-type]')?.value === 'lottie' ? 'overlay_lottie' : 'overlay_image';
        const input = form.querySelector(`[name="${inputName}"]`);
        const pond = input && window.FilePond ? FilePond.find(input) : null;
        if (pond) pond.removeFiles();
        selectedFiles.delete(input);

        if (kind === 'background') {
            const backgroundType = form.querySelector('[data-splash-background-type]');
            const backgroundColor = form.querySelector('[name="background_color"]');
            if (!backgroundColor.value) backgroundColor.value = '#f8f8fc';
            backgroundType.value = 'color';
            backgroundType.dispatchEvent(new Event('change', {bubbles: true}));
        } else {
            const overlayToggle = form.querySelector('[data-splash-overlay-toggle]');
            overlayToggle.checked = false;
            overlayToggle.dispatchEvent(new Event('change', {bubbles: true}));
        }
    });

    selectSlot(activeSurface, activeSlots[activeSurface]);
    renderPreview();
})();
