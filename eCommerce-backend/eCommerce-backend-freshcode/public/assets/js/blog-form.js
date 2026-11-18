document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('blog-form');
    const imageInput = document.getElementById('blog-image');
    const removeImageInput = document.getElementById('blog-remove-image');
    const currentImage = document.getElementById('blog-current-image');
    const tagInput = document.getElementById('blog-tags');
    const categoryInput = document.getElementById('blog-category-ids');
    const productInput = document.getElementById('blog-product-ids');
    let imagePond = null;

    if (window.TomSelect && window.remove_button && !window.blogRemoveButtonRegistered) {
        window.TomSelect.define('remove_button', window.remove_button);
        window.blogRemoveButtonRegistered = true;
    }

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

    if (tagInput && window.TomSelect && !tagInput.tomselect) {
        new TomSelect(tagInput, {
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            controlInput: '<input>',
            plugins: ['remove_button'],
            create: true,
            createOnBlur: true,
            persist: false,
            maxItems: 30,
            placeholder: tagInput.dataset.placeholder,
        });
    }

    if (categoryInput && window.TomSelect && !categoryInput.tomselect) {
        new TomSelect(categoryInput, {
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            controlInput: '<input>',
            plugins: ['remove_button'],
            placeholder: categoryInput.dataset.placeholder,
            allowEmptyOption: true,
        });
    }

    if (productInput && window.TomSelect && !productInput.tomselect) {
        let selected = [];
        try { selected = JSON.parse(productInput.dataset.selected || '[]'); } catch (error) {}
        const endpoint = productInput.dataset.endpoint;
        const preload = productInput.dataset.preload === 'focus';
        const preloadLimit = parseInt(productInput.dataset.preloadLimit || '15', 10);
        const extractRows = payload => Array.isArray(payload) ? payload : (Array.isArray(payload?.data) ? payload.data : []);
        const appendQuery = (url, query) => url + (url.includes('?') ? '&' : '?') + query;
        const products = new TomSelect(productInput, {
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            controlInput: '<input>',
            plugins: ['remove_button'],
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            placeholder: productInput.dataset.placeholder,
            preload: preload ? 'focus' : false,
            load(query, callback) {
                if (!query.length && !preload) return callback();
                const params = 'search=' + encodeURIComponent(query || '') + '&limit=' + encodeURIComponent(preloadLimit);
                fetch(appendQuery(endpoint, params), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(response => response.json())
                    .then(payload => callback(extractRows(payload)))
                    .catch(() => callback());
            },
        });

        if (selected.length) {
            const params = selected.map(value => 'find_in[]=' + encodeURIComponent(value)).join('&');
            fetch(appendQuery(endpoint, params), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(response => response.json())
                .then(payload => {
                    extractRows(payload).forEach(option => products.addOption(option));
                    products.setValue(selected.map(String), true);
                })
                .catch(() => {});
        }
    }

    document.getElementById('blog-image-remove')?.addEventListener('click', () => {
        imagePond?.removeFiles();
        removeImageInput.value = '1';
        currentImage?.classList.add('d-none');
    });

    form?.addEventListener('submit', () => {
        window.hugeRTE?.triggerSave?.();
        window.tinymce?.triggerSave?.();
        window.tinyMCE?.triggerSave?.();
    }, true);
});
