document.addEventListener('DOMContentLoaded', () => {
    const audienceEl = document.getElementById('audience_type');
    const targetTypeEl = document.getElementById('target_type');
    const usersEl = document.getElementById('select-users');
    const targetTypeWrapper = document.getElementById('target_type_wrapper');
    const targetIdWrapper = document.getElementById('target_id_wrapper');
    const titleInput = document.querySelector('[name="title"]');
    const messageInput = document.querySelector('[name="message"]');
    const imageInput = document.getElementById('app-notification-image-input');
    const collapsedTitle = document.getElementById('app-notification-collapsed-title');
    const previewMessage = document.getElementById('app-notification-preview-message');
    const previewImages = [
        document.getElementById('app-notification-preview-thumbnail'),
        document.getElementById('app-notification-preview-image')
    ].filter(Boolean);
    const expandedTitle = document.getElementById('app-notification-expanded-title');
    const expandedMessage = document.getElementById('app-notification-expanded-message');
    const previewCard = document.getElementById('app-notification-preview-card');
    const previewToggle = document.getElementById('app-notification-toggle');
    let previewImageUrl = null;

    const syncPreviewText = () => {
        const title = titleInput?.value.trim() || collapsedTitle?.dataset.placeholder || '';
        const message = messageInput?.value.trim() || previewMessage?.dataset.placeholder || '';
        if (collapsedTitle) collapsedTitle.textContent = title;
        if (previewMessage) previewMessage.textContent = message;
        if (expandedTitle) expandedTitle.textContent = title;
        if (expandedMessage) expandedMessage.textContent = message;
    };

    const syncPreviewImage = (file) => {
        if (previewImageUrl) URL.revokeObjectURL(previewImageUrl);
        previewImageUrl = file ? URL.createObjectURL(file) : null;
        previewImages.forEach((previewImage) => {
            previewImage.src = previewImageUrl || '';
            previewImage.classList.toggle('d-none', !previewImageUrl);
        });
    };

    previewToggle?.addEventListener('click', () => {
        const expanded = previewToggle.getAttribute('aria-expanded') !== 'true';
        previewToggle.setAttribute('aria-expanded', String(expanded));
        previewToggle.setAttribute('aria-label', expanded ? previewToggle.dataset.collapseLabel : previewToggle.dataset.expandLabel);
        previewCard?.classList.toggle('is-expanded', expanded);
        document.getElementById('app-notification-expanded')?.toggleAttribute('hidden', !expanded);
        const icon = previewToggle.querySelector('i');
        icon?.classList.toggle('ti-chevron-up', expanded);
        icon?.classList.toggle('ti-chevron-down', !expanded);
    });

    titleInput?.addEventListener('input', syncPreviewText);
    messageInput?.addEventListener('input', syncPreviewText);
    syncPreviewText();

    const bindImagePreview = (pond) => {
        if (!pond) return;
        pond.on('addfile', (error, item) => syncPreviewImage(error ? null : item?.file));
        pond.on('removefile', () => syncPreviewImage(null));
        const currentFile = pond.getFile();
        if (currentFile) syncPreviewImage(currentFile.file);
    };

    if (imageInput && window.FilePond) {
        const pond = FilePond.find(imageInput);
        if (pond) {
            bindImagePreview(pond);
        } else {
            imageInput.addEventListener('filepond:initialized', (event) => bindImagePreview(event.detail?.pond), {once: true});
        }
    }

    const typeMap = {
        seller: 'seller',
        rider: 'rider',
        customer: 'customer'
    };

    const toggleDisplay = (el, show) => {
        if (el) el.style.display = show ? '' : 'none';
    };

    const syncFormVisibility = () => {
        const audienceType = audienceEl?.value || 'customer';
        const targetType = targetTypeEl?.value;

        usersEl?.setAttribute('data-type', typeMap[audienceType] || 'customer');

        const isCustomer = audienceType === 'customer';

        // Toggle sections
        toggleDisplay(targetTypeWrapper, isCustomer);
        toggleDisplay(targetIdWrapper, isCustomer && !!targetType);
        initTomSelect();
        initTargetTomSelect();
    };

    audienceEl?.addEventListener('change', syncFormVisibility);
    targetTypeEl?.addEventListener('change', syncFormVisibility);

    syncFormVisibility();
});

let tomSelectUserInstance = null;
let tomSelectTargetInstance = null;

const getMarketIds = () => {
    const marketEl = document.getElementById('select-markets');
    if (!marketEl) return [];

    let values = [];

    if (marketEl.tomselect) {
        values = marketEl.tomselect.getValue();
    } else if (marketEl.value) {
        values = marketEl.value.split(',');
    }

    return (Array.isArray(values) ? values : [values])
        .flatMap(v => String(v).split(',')) // handles "14,1"
        .map(v => parseInt(v, 10))
        .filter(Number.isInteger);
};

const initTomSelect = () => {
    const userEl = document.getElementById('select-users');
    if (!userEl) return;

    if (tomSelectUserInstance) {
        tomSelectUserInstance.clear();
        tomSelectUserInstance.destroy();
    }

    tomSelectUserInstance = new TomSelect(userEl, {
        copyClassesToDropdown: false,
        dropdownParent: 'body',
        valueField: 'value',
        labelField: 'text',
        searchField: 'text',
        placeholder: 'User name',

        load: (query, callback) => {
            if (!query || query.trim().length < 2) return callback();

            axios.get(`${base_url}/admin/users/search`, {
                params: {
                    search: query.trim(),
                    type: userEl.dataset.type || 'customer',
                    market_ids: getMarketIds()
                }
            })
                .then(res => callback(res.data))
                .catch(() => callback());
        }
    });
};

const initTargetTomSelect = () => {
    const targetTypeMap = {
        product: {
            title: 'Product',
            url: '/api/products/search'
        },
        brand: {
            title: 'Brand',
            url: '/api/brands/search'
        },
        category: {
            title: 'Category',
            url: '/api/categories/search'
        },
        store: {
            title: 'Store',
            url: '/api/stores/search'
        },
    };
    const targetEl = document.getElementById('target_id');
    const targetTypeEl = document.getElementById('target_type');
    if (!targetEl) return;

    if (tomSelectTargetInstance) {
        tomSelectTargetInstance.clear();
        tomSelectTargetInstance.destroy();
    }


    tomSelectTargetInstance = new TomSelect(targetEl, {
        copyClassesToDropdown: false,
        dropdownParent: 'body',
        valueField: 'value',
        labelField: 'text',
        searchField: 'text',
        placeholder: targetTypeMap[targetTypeEl.value] ? targetTypeMap[targetTypeEl.value].title : 'Select Target',

        load: (query, callback) => {
            if (!query) return callback();

            axios.get(`${base_url}${targetTypeMap[targetTypeEl.value].url}`, {
                params: {
                    search: query
                }
            })
                .then(res => callback(res.data))
                .catch(() => callback());
        }
    });
};
// Reset Users TomSelect when Markets selection changes so the recipient list
// re-queries against the newly selected markets.
document.addEventListener('DOMContentLoaded', () => {
    const marketEl = document.getElementById('select-markets');
    if (!marketEl) return;

    const resetTomSelect = (instance) => {
        if (!instance) return;

        try {
            instance.clear();
            instance.clearOptions();
            if (instance.loadedSearches) {
                instance.loadedSearches = {};
            }
        } catch (e) {
            console.warn('TomSelect reset failed:', e);
        }
    };

    marketEl.addEventListener('change', () => {
        [tomSelectUserInstance, tomSelectTargetInstance].forEach(resetTomSelect);
    });
});
