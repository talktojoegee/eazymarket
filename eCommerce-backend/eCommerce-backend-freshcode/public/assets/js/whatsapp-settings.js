document.addEventListener('DOMContentLoaded', () => {
    const mappingForm = document.getElementById('whatsapp-mapping-form');
    const rowsContainer = document.getElementById('whatsapp-mapping-rows');
    const syncButton = document.getElementById('whatsapp-sync-templates');
    const saveButton = document.getElementById('whatsapp-save-templates');
    const testForm = document.getElementById('whatsapp-test-form');
    const templateSelect = document.getElementById('whatsapp-test-template');
    const testStatus = document.getElementById('whatsapp-test-status');
    const dataElement = document.getElementById('whatsapp-mapping-data');
    const mappingData = dataElement ? JSON.parse(dataElement.textContent) : {};
    const events = mappingData.events || [];
    const canEdit = Boolean(mappingData.canEdit);
    const defaultLanguage = mappingData.defaultLanguage || 'en_US';
    let mappings = mappingData.mappings || {};

    const csrfHeaders = () => ({
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
    });

    const wait = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));
    const statusLabel = (status) => {
        const value = String(status || 'UNKNOWN').toLowerCase();
        return value.charAt(0).toUpperCase() + value.slice(1);
    };

    const createElement = (tag, className, text) => {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined) element.textContent = text;
        return element;
    };

    const addCell = (row, content) => {
        const cell = document.createElement('td');
        if (content) cell.append(content);
        row.append(cell);
        return cell;
    };

    const mappingFor = (event) => mappings[event.value] || {};

    const renderRow = (event) => {
        const mapping = mappingFor(event);
        const row = createElement('tr');
        row.dataset.whatsappMappingRow = event.value;

        const eventCell = addCell(row);
        eventCell.append(createElement('strong', '', event.label));
        eventCell.append(createElement('div', 'text-secondary', 'Triggered by this app event.'));

        const nameCell = addCell(row);
        const name = createElement('input', 'form-control');
        name.type = 'text';
        name.name = `templateMappings[${event.value}][templateName]`;
        name.value = mapping.templateName || '';
        name.placeholder = 'Exact Meta template name';
        name.maxLength = 512;
        name.disabled = !canEdit;
        name.dataset.templateName = '';
        nameCell.append(name);
        nameCell.append(createElement('div', 'form-hint', 'Create and approve this template in Meta first.'));

        const languageCell = addCell(row);
        const language = createElement('input', 'form-control');
        language.type = 'text';
        language.name = `templateMappings[${event.value}][language]`;
        language.value = mapping.language || defaultLanguage;
        language.placeholder = 'en_US';
        language.maxLength = 35;
        language.disabled = !canEdit;
        language.dataset.templateLanguage = '';
        languageCell.append(language);
        languageCell.append(createElement('div', 'form-hint', 'Enter the exact Meta language code, e.g. en_US.'));

        const formatCell = addCell(row);
        const format = createElement('span', 'badge bg-secondary-lt', 'Positional');
        formatCell.append(format);
        const expectedParameters = createElement('div', 'form-hint text-secondary');
        expectedParameters.dataset.templateExpected = '';
        expectedParameters.textContent = `Meta body expects: ${event.fields.map((field, index) => `{{${index + 1}}}`).join(', ') || 'no variables'}`;
        formatCell.append(expectedParameters);
        const mappingError = createElement('div', 'form-hint text-danger', mapping.mappingError || '');
        mappingError.dataset.templateMappingError = '';
        formatCell.append(mappingError);

        const statusCell = addCell(row);
        const status = String(mapping.status || 'UNKNOWN').toUpperCase();
        const badge = createElement('span', `badge ${status === 'APPROVED' ? 'bg-success-lt' : status === 'REJECTED' ? 'bg-danger-lt' : 'bg-secondary-lt'}`, statusLabel(status));
        badge.dataset.templateStatus = '';
        statusCell.append(badge);
        const metaInfo = createElement('div', 'text-secondary');
        metaInfo.dataset.templateMetaInfo = '';
        metaInfo.textContent = `Quality: ${mapping.quality || 'UNKNOWN'} · Category: ${mapping.category || '—'} · ${mapping.syncedAt ? `Synced ${mapping.syncedAt}` : 'Not synced yet.'}`;
        statusCell.append(metaInfo);
        const rejected = createElement('div', 'text-danger', mapping.rejectedReason || '');
        rejected.dataset.templateRejected = '';
        statusCell.append(rejected);

        const variablesCell = addCell(row);
        const variables = createElement('textarea', 'form-control font-monospace');
        variables.rows = 4;
        variables.dataset.templateVariables = '';
        variables.value = JSON.stringify(Object.fromEntries(event.fields.map((field, index) => [String(index + 1), field])), null, 2);
        variables.disabled = true;
        variablesCell.append(variables);

        const syncCell = addCell(row);
        if (canEdit) {
            const sync = createElement('button', 'btn btn-outline-secondary btn-sm', 'Sync');
            sync.type = 'button';
            sync.dataset.syncTemplate = event.value;
            syncCell.append(sync);
        }

        return row;
    };

    const renderRows = () => {
        if (!rowsContainer) return;
        rowsContainer.replaceChildren();
        if (!events.length) {
            const row = createElement('tr');
            const cell = addCell(row, createElement('div', 'text-center text-secondary', 'No app events are configured.'));
            cell.colSpan = 7;
            rowsContainer.append(row);
            refreshTestTemplates(mappings);
            return;
        }
        events.forEach((event) => rowsContainer.append(renderRow(event)));
        refreshTestTemplates(mappings);
    };

    const mappingPayload = (eventKey = null) => {
        const templateMappings = {};
        mappingForm?.querySelectorAll('[data-whatsapp-mapping-row]').forEach((row) => {
            const key = row.dataset.whatsappMappingRow;
            if (eventKey !== null && key !== eventKey) return;
            const appEvent = events.find((item) => item.value === key);
            const variables = Object.fromEntries((appEvent?.fields || []).map((field, index) => [String(index + 1), field]));
            templateMappings[key] = {
                templateName: row.querySelector('[data-template-name]').value.trim(),
                language: row.querySelector('[data-template-language]').value.trim(),
                format: 'positional',
                variables,
            };
        });
        return { templateMappings };
    };

    const refreshTestTemplates = (availableMappings) => {
        if (!templateSelect) return;
        const previousValue = templateSelect.value;
        templateSelect.replaceChildren(new Option('Select an active approved template', ''));
        events.forEach((event) => {
            const mapping = availableMappings?.[event.value];
            if (!mapping?.active || String(mapping.status || '').toUpperCase() !== 'APPROVED' || !mapping.templateName) return;
            templateSelect.add(new Option(`${event.label} — ${mapping.templateName} (${mapping.language})`, event.value));
        });
        templateSelect.disabled = templateSelect.options.length < 2;
        if ([...templateSelect.options].some((option) => option.value === previousValue)) templateSelect.value = previousValue;
    };

    const updateStatusRow = (key, mapping) => {
        const row = document.querySelector(`[data-whatsapp-mapping-row="${key}"]`);
        if (!row || !mapping) return;
        const status = String(mapping.status || 'UNKNOWN').toUpperCase();
        const badge = row.querySelector('[data-template-status]');
        badge.textContent = statusLabel(status);
        badge.className = `badge ${status === 'APPROVED' ? 'bg-success-lt' : status === 'REJECTED' ? 'bg-danger-lt' : 'bg-secondary-lt'}`;
        row.querySelector('[data-template-meta-info]').textContent = `Quality: ${mapping.quality || 'UNKNOWN'} · Category: ${mapping.category || '—'} · Synced ${mapping.syncedAt || 'just now'}`;
        row.querySelector('[data-template-rejected]').textContent = mapping.rejectedReason || '';
        const appEvent = events.find((event) => event.value === key);
        row.querySelector('[data-template-expected]').textContent = `Meta body expects: ${(appEvent?.fields || []).map((field, index) => `{{${index + 1}}}`).join(', ') || 'no variables'}`;
        row.querySelector('[data-template-mapping-error]').textContent = mapping.mappingError || '';
    };

    const applyMappings = (savedMappings) => {
        mappings = savedMappings || {};
        renderRows();
    };

    const saveMappings = async (eventKey = null) => {
        const payload = mappingPayload(eventKey);
        const response = await window.axios.post(saveButton.dataset.url, payload, { headers: csrfHeaders() });
        applyMappings(response.data.data || payload.templateMappings);
        return response.data;
    };

    renderRows();

    saveButton?.addEventListener('click', async () => {
        const original = saveButton.innerHTML;
        saveButton.disabled = true;
        try {
            const response = await saveMappings();
            Toast.fire({ icon: 'success', title: response.message });
        } catch (error) {
            Toast.fire({ icon: 'error', title: error.response?.data?.message || error.message || 'Unable to save template mappings.' });
        } finally {
            saveButton.disabled = false;
            saveButton.innerHTML = original;
        }
    });

    syncButton?.addEventListener('click', async () => {
        const original = syncButton.innerHTML;
        syncButton.disabled = true;
        try {
            await saveMappings();
            const response = await window.axios.post(syncButton.dataset.url, {}, { headers: csrfHeaders() });
            applyMappings(response.data.data || mappings);
            Toast.fire({ icon: 'success', title: response.data.message });
        } catch (error) {
            Toast.fire({ icon: 'error', title: error.response?.data?.message || error.message || 'Unable to sync template statuses from Meta.' });
        } finally {
            syncButton.disabled = false;
            syncButton.innerHTML = original;
        }
    });

    mappingForm?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-sync-template]');
        if (!button) return;
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        try {
            await saveMappings(button.dataset.syncTemplate);
            const response = await window.axios.post(syncButton.dataset.url, { templateKey: button.dataset.syncTemplate }, { headers: csrfHeaders() });
            mappings[button.dataset.syncTemplate] = response.data.data;
            updateStatusRow(button.dataset.syncTemplate, response.data.data);
            refreshTestTemplates(mappings);
            Toast.fire({ icon: 'success', title: response.data.message });
        } catch (error) {
            Toast.fire({ icon: 'error', title: error.response?.data?.message || error.message || 'Unable to sync this template.' });
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    });

    testForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = testForm.querySelector('button[type="submit"]');
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sending';
        try {
            const response = await window.axios.post(testForm.action, new FormData(testForm), { headers: csrfHeaders() });
            Toast.fire({ icon: 'success', title: response.data.message });
            const messageId = response.data.message_id;
            if (messageId && testStatus) {
                testStatus.textContent = 'Meta accepted the message. Waiting for a delivery status…';
                for (let attempt = 0; attempt < 15; attempt += 1) {
                    await wait(2000);
                    try {
                        const statusResponse = await window.axios.get(testForm.dataset.statusUrl, { params: { message_id: messageId } });
                        const messageStatus = statusResponse.data.data;
                        const currentStatus = String(messageStatus.status || 'accepted').toLowerCase();
                        testStatus.textContent = currentStatus === 'failed'
                            ? `WhatsApp status: Failed${messageStatus.error_code ? ` (${messageStatus.error_code})` : ''}${messageStatus.error_details ? ` — ${messageStatus.error_details}` : ''}`
                            : `WhatsApp status: ${statusLabel(currentStatus)}.`;
                        if (['delivered', 'read', 'failed'].includes(currentStatus)) break;
                    } catch (statusError) {
                        if (statusError.response?.status !== 404) {
                            testStatus.textContent = 'Meta accepted the message, but its delivery status could not be checked.';
                            break;
                        }
                    }
                }
                if (/Waiting for a delivery status/.test(testStatus.textContent)) {
                    testStatus.textContent = 'Meta accepted the message, but no delivery update arrived. Check the callback URL, verify token, App Secret, and Meta messages subscription.';
                }
            }
        } catch (error) {
            Toast.fire({ icon: 'error', title: error.response?.data?.message || 'WhatsApp could not send the test message.' });
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    });
});
