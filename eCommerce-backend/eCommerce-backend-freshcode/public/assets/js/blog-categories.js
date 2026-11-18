document.addEventListener('DOMContentLoaded', () => {
    const tableId = 'blog-categories-table';
    const table = document.getElementById(tableId);
    const modalElement = document.getElementById('blog-category-modal');
    const form = document.getElementById('blog-category-form');
    const title = document.getElementById('blog-category-modal-title');
    const imageInput = document.getElementById('blog-category-image');
    const currentImage = document.getElementById('blog-category-current-image');
    const removeImage = document.getElementById('blog-category-remove-image');
    const deleteForm = document.getElementById('blog-category-delete-form');
    let imagePond = null;

    const field = (id) => document.getElementById(id);
    const showModal = () => {
        if (window.jQuery && typeof window.jQuery(modalElement).modal === 'function') {
            window.jQuery(modalElement).modal('show');
            return;
        }

        const modalApi = window.bootstrap?.Modal || window.tabler?.bootstrap?.Modal;
        if (modalApi) {
            modalApi.getOrCreateInstance(modalElement).show();
            return;
        }

        Toast.fire({icon: 'error', title: form.dataset.errorText});
    };
    const resetImage = () => {
        if (imagePond) imagePond.removeFiles();
        if (currentImage) {
            currentImage.classList.add('d-none');
            currentImage.querySelector('img').removeAttribute('src');
        }
        if (removeImage) removeImage.value = '0';
    };

    if (imageInput && typeof FilePond !== 'undefined') {
        FilePond.registerPlugin(FilePondPluginImagePreview, FilePondPluginFileValidateType, FilePondPluginFileValidateSize);
        imagePond = FilePond.create(imageInput, {
            allowImagePreview: true,
            allowMultiple: false,
            acceptedFileTypes: ['image/jpeg', 'image/png', 'image/webp'],
            maxFileSize: '5MB',
            credits: false,
            storeAsFile: true,
        });
    }

    const resetForm = () => {
        form.reset();
        form.action = form.dataset.storeUrl;
        field('blog-category-method').value = 'POST';
        field('blog-category-sort-order').value = '0';
        field('blog-category-active').checked = true;
        title.textContent = form.dataset.createTitle;
        resetImage();
    };

    document.getElementById('blog-category-add')?.addEventListener('click', resetForm);

    document.getElementById('blog-categories-table')?.addEventListener('click', (event) => {
        const button = event.target.closest('.blog-category-edit');
        if (!button) return;

        const category = JSON.parse(button.dataset.category);
        resetForm();
        title.textContent = form.dataset.editTitle;
        form.action = form.dataset.updateUrl.replace('__ID__', category.id);
        field('blog-category-method').value = 'POST';
        field('blog-category-name').value = category.name || '';
        field('blog-category-slug').value = category.slug || '';
        field('blog-category-sort-order').value = category.sort_order ?? 0;
        field('blog-category-description').value = category.description || '';
        field('blog-category-active').checked = Boolean(category.is_active);
        const metadata = category.metadata || {};
        field('blog-category-seo-title').value = metadata.seo_title || '';
        field('blog-category-seo-keywords').value = Array.isArray(metadata.seo_keywords) ? metadata.seo_keywords.join(', ') : (metadata.seo_keywords || '');
        field('blog-category-seo-description').value = metadata.seo_description || '';
        if (category.image) {
            currentImage.querySelector('img').src = category.image;
            currentImage.classList.remove('d-none');
        }
        showModal();
    });

    document.getElementById('blog-category-image-remove')?.addEventListener('click', () => {
        if (imagePond) imagePond.removeFiles();
        removeImage.value = '1';
        currentImage.classList.add('d-none');
    });

    document.getElementById('blog-category-refresh')?.addEventListener('click', () => {
        if (window.DatatableUtils) window.DatatableUtils.refreshDatatable(tableId);
    });

    document.getElementById(tableId)?.addEventListener('click', async (event) => {
        const button = event.target.closest('.blog-category-delete');
        if (!button || !deleteForm) return;

        if (typeof Swal === 'undefined') {
            Toast.fire({icon: 'error', title: form.dataset.errorText});
            return;
        }

        const result = await Swal.fire({
            title: form.dataset.deleteTitle,
            text: form.dataset.deleteText.replace(':name', button.dataset.name),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: form.dataset.deleteConfirm,
            cancelButtonText: form.dataset.cancelText,
            reverseButtons: true,
        });
        if (!result.isConfirmed) return;

        try {
            const response = await axios.delete(form.dataset.deleteUrl.replace('__ID__', button.dataset.id), {
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
            });
            if (response.data?.success) {
                Toast.fire({icon: 'success', title: response.data.message});
                window.DatatableUtils?.refreshDatatable(tableId);
            } else {
                Toast.fire({icon: 'error', title: response.data?.message || form.dataset.errorText});
            }
        } catch (error) {
            Toast.fire({icon: 'error', title: error.response?.data?.message || form.dataset.errorText});
        }
    });

    modalElement?.addEventListener('hidden.bs.modal', () => {
        if (form) resetForm();
    });

    if (form) {
        form.dataset.storeUrl = form.action;
        form.dataset.createTitle = title.textContent;
    }

    if (table && window.jQuery && jQuery.fn.DataTable) {
        document.addEventListener('resource:saved', (event) => {
            if (event.detail?.form === form) resetForm();
        });
    }
});
