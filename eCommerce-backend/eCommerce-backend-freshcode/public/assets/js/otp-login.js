import { initializeApp } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-app.js";
import { getAuth, RecaptchaVerifier, signInWithPhoneNumber } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-auth.js";

const root = document.querySelector('.otp-login');

if (root) {
    const sendUrl = root.dataset.sendUrl;
    const verifyUrl = root.dataset.verifyUrl;
    const configUrl = root.dataset.firebaseConfigUrl || '/api/settings/firebase-config';
    const reauthenticationToken = root.dataset.reauthenticationToken;

    const identifierInput = document.getElementById('ol-identifier');
    const sendBtn = document.getElementById('ol-send-btn');
    const otpSection = document.getElementById('ol-otp-section');
    const otpInput = document.getElementById('ol-otp');
    const verifyBtn = document.getElementById('ol-verify-btn');
    const msg = document.getElementById('ol-message');

    let firebaseAuth = null;
    let confirmationResult = null;
    let cooldownTimer = null;
    let channel = null;
    let gateway = null;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || root.dataset.csrf;
    }

    function withReauthentication(payload) {
        if (!reauthenticationToken) return payload;

        return {
            ...payload,
            reauthentication: true,
            reauthentication_token: reauthenticationToken,
        };
    }

    function setMessage(text, ok) {
        if (!msg) return;
        msg.textContent = text || '';
        msg.className = 'small mt-2 ' + (ok ? 'text-success' : 'text-danger');
    }

    function isFirebaseMobile() {
        return channel === 'mobile' && gateway === 'firebase';
    }

    function startResendCooldown(seconds) {
        if (!sendBtn) return;
        let remaining = seconds;
        clearInterval(cooldownTimer);
        sendBtn.disabled = true;
        sendBtn.textContent = 'Resend OTP in ' + remaining + 's';
        cooldownTimer = setInterval(function () {
            remaining -= 1;
            if (remaining <= 0) {
                clearInterval(cooldownTimer);
                sendBtn.disabled = false;
                sendBtn.textContent = 'Resend OTP';
            } else {
                sendBtn.textContent = 'Resend OTP in ' + remaining + 's';
            }
        }, 1000);
    }

    function lockIdentifier() {
        if (identifierInput) identifierInput.readOnly = true;
        if (otpSection) otpSection.style.display = '';
    }

    async function postJson(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify(withReauthentication(body)),
        });
        return res.json();
    }

    async function initFirebase() {
        if (firebaseAuth) return firebaseAuth;
        const res = await fetch(configUrl);
        const json = await res.json();
        const config = json.data || json;
        const app = initializeApp(config);
        firebaseAuth = getAuth(app);
        window.recaptchaVerifier = new RecaptchaVerifier(firebaseAuth, 'recaptcha-container', { size: 'invisible' });
        return firebaseAuth;
    }

    if (sendBtn) {
        sendBtn.addEventListener('click', async function () {
            const identifier = (identifierInput.value || '').trim();
            if (!identifier) { setMessage('Enter your email or mobile number.'); return; }

            sendBtn.disabled = true;
            try {
                const result = await postJson(sendUrl, { identifier });
                if (!result.success) { setMessage(result.message); sendBtn.disabled = false; return; }

                channel = result.data ? result.data.channel : 'email';
                gateway = result.data ? result.data.gateway : null;

                if (isFirebaseMobile()) {
                    if (!identifier.startsWith('+')) {
                        setMessage('Enter your mobile in international format, e.g. +14155552671.');
                        sendBtn.disabled = false;
                        return;
                    }
                    await initFirebase();
                    confirmationResult = await signInWithPhoneNumber(firebaseAuth, identifier, window.recaptchaVerifier);
                }

                lockIdentifier();
                setMessage(result.message, true);
                startResendCooldown(30);
            } catch (e) {
                setMessage('Failed to send code: ' + (e.message || e));
                sendBtn.disabled = false;
            }
        });
    }

    if (verifyBtn) {
        verifyBtn.addEventListener('click', async function () {
            const code = (otpInput.value || '').trim();
            if (!code) { setMessage('Enter the code you received.'); return; }

            verifyBtn.disabled = true;
            try {
                let payload;
                if (isFirebaseMobile()) {
                    if (!confirmationResult) { setMessage('Please send the code first.'); verifyBtn.disabled = false; return; }
                    const credential = await confirmationResult.confirm(code);
                    payload = { idToken: await credential.user.getIdToken() };
                } else {
                    payload = { identifier: (identifierInput.value || '').trim(), otp: code };
                }

                const result = await postJson(verifyUrl, payload);
                if (result.success && result.data && result.data.reauthenticated) {
                    document.dispatchEvent(new CustomEvent('panel:reauthenticated', {
                        detail: { csrfToken: result.data.csrf_token },
                    }));
                } else if (result.success && result.data && result.data.redirect) {
                    window.location = result.data.redirect;
                } else {
                    setMessage(result.message || 'Verification failed.');
                    verifyBtn.disabled = false;
                }
            } catch (e) {
                setMessage('Invalid or expired code. Please try again.');
                verifyBtn.disabled = false;
            }
        });
    }

    document.addEventListener('panel:reauthentication-complete', function () {
        clearInterval(cooldownTimer);
        confirmationResult = null;
        channel = null;
        gateway = null;
        if (otpInput) otpInput.value = '';
        if (otpSection) otpSection.style.display = 'none';
        if (sendBtn) {
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send OTP';
        }
        if (verifyBtn) verifyBtn.disabled = false;
        setMessage('');
    });
}
