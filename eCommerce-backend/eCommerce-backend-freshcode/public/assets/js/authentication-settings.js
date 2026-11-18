const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const testCenter = document.querySelector('[data-auth-section="tests"]');
const firebaseForm = document.getElementById('firebaseSettingsForm');
const otpForm = document.querySelector('[data-auth-section="otp"] form');
const resultBox = document.getElementById('authenticationTestResult');
const messages = testCenter?.dataset || {};
let dirty = false;
let phoneConfirmation = null;
let phoneApp = null;
let phoneAuth = null;
let phoneSdk = null;
let recaptchaVerifier = null;
let firebaseSdkPromise = null;

function loadFirebaseSdk() {
    if (!firebaseSdkPromise) {
        firebaseSdkPromise = Promise.all([
            import('https://www.gstatic.com/firebasejs/11.0.1/firebase-app.js'),
            import('https://www.gstatic.com/firebasejs/11.0.1/firebase-auth.js'),
        ]).then(([app, auth]) => ({...app, ...auth}));
    }

    return firebaseSdkPromise;
}

function tabName(trigger) {
    const target = trigger.dataset.authTabName
        || trigger.id?.replace(/-tab$/, '')
        || trigger.dataset.bsTarget
        || trigger.getAttribute('href')
        || '';

    return target.replace(/^#(?:auth-)?/, '');
}

function activateTab(name) {
    document.querySelectorAll('#authenticationTabs [data-auth-tab-name]').forEach((trigger) => {
        const active = tabName(trigger) === name;
        trigger.classList.toggle('active', active);
        trigger.setAttribute('aria-selected', active ? 'true' : 'false');
        trigger.setAttribute('tabindex', active ? '0' : '-1');
    });
    document.querySelectorAll('[data-auth-section]').forEach((pane) => {
        const active = pane.dataset.authSection === name;
        pane.classList.toggle('active', active);
        pane.classList.toggle('show', active);
        pane.hidden = !active;
    });
    history.replaceState(null, '', `#${name}`);
}

function bindTabs() {
    document.querySelectorAll('[data-auth-tab-link]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            activateTab(link.dataset.authTabLink);
        });
    });
    document.querySelectorAll('#authenticationTabs [data-auth-tab-name]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            activateTab(tabName(trigger));
        });
        trigger.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const tabs = [...document.querySelectorAll('#authenticationTabs [data-auth-tab-name]')];
            const current = tabs.indexOf(trigger);
            const next = event.key === 'Home'
                ? 0
                : event.key === 'End'
                    ? tabs.length - 1
                    : (current + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            activateTab(tabName(tabs[next]));
            tabs[next].focus();
        });
    });
    const initial = window.location.hash.replace('#', '').replace(/^auth-/, '');
    if (['account', 'otp', 'firebase', 'social', 'tests'].includes(initial)) activateTab(initial);
}

function bindDirtyState() {
    document.querySelectorAll('.auth-section-form').forEach((form) => {
        form.addEventListener('input', () => { dirty = true; });
    });
    window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
    document.addEventListener('resource:saved', (event) => {
        if (event.detail?.form?.classList.contains('auth-section-form')) dirty = false;
    });
    document.addEventListener('resource:save-failed', (event) => {
        const section = event.detail?.form?.closest('[data-auth-section]')?.dataset.authSection;
        if (!section) return;
        activateTab(section);
        window.setTimeout(() => event.detail.form.querySelector('.is-invalid')?.focus(), 0);
    });
}

function selectedGateway() {
    return document.querySelector('input[name="smsGateway"]:checked')?.value || '';
}

function refreshGateway() {
    const gateway = selectedGateway();
    document.getElementById('customSmsFields')?.classList.toggle('d-none', gateway !== 'custom');
    document.getElementById('firebaseOtpNotice')?.classList.toggle('d-none', gateway !== 'firebase');
    document.querySelectorAll('.sms-gateway-card').forEach((card) => {
        card.classList.toggle('border-primary', card.querySelector('input')?.checked === true);
    });
}

function bindGateway() {
    document.querySelectorAll('input[name="smsGateway"]').forEach((input) => input.addEventListener('change', refreshGateway));
    refreshGateway();
}

function bindSmsGuidance() {
    const template = document.getElementById('customSmsTextFormatData');
    const format = document.getElementById('customSmsMobileFormat');
    const testMobile = document.getElementById('testSmsMobile');
    const refreshPlaceholder = () => {
        const example = format?.selectedOptions?.[0]?.dataset.example;
        if (testMobile && example) testMobile.placeholder = example;
    };

    document.querySelectorAll('.sms-template-suggestion').forEach((button) => {
        button.addEventListener('click', () => {
            if (!template) return;
            template.value = button.dataset.template;
            template.dispatchEvent(new Event('input', {bubbles: true}));
            template.focus();
        });
    });
    format?.addEventListener('change', refreshPlaceholder);
    refreshPlaceholder();
}

function bindSmsPairs() {
    document.querySelectorAll('.add-sms-field').forEach((button) => {
        button.addEventListener('click', () => {
            const container = document.getElementById(`${button.dataset.group}Fields`);
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 sms-pair-row';
            row.innerHTML = `<div class="col-md-5"><input type="text" class="form-control" name="${button.dataset.keyName}[]" placeholder="${messages.keyLabel}"></div><div class="col-md-5"><input type="text" class="form-control" name="${button.dataset.valueName}[]" placeholder="${messages.valueLabel}"></div><div class="col-md-2"><button type="button" class="btn btn-icon btn-outline-danger remove-sms-field" aria-label="${messages.removeLabel}"><i class="ti ti-trash"></i></button></div>`;
            container?.appendChild(row);
            dirty = true;
        });
    });
    document.addEventListener('click', (event) => {
        const remove = event.target.closest('.remove-sms-field');
        if (!remove) return;
        remove.closest('.sms-pair-row')?.remove();
        dirty = true;
    });
}

function firebaseConfig() {
    const value = (name) => firebaseForm?.querySelector(`[name="${name}"]`)?.value.trim() || '';
    const config = {
        apiKey: value('fireBaseApiKey'),
        authDomain: value('fireBaseAuthDomain'),
        databaseURL: value('fireBaseDatabaseURL'),
        projectId: value('fireBaseProjectId'),
        storageBucket: value('fireBaseStorageBucket'),
        messagingSenderId: value('fireBaseMessagingSenderId'),
        appId: value('fireBaseAppId'),
        measurementId: value('fireBaseMeasurementId'),
    };
    const missing = ['apiKey', 'authDomain', 'projectId', 'messagingSenderId', 'appId'].filter((key) => !config[key]);
    if (missing.length) throw new Error(`${messages.missingConfig} ${missing.join(', ')}`);
    return config;
}

function serviceAccountFile() {
    return firebaseForm?.querySelector('[name="serviceAccountFile"]')?.files?.[0] || null;
}

function readableDetails(details) {
    if (!details) return '';
    const status = details.gateway_status;
    const content = details.gateway_body || details.gateway_error || details.error;
    if (content !== null && content !== undefined && content !== '') {
        const text = typeof content === 'string' ? content : JSON.stringify(content, null, 2);
        try {
            return `${status ? `HTTP ${status}\n` : ''}${JSON.stringify(JSON.parse(text), null, 2)}`;
        } catch (_) {
            return `${status ? `HTTP ${status}\n` : ''}${text}`;
        }
    }

    const filtered = Object.fromEntries(Object.entries(details).filter(([, value]) => value !== null && value !== ''));
    return Object.keys(filtered).length ? JSON.stringify(filtered, null, 2) : '';
}

function showResult(success, message, details = null) {
    if (!resultBox) return;
    resultBox.className = `alert mt-4 ${success ? 'alert-success' : 'alert-danger'}`;
    resultBox.replaceChildren();
    const heading = document.createElement('div');
    heading.className = 'fw-semibold';
    heading.textContent = message;
    resultBox.appendChild(heading);
    const detailText = readableDetails(details);
    if (detailText) {
        const pre = document.createElement('pre');
        pre.className = 'bg-dark text-white rounded p-3 mt-3 mb-0 text-start overflow-auto';
        pre.style.maxHeight = '320px';
        pre.textContent = detailText;
        resultBox.appendChild(pre);
    }
}

function showDiagnosticModal(success, message, details) {
    const content = document.createElement('div');
    content.className = 'text-start';
    const summary = document.createElement('div');
    summary.className = 'mb-3';
    summary.textContent = message;
    content.appendChild(summary);
    const detailText = readableDetails(details);
    if (detailText) {
        const pre = document.createElement('pre');
        pre.className = 'bg-dark text-white rounded p-3 mb-0 overflow-auto';
        pre.style.maxHeight = '360px';
        pre.textContent = detailText;
        content.appendChild(pre);
    }
    window.Swal.fire({
        icon: success ? 'success' : 'error',
        title: success ? messages.testCompleted : messages.testFailed,
        html: content,
        width: 700,
    });
}

function showDiagnosticError(message, details) {
    showDiagnosticModal(false, message, details);
}

function setBusy(button, busy) {
    button.disabled = busy;
    button.classList.toggle('disabled', busy);
}

async function postForm(endpoint, data) {
    const response = await window.axios.post(endpoint, data, {
        headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'},
    });
    if (response.data?.success === false) {
        const error = new Error(response.data?.message || messages.testFailed);
        error.details = response.data?.data;
        throw error;
    }
    return response.data;
}

async function testConnection() {
    const data = new FormData();
    data.append('fireBaseProjectId', firebaseConfig().projectId);
    const file = serviceAccountFile();
    if (file) data.append('serviceAccountFile', file);
    const response = await postForm(testCenter.dataset.connectionEndpoint, data);
    showResult(true, response.message, response.data);
    showDiagnosticModal(true, response.message, response.data);
}

async function testSms() {
    const mobile = document.getElementById('testSmsMobile')?.value.trim();
    if (!mobile) throw new Error(messages.enterSmsMobile);
    const data = new FormData(otpForm);
    data.append('test_mobile', mobile);
    const response = await postForm(testCenter.dataset.smsEndpoint, data);
    showResult(true, response.message, response.data);
}

async function createFirebaseAuth(name) {
    const sdk = await loadFirebaseSdk();
    const app = sdk.initializeApp(firebaseConfig(), `${name}-${Date.now()}`);
    return {sdk, app, auth: sdk.getAuth(app)};
}

async function verifyIdentity(provider, idToken) {
    const data = new FormData();
    data.append('provider', provider);
    data.append('id_token', idToken);
    const file = serviceAccountFile();
    if (file) data.append('serviceAccountFile', file);
    const response = await postForm(testCenter.dataset.identityEndpoint, data);
    showResult(true, `${response.message} ${response.data?.identity || ''}`.trim());
}

async function testProvider(provider) {
    const {sdk, app, auth} = await createFirebaseAuth(provider);
    const authProvider = provider === 'google' ? new sdk.GoogleAuthProvider() : new sdk.OAuthProvider('apple.com');
    if (provider === 'apple') {
        authProvider.addScope('email');
        authProvider.addScope('name');
    }
    try {
        const credential = await sdk.signInWithPopup(auth, authProvider);
        await verifyIdentity(provider, await credential.user.getIdToken(true));
        await sdk.signOut(auth);
    } finally {
        await sdk.deleteApp(app);
    }
}

function firebaseTestError(error, test) {
    if (test === 'apple' && error.code === 'auth/operation-not-allowed') return messages.appleOperationNotAllowed;
    if (error.code === 'auth/unauthorized-domain') return messages.firebaseUnauthorizedDomain;
    if (error.code === 'auth/popup-blocked') return messages.firebasePopupBlocked;
    if (error.code === 'auth/popup-closed-by-user' || error.code === 'auth/cancelled-popup-request') return messages.firebasePopupClosed;

    return error.response?.data?.message || error.message;
}

function firebaseTestDetails(error, test) {
    const details = error.response?.data?.data || error.details || {};
    if (!error.code) return Object.keys(details).length ? details : null;

    return {
        ...details,
        error_code: error.code,
        provider: ['google', 'apple', 'phone-send', 'phone-verify'].includes(test) ? test.replace(/-.+$/, '') : undefined,
        project_id: firebaseConfig().projectId,
        auth_domain: firebaseConfig().authDomain,
    };
}

async function sendPhoneOtp() {
    const phone = document.getElementById('firebaseTestPhone')?.value.trim();
    if (!phone) throw new Error(messages.enterFirebasePhone);
    await resetPhoneTest();
    const instance = await createFirebaseAuth('phone');
    phoneApp = instance.app;
    phoneAuth = instance.auth;
    phoneSdk = instance.sdk;
    document.getElementById('firebaseTestRecaptcha')?.replaceChildren();
    recaptchaVerifier = new phoneSdk.RecaptchaVerifier(phoneAuth, 'firebaseTestRecaptcha', {size: 'normal'});
    phoneConfirmation = await phoneSdk.signInWithPhoneNumber(phoneAuth, phone, recaptchaVerifier);
    document.querySelector('[data-test="phone-verify"]').disabled = false;
    showResult(true, messages.otpSent);
}

async function resetPhoneTest() {
    recaptchaVerifier?.clear();
    recaptchaVerifier = null;
    phoneConfirmation = null;
    if (phoneApp && phoneSdk) await phoneSdk.deleteApp(phoneApp);
    phoneApp = null;
    phoneAuth = null;
    phoneSdk = null;
    document.getElementById('firebaseTestRecaptcha')?.replaceChildren();
    document.querySelector('[data-test="phone-verify"]')?.setAttribute('disabled', 'disabled');
}

async function verifyPhoneOtp() {
    const otp = document.getElementById('firebaseTestOtp')?.value.trim();
    if (!phoneConfirmation || !otp) throw new Error(messages.completePhone);
    const credential = await phoneConfirmation.confirm(otp);
    await verifyIdentity('phone', await credential.user.getIdToken(true));
    await phoneSdk.signOut(phoneAuth);
    document.getElementById('firebaseTestOtp').value = '';
    await resetPhoneTest();
}

function bindTests() {
    document.querySelectorAll('.auth-test-button').forEach((button) => {
        button.addEventListener('click', async () => {
            setBusy(button, true);
            const test = button.dataset.test;
            try {
                if (test === 'connection') await testConnection();
                if (test === 'sms') await testSms();
                if (test === 'google' || test === 'apple') await testProvider(test);
                if (test === 'phone-send') await sendPhoneOtp();
                if (test === 'phone-verify') await verifyPhoneOtp();
                if (test !== 'connection') Toast.fire({icon: 'success', title: resultBox?.textContent || messages.testCompleted});
            } catch (error) {
                const message = firebaseTestError(error, test);
                const details = firebaseTestDetails(error, test);
                showResult(false, message, details);
                showDiagnosticError(message, details);
            } finally {
                setBusy(button, false);
                if (button.dataset.test === 'phone-verify') button.disabled = !phoneConfirmation;
            }
        });
    });
}

bindTabs();
bindDirtyState();
bindGateway();
bindSmsGuidance();
bindSmsPairs();
bindTests();
