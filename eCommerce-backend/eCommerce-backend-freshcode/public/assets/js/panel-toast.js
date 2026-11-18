(function () {
    'use strict';

    const state = {
        expanded: false,
        items: [],
        labels: {
            notifications: 'Notifications',
            clearAll: 'Clear all',
            showLess: 'Show less',
            close: 'Close',
            view: 'View',
        },
    };
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    const durations = {success: 4000, info: 6000, warning: 8000, default: 6000, error: null};
    const iconClasses = {
        success: 'ti ti-circle-check',
        error: 'ti ti-alert-circle',
        warning: 'ti ti-alert-triangle',
        info: 'ti ti-info-circle',
        default: 'ti ti-bell',
    };
    let sequence = 0;
    let shell;
    let list;
    let toolbarTitle;
    let clearCollapsedButton;
    let clearCollapsedLabel;
    let clearExpandedButton;
    let clearExpandedLabel;
    let collapseButton;
    let animationFrame;

    function createElement(tag, className, text) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined) element.textContent = text;
        return element;
    }

    function createButton(className, label, iconClass) {
        const button = createElement('button', className);
        button.type = 'button';
        button.setAttribute('aria-label', label);
        if (iconClass) {
            const icon = createElement('i', iconClass);
            icon.setAttribute('aria-hidden', 'true');
            button.appendChild(icon);
        }
        return button;
    }

    function viewportWidth(element) {
        return Math.max(document.documentElement.clientWidth, window.innerWidth || 0, element?.offsetWidth || 0);
    }

    function swipeFadeDistance(element) {
        return Math.max(140, viewportWidth(element) * .45);
    }

    function swipeDismissThreshold(element, startPosition, direction) {
        const availableDistance = direction < 0
            ? startPosition
            : viewportWidth(element) - startPosition;
        const naturalThreshold = Math.min(72, Math.max(40, (element?.offsetWidth || 0) * .14));
        return Math.min(naturalThreshold, Math.max(8, availableDistance * .45));
    }

    function bindSwipeDismiss(gestureElement, motionElement, onDismiss) {
        let pointerId = null;
        let startX = 0;
        let startY = 0;
        let currentX = 0;
        let startedAt = 0;
        let moved = false;
        let suppressClick = false;
        let restoreAnimation = null;

        const cleanStyles = () => {
            motionElement.classList.remove('is-swiping');
            motionElement.style.removeProperty('transform');
            motionElement.style.removeProperty('opacity');
        };

        const stopRestoreAnimation = () => {
            if (!restoreAnimation) return;
            const animation = restoreAnimation;
            restoreAnimation = null;
            animation.cancel();
        };

        const restore = () => {
            stopRestoreAnimation();
            if (!moved || reduceMotion) {
                cleanStyles();
                return;
            }
            const animation = motionElement.animate([
                {transform: motionElement.style.transform, opacity: motionElement.style.opacity || 1},
                {transform: 'translateX(0)', opacity: 1},
            ], {duration: 180, easing: 'cubic-bezier(.2,.8,.2,1)'});
            restoreAnimation = animation;
            animation.finished.then(() => {
                if (restoreAnimation !== animation) return;
                restoreAnimation = null;
                cleanStyles();
            }).catch(() => {});
        };

        const releasePointer = activePointerId => {
            if (activePointerId === null || !gestureElement.hasPointerCapture?.(activePointerId)) return;
            try {
                gestureElement.releasePointerCapture(activePointerId);
            } catch {}
        };

        const finish = (event, cancelled) => {
            if (event.pointerId !== pointerId) return;
            const distance = currentX - startX;
            const elapsed = Math.max(1, performance.now() - startedAt);
            const direction = distance < 0 ? -1 : 1;
            const threshold = swipeDismissThreshold(motionElement, startX, direction);
            const velocity = Math.abs(distance / elapsed);
            const activePointerId = pointerId;
            pointerId = null;
            releasePointer(activePointerId);
            if (cancelled) suppressClick = false;
            if (!cancelled && moved && (Math.abs(distance) >= threshold || (Math.abs(distance) >= 8 && velocity > .28))) {
                onDismiss(direction);
                moved = false;
                return;
            }
            restore();
            moved = false;
        };

        gestureElement.addEventListener('pointerdown', event => {
            if (!event.isPrimary || (event.pointerType === 'mouse' && event.button !== 0)) return;
            if (event.target.closest('button, a, input, textarea, select')) return;
            if (pointerId !== null) finish({pointerId}, true);
            stopRestoreAnimation();
            cleanStyles();
            pointerId = event.pointerId;
            startX = event.clientX;
            startY = event.clientY;
            currentX = startX;
            startedAt = performance.now();
            moved = false;
            try {
                gestureElement.setPointerCapture?.(pointerId);
            } catch {}
        });

        gestureElement.addEventListener('pointermove', event => {
            if (event.pointerId !== pointerId) return;
            currentX = event.clientX;
            const distance = currentX - startX;
            const verticalDistance = event.clientY - startY;
            if (!moved) {
                if (Math.abs(distance) < 7 && Math.abs(verticalDistance) < 7) return;
                if (Math.abs(verticalDistance) >= Math.abs(distance)) {
                    finish(event, true);
                    return;
                }
                moved = true;
                suppressClick = true;
                motionElement.classList.add('is-swiping');
            }
            event.preventDefault();
            motionElement.style.transform = `translateX(${distance}px)`;
            motionElement.style.opacity = String(Math.max(0, 1 - Math.abs(distance) / swipeFadeDistance(motionElement)));
        });

        gestureElement.addEventListener('pointerup', event => finish(event, false));
        gestureElement.addEventListener('pointercancel', event => finish(event, true));
        gestureElement.addEventListener('lostpointercapture', event => finish(event, true));
        gestureElement.addEventListener('pointerleave', event => {
            if (event.pointerId === pointerId && !gestureElement.hasPointerCapture?.(pointerId)) finish(event, true);
        });
        gestureElement.addEventListener('click', event => {
            if (!suppressClick) return;
            suppressClick = false;
            event.preventDefault();
            event.stopImmediatePropagation();
        }, true);
    }

    function ensureRegion() {
        if (shell?.isConnected) return;

        shell = createElement('section', 'panel-toast-shell');
        shell.setAttribute('aria-label', state.labels.notifications);
        shell.addEventListener('mouseenter', pauseAll);
        shell.addEventListener('mouseleave', resumeAll);
        shell.addEventListener('focusin', pauseAll);
        shell.addEventListener('focusout', event => {
            if (!shell.contains(event.relatedTarget)) resumeAll();
        });

        const toolbar = createElement('div', 'panel-toast-toolbar');
        toolbarTitle = createElement('strong', 'panel-toast-toolbar__title', state.labels.notifications);
        const toolbarActions = createElement('div', 'panel-toast-toolbar__actions');
        clearExpandedButton = createButton('panel-toast-toolbar__button panel-toast-clear-expanded', state.labels.clearAll, 'ti ti-x');
        clearExpandedLabel = createElement('span', 'panel-toast-clear-label', state.labels.clearAll);
        clearExpandedButton.appendChild(clearExpandedLabel);
        collapseButton = createButton('panel-toast-toolbar__button panel-toast-collapse', state.labels.showLess);
        collapseButton.textContent = state.labels.showLess;
        toolbarActions.append(clearExpandedButton, collapseButton);
        toolbar.append(toolbarTitle, toolbarActions);

        clearCollapsedButton = createButton('panel-toast-clear-collapsed', state.labels.clearAll, 'ti ti-x');
        clearCollapsedLabel = createElement('span', 'panel-toast-clear-label', state.labels.clearAll);
        clearCollapsedButton.appendChild(clearCollapsedLabel);
        list = createElement('div', 'panel-toast-list');

        clearCollapsedButton.addEventListener('click', event => {
            event.stopPropagation();
            clearAll();
        });
        clearExpandedButton.addEventListener('click', clearAll);
        collapseButton.addEventListener('click', collapse);
        shell.append(toolbar, clearCollapsedButton, list);
        document.body.appendChild(shell);
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && state.expanded) collapse();
        });
        document.addEventListener('pointerdown', event => {
            if (state.expanded && !shell.contains(event.target)) collapse();
        });
    }

    function normalizeType(icon) {
        return Object.prototype.hasOwnProperty.call(iconClasses, icon) ? icon : 'default';
    }

    function normalizeUrl(url) {
        if (!url) return null;
        try {
            const normalized = new URL(url, window.location.origin);
            return normalized.origin === window.location.origin ? normalized.href : null;
        } catch (_) {
            return null;
        }
    }

    function normalizeImageUrl(url) {
        if (!url) return '';
        try {
            const normalized = new URL(url, window.location.origin);
            return ['http:', 'https:'].includes(normalized.protocol) ? normalized.href : '';
        } catch (_) {
            return '';
        }
    }

    function slideDistance() {
        return document.documentElement.dir === 'rtl' ? -48 : 48;
    }

    function defaultTimer(type, options) {
        if (options.persistent === true || options.timer === false || options.timer === 0 || options.timer === null) return null;
        if (Number.isFinite(Number(options.timer))) return Math.max(0, Number(options.timer));
        return durations[type];
    }

    function createToastItem(options) {
        const type = normalizeType(options.icon);
        const timer = defaultTimer(type, options);
        let resolvePromise;
        const promise = new Promise(resolve => {
            resolvePromise = resolve;
        });
        const item = {
            id: `panel-toast-${++sequence}`,
            type,
            title: String(options.title || state.labels.notifications),
            text: String(options.text || options.description || ''),
            imageUrl: normalizeImageUrl(options.imageUrl || options.image),
            actionUrl: normalizeUrl(options.actionUrl || options.url),
            actionLabel: String(options.actionLabel || state.labels.view),
            timer,
            remaining: timer,
            lastTick: null,
            paused: state.expanded || shell?.matches(':hover') || shell?.contains(document.activeElement),
            closing: false,
            didOpen: typeof options.didOpen === 'function' ? options.didOpen : null,
            didClose: typeof options.didClose === 'function' ? options.didClose : null,
            resolve: resolvePromise,
            promise,
        };

        item.slot = createElement('div', 'panel-toast-slot');
        item.slot.dataset.toastId = item.id;
        item.card = createElement('article', `panel-toast-card panel-toast-card--${type}`);
        item.card.setAttribute('role', type === 'error' ? 'alert' : 'status');
        item.card.setAttribute('aria-atomic', 'true');

        const closeButton = createButton('panel-toast-card__close', state.labels.close, 'ti ti-x');
        closeButton.addEventListener('click', event => {
            event.stopPropagation();
            dismiss(item, 'close');
        });

        const icon = createElement('span', 'panel-toast-card__icon');
        if (item.imageUrl) {
            const image = createElement('img');
            image.src = item.imageUrl;
            image.alt = '';
            image.loading = 'lazy';
            image.referrerPolicy = 'no-referrer';
            icon.appendChild(image);
        } else {
            const iconElement = createElement('i', iconClasses[type]);
            iconElement.setAttribute('aria-hidden', 'true');
            icon.appendChild(iconElement);
        }

        const content = createElement('div', 'panel-toast-card__content');
        content.appendChild(createElement('strong', 'panel-toast-card__title', item.title));
        if (item.text) content.appendChild(createElement('div', 'panel-toast-card__message', item.text));

        const action = createButton('panel-toast-card__action', item.actionLabel);
        action.textContent = item.actionLabel;
        action.hidden = !item.actionUrl;
        action.addEventListener('click', event => {
            event.stopPropagation();
            openAction(item);
        });

        const progress = createElement('span', 'panel-toast-card__progress');
        item.progress = createElement('span', 'panel-toast-card__progress-value');
        progress.hidden = timer === null;
        progress.appendChild(item.progress);
        item.card.append(closeButton, icon, content, action, progress);
        item.slot.appendChild(item.card);
        item.card.addEventListener('click', event => handleCardActivation(item, event));
        item.card.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                handleCardActivation(item, event);
            }
        });
        bindSwipeDismiss(item.card, item.slot, direction => dismiss(item, 'swipe', direction));
        return item;
    }

    function handleCardActivation(item, event) {
        if (event.target.closest('button')) return;
        if (!state.expanded && state.items.length > 1) {
            expand();
            return;
        }
        if (item.actionUrl) openAction(item);
    }

    function openAction(item) {
        if (!item.actionUrl) return;
        window.location.assign(item.actionUrl);
        dismiss(item, 'action');
    }

    function fire(options = {}) {
        ensureRegion();
        const normalizedOptions = typeof options === 'string' ? {title: options} : options;
        const item = createToastItem(normalizedOptions);
        state.items.unshift(item);
        list.prepend(item.slot);
        syncLayout();
        requestAnimationFrame(() => {
            if (!reduceMotion) {
                item.slot.animate([
                    {opacity: 0, transform: `translateX(${slideDistance()}px) scale(.96)`},
                    {opacity: 1, transform: 'translateX(0) scale(1)'},
                ], {duration: 280, easing: 'cubic-bezier(.2,.8,.2,1)'});
            }
            item.didOpen?.(item.card);
        });
        startTicker();
        return item.promise;
    }

    function layoutRects() {
        return new Map(state.items.filter(item => !item.slot.hidden).map(item => [item.id, item.slot.getBoundingClientRect()]));
    }

    function animateLayout(previousRects) {
        if (reduceMotion) return;
        state.items.forEach(item => {
            if (item.slot.hidden) return;
            const previous = previousRects.get(item.id);
            const current = item.slot.getBoundingClientRect();
            if (!previous || !current.width) return;
            const x = previous.left - current.left;
            const y = previous.top - current.top;
            if (Math.abs(x) < 1 && Math.abs(y) < 1) return;
            item.slot.animate([
                {transform: `translate(${x}px, ${y}px)`},
                {transform: 'translate(0, 0)'},
            ], {duration: 300, easing: 'cubic-bezier(.2,.8,.2,1)'});
        });
    }

    function syncLayout() {
        if (!shell) return;
        const previousRects = layoutRects();
        shell.classList.toggle('is-expanded', state.expanded);
        shell.classList.toggle('has-multiple', state.items.length > 1);
        shell.hidden = state.items.length === 0;
        toolbarTitle.textContent = state.labels.notifications;
        clearCollapsedLabel.textContent = state.labels.clearAll;
        clearCollapsedButton.setAttribute('aria-label', state.labels.clearAll);
        clearExpandedLabel.textContent = state.labels.clearAll;
        clearExpandedButton.setAttribute('aria-label', state.labels.clearAll);
        collapseButton.textContent = state.labels.showLess;
        state.items.forEach((item, index) => {
            const visible = state.expanded || index < 3;
            item.slot.hidden = !visible;
            item.slot.style.setProperty('--toast-index', String(index));
            item.slot.style.setProperty('--toast-offset', `${Math.min(index, 2) * 12}px`);
            item.slot.style.setProperty('--toast-scale', String(1 - Math.min(index, 2) * .025));
            item.slot.style.setProperty('--toast-layer-opacity', String(1 - Math.min(index, 2) * .08));
            item.slot.style.zIndex = String(100 - index);
            item.slot.setAttribute('aria-hidden', visible ? 'false' : 'true');
            item.card.tabIndex = visible && (state.expanded || index === 0) ? 0 : -1;
        });
        requestAnimationFrame(() => {
            if (!state.expanded && state.items[0]) {
                const backgroundLayers = Math.min(Math.max(state.items.length - 1, 0), 2);
                list.style.height = `${state.items[0].card.offsetHeight + (backgroundLayers * 12)}px`;
            } else {
                list.style.height = '';
            }
            animateLayout(previousRects);
        });
    }

    function expand() {
        if (state.items.length < 2 || state.expanded) return;
        state.expanded = true;
        syncLayout();
    }

    function collapse() {
        if (!state.expanded) return;
        state.expanded = false;
        resumeAll();
        syncLayout();
        state.items[0]?.card.focus({preventScroll: true});
    }

    function pauseAll() {
        state.items.forEach(item => {
            item.paused = true;
            item.lastTick = null;
        });
    }

    function resumeAll() {
        if (state.expanded || shell?.matches(':hover') || shell?.contains(document.activeElement)) return;
        state.items.forEach(item => {
            item.paused = false;
            item.lastTick = null;
        });
    }

    function finishDismiss(item, reason) {
        const index = state.items.indexOf(item);
        if (index !== -1) state.items.splice(index, 1);
        item.slot.remove();
        item.didClose?.();
        item.resolve({isDismissed: true, dismiss: reason});
        if (state.items.length < 2) state.expanded = false;
        syncLayout();
    }

    function dismiss(itemOrId, reason = 'close', direction = Math.sign(slideDistance())) {
        const item = typeof itemOrId === 'string'
            ? state.items.find(candidate => candidate.id === itemOrId)
            : itemOrId;
        if (!item || item.closing) return;
        item.closing = true;
        if (reduceMotion) {
            finishDismiss(item, reason);
            return;
        }
        const startTransform = item.slot.style.transform || 'translateX(0) scale(1)';
        const startOpacity = item.slot.style.opacity || 1;
        const exitDistance = reason === 'swipe'
            ? swipeFadeDistance(item.slot)
            : Math.max(120, item.slot.offsetWidth);
        const animation = item.slot.animate([
            {opacity: startOpacity, transform: startTransform},
            {opacity: 0, transform: `translateX(${direction * exitDistance}px) scale(.96)`},
        ], {duration: reason === 'swipe' ? 180 : 220, easing: 'cubic-bezier(.4,0,1,1)'});
        animation.finished.then(() => finishDismiss(item, reason)).catch(() => finishDismiss(item, reason));
    }

    function clearAll() {
        const items = [...state.items];
        state.expanded = false;
        items.forEach(item => dismiss(item, 'clear-all'));
    }

    function isExposed(item, index) {
        return !state.expanded
            && index === 0
            && !item.paused
            && document.visibilityState === 'visible';
    }

    function tick(timestamp) {
        animationFrame = null;
        state.items.forEach((item, index) => {
            if (item.timer === null || item.closing || !isExposed(item, index)) {
                item.lastTick = null;
                return;
            }
            if (item.lastTick === null) {
                item.lastTick = timestamp;
                return;
            }
            item.remaining -= timestamp - item.lastTick;
            item.lastTick = timestamp;
            item.progress.style.width = `${Math.max(0, (item.remaining / item.timer) * 100)}%`;
            if (item.remaining <= 0) dismiss(item, 'timer');
        });
        if (state.items.length) animationFrame = requestAnimationFrame(tick);
    }

    function startTicker() {
        if (!animationFrame) animationFrame = requestAnimationFrame(tick);
    }

    function configure(options = {}) {
        state.labels = {...state.labels, ...(options.labels || {})};
        if (shell) syncLayout();
    }

    function dismissPageAlert(alert, direction = 0) {
        if (alert.dataset.dismissing === '1') return;
        alert.dataset.dismissing = '1';
        const remove = () => {
            alert.remove();
            const region = document.querySelector('.panel-alert-region');
            if (region && !region.children.length) region.remove();
        };
        if (reduceMotion) {
            remove();
            return;
        }
        if (direction) {
            const exitDistance = swipeFadeDistance(alert);
            const animation = alert.animate([
                {opacity: alert.style.opacity || 1, transform: alert.style.transform || 'translateX(0)'},
                {opacity: 0, transform: `translateX(${direction * exitDistance}px)`},
            ], {duration: 180, easing: 'cubic-bezier(.4,0,1,1)'});
            animation.finished.then(remove).catch(remove);
            return;
        }
        alert.classList.add('is-leaving');
        alert.addEventListener('transitionend', remove, {once: true});
        window.setTimeout(remove, 350);
    }

    function bindPageAlert(alert) {
        const closeButton = alert.querySelector('[data-panel-alert-close]');
        const progress = alert.querySelector('[data-panel-alert-progress]');
        const timer = Number(alert.dataset.autoDismiss || 0);
        let remaining = timer;
        let lastTick = null;
        let paused = false;

        closeButton?.addEventListener('click', () => dismissPageAlert(alert));
        alert.addEventListener('mouseenter', () => {
            paused = true;
            lastTick = null;
        });
        alert.addEventListener('mouseleave', () => {
            paused = false;
            lastTick = null;
        });
        alert.addEventListener('focusin', () => {
            paused = true;
            lastTick = null;
        });
        alert.addEventListener('focusout', event => {
            if (!alert.contains(event.relatedTarget)) {
                paused = false;
                lastTick = null;
            }
        });
        bindSwipeDismiss(alert, alert, direction => dismissPageAlert(alert, direction));
        requestAnimationFrame(() => alert.classList.add('is-visible'));
        if (!timer) return;

        const update = timestamp => {
            if (!alert.isConnected) return;
            if (paused || document.visibilityState !== 'visible') {
                lastTick = null;
                requestAnimationFrame(update);
                return;
            }
            if (lastTick === null) lastTick = timestamp;
            remaining -= timestamp - lastTick;
            lastTick = timestamp;
            if (progress) progress.style.width = `${Math.max(0, (remaining / timer) * 100)}%`;
            if (remaining <= 0) {
                dismissPageAlert(alert);
                return;
            }
            requestAnimationFrame(update);
        };
        requestAnimationFrame(update);
    }

    function showAlert(options = {}) {
        const type = normalizeType(options.icon || options.type);
        const timer = options.timer === undefined && ['warning', 'error'].includes(type)
            ? null
            : defaultTimer(type, options);
        let region = document.querySelector('.panel-alert-region');
        if (!region) {
            region = createElement('div', 'panel-alert-region');
            region.setAttribute('aria-label', state.labels.notifications);
            const pageWrapper = document.querySelector('.page-wrapper');
            if (pageWrapper) pageWrapper.prepend(region);
            else document.body.prepend(region);
        }

        const alert = createElement('section', `panel-alert panel-alert--${type}`);
        alert.dataset.panelAlert = '';
        alert.dataset.autoDismiss = timer === null ? '0' : String(timer);
        alert.setAttribute('role', type === 'error' ? 'alert' : 'status');
        alert.setAttribute('aria-atomic', 'true');

        const icon = createElement('span', 'panel-alert__icon');
        icon.setAttribute('aria-hidden', 'true');
        icon.appendChild(createElement('i', iconClasses[type]));

        const content = createElement('div', 'panel-alert__content');
        content.appendChild(createElement('strong', 'panel-alert__title', String(options.title || state.labels.notifications)));
        if (options.text || options.description) {
            content.appendChild(createElement('div', 'panel-alert__message', String(options.text || options.description)));
        }

        const closeButton = createButton('panel-alert__close', state.labels.close, 'ti ti-x');
        closeButton.dataset.panelAlertClose = '';
        alert.append(icon, content, closeButton);

        if (timer !== null) {
            const progress = createElement('span', 'panel-alert__progress');
            progress.setAttribute('aria-hidden', 'true');
            const progressValue = createElement('span');
            progressValue.dataset.panelAlertProgress = '';
            progress.appendChild(progressValue);
            alert.appendChild(progress);
        }

        region.prepend(alert);
        bindPageAlert(alert);
        return alert;
    }

    function initializePageAlerts() {
        document.querySelectorAll('[data-panel-alert]').forEach(bindPageAlert);
    }

    const api = {fire, showAlert, close: dismiss, clearAll, expand, collapse, configure};
    window.PanelToast = api;
    window.Toast = api;
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePageAlerts, {once: true});
    } else {
        initializePageAlerts();
    }
}());
