import {initializeApp} from "https://www.gstatic.com/firebasejs/11.0.1/firebase-app.js";
import {deleteToken, getMessaging, getToken, onMessage} from "https://www.gstatic.com/firebasejs/11.0.1/firebase-messaging.js";

// Best-effort sync of the browser's FCM token to the backend so admin/seller
// panels can be targeted by push notifications. Customer + delivery boy tokens
// continue to flow through the API login (AuthTrait::storeFcmToken) and don't
// need this path.
//
// `firebase.js` is loaded as a module, so the inline non-module globals
// (`base_url`, `panel`, `user_id`, `csrfToken`) aren't visible here — read
// them from the DOM the same way the inline bootstrap does.
function readPanelContext() {
    const panelEl = document.getElementById('panel');
    const baseEl = document.getElementById('base_url');
    const userEl = document.getElementById('user_id');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const panel = panelEl ? (panelEl.getAttribute('data-panel') || '') : '';

    return {
        panel,
        baseUrl: baseEl ? (baseEl.value || '') : window.location.origin,
        userId: userEl ? (userEl.value || '') : '',
        csrf: csrfMeta ? (csrfMeta.getAttribute('content') || '') : '',
        roleType: panel === 'admin' ? 'admin' : (panel === 'seller' ? 'seller' : ''),
    };
}

function normalizeForegroundMessage(payload) {
    const notification = payload?.notification || {};
    const data = payload?.data || {};

    return {
        title: notification.title || data.title || 'Notification',
        body: notification.body || data.body || data.message || '',
        image: notification.image || notification.imageUrl || data.image || data.imageUrl || '',
        url: notification.click_action || data.click_action || data.url || '',
        data,
    };
}

function isAdminSupportMessage(data) {
    return data.type === 'support_message' && data.role_type === 'admin';
}

function shouldSuppressSupportMessage(data) {
    if (!isAdminSupportMessage(data)) {
        return false;
    }

    if (document.getElementById('support-app')) {
        return true;
    }

    const messageId = String(data.message_id || '');
    const seenMessageIds = window.SupportSeenMessageIds || new Set();
    window.SupportSeenMessageIds = seenMessageIds;
    if (messageId && seenMessageIds.has(messageId)) {
        return true;
    }

    const realtime = window.SupportAdminRealtime;
    if (realtime?.threadUuid && realtime.threadUuid === data.thread_uuid) {
        return true;
    }

    return false;
}

function rememberSupportMessage(data) {
    if (!isAdminSupportMessage(data) || !data.message_id) {
        return;
    }

    const seenMessageIds = window.SupportSeenMessageIds || new Set();
    seenMessageIds.add(String(data.message_id));
    window.SupportSeenMessageIds = seenMessageIds;
}

let messaging = null;
let serviceWorkerRegistration = null;
let firebaseConfig = null;
let firebaseConfigFingerprint = '';
let reconciliationPromise = null;

function getNotificationTrigger() {
    return document.querySelector('[data-header-notification-button]');
}

function updateNotificationState(state) {
    const trigger = getNotificationTrigger();
    if (!trigger) {
        return;
    }

    trigger.dataset.notificationState = state;
}

function incrementHeaderNotificationCount() {
    const button = document.querySelector('[data-header-notification-button]');
    if (!button) {
        return;
    }

    let badge = button.querySelector('[data-header-notification-count]');
    const count = Number.parseInt(badge?.dataset.count || '0', 10) + 1;

    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'badge bg-red badge-sm mt-1 badge-notification text-red-fg';
        badge.dataset.headerNotificationCount = '0';
        button.appendChild(badge);
    }

    badge.dataset.count = String(count);
    badge.textContent = count > 9 ? '9+' : String(count);
    button.setAttribute('aria-label', `Show notifications, ${count} unread`);
}

window.incrementPanelNotificationCount = incrementHeaderNotificationCount;

function getFirebaseConfigFingerprint(config) {
    return JSON.stringify({
        apiKey: config.apiKey || '',
        authDomain: config.authDomain || '',
        projectId: config.projectId || '',
        messagingSenderId: config.messagingSenderId || '',
        appId: config.appId || '',
        vapidKey: config.vapidKey || '',
    });
}

async function fetchFirebaseConfig(ctx) {
    const {data} = await axios.get(`${ctx.baseUrl}/api/settings/firebase-config`);
    const config = data.data;

    if (!config || typeof config !== 'object') {
        throw new Error('Firebase configuration is unavailable.');
    }

    firebaseConfigFingerprint = getFirebaseConfigFingerprint(config);
    localStorage.setItem('firebase_config', JSON.stringify(config));
    localStorage.setItem('firebase_config_fingerprint', firebaseConfigFingerprint);

    return config;
}

async function ensureMessagingServiceWorker(ctx) {
    if (!('serviceWorker' in navigator)) {
        throw new Error('Service workers are not supported in this browser.');
    }
    console.log('Registering Firebase messaging service worker...');

    if (window.panelMessagingWorkerReady) {
        await window.panelMessagingWorkerReady;
    }

    const serviceWorkerUrl = `${ctx.baseUrl}/firebase-messaging-sw.js`;
    const registration = await navigator.serviceWorker.register(serviceWorkerUrl, {
        scope: '/',
        updateViaCache: 'none',
    });

    // Force the browser to refresh the worker script when it has changed so we
    // don't stay stuck on an older worker that registered push handlers too late.
    await registration.update().catch(() => {});

    if (registration.active) {
        return registration;
    }

    return navigator.serviceWorker.ready;
}

async function syncTokenWithPanel(token, previousToken) {
    try {
        const ctx = readPanelContext();

        if (!ctx.userId || (ctx.panel !== 'admin' && ctx.panel !== 'seller')) {
            return false;
        }

        const response = await axios.post(`${ctx.baseUrl}/${ctx.panel}/devices/sync`, {
            fcm_token: token,
            device_type: 'web',
            previous_token: previousToken || undefined,
            role_type: ctx.roleType || undefined,
        }, {
            headers: {
                'X-CSRF-TOKEN': ctx.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        return response.data?.success !== false;
    } catch (err) {
        // Never break the page if the sync fails — token is already cached locally
        // and the next page load will retry.
        console.warn('Panel device sync failed:', err);
        return false;
    }
}

async function forgetTokenWithPanel(token) {
    try {
        const ctx = readPanelContext();

        if (!ctx.userId || !token || (ctx.panel !== 'admin' && ctx.panel !== 'seller')) {
            return false;
        }

        const response = await axios.delete(`${ctx.baseUrl}/${ctx.panel}/devices`, {
            data: {fcm_token: token},
            headers: {
                'X-CSRF-TOKEN': ctx.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        return response.data?.success !== false;
    } catch (err) {
        console.warn('Panel device forget failed:', err);
        return false;
    }
}

async function syncBrowserToken(forceSync = false) {
    const ctx = readPanelContext();
    const token = await getToken(messaging, {
        vapidKey: firebaseConfig.vapidKey,
        serviceWorkerRegistration,
    });

    if (!token) {
        throw new Error('Firebase did not return a browser token.');
    }

    const previousToken = localStorage.getItem('fcm_token');
    const syncContext = `${ctx.panel}:${ctx.userId}:${firebaseConfigFingerprint}:${token}`;
    const needsSync = forceSync
        || previousToken !== token
        || localStorage.getItem('fcm_token_synced_context') !== syncContext;

    localStorage.setItem('fcm_token', token);

    if (!needsSync) {
        return true;
    }

    const synced = await syncTokenWithPanel(
        token,
        previousToken && previousToken !== token ? previousToken : null
    );

    if (synced) {
        localStorage.setItem('fcm_token_synced_context', syncContext);
    }

    return synced;
}

async function rotateBrowserToken() {
    if (!messaging || !serviceWorkerRegistration || !firebaseConfig || Notification.permission !== 'granted') {
        return null;
    }

    const previousToken = localStorage.getItem('fcm_token');

    try {
        await deleteToken(messaging);
    } catch (err) {
        const subscription = await serviceWorkerRegistration.pushManager.getSubscription();
        await subscription?.unsubscribe();
    }

    localStorage.removeItem('fcm_token');
    localStorage.removeItem('fcm_token_synced_context');

    const token = await getToken(messaging, {
        vapidKey: firebaseConfig.vapidKey,
        serviceWorkerRegistration,
    });

    if (!token) {
        throw new Error('Firebase did not return a fresh browser token.');
    }

    const synced = await syncTokenWithPanel(
        token,
        previousToken && previousToken !== token ? previousToken : null
    );

    if (!synced) {
        throw new Error('The refreshed browser token could not be synced to the Admin account.');
    }

    const ctx = readPanelContext();
    localStorage.setItem('fcm_token', token);
    localStorage.setItem('fcm_token_synced_context', `${ctx.panel}:${ctx.userId}:${firebaseConfigFingerprint}:${token}`);

    return token;
}

window.refreshPanelNotificationToken = rotateBrowserToken;

async function forgetBrowserToken() {
    const token = localStorage.getItem('fcm_token');
    if (!token) {
        localStorage.removeItem('fcm_token_synced_context');
        return true;
    }

    try {
        await deleteToken(messaging);
    } catch (err) {
        console.warn('Firebase token deletion failed:', err);
    }

    const forgotten = await forgetTokenWithPanel(token);
    if (forgotten) {
        localStorage.removeItem('fcm_token');
        localStorage.removeItem('fcm_token_synced_context');
    }

    return forgotten;
}

async function reconcileNotificationState(forceSync = false) {
    if (reconciliationPromise) {
        return reconciliationPromise;
    }

    reconciliationPromise = (async () => {
        const permission = Notification.permission;

        if (permission === 'granted') {
            updateNotificationState('syncing');
            const synced = await syncBrowserToken(forceSync);
            updateNotificationState(synced ? 'enabled' : 'sync_failed');
            return;
        }

        if (localStorage.getItem('fcm_token')) {
            await forgetBrowserToken();
        }

        updateNotificationState(permission === 'denied' ? 'blocked' : 'needs_action');
    })().catch((err) => {
        console.warn('Notification state reconciliation failed:', err);
        updateNotificationState('sync_failed');
    }).finally(() => {
        reconciliationPromise = null;
    });

    return reconciliationPromise;
}

function registerNotificationLifecycle() {
    const trigger = getNotificationTrigger();
    if (trigger) {
        trigger.addEventListener('click', async () => {
            if (Notification.permission === 'denied') {
                return;
            }

            if (Notification.permission === 'default') {
                const permission = window.requestPanelNotificationPermission
                    ? await window.requestPanelNotificationPermission()
                    : await Notification.requestPermission();
                await reconcileNotificationState(permission === 'granted');
                return;
            }

            await reconcileNotificationState(true);
        });
    }

    if (navigator.permissions?.query) {
        navigator.permissions.query({name: 'notifications'}).then((status) => {
            status.addEventListener('change', () => reconcileNotificationState(true));
        }).catch(() => {});
    }

    document.addEventListener('panel:notification-permission-changed', () => reconcileNotificationState(true));

    window.addEventListener('focus', () => reconcileNotificationState());
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            reconcileNotificationState();
        }
    });
}

async function initFirebase() {
    const ctx = readPanelContext();

    if (!ctx.userId || (ctx.panel !== 'admin' && ctx.panel !== 'seller')) {
        return;
    }

    if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
        updateNotificationState('unsupported');
        return;
    }

    updateNotificationState('syncing');

    try {
        firebaseConfig = await fetchFirebaseConfig(ctx);
        const app = initializeApp(firebaseConfig);
        messaging = getMessaging(app);
        serviceWorkerRegistration = await ensureMessagingServiceWorker(ctx);
        registerNotificationLifecycle();
        await reconcileNotificationState(true);

        // Foreground messages — render an in-page toast.
        onMessage(messaging, async (payload) => {
            console.log('Message received in foreground:', payload);

            const {title, body, image, url, data} = normalizeForegroundMessage(payload);
            if (isAdminSupportMessage(data) && window.SupportAdminRealtime?.mode === 'live') {
                await new Promise(resolve => window.setTimeout(resolve, 500));
            }
            if (shouldSuppressSupportMessage(data)) {
                return;
            }
            rememberSupportMessage(data);
            const displayDuration = 10000;
            window.Toast?.fire({
                icon: 'info',
                title: title || 'Notification',
                text: body || '',
                imageUrl: image || '',
                actionUrl: url || '',
                timer: displayDuration,
            });
            incrementHeaderNotificationCount();
            document.dispatchEvent(new CustomEvent('panel:notification-received'));

            try {
                const playback = window.playPanelNotificationSound
                    ? window.playPanelNotificationSound()
                    : new Audio('/assets/sound/notification.wav').play();
                playback?.catch((err) => {
                    console.warn('Autoplay blocked for notification sound:', err);
                });
            } catch (e) {
                console.warn('Failed to play notification sound:', e);
            }
        });


    } catch (err) {
        console.warn('Firebase is not setuped - Error initializing Firebase:', err);
        updateNotificationState('sync_failed');
    }
}

initFirebase();
