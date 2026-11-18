'use strict';

(function () {
    const translations = window.attributeManagementTranslations || {};
    const createAttributeLink = document.getElementById('create-attribute-link');

    createAttributeLink?.addEventListener('click', function (event) {
        event.preventDefault();
        window.location.assign(createAttributeLink.href);
    });

    document.addEventListener('click', function (event) {
        handleDelete(
            event,
            '.delete-attribute-create-update',
            `/${panel}/attributes/`,
            translations.deleteAttribute
        );
    });

    $(function () {
        if (!$('#attributes-table').length) return;
        const table = $('#attributes-table').DataTable();
        $('#typeFilter, #sellerFilter').on('change', function () {
            table.ajax.reload(null, false);
        });
        table.on('preXhr.dt', function (event, settings, data) {
            data.swatche_type = $('#typeFilter').val();
            data.seller_id = $('#sellerFilter').val();
        });
        $('#sellerFilter').on('change', function () {
            if (!createAttributeLink) return;
            const url = new URL(createAttributeLink.href, window.location.origin);
            if (this.value) url.searchParams.set('seller_id', this.value);
            else url.searchParams.delete('seller_id');
            createAttributeLink.href = url.toString();
        });
    });

    const form = document.getElementById('attribute-form');
    const valuesContainer = document.getElementById('attribute-values');
    const removedValuesContainer = document.getElementById('removed-attribute-values');
    const addValueButton = document.getElementById('add-attribute-value');
    const initialData = window.attributeInitialData || null;
    let nextValueIndex = 0;

    if (!form || !valuesContainer || !removedValuesContainer || !addValueButton) return;

    document.addEventListener('resource:saved', function (event) {
        if (event.detail?.form !== form || new URL(window.location.href).searchParams.get('source') !== 'product') return;
        const attribute = event.detail?.data?.data || {};
        localStorage.setItem('product-attribute-created', JSON.stringify({
            id: attribute.id,
            seller_id: attribute.seller_id,
            value_ids: (attribute.values || []).map(value => value.id),
            saved_at: Date.now(),
        }));
        if (window.opener) setTimeout(() => window.close(), 500);
    });

    function selectedType() {
        return form.querySelector('input[name="swatche_type"]:checked')?.value || 'text';
    }

    function destroyFilePonds(root) {
        if (!window.FilePond) return;
        root.querySelectorAll('input[type="file"]').forEach(function (input) {
            const pond = window.FilePond.find(input);
            if (pond) pond.destroy();
        });
    }

    function initializeFilePond(input, imageUrl = '') {
        if (!window.FilePond || window.FilePond.find(input)) return;
        const plugins = [
            window.FilePondPluginImagePreview,
            window.FilePondPluginFileValidateType,
            window.FilePondPluginFileValidateSize,
        ].filter(Boolean);
        if (plugins.length) window.FilePond.registerPlugin(...plugins);
        window.FilePond.create(input, {
            storeAsFile: true,
            allowMultiple: false,
            allowImagePreview: true,
            acceptedFileTypes: ['image/jpeg', 'image/png', 'image/webp'],
            maxFileSize: '2MB',
            credits: false,
            files: imageUrl ? [{source: imageUrl, options: {type: 'local'}}] : [],
            server: {
                load: (source, load, error) => fetch(source)
                    .then(response => {
                        if (!response.ok) throw new Error(response.statusText);
                        return response.blob();
                    })
                    .then(load)
                    .catch(error),
            },
        });
    }

    function swatchField(row, type, index, value) {
        const container = row.querySelector('.attribute-swatch-field');
        container.replaceChildren();
        if (type === 'text') return;

        const label = document.createElement('label');
        label.className = 'form-label required';
        label.htmlFor = `attribute-swatch-${index}`;
        label.textContent = type === 'color' ? translations.color : translations.image;
        container.appendChild(label);

        if (type === 'color') {
            const group = document.createElement('div');
            group.className = 'input-group';
            const picker = document.createElement('input');
            picker.type = 'color';
            picker.className = 'form-control form-control-color';
            picker.value = value.swatche_value || '#000000';
            picker.setAttribute('aria-label', translations.color || 'Color');
            const text = document.createElement('input');
            text.type = 'text';
            text.className = 'form-control text-uppercase';
            text.id = `attribute-swatch-${index}`;
            text.name = `values[${index}][swatch_value]`;
            text.value = value.swatche_value || '#000000';
            text.pattern = '^#[0-9A-Fa-f]{6}$';
            text.maxLength = 7;
            text.required = true;
            picker.addEventListener('input', function () {
                text.value = picker.value.toUpperCase();
            });
            text.addEventListener('input', function () {
                if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) picker.value = text.value;
            });
            group.append(picker, text);
            container.appendChild(group);
            return;
        }

        const input = document.createElement('input');
        input.type = 'file';
        input.className = 'form-control';
        input.id = `attribute-swatch-${index}`;
        input.name = `values[${index}][swatch_value]`;
        input.accept = 'image/jpeg,image/png,image/webp';
        if (!value.id || !value.swatche_value) input.required = true;
        container.appendChild(input);
        initializeFilePond(input, value.swatche_value || '');
    }

    function createValueRow(value = {}) {
        const index = nextValueIndex++;
        const row = document.createElement('div');
        const usageCount = Number(value.product_variant_attribute_value_count || 0);
        row.className = 'card attribute-value-row';
        row.dataset.index = String(index);
        row.dataset.valueId = value.id || '';
        row.innerHTML = `
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md">
                        <label class="form-label required" for="attribute-value-${index}"></label>
                        <input type="text" class="form-control attribute-value-title" id="attribute-value-${index}"
                               name="values[${index}][title]" maxlength="191" required>
                    </div>
                    <div class="col-12 col-md attribute-swatch-field"></div>
                    <div class="col-12 col-md-auto">
                        <button type="button" class="btn btn-outline-danger remove-attribute-value"></button>
                    </div>
                </div>
                <div class="attribute-value-usage mt-2 d-none"></div>
            </div>`;

        row.querySelector('label').textContent = translations.valueName || 'Value name';
        const titleInput = row.querySelector('.attribute-value-title');
        titleInput.placeholder = translations.valueExample || '';
        titleInput.value = value.title || '';

        if (value.id) {
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = `values[${index}][id]`;
            idInput.value = value.id;
            row.querySelector('.card-body').prepend(idInput);
        }

        const removeButton = row.querySelector('.remove-attribute-value');
        removeButton.innerHTML = `<i class="ti ti-trash me-1"></i>${translations.remove || 'Remove'}`;
        if (usageCount > 0) {
            removeButton.disabled = true;
            const usage = row.querySelector('.attribute-value-usage');
            usage.className = 'attribute-value-usage mt-2 text-warning small';
            usage.innerHTML = `<i class="ti ti-lock me-1"></i>${translations.usedByProducts || 'Used by products'}`;
        }

        removeButton.addEventListener('click', function () {
            if (value.id) {
                const removed = document.createElement('input');
                removed.type = 'hidden';
                removed.name = 'removed_value_ids[]';
                removed.value = value.id;
                removedValuesContainer.appendChild(removed);
            }
            destroyFilePonds(row);
            row.remove();
            ensureValueRow();
        });

        swatchField(row, selectedType(), index, value);
        valuesContainer.appendChild(row);
    }

    function ensureValueRow() {
        if (!valuesContainer.querySelector('.attribute-value-row')) createValueRow();
    }

    function lockDisplayStyle(locked) {
        form.querySelectorAll('input[name="swatche_type"]').forEach(function (input) {
            input.disabled = locked && !input.checked;
        });
        document.getElementById('attribute-style-locked-hint').classList.toggle('d-none', !locked);
    }

    addValueButton.addEventListener('click', function () {
        createValueRow();
    });

    form.querySelectorAll('input[name="swatche_type"]').forEach(function (input) {
        input.addEventListener('change', function () {
            valuesContainer.querySelectorAll('.attribute-value-row').forEach(function (row) {
                const index = row.dataset.index;
                const id = row.dataset.valueId;
                const title = row.querySelector('.attribute-value-title')?.value || '';
                destroyFilePonds(row);
                swatchField(row, selectedType(), index, {id: id, title: title});
            });
        });
    });

    if (initialData?.swatche_type) {
        const typeInput = form.querySelector(`input[name="swatche_type"][value="${initialData.swatche_type}"]`);
        if (typeInput) typeInput.checked = true;
    }
    (initialData?.values || []).forEach(createValueRow);
    ensureValueRow();
    lockDisplayStyle((initialData?.values || []).length > 0);
})();
