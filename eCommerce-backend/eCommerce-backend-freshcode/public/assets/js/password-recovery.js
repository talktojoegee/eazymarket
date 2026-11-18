import { initializeApp } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-app.js";
import { getAuth, RecaptchaVerifier, signInWithPhoneNumber } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-auth.js";

const root = document.querySelector('.password-recovery');

if (root) {
    const gateway = root.dataset.gateway || 'custom';
    const defaultChannel = root.dataset.defaultChannel || 'email';
    const sendUrl = root.dataset.sendOtpUrl;
    const verifyUrl = root.dataset.verifyOtpUrl;
    const configUrl = root.dataset.firebaseConfigUrl || '/api/settings/firebase-config';
    const csrf = root.dataset.csrf;

    const emailInput = document.getElementById('pr-email');
    const countrySelect = document.getElementById('pr-country');
    const numberInput = document.getElementById('pr-number');
    const sendBtn = document.getElementById('pr-send-btn');
    const otpSection = document.getElementById('pr-otp-section');
    const otpInput = document.getElementById('pr-otp');
    const verifyBtn = document.getElementById('pr-verify-btn');
    const msg = document.getElementById('pr-message');

    document.querySelectorAll('input[name="recovery_channel"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.recovery-pane').forEach(function (pane) {
                pane.style.display = pane.dataset.pane === radio.value ? '' : 'none';
            });
        });
    });

    let firebaseAuth = null;
    let confirmationResult = null;
    let cooldownTimer = null;

    function activeChannel() {
        const checked = document.querySelector('input[name="recovery_channel"]:checked');
        return checked ? checked.value : defaultChannel;
    }

    function isFirebaseMobile() {
        return activeChannel() === 'mobile' && gateway === 'firebase';
    }

    function setMessage(text, ok) {
        if (!msg) return;
        msg.textContent = text || '';
        msg.className = 'small mt-2 ' + (ok ? 'text-success' : 'text-danger');
    }

    function selectedDialCode() {
        const ts = countrySelect ? countrySelect.tomselect : null;
        if (!ts) return '';
        const value = ts.getValue();
        const option = value && ts.options ? ts.options[value] : null;
        const raw = option && option.phonecode ? String(option.phonecode) : '';
        return raw.replace(/\D/g, '');
    }

    function fullMobile() {
        const code = selectedDialCode();
        const number = (numberInput && numberInput.value ? numberInput.value : '').replace(/\D/g, '');
        if (!code || !number) return '';
        return '+' + code + number;
    }

    function identifier() {
        return activeChannel() === 'email'
            ? (emailInput && emailInput.value ? emailInput.value.trim() : '')
            : fullMobile();
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

    function lockInputs() {
        if (emailInput) emailInput.readOnly = true;
        if (numberInput) numberInput.readOnly = true;
        if (countrySelect && countrySelect.tomselect) countrySelect.tomselect.disable();
        document.querySelectorAll('input[name="recovery_channel"]').forEach(function (radio) {
            radio.disabled = true;
        });
        if (otpSection) otpSection.style.display = '';
    }

    async function postJson(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify(body),
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
            const id = identifier();
            if (!id) {
                setMessage(activeChannel() === 'email'
                    ? 'Enter your email address.'
                    : 'Select your country code and enter your mobile number.');
                return;
            }

            sendBtn.disabled = true;
            try {
                const check = await postJson(sendUrl, { identifier: id });
                if (!check.success) { setMessage(check.message); sendBtn.disabled = false; return; }

                if (isFirebaseMobile()) {
                    await initFirebase();
                    confirmationResult = await signInWithPhoneNumber(firebaseAuth, id, window.recaptchaVerifier);
                }

                lockInputs();
                setMessage(check.message, true);
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
                    payload = { identifier: identifier(), otp: code };
                }

                const result = await postJson(verifyUrl, payload);
                if (result.success && result.data && result.data.redirect) {
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
}
