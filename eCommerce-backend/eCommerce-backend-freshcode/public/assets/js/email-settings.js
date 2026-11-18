'use strict';

/**
 * Admin email settings — "Test SMTP" button.
 *
 * Sends the SMTP values currently in the form (unsaved) to the test endpoint,
 * which fires a one-off test email. Mirrors the Test SMS flow.
 */
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

    function buildResultHtml(message, payload) {
        const parts = [];
        parts.push(`<div class="mb-2">${escapeHtml(message || '')}</div>`);

        // Surface the error line (if any), then the SMTP conversation log so the
        // admin can see exactly what the mail server said — success or failure.
        const error = payload?.gateway_error;
        if (error) {
            parts.push(`<div class="text-danger small text-start mb-2">${escapeHtml(error)}</div>`);
        }

        const log = payload?.gateway_body;
        if (log) {
            parts.push(
                '<pre class="text-start mb-0" style="max-height:280px;overflow:auto;background:#0f172a;color:#e2e8f0;padding:12px;border-radius:6px;font-size:12px;line-height:1.4;white-space:pre-wrap;">' +
                `<code>${escapeHtml(log)}</code>` +
                '</pre>'
            );
        }
        return parts.join('');
    }

    function bindTestSmtp() {
        const button = document.getElementById('testSmtpButton');
        if (!button) return;

        const form = document.querySelector('form.form-submit');
        const emailInput = document.getElementById('testSmtpEmail');
        const endpoint = button.dataset.endpoint;
        const successTitle = button.dataset.successTitle || 'Test email sent';
        const failedTitle = button.dataset.failedTitle || 'Test failed';

        const setBusy = (busy) => {
            button.disabled = busy;
            button.querySelector('.spinner-border')?.classList.toggle('d-none', !busy);
        };

        button.addEventListener('click', async (e) => {
            e.preventDefault();
            if (!form || !emailInput) return;

            const email = emailInput.value.trim();
            if (email === '') {
                Toast.fire({ icon: 'warning', title: button.dataset.missingEmail || 'Enter a recipient email address.' });
                emailInput.focus();
                return;
            }

            const formData = new FormData(form);
            // The save endpoint expects type=email; the extra test_email key is harmless to it.
            formData.append('test_email', email);

            setBusy(true);
            try {
                const { data } = await axios.post(endpoint, formData, {
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                });

                Swal.fire({
                    icon: data?.success ? 'success' : 'error',
                    title: data?.success ? successTitle : failedTitle,
                    html: buildResultHtml(data?.message, data?.data),
                    width: 600,
                });
            } catch (err) {
                const responseData = err?.response?.data;
                Swal.fire({
                    icon: 'error',
                    title: failedTitle,
                    html: buildResultHtml(
                        responseData?.message || err?.message || 'Request failed.',
                        responseData?.data || { gateway_error: err?.message }
                    ),
                    width: 600,
                });
            } finally {
                setBusy(false);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', bindTestSmtp);
})();
