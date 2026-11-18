(function () {
    const root = document.getElementById('panel-session');
    const modalElement = document.getElementById('panelReloginModal');

    if (!root || !modalElement) return;

    const panel = root.dataset.panel;
    const panelPrefix = '/' + panel;
    const statusUrl = root.dataset.statusUrl;
    const loginUrl = root.dataset.loginUrl;
    const loginPageUrl = root.dataset.loginPageUrl;
    const reauthenticationToken = root.dataset.reauthenticationToken;
    const sessionChannelName = 'hypercommerce-panel-session:' + panel;
    const sessionStorageKey = sessionChannelName + ':renewed';
    const sessionChannel = typeof window.BroadcastChannel === 'function'
        ? new window.BroadcastChannel(sessionChannelName)
        : null;
    const originalFetch = window.fetch.bind(window);
    const bypassedForms = new WeakSet();
    const $modal = window.jQuery(modalElement);
    const authForm = modalElement.querySelector('[data-panel-auth-form]');
    const passwordPane = authForm?.querySelector('[data-panel-auth-password]');
    const otpPane = authForm?.querySelector('[data-panel-auth-otp]');
    const passwordForm = document.getElementById('panel-relogin-password-form');
    const passwordMessage = document.getElementById('panel-relogin-message');
    let expiresIn = Number(root.dataset.expiresIn || 7200);
    let lastActivity = Date.now();
    let expiryTimer = null;
    let recoveryPromise = null;
    let modalPromise = null;
    let resolveModal = null;
    let allowModalHide = false;
    let externalSyncPromise = null;

    function showPasswordAuth() {
        if (passwordPane) passwordPane.hidden = false;
        if (otpPane) otpPane.hidden = true;
        window.setTimeout(function () {
            passwordForm?.querySelector('input[name="password"]')?.focus();
        }, 0);
    }

    function showOtpAuth() {
        if (passwordPane) passwordPane.hidden = true;
        if (otpPane) otpPane.hidden = false;
        window.setTimeout(function () {
            otpPane?.querySelector('input')?.focus();
        }, 0);
    }

    function urlFor(value) {
        try {
            return new URL(value, window.location.href);
        } catch (error) {
            return null;
        }
    }

    function isPanelRequest(value) {
        const url = urlFor(value);
        return Boolean(url
            && url.origin === window.location.origin
            && (url.pathname === panelPrefix || url.pathname.startsWith(panelPrefix + '/')));
    }

    function isSessionEndpoint(value) {
        const url = urlFor(value);
        if (!url) return false;

        return url.href === urlFor(statusUrl)?.href
            || url.href === urlFor(loginUrl)?.href
            || url.pathname.startsWith(panelPrefix + '/login');
    }

    function currentCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function updateCsrfToken(token) {
        if (!token) return;

        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.content = token;

        document.querySelectorAll('input[name="_token"]').forEach(function (input) {
            input.value = token;
        });

        if (window.axios) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        }
    }

    function scheduleExpiryCheck() {
        window.clearTimeout(expiryTimer);
        const elapsed = Date.now() - lastActivity;
        const delay = Math.max(1000, expiresIn * 1000 - elapsed + 500);
        expiryTimer = window.setTimeout(function () {
            verifySession().catch(function () {
                scheduleExpiryCheck();
            });
        }, delay);
    }

    function touchSession() {
        lastActivity = Date.now();
        scheduleExpiryCheck();
    }

    async function sessionStatus() {
        const response = await originalFetch(statusUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Reauthentication-Token': reauthenticationToken,
                'X-Panel-Session-Skip': '1',
            },
            cache: 'no-store',
        });
        const result = await response.json();

        if (!response.ok || !result.success) {
            if (response.status === 422) window.location.href = loginPageUrl;
            throw new Error(result.message || 'Unable to verify the current session.');
        }

        updateCsrfToken(result.data.csrf_token);
        expiresIn = Number(result.data.expires_in || expiresIn);

        if (result.data.authenticated) touchSession();

        return result.data;
    }

    function showModal() {
        if (modalPromise) return modalPromise;

        modalPromise = new Promise(function (resolve) {
            resolveModal = resolve;
        });
        $modal.modal('show');
        window.setTimeout(function () {
            passwordForm?.querySelector('input[name="password"]')?.focus();
        }, 250);

        return modalPromise;
    }

    function announceReauthentication() {
        if (sessionChannel) {
            sessionChannel.postMessage({ type: 'reauthenticated' });
            return;
        }

        try {
            window.localStorage.setItem(sessionStorageKey, Date.now() + ':' + Math.random());
        } catch (error) {
            return;
        }
    }

    function completeReauthentication(token, announce) {
        updateCsrfToken(token);
        touchSession();
        passwordForm?.reset();
        if (passwordMessage) passwordMessage.textContent = '';
        showPasswordAuth();
        document.dispatchEvent(new CustomEvent('panel:reauthentication-complete'));
        allowModalHide = true;
        $modal.modal('hide');
        allowModalHide = false;

        if (resolveModal) resolveModal(true);
        modalPromise = null;
        resolveModal = null;

        if (announce !== false) announceReauthentication();
    }

    function syncExternalReauthentication() {
        if (externalSyncPromise) return externalSyncPromise;

        externalSyncPromise = sessionStatus()
            .then(function (state) {
                if (state.authenticated) completeReauthentication(state.csrf_token, false);
            })
            .catch(function () {
                scheduleExpiryCheck();
            })
            .finally(function () {
                externalSyncPromise = null;
            });

        return externalSyncPromise;
    }

    async function verifySession() {
        const state = await sessionStatus();
        if (!state.authenticated) await showModal();
        return state.authenticated;
    }

    function recoverSession(status) {
        if (recoveryPromise) return recoveryPromise;

        recoveryPromise = sessionStatus()
            .then(function (state) {
                if (state.authenticated) return status === 419;
                return showModal();
            })
            .finally(function () {
                recoveryPromise = null;
            });

        return recoveryPromise;
    }

    if (passwordForm) {
        passwordForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const submitButton = passwordForm.querySelector('button[type="submit"]');
            const originalContent = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + originalContent;
            if (passwordMessage) passwordMessage.textContent = '';

            try {
                const response = await originalFetch(loginUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': currentCsrfToken(),
                        'X-Panel-Session-Skip': '1',
                    },
                    body: new FormData(passwordForm),
                });
                const result = await response.json();

                if (!response.ok || !result.success || !result.reauthenticated) {
                    throw new Error(result.message || 'Sign in failed.');
                }

                completeReauthentication(result.csrf_token);
            } catch (error) {
                if (passwordMessage) passwordMessage.textContent = error.message || 'Sign in failed.';
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = originalContent;
            }
        });
    }

    authForm?.querySelector('[data-panel-auth-show-otp]')?.addEventListener('click', showOtpAuth);
    authForm?.querySelector('[data-panel-auth-show-password]')?.addEventListener('click', showPasswordAuth);

    document.addEventListener('panel:reauthenticated', function (event) {
        completeReauthentication(event.detail?.csrfToken);
    });

    sessionChannel?.addEventListener('message', function (event) {
        if (event.data?.type === 'reauthenticated') syncExternalReauthentication();
    });

    window.addEventListener('storage', function (event) {
        if (event.key === sessionStorageKey && event.newValue) syncExternalReauthentication();
    });

    function verifyReturnedPage() {
        verifySession().catch(function () {
            scheduleExpiryCheck();
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') verifyReturnedPage();
    });

    window.addEventListener('focus', verifyReturnedPage);

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) verifyReturnedPage();
    });

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)
            || bypassedForms.has(form)
            || form === passwordForm
            || form.classList.contains('form-submit')
            || form.closest('#panelReloginModal')) {
            return;
        }

        const method = (form.method || 'GET').toUpperCase();
        if (method === 'GET' || !isPanelRequest(form.action)) return;

        event.preventDefault();
        const submitter = event.submitter;
        sessionStatus()
            .then(function (state) {
                return state.authenticated ? true : showModal();
            })
            .then(function () {
                bypassedForms.add(form);
                form.requestSubmit(submitter || undefined);
                bypassedForms.delete(form);
            })
            .catch(function (error) {
                if (window.Swal) {
                    window.Swal.fire({ icon: 'error', title: error.message });
                }
            });
    }, true);

    if (window.axios) {
        window.axios.interceptors.request.use(function (config) {
            if (!isPanelRequest(config.url) || isSessionEndpoint(config.url)
                || config.headers?.['X-Panel-Session-Skip'] === '1') return config;

            config.headers = config.headers || {};
            config.headers['X-Requested-With'] = 'XMLHttpRequest';
            config.headers['X-CSRF-TOKEN'] = currentCsrfToken();

            if (config.data instanceof FormData && config.data.has('_token')) {
                config.data.set('_token', currentCsrfToken());
            } else if (config.data && Object.prototype.hasOwnProperty.call(config.data, '_token')) {
                config.data._token = currentCsrfToken();
            }

            return config;
        });

        window.axios.interceptors.response.use(function (response) {
            if (isPanelRequest(response.config?.url) && !isSessionEndpoint(response.config?.url)) touchSession();
            return response;
        }, function (error) {
            const status = error.response?.status;
            const config = error.config;

            if (!config || ![401, 419].includes(status) || config.__panelSessionRetried
                || !isPanelRequest(config.url) || isSessionEndpoint(config.url)
                || config.headers?.['X-Panel-Session-Skip'] === '1') {
                if (config && isPanelRequest(config.url) && ![401, 419].includes(status)) touchSession();
                return Promise.reject(error);
            }

            return recoverSession(status).then(function (shouldRetry) {
                if (!shouldRetry) return Promise.reject(error);
                config.__panelSessionRetried = true;
                return window.axios.request(config);
            });
        });
    }

    window.fetch = async function (input, init) {
        let request = new Request(input, init);

        if (!isPanelRequest(request.url) || isSessionEndpoint(request.url)
            || request.headers.get('X-Panel-Session-Skip') === '1') {
            return originalFetch(request);
        }

        const headers = new Headers(request.headers);
        headers.set('X-Requested-With', 'XMLHttpRequest');
        headers.set('X-CSRF-TOKEN', currentCsrfToken());
        request = new Request(request, { headers });
        const retryRequest = request.clone();
        const response = await originalFetch(request);

        if (![401, 419].includes(response.status)) {
            touchSession();
            return response;
        }

        const shouldRetry = await recoverSession(response.status);
        if (!shouldRetry) return response;

        const retryHeaders = new Headers(retryRequest.headers);
        retryHeaders.set('X-CSRF-TOKEN', currentCsrfToken());
        retryHeaders.set('X-Panel-Session-Retried', '1');

        return originalFetch(new Request(retryRequest, { headers: retryHeaders }));
    };

    modalElement.addEventListener('hide.bs.modal', function (event) {
        if (modalPromise && !allowModalHide) event.preventDefault();
    });

    document.querySelectorAll('a[href="' + loginPageUrl + '"]').forEach(function (link) {
        link.addEventListener('click', function () {
            window.clearTimeout(expiryTimer);
        });
    });

    verifyReturnedPage();
})();
