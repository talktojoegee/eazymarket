'use strict';

(function () {
    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatBody(raw) {
        if (raw === null || raw === undefined || raw === '') return '';
        const value = String(raw).trim();
        if ((value.startsWith('{') && value.endsWith('}')) || (value.startsWith('[') && value.endsWith(']'))) {
            try {
                return JSON.stringify(JSON.parse(value), null, 2);
            } catch (_) {
                return value;
            }
        }
        return value;
    }

    function buildResultHtml(message, payload) {
        const status = payload?.gateway_status;
        const body = payload?.gateway_body;
        const error = payload?.gateway_error;
        const parts = [`<div class="mb-2">${escapeHtml(message || '')}</div>`];

        if (status !== null && status !== undefined) {
            parts.push(`<div class="text-start small text-muted mb-1">HTTP ${escapeHtml(status)}</div>`);
        }

        const details = body ? formatBody(body) : (error || '');
        if (details !== '') {
            parts.push(
                '<pre class="text-start mb-0" style="max-height:280px;overflow:auto;background:#0f172a;color:#e2e8f0;padding:12px;border-radius:6px;font-size:12px;line-height:1.4;">' +
                `<code>${escapeHtml(details)}</code>` +
                '</pre>'
            );
        }

        return parts.join('');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('form.form-submit');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const enabledCount = document.querySelector('[data-enabled-payment-count]');

        const syncEnabledCount = () => {
            if (!enabledCount || !form) return;
            enabledCount.textContent = String(
                Array.from(document.querySelectorAll('[data-payment-card]'))
                    .filter((card) => form.querySelector(`[name="${card.dataset.toggleName || ''}"]`)?.checked)
                    .length
            );
        };

        document.querySelectorAll('[data-payment-card]').forEach((card) => {
            const toggleName = card.dataset.toggleName || '';
            const toggle = form?.querySelector(`[name="${toggleName}"]`);
            if (!toggle) return;

            const syncStatus = () => {
                const enabled = toggle.checked;
                card.classList.toggle('is-enabled', enabled);
                document.querySelectorAll(`[data-payment-status-for="${toggleName}"]`).forEach((status) => {
                    status.classList.toggle('is-enabled', enabled);
                    if (status.hasAttribute('data-payment-status-label')) {
                        const label = status.querySelector('strong');
                        if (label) {
                            label.textContent = enabled ? status.dataset.enabledLabel : status.dataset.disabledLabel;
                        }
                    }
                });
                document.querySelectorAll(`[data-payment-overview-for="${toggleName}"]`).forEach((logo) => {
                    logo.classList.toggle('is-enabled', enabled);
                });
                syncEnabledCount();
            };

            toggle.addEventListener('change', syncStatus);
            syncStatus();
        });

        document.querySelectorAll('[data-payment-mode-for]').forEach((badge) => {
            const fieldName = badge.dataset.paymentModeFor || '';
            const select = form?.querySelector(`[name="${fieldName}"]`);
            if (!select) return;

            const syncMode = () => {
                badge.textContent = select.value || '';
                badge.classList.toggle('d-none', !select.value);
            };

            select.addEventListener('change', syncMode);
            syncMode();
        });

        document.querySelectorAll('.test-payment-button').forEach((button) => {
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                const secretField = form?.querySelector(`[name="${button.dataset.secretField || ''}"]`);
                const secret = String(secretField?.value || '').trim();

                if (!form || !button.dataset.endpoint || secret === '') {
                    Toast.fire({ icon: 'warning', title: button.dataset.missing });
                    return;
                }

                const payload = new FormData();
                payload.append('gateway', button.dataset.gateway || 'xendit');
                payload.append('secret_key', secret);
                button.disabled = true;
                button.querySelector('.spinner-border')?.classList.remove('d-none');

                try {
                    const { data } = await axios.post(button.dataset.endpoint, payload, {
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    });
                    Swal.fire({
                        icon: data?.success ? 'success' : 'error',
                        title: data?.success ? button.dataset.successTitle : button.dataset.failedTitle,
                        html: buildResultHtml(data?.message, data?.data),
                        width: 600,
                    });
                } catch (error) {
                    const response = error?.response?.data;
                    Swal.fire({
                        icon: 'error',
                        title: button.dataset.failedTitle,
                        html: buildResultHtml(
                            response?.message || error?.message || 'Request failed.',
                            response?.data || { gateway_error: error?.message }
                        ),
                        width: 600,
                    });
                } finally {
                    button.disabled = false;
                    button.querySelector('.spinner-border')?.classList.add('d-none');
                }
            });
        });
    });
})();
