document.addEventListener('DOMContentLoaded', () => {
    const ensureImagePreviewModal = () => {
        let modal = document.getElementById('dynamic-field-image-preview-modal');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = 'dynamic-field-image-preview-modal';
        modal.className = 'modal fade';
        modal.tabIndex = -1;
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = '<div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content bg-dark"><div class="modal-header border-0"><button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body pt-0 text-center"><img class="img-fluid rounded" alt="Uploaded document preview" style="max-height: 82vh; object-fit: contain;"></div></div></div>';
        document.body.appendChild(modal);
        const style = document.createElement('style');
        style.textContent = '.filepond--image-preview-wrapper, .filepond--image-preview { cursor: zoom-in; }';
        document.head.appendChild(style);
        return modal;
    };

    if (!window.dynamicFieldImagePreviewReady) {
        window.dynamicFieldImagePreviewReady = true;
        ensureImagePreviewModal();
        document.addEventListener('click', (event) => {
            const root = event.target.closest('.filepond--root.js-dynamic-filepond');
            if (!root) return;
            if (event.target.closest('.filepond--file-action-button, .filepond--browser')) return;
            if (!root.querySelector('.filepond--item')) return;

            const input = root.querySelector('input[type="file"]');
            const pond = window.FilePond ? (FilePond.find(root) || (input && FilePond.find(input))) : null;
            const pondFile = pond?.getFiles().slice(-1)[0];
            const src = pondFile?.file instanceof Blob
                ? URL.createObjectURL(pondFile.file)
                : (typeof pondFile?.source === 'string' ? pondFile.source : (root.dataset.imageUrl || input?.dataset.imageUrl));
            if (!src) return;

            event.preventDefault();
            event.stopPropagation();
            const modal = ensureImagePreviewModal();
            const image = modal.querySelector('img');
            image.src = src;
            if (window.bootstrap?.Modal) {
                bootstrap.Modal.getOrCreateInstance(modal).show();
            } else {
                window.open(src, '_blank', 'noopener');
            }
            modal.addEventListener('hidden.bs.modal', () => {
                image.removeAttribute('src');
                if (src.startsWith('blob:')) URL.revokeObjectURL(src);
            }, { once: true });
        }, true);
    }

    const form = document.querySelector('form[data-country-fields-url]');
    const countrySelect = document.getElementById('select-countries');
    if (!form || !countrySelect) return;

    let requestVersion = 0;
    let values = {};
    try {
        values = JSON.parse(form.dataset.countryFieldValues || '{}') || {};
    } catch (error) {
        values = {};
    }

    const initializeFilePond = (container, localFiles = {}) => {
        if (!window.FilePond) return;
        container.querySelectorAll('.js-dynamic-filepond').forEach((input) => {
            if (input.dataset.filepondReady === '1') return;
            const uploadName = input.getAttribute('name');
            if (!uploadName) return;
            const key = uploadName.match(/^dynamic_fields\[([^\]]+)\]$/)?.[1];
            input.dataset.filepondReady = '1';
            const pond = FilePond.create(input, {
                name: uploadName,
                allowImagePreview: true,
                acceptedFileTypes: ['image/jpeg', 'image/png', 'image/webp'],
                maxFileSize: '5MB',
                labelIdle: input.dataset.filepondPlaceholder || 'Drop or browse an image',
                credits: false,
                storeAsFile: true,
                files: input.dataset.imageUrl ? [{ source: input.dataset.imageUrl, options: { type: 'remote' } }] : [],
            });
            if (key && localFiles[key]) pond.addFile(localFiles[key]);
        });
    };

    const loadCountryFields = async () => {
        const selectedValue = countrySelect.value;
        const selectedOption = countrySelect.tomselect?.options?.[selectedValue];
        const countryIso2 = selectedOption?.iso2 || (/^[a-z]{2}$/i.test(selectedValue) ? selectedValue : null);
        if (!countryIso2) return;

        const version = ++requestVersion;
        const containers = [...form.querySelectorAll('.js-country-dynamic-fields')];
        const localFiles = {};
        containers.forEach((container) => {
            container.querySelectorAll('[name^="dynamic_fields["]').forEach((input) => {
                const key = input.name.match(/^dynamic_fields\[([^\]]+)\]$/)?.[1];
                if (!key) return;
                if (input.type !== 'file') {
                    values[key] = input.value;
                    return;
                }
                const pond = window.FilePond?.find(input);
                const localFile = pond?.getFiles().find((file) => file.origin !== window.FilePond.FileOrigin?.LIMBO)?.file;
                if (localFile) localFiles[key] = localFile;
                values[key] = input.dataset.imageUrl || values[key] || '';
            });
        });

        try {
            const endpoint = form.dataset.countryFieldsUrl.replace('__COUNTRY__', encodeURIComponent(countryIso2));
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Unable to load fields for this country.');
            if (version !== requestVersion) return;

            containers.forEach((container) => {
                container.innerHTML = data.sections?.[container.dataset.section] || '';
                container.querySelectorAll('[name^="dynamic_fields["]').forEach((input) => {
                    const key = input.name.match(/^dynamic_fields\[([^\]]+)\]$/)?.[1];
                    if (!key) return;
                    if (input.type === 'file') {
                        input.dataset.imageUrl = values[key] || '';
                    } else if (Object.prototype.hasOwnProperty.call(values, key)) {
                        input.value = values[key] ?? '';
                    }
                });
                initializeFilePond(container, localFiles);
            });
        } catch (error) {
            if (version === requestVersion) {
                window.Toast?.fire({ icon: 'error', title: error.message || 'Unable to load fields for this country.' });
            }
        }
    };

    countrySelect.addEventListener('change', loadCountryFields);

    form.querySelectorAll('[data-country-field-manager]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const selectedValue = countrySelect.value;
            const selectedOption = countrySelect.tomselect?.options?.[selectedValue];
            const countryIso2 = selectedOption?.iso2;
            if (!countryIso2) return;
            event.preventDefault();
            const target = new URL(link.href, window.location.origin);
            target.searchParams.set('section', link.dataset.section);
            target.searchParams.append('country_codes[]', countryIso2);
            window.location.href = target.toString();
        });
    });
});
