'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('onboarding-page-modal');
    const modalBody = modalElement?.querySelector('.modal-body');
    const form = document.getElementById('onboarding-page-form');
    const imageInput = document.getElementById('onboarding-image');
    const imageLabel = document.getElementById('onboarding-image-label');
    const currentImage = document.getElementById('onboarding-current-image');
    const title = document.getElementById('onboarding-page-modal-title');
    const colorPicker = document.getElementById('onboarding-background-color');
    const colorText = document.getElementById('onboarding-background-color-text');
    const fontColorPicker = document.getElementById('onboarding-font-color');
    const fontColorText = document.getElementById('onboarding-font-color-text');
    const nextButtonBackgroundColor = document.getElementById('onboarding-next-button-background-color');
    const nextButtonTextColor = document.getElementById('onboarding-next-button-text-color');
    const skipToggles = document.querySelectorAll('[data-onboarding-skip-enabled]');
    const backgroundColorField = document.getElementById('onboarding-background-color-field');
    const backgroundImageField = document.getElementById('onboarding-background-image-field');
    const backgroundImageInput = document.getElementById('onboarding-background-image');
    const backgroundImageLabel = document.getElementById('onboarding-background-image-label');
    const currentBackgroundImage = document.getElementById('onboarding-current-background-image');
    const previewScreen = document.getElementById('onboarding-phone-screen');
    const previewTitle = document.getElementById('onboarding-preview-title');
    const previewSubtitle = document.getElementById('onboarding-preview-subtitle');
    const previewImage = document.getElementById('onboarding-preview-image');
    const previewBackground = document.getElementById('onboarding-preview-background');
    const modalPreviewScreen = document.getElementById('onboarding-modal-preview-screen');
    const modalPreviewTitle = document.getElementById('onboarding-modal-preview-title');
    const modalPreviewSubtitle = document.getElementById('onboarding-modal-preview-subtitle');
    const modalPreviewImage = document.getElementById('onboarding-modal-preview-image');
    const modalPreviewBackground = document.getElementById('onboarding-modal-preview-background');
    const modalPreviewSkip = document.getElementById('onboarding-modal-preview-skip');
    const modalPreviewNext = document.getElementById('onboarding-modal-preview-next');
    const titleInput = document.getElementById('onboarding-title');
    const subtitleInput = document.getElementById('onboarding-subtitle');
    const previewHint = document.getElementById('onboarding-preview-hint');
    const previewNext = document.getElementById('onboarding-preview-next');
    const previewSkip = document.getElementById('onboarding-preview-skip');
    const previewDots = document.getElementById('onboarding-preview-dots');
    const previewBack = document.getElementById('onboarding-preview-back');
    const pagesTable = document.getElementById('onboarding-pages-table');
    const surfaceInput = document.getElementById('onboarding-surface');
    const labels = window.onboardingPageLabels || {};
    const routes = window.onboardingPageRoutes || {};
    let activeSurface = 'customer_app';
    const skipEnabledBySurface = {...(labels.skipEnabled || {})};
    let pond = null;
    let backgroundPond = null;
    let hasCurrentBackgroundImage = false;
    let hasCurrentImage = false;
    let selectedMainImageUrl = null;
    let selectedBackgroundImageUrl = null;
    let previewPages = [];
    let activePreviewIndex = 0;
    let previewRequestSequence = 0;

    function renderPreviewPage() {
        const page = previewPages[activePreviewIndex];
        if (!page) {
            previewScreen.classList.remove('onboarding-phone-image-only');
            previewTitle.textContent = labels.previewTitle || '';
            previewSubtitle.textContent = labels.previewSubtitle || '';
            previewTitle.style.color = '#1c2737';
            previewSubtitle.style.color = '#85858c';
            previewScreen.style.setProperty('--onboarding-indicator-color', '#ffb511');
            previewNext.style.backgroundColor = '#ffb511';
            previewNext.style.color = '#ffffff';
            previewSkip.parentElement.classList.add('d-none');
            previewImage.removeAttribute('src');
            previewImage.classList.add('d-none');
            previewBackground.removeAttribute('src');
            previewBackground.classList.add('d-none');
            previewScreen.style.backgroundColor = '#ffffff';
            previewDots.replaceChildren();
            previewBack.disabled = true;
            previewNext.disabled = true;
            previewHint.textContent = labels.previewEmpty || '';
            previewHint.classList.remove('d-none');
            return;
        }

        previewTitle.textContent = page.title || '';
        previewSubtitle.textContent = page.subtitle || '';
        previewTitle.style.color = page.font_color || '#172033';
        previewSubtitle.style.color = page.font_color || '#172033';
        const nextBackground = page.next_button_background_color || '#ffb511';
        previewScreen.style.setProperty('--onboarding-indicator-color', nextBackground);
        previewNext.style.backgroundColor = nextBackground;
        previewNext.style.color = page.next_button_text_color || '#ffffff';
        previewSkip.style.color = nextBackground;
        previewSkip.parentElement.classList.toggle('d-none', !page.show_skip);
        previewImage.src = page.image || '';
        previewImage.alt = page.title || '';
        previewImage.classList.toggle('d-none', !page.image);
        const imageOnly = Boolean(page.image && !page.background_image && !String(page.title || '').trim() && !String(page.subtitle || '').trim());
        previewScreen.classList.toggle('onboarding-phone-image-only', imageOnly);
        const useBackgroundImage = page.background_type === 'image' && page.background_image;
        previewBackground.src = useBackgroundImage ? page.background_image : '';
        previewBackground.classList.toggle('d-none', !useBackgroundImage);
        previewScreen.style.backgroundColor = page.background_color || '#ffffff';
        previewDots.replaceChildren(...previewPages.map(function (_, index) {
            const dot = document.createElement('span');
            dot.className = 'onboarding-phone-page-dot' + (index === activePreviewIndex ? ' is-active' : '');
            dot.setAttribute('aria-current', index === activePreviewIndex ? 'step' : 'false');
            return dot;
        }));
        previewNext.disabled = activePreviewIndex >= previewPages.length - 1;
        previewBack.disabled = activePreviewIndex === 0;
        previewHint.classList.add('d-none');
    }

    function renderModalPreview() {
        if (!modalPreviewScreen) return;

        const pageTitle = titleInput.value.trim();
        const pageSubtitle = subtitleInput.value.trim();
        const mainImage = selectedMainImageUrl || (!currentImage.classList.contains('d-none') ? currentImage.src : '');
        const backgroundType = document.querySelector('[name="background_type"]:checked')?.value;
        const backgroundImage = backgroundType === 'image'
            ? selectedBackgroundImageUrl || (!currentBackgroundImage.classList.contains('d-none') ? currentBackgroundImage.src : '')
            : '';
        const imageOnly = Boolean(mainImage && !backgroundImage && !pageTitle && !pageSubtitle);

        modalPreviewTitle.textContent = pageTitle || '';
        modalPreviewSubtitle.textContent = pageSubtitle || '';
        modalPreviewTitle.style.color = fontColorPicker.value || '#172033';
        modalPreviewSubtitle.style.color = fontColorPicker.value || '#172033';
        modalPreviewImage.src = mainImage;
        modalPreviewImage.alt = pageTitle;
        modalPreviewImage.classList.toggle('d-none', !mainImage);
        modalPreviewBackground.src = backgroundImage;
        modalPreviewBackground.classList.toggle('d-none', !backgroundImage);
        modalPreviewScreen.classList.toggle('onboarding-phone-image-only', imageOnly);
        modalPreviewScreen.style.backgroundColor = backgroundType === 'color' ? colorPicker.value : '#ffffff';
        const nextBackground = nextButtonBackgroundColor.value || '#ffb511';
        modalPreviewScreen.style.setProperty('--onboarding-indicator-color', nextBackground);
        modalPreviewNext.style.backgroundColor = nextBackground;
        modalPreviewNext.style.color = nextButtonTextColor.value || '#ffffff';
        modalPreviewSkip.style.color = nextBackground;
        modalPreviewSkip.parentElement.classList.toggle('d-none', !(skipEnabledBySurface[surfaceInput.value] ?? true));
    }

    function replaceObjectUrl(currentUrl, file) {
        if (currentUrl) URL.revokeObjectURL(currentUrl);
        return file ? URL.createObjectURL(file) : null;
    }

    modalElement?.addEventListener('hidden.bs.modal', function () {
        selectedMainImageUrl = replaceObjectUrl(selectedMainImageUrl, null);
        selectedBackgroundImageUrl = replaceObjectUrl(selectedBackgroundImageUrl, null);
    });

    function loadPreviewPages(surface) {
        const requestSequence = ++previewRequestSequence;
        previewPages = [];
        activePreviewIndex = 0;
        renderPreviewPage();
        previewHint.textContent = labels.previewLoading || '';
        previewHint.classList.remove('d-none');

        axios.get(routes.preview, {params: {surface: surface}}).then(function (response) {
            if (requestSequence !== previewRequestSequence || surface !== activeSurface) return;
            previewPages = Array.isArray(response.data?.data) ? response.data.data : [];
            activePreviewIndex = 0;
            renderPreviewPage();
        }).catch(function () {
            if (requestSequence !== previewRequestSequence || surface !== activeSurface) return;
            previewHint.textContent = labels.previewEmpty || '';
            previewHint.classList.remove('d-none');
        });
    }

    function setSurfaceEnabled(surface, enabled) {
        const toggle = document.querySelector('[data-onboarding-enabled][data-surface="' + surface + '"]');
        const status = document.querySelector('[data-onboarding-enabled-status="' + surface + '"]');
        if (toggle) toggle.checked = enabled;
        if (status) status.textContent = enabled ? labels.enabled : labels.disabled;
    }

    function showSurfaceToggle(surface) {
        document.querySelectorAll('[data-onboarding-toggle-wrapper]').forEach(function (wrapper) {
            wrapper.classList.toggle('d-none', wrapper.dataset.onboardingToggleWrapper !== surface);
        });
        document.querySelectorAll('[data-onboarding-skip-wrapper]').forEach(function (wrapper) {
            wrapper.classList.toggle('d-none', wrapper.dataset.onboardingSkipWrapper !== surface);
        });
    }

    document.querySelectorAll('[data-onboarding-enabled]').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            const enabled = toggle.checked;
            const previous = !enabled;
            toggle.disabled = true;

            axios.patch(toggle.dataset.url, {enabled: enabled}, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json'
                }
            }).then(function (response) {
                if (!response.data?.success) throw new Error(response.data?.message || labels.toggleFailed);
                const result = response.data.data;
                setSurfaceEnabled(result.surface, result.enabled);
                window.Toast?.fire({icon: 'success', title: response.data.message || labels.toggleUpdated});
            }).catch(function (error) {
                setSurfaceEnabled(toggle.dataset.surface, previous);
                window.Toast?.fire({icon: 'error', title: error.response?.data?.message || error.message || labels.toggleFailed});
            }).finally(function () {
                toggle.disabled = false;
            });
        });
    });

    previewNext?.addEventListener('click', function () {
        if (activePreviewIndex >= previewPages.length - 1) return;
        activePreviewIndex += 1;
        renderPreviewPage();
    });

    previewBack?.addEventListener('click', function () {
        if (activePreviewIndex <= 0) return;
        activePreviewIndex -= 1;
        renderPreviewPage();
    });

    previewSkip?.addEventListener('click', function () {
        if (previewPages.length === 0) return;
        activePreviewIndex = previewPages.length - 1;
        renderPreviewPage();
    });

    if (window.jQuery && pagesTable) {
        window.jQuery(pagesTable).on('xhr.dt', function () {
            loadPreviewPages(activeSurface);
        });
    }

    loadPreviewPages(activeSurface);

    modalBody?.addEventListener('wheel', function (event) {
        if (event.ctrlKey || modalBody.scrollHeight <= modalBody.clientHeight) return;
        event.preventDefault();
        const multiplier = event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? modalBody.clientHeight : 1;
        modalBody.scrollTop += event.deltaY * multiplier;
    }, {passive: false});

    if (window.FilePond && imageInput) {
        pond = window.FilePond.create(imageInput, {
            storeAsFile: true,
            allowMultiple: false,
            acceptedFileTypes: ['image/jpeg', 'image/png', 'image/webp'],
            maxFileSize: '5MB',
            credits: false
        });
        pond.on('addfile', function (error, file) {
            if (error) return;
            selectedMainImageUrl = replaceObjectUrl(selectedMainImageUrl, file.file);
            currentImage.classList.add('d-none');
            renderModalPreview();
        });
        pond.on('removefile', function () {
            selectedMainImageUrl = replaceObjectUrl(selectedMainImageUrl, null);
            currentImage.classList.toggle('d-none', !hasCurrentImage);
            renderModalPreview();
        });
    }

    if (window.FilePond && backgroundImageInput) {
        backgroundPond = window.FilePond.create(backgroundImageInput, {
            storeAsFile: true,
            allowMultiple: false,
            acceptedFileTypes: ['image/jpeg', 'image/png', 'image/webp'],
            maxFileSize: '5MB',
            credits: false
        });
        backgroundPond.on('addfile', function (error) {
            if (error) return;
            const file = backgroundPond.getFile();
            selectedBackgroundImageUrl = replaceObjectUrl(selectedBackgroundImageUrl, file?.file);
            currentBackgroundImage.classList.add('d-none');
            renderModalPreview();
        });
        backgroundPond.on('removefile', function () {
            selectedBackgroundImageUrl = replaceObjectUrl(selectedBackgroundImageUrl, null);
            currentBackgroundImage.classList.toggle('d-none', !hasCurrentBackgroundImage);
            renderModalPreview();
        });
    }

    form?.addEventListener('formdata', function (event) {
        event.formData.delete('background_image');
        const backgroundType = document.querySelector('[name="background_type"]:checked')?.value;
        if (backgroundType !== 'image') return;
        const file = backgroundPond?.getFile()?.file || backgroundImageInput.files?.[0];
        if (file) event.formData.append('background_image', file, file.name);
    });

    function setBackgroundType(type) {
        const useImage = type === 'image';
        backgroundColorField.classList.toggle('d-none', useImage);
        backgroundImageField.classList.toggle('d-none', !useImage);
        colorPicker.required = !useImage;
        backgroundImageInput.required = useImage && !hasCurrentBackgroundImage;
        backgroundImageLabel.classList.toggle('required', useImage && !hasCurrentBackgroundImage);
        backgroundPond?.setOptions({required: useImage && !hasCurrentBackgroundImage});
    }

    function resetForm() {
        form.reset();
        form.action = routes.store;
        surfaceInput.value = activeSurface;
        if (pond) pond.removeFiles();
        if (backgroundPond) backgroundPond.removeFiles();
        selectedMainImageUrl = replaceObjectUrl(selectedMainImageUrl, null);
        selectedBackgroundImageUrl = replaceObjectUrl(selectedBackgroundImageUrl, null);
        currentImage.classList.add('d-none');
        currentImage.removeAttribute('src');
        hasCurrentImage = false;
        currentBackgroundImage.classList.add('d-none');
        currentBackgroundImage.removeAttribute('src');
        hasCurrentBackgroundImage = false;
        setImageRequired(false);
        colorPicker.value = '#ffffff';
        colorText.value = '#ffffff';
        fontColorPicker.value = '#172033';
        fontColorText.value = '#172033';
        nextButtonBackgroundColor.value = '#ffb511';
        nextButtonTextColor.value = '#ffffff';
        document.querySelector('[name="background_type"][value="color"]').checked = true;
        setBackgroundType('color');
        renderModalPreview();
        title.textContent = labels.create || '';
        setSubmitLabel(labels.create || '');
    }

    function setSubmitLabel(label) {
        const submitLabel = document.getElementById('onboarding-page-submit-label');
        if (submitLabel) submitLabel.textContent = label;
    }

    function setImageRequired(required) {
        imageInput.required = required;
        imageLabel.classList.toggle('required', required);
        pond?.setOptions({required: required});
    }

    document.getElementById('add-onboarding-page')?.addEventListener('click', function () {
        resetForm();
        form.action = routes.store;
        surfaceInput.value = activeSurface;
        title.textContent = labels.create || '';
        setSubmitLabel(labels.create || '');
    });

    modalElement?.addEventListener('show.bs.modal', function (event) {
        resetForm();
        const button = event.relatedTarget?.closest('.edit-onboarding-page');
        if (!button) {
            form.action = routes.store;
            surfaceInput.value = activeSurface;
            title.textContent = labels.create || '';
            setSubmitLabel(labels.create || '');
            renderModalPreview();
            return;
        }

        const id = button.dataset.id;
        form.action = routes.update.replace('__id__', id);
        surfaceInput.value = button.dataset.surface || activeSurface;
        document.getElementById('onboarding-title').value = button.dataset.title || '';
        document.getElementById('onboarding-subtitle').value = button.dataset.subtitle || '';
        colorPicker.value = button.dataset.backgroundColor || '#ffffff';
        colorText.value = colorPicker.value;
        fontColorPicker.value = button.dataset.fontColor || '#172033';
        fontColorText.value = fontColorPicker.value;
        nextButtonBackgroundColor.value = button.dataset.nextButtonBackgroundColor || '#ffb511';
        nextButtonTextColor.value = button.dataset.nextButtonTextColor || '#ffffff';
        setImageRequired(false);
        const backgroundType = button.dataset.backgroundType || 'color';
        hasCurrentBackgroundImage = Boolean(button.dataset.backgroundImage);
        if (hasCurrentBackgroundImage) {
            currentBackgroundImage.src = button.dataset.backgroundImage;
            currentBackgroundImage.classList.remove('d-none');
        }
        document.querySelector('[name="background_type"][value="' + backgroundType + '"]').checked = true;
        setBackgroundType(backgroundType);
        currentImage.src = button.dataset.image || '';
        hasCurrentImage = Boolean(button.dataset.image);
        currentImage.classList.toggle('d-none', !hasCurrentImage);
        title.textContent = labels.edit || '';
        setSubmitLabel(labels.edit || '');
        renderModalPreview();
    });

    document.addEventListener('click', function (event) {
        const surfaceTab = event.target.closest('[data-onboarding-surface]');
        if (surfaceTab) {
            activeSurface = surfaceTab.dataset.onboardingSurface;
            surfaceInput.value = activeSurface;
            showSurfaceToggle(activeSurface);
            loadPreviewPages(activeSurface);
            document.querySelectorAll('[data-onboarding-surface]').forEach(function (tab) {
                const selected = tab === surfaceTab;
                tab.classList.toggle('active', selected);
                tab.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
            const table = window.jQuery?.('#onboarding-pages-table').DataTable();
            if (table) table.ajax.url(routes.datatable + '?surface=' + encodeURIComponent(activeSurface)).load();
            return;
        }

    });

    colorPicker?.addEventListener('input', function () {
        colorText.value = colorPicker.value;
        renderModalPreview();
    });
    colorText?.addEventListener('input', function () {
        if (/^#[0-9a-fA-F]{6}$/.test(colorText.value)) {
            colorPicker.value = colorText.value;
            renderModalPreview();
        }
    });
    fontColorPicker?.addEventListener('input', function () {
        fontColorText.value = fontColorPicker.value;
        renderModalPreview();
    });
    fontColorText?.addEventListener('input', function () {
        if (/^#[0-9a-fA-F]{6}$/.test(fontColorText.value)) {
            fontColorPicker.value = fontColorText.value;
            renderModalPreview();
        }
    });

    nextButtonBackgroundColor?.addEventListener('input', renderModalPreview);
    nextButtonTextColor?.addEventListener('input', renderModalPreview);
    skipToggles.forEach(function (skipToggle) {
        skipToggle.addEventListener('change', function () {
        const surface = skipToggle.dataset.surface;
        const enabled = skipToggle.checked;
        const previous = !enabled;
        skipToggle.disabled = true;
        axios.patch(routes.skipEnabled, {surface: surface, enabled: enabled}, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json'
            }
        }).then(function (response) {
            if (!response.data?.success) throw new Error(response.data?.message || labels.skipToggleFailed);
            const skipEnabled = Boolean(response.data.data.enabled);
            skipEnabledBySurface[surface] = skipEnabled;
            skipToggle.checked = skipEnabled;
            const status = document.querySelector('[data-onboarding-skip-status="' + surface + '"]');
            if (status) status.textContent = skipEnabled ? labels.enabled : labels.disabled;
            if (activeSurface === surface) {
                previewPages = previewPages.map((page) => ({...page, show_skip: skipEnabled}));
                renderPreviewPage();
            }
            if (surfaceInput.value === surface) renderModalPreview();
            window.Toast?.fire({icon: 'success', title: response.data.message || labels.skipToggleUpdated});
        }).catch(function (error) {
            skipToggle.checked = previous;
            window.Toast?.fire({icon: 'error', title: error.response?.data?.message || error.message || labels.skipToggleFailed});
        }).finally(function () {
            skipToggle.disabled = false;
        });
        });
    });

    titleInput?.addEventListener('input', renderModalPreview);
    subtitleInput?.addEventListener('input', renderModalPreview);

    document.querySelectorAll('[name="background_type"]').forEach(function (input) {
        input.addEventListener('change', function () {
            setBackgroundType(input.value);
            renderModalPreview();
        });
    });

    document.getElementById('refresh-onboarding-pages')?.addEventListener('click', function () {
        window.jQuery?.('#onboarding-pages-table').DataTable().ajax.reload(null, false);
    });

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.delete-onboarding-page');
        if (!button) return;

        if (!window.Swal) {
            window.Toast?.fire({icon: 'error', title: labels.confirmationUnavailable || 'Confirmation is unavailable.'});
            return;
        }

        window.Swal.fire({
            title: labels.deleteConfirm || '',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: labels.delete || 'Delete',
            cancelButtonText: labels.cancel || 'Cancel',
            reverseButtons: true
        }).then(function (result) {
            if (!result.isConfirmed) return;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            axios.delete(routes.delete.replace('__id__', button.dataset.id), {
                headers: {'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json'}
            }).then(function (response) {
                window.Toast?.fire({icon: 'success', title: response.data.message});
                if (response.data?.data?.surface && typeof response.data.data.enabled === 'boolean') {
                    setSurfaceEnabled(response.data.data.surface, response.data.data.enabled);
                }
                window.jQuery?.('#onboarding-pages-table').DataTable().ajax.reload(null, false);
            }).catch(function (error) {
                window.Toast?.fire({icon: 'error', title: error.response?.data?.message || 'Unable to delete this page.'});
            });
        });
    });

});
