'use strict';

(function () {
    const button = document.getElementById('testNotificationSelf');
    const result = document.getElementById('testNotificationResult');

    if (!button || !result) return;

    const setBusy = (busy) => {
        button.disabled = busy;
        button.querySelector('.spinner-border')?.classList.toggle('d-none', !busy);
    };

    const withTimeout = (promise, milliseconds) => Promise.race([
        promise,
        new Promise((_, reject) => window.setTimeout(
            () => reject(new Error(button.dataset.refreshTimeoutMessage)),
            milliseconds,
        )),
    ]);

    const sendTest = (token, pushOnly = false) => axios.post(button.dataset.endpoint, {
        fcm_token: token,
        push_only: pushOnly,
    }, {
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
    });

    button.addEventListener('click', async () => {
        setBusy(true);
        result.classList.add('d-none');

        try {
            const token = localStorage.getItem('fcm_token') || null;
            let response = await sendTest(token);
            let data = response.data?.data || {};

            if (data.push?.status === 'stale' && typeof window.refreshPanelNotificationToken === 'function') {
                try {
                    const freshToken = await withTimeout(window.refreshPanelNotificationToken(), 15000);
                    if (!freshToken) {
                        throw new Error(button.dataset.refreshFailedMessage);
                    }
                    response = await sendTest(freshToken, true);
                    data = response.data?.data || {};
                } catch (error) {
                    data.push = {
                        status: 'failed',
                        message: error.message,
                    };
                }
            }

            const pushStatus = data.push?.status || 'skipped';
            const alertClass = pushStatus === 'sent' ? 'alert-success' : 'alert-warning';
            result.className = `alert ${alertClass} mt-3`;
            result.textContent = `${response.data?.message || ''} ${data.push?.message || ''}`.trim();
            Toast.fire({icon: pushStatus === 'sent' ? 'success' : 'info', title: response.data?.message});
        } catch (error) {
            const message = error.response?.data?.message || error.message;
            result.className = 'alert alert-danger mt-3';
            result.textContent = message;
            Toast.fire({icon: 'error', title: message});
        } finally {
            result.classList.remove('d-none');
            setBusy(false);
        }
    });
})();
