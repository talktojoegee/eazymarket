(function () {
    'use strict';

    const DEFAULT_HOLD_DELAY = 900;
    const MOVE_TOLERANCE = 7;

    function sortableItem(element, target, options) {
        const selector = options.draggable || options.handle || ':scope > *';
        if (selector !== ':scope > *') {
            const item = target.closest(selector);
            return item && item.parentElement === element ? item : null;
        }
        let item = target;
        while (item && item.parentElement !== element) item = item.parentElement;
        return item && item.parentElement === element ? item : null;
    }

    function bindMouseScroll(element, options, behavior) {
        if (behavior.mouseScroll === false || element.closest('.swiper')) return;

        let gesture = null;
        let suppressClickUntil = 0;

        element.addEventListener('pointerdown', event => {
            if (event.button !== 0 || event.pointerType === 'touch') return;
            if (!sortableItem(element, event.target, options)) return;
            gesture = {
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                scrollLeft: element.scrollLeft,
                startedAt: Date.now(),
                moved: false,
            };
        });

        element.addEventListener('pointermove', event => {
            if (!gesture || gesture.pointerId !== event.pointerId || element.classList.contains('is-scroll-sorting')) return;
            const deltaX = event.clientX - gesture.startX;
            const deltaY = event.clientY - gesture.startY;
            if (!gesture.moved && Math.abs(deltaX) < MOVE_TOLERANCE) return;
            if (!gesture.moved && Date.now() - gesture.startedAt >= behavior.holdDelay) return;
            if (!gesture.moved && Math.abs(deltaY) > Math.abs(deltaX)) {
                gesture = null;
                return;
            }
            gesture.moved = true;
            element.classList.add('is-scroll-dragging');
            element.scrollLeft = gesture.scrollLeft - deltaX;
            event.preventDefault();
        });

        const endGesture = event => {
            if (!gesture || (event.pointerId !== undefined && gesture.pointerId !== event.pointerId)) return;
            if (gesture.moved) suppressClickUntil = Date.now() + 250;
            gesture = null;
            element.classList.remove('is-scroll-dragging');
        };
        element.addEventListener('pointerup', endGesture);
        element.addEventListener('pointercancel', endGesture);
        element.addEventListener('lostpointercapture', endGesture);
        element.addEventListener('click', event => {
            if (Date.now() > suppressClickUntil) return;
            event.preventDefault();
            event.stopImmediatePropagation();
        }, true);
    }

    function create(element, sortableOptions, behaviorOptions) {
        if (!element || !window.Sortable) return null;
        const options = Object.assign({}, sortableOptions || {});
        const behavior = Object.assign({scrollable: false, holdDelay: DEFAULT_HOLD_DELAY, holdTitle: 'Hold to sort'}, behaviorOptions || {});
        behavior.holdDelay = Math.max(250, Number(behavior.holdDelay) || DEFAULT_HOLD_DELAY);

        if (!behavior.scrollable) return window.Sortable.create(element, options);

        Array.from(element.children).forEach(item => {
            if (!item.hasAttribute('title')) item.setAttribute('title', behavior.holdTitle);
        });

        const onChoose = options.onChoose;
        const onUnchoose = options.onUnchoose;
        const onEnd = options.onEnd;
        options.delay = behavior.holdDelay;
        options.delayOnTouchOnly = false;
        options.forceFallback = true;
        options.fallbackTolerance = MOVE_TOLERANCE;
        options.touchStartThreshold = MOVE_TOLERANCE;
        options.chosenClass = options.chosenClass || 'scroll-sort-chosen';
        options.ghostClass = options.ghostClass || 'scroll-sort-ghost';
        options.dragClass = options.dragClass || 'scroll-sort-drag';
        options.scroll = true;
        options.scrollSensitivity = options.scrollSensitivity || 48;
        options.scrollSpeed = options.scrollSpeed || 12;
        options.onChoose = event => {
            element.classList.add('is-scroll-sorting');
            if (typeof onChoose === 'function') onChoose(event);
        };
        options.onUnchoose = event => {
            element.classList.remove('is-scroll-sorting');
            if (typeof onUnchoose === 'function') onUnchoose(event);
        };
        options.onEnd = event => {
            element.classList.remove('is-scroll-sorting');
            if (typeof onEnd === 'function') onEnd(event);
        };

        element.classList.add('scroll-sort-enabled');
        bindMouseScroll(element, options, behavior);
        return window.Sortable.create(element, options);
    }

    window.HyperScrollSort = {create};
}());
