(function () {
    const modalElement = document.getElementById('panel-new-order-modal');
    if (!modalElement) return;

    const countElement = document.getElementById('panel-new-order-count');
    const headingElement = document.getElementById('panel-new-order-heading');
    const listElement = document.getElementById('panel-new-order-list');
    const moreElement = document.getElementById('panel-new-order-more');
    const dismissCheckbox = document.getElementById('panel-new-order-dismiss-checked');
    const viewOrdersLink = document.getElementById('panel-new-order-view-orders');
    const viewLatestLink = document.getElementById('panel-new-order-view-latest');
    const panel = modalElement.dataset.panel;
    const userId = modalElement.dataset.userId;
    const sellerId = modalElement.dataset.sellerId;
    const statusUrl = modalElement.dataset.statusUrl;
    const storageKey = `panel-new-orders:${panel}:${userId}${sellerId ? `:${sellerId}` : ''}`;
    const popupDisabledKey = `${storageKey}:popup-disabled`;
    const dismissedKey = `${storageKey}:dismissed`;
    const snoozedKey = `${storageKey}:snoozed`;
    const pendingOrders = readStorage(storageKey);
    const dismissedOrders = readStorage(dismissedKey);
    const snoozedOrders = readStorage(snoozedKey);
    const dismissedSet = new Set(dismissedOrders.map(String));
    const snoozedSet = new Set(snoozedOrders.map(String));
    let popupDisabled = readSessionFlag(popupDisabledKey);
    let modalInstance;
    let fallbackBackdrop;
    let statusPoll;

    function readStorage(key) {
        try {
            const parsed = JSON.parse(localStorage.getItem(key) || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (_) {
            return [];
        }
    }

    function readSessionFlag(key) {
        try {
            return sessionStorage.getItem(key) === 'true';
        } catch (_) {
            return false;
        }
    }

    function saveStorage() {
        try {
            localStorage.setItem(storageKey, JSON.stringify(pendingOrders));
            localStorage.setItem(dismissedKey, JSON.stringify([...dismissedSet].slice(-500)));
            localStorage.setItem(snoozedKey, JSON.stringify([...snoozedSet].slice(-500)));
        } catch (_) {
        }
    }

    function showModal() {
        if (window.bootstrap?.Modal) {
            modalInstance ||= window.bootstrap.Modal.getOrCreateInstance(modalElement);
            modalInstance.show();
            return;
        }

        if (window.jQuery?.fn?.modal) {
            window.jQuery(modalElement).modal('show');
            return;
        }

        modalElement.classList.add('show');
        modalElement.style.display = 'block';
        modalElement.setAttribute('aria-hidden', 'false');
        modalElement.setAttribute('aria-modal', 'true');
        modalElement.setAttribute('role', 'dialog');
        document.body.classList.add('modal-open');

        if (!fallbackBackdrop) {
            fallbackBackdrop = document.createElement('div');
            fallbackBackdrop.className = 'modal-backdrop fade show';
            document.body.append(fallbackBackdrop);
        }
    }

    function hideModal() {
        if (window.bootstrap?.Modal) {
            window.bootstrap.Modal.getInstance(modalElement)?.hide();
            return;
        }

        if (window.jQuery?.fn?.modal) {
            window.jQuery(modalElement).modal('hide');
            return;
        }

        modalElement.classList.remove('show');
        modalElement.style.display = 'none';
        modalElement.setAttribute('aria-hidden', 'true');
        modalElement.removeAttribute('aria-modal');
        modalElement.removeAttribute('role');
        document.body.classList.remove('modal-open');
        fallbackBackdrop?.remove();
        fallbackBackdrop = null;
        modalElement.dispatchEvent(new Event('hidden.bs.modal'));
    }

    function render() {
        const count = pendingOrders.length;
        const countText = count === 1
            ? modalElement.dataset.countOne
            : modalElement.dataset.countMany.replace(':count', String(count));
        countElement.textContent = countText;
        headingElement.textContent = count === 1
            ? modalElement.dataset.headingOne
            : modalElement.dataset.headingMany.replace(':count', String(count));
        const visibleOrders = pendingOrders.slice(-5).reverse();
        listElement.replaceChildren(...visibleOrders.map((order, index) => {
            const item = document.createElement('li');
            item.className = 'panel-order-row';

            const primary = document.createElement('div');
            primary.className = 'panel-order-primary';
            const numberLine = document.createElement('div');
            numberLine.className = 'panel-order-number-line';
            const number = document.createElement('strong');
            number.className = 'panel-order-number';
            number.textContent = order.label || `#${order.id}`;
            const badge = document.createElement('span');
            badge.className = 'panel-order-new-badge';
            badge.textContent = modalElement.dataset.newLabel;
            numberLine.append(number, badge);
            const customer = document.createElement('div');
            customer.className = 'panel-order-customer';
            customer.textContent = order.customer || modalElement.dataset.customerLabel;
            const location = document.createElement('div');
            location.className = 'panel-order-location';
            if (order.location) {
                const pin = document.createElement('i');
                pin.className = 'ti ti-map-pin';
                location.append(pin, document.createTextNode(order.location));
            }
            primary.append(numberLine, customer, location);

            const products = document.createElement('div');
            products.className = 'panel-order-products';
            const productsLine = document.createElement('div');
            productsLine.className = 'panel-order-products-line';
            (order.items || []).slice(0, 3).forEach((product) => {
                const image = document.createElement('img');
                image.className = 'panel-order-product-image';
                image.src = product.image || modalElement.dataset.productPlaceholder;
                image.alt = product.name || '';
                image.loading = 'lazy';
                productsLine.append(image);
            });
            const itemCount = document.createElement('span');
            itemCount.className = 'panel-order-item-count';
            itemCount.textContent = `${order.item_count || 0} ${modalElement.dataset.itemsLabel}`;
            productsLine.append(itemCount);
            const time = document.createElement('div');
            time.className = 'panel-order-time';
            const clock = document.createElement('i');
            clock.className = 'ti ti-clock';
            time.append(clock, document.createTextNode(order.placed_at || modalElement.dataset.nowLabel));
            products.append(productsLine, time);

            const total = document.createElement('div');
            total.className = 'panel-order-total';
            const amount = document.createElement('strong');
            amount.textContent = order.total || '';
            const view = document.createElement('a');
            view.className = `btn ${index === 0 ? 'btn-warning' : 'btn-light'} panel-order-view`;
            view.href = order.url || modalElement.dataset.ordersUrl;
            view.textContent = modalElement.dataset.viewLabel;
            total.append(amount, view);

            item.append(primary, products, total);
            return item;
        }));

        const hiddenCount = Math.max(0, count - visibleOrders.length);
        moreElement.classList.toggle('d-none', hiddenCount === 0);
        moreElement.replaceChildren();
        if (hiddenCount > 0) {
            moreElement.append(document.createTextNode(modalElement.dataset.moreLabel.replace(':count', String(hiddenCount))));
            const arrow = document.createElement('i');
            arrow.className = 'ti ti-chevron-right';
            moreElement.append(arrow);
        }

        const latestOrder = pendingOrders[pendingOrders.length - 1];
        viewLatestLink.href = latestOrder?.url || modalElement.dataset.ordersUrl;
    }

    function openModal() {
        if (popupDisabled) return;
        if (!pendingOrders.some((order) => !snoozedSet.has(String(order.id)))) return;
        render();
        showModal();
    }

    function disablePopupForSession() {
        popupDisabled = true;
        try {
            sessionStorage.setItem(popupDisabledKey, 'true');
        } catch (_) {
        }
    }

    function dismissPendingOrders() {
        pendingOrders.forEach((order) => {
            const id = String(order.id);
            dismissedSet.add(id);
            snoozedSet.delete(id);
        });
        pendingOrders.splice(0, pendingOrders.length);
        saveStorage();
        updateStatusPolling();
    }

    function snoozePendingOrders() {
        pendingOrders.forEach((order) => snoozedSet.add(String(order.id)));
        saveStorage();
    }

    function updateStatusPolling() {
        if (pendingOrders.length > 0 && !statusPoll) {
            statusPoll = window.setInterval(reconcileStatuses, 15000);
        } else if (pendingOrders.length === 0 && statusPoll) {
            window.clearInterval(statusPoll);
            statusPoll = null;
        }
    }

    async function reconcileStatuses() {
        if (!statusUrl || pendingOrders.length === 0) return;

        const params = new URLSearchParams();
        pendingOrders.forEach((order) => params.append('order_ids[]', String(order.id)));

        try {
            const response = await fetch(`${statusUrl}?${params.toString()}`, {
                credentials: 'same-origin',
                headers: {Accept: 'application/json'},
            });

            if (!response.ok) return;

            const payload = await response.json();
            const resolvedIds = new Set((payload.resolved_order_ids || []).map(String));
            const currentOrders = new Map((payload.orders || []).map((order) => [String(order.id), order]));
            for (const order of pendingOrders) {
                const current = currentOrders.get(String(order.id));
                if (current) Object.assign(order, current);
            }

            for (const id of resolvedIds) {
                dismissedSet.delete(id);
                snoozedSet.delete(id);
            }

            if (resolvedIds.size > 0) {
                for (let index = pendingOrders.length - 1; index >= 0; index--) {
                    if (resolvedIds.has(String(pendingOrders[index].id))) pendingOrders.splice(index, 1);
                }
            }

            saveStorage();
            updateStatusPolling();
            if (modalElement.classList.contains('show')) render();

            if (pendingOrders.length === 0) {
                if (modalElement.classList.contains('show')) hideModal();
            }
        } catch (_) {
        }
    }

    function handleOrderPlaced(event) {
        const id = String(event?.order_id || '');
        if (!id || dismissedSet.has(id) || pendingOrders.some((order) => String(order.id) === id)) return;

        const playback = window.playPanelNotificationSound?.();
        playback?.catch(() => {});
        if (popupDisabled) return;

        pendingOrders.push({id, slug: event.order_slug || null, placedAt: event.placed_at || null});
        snoozedSet.clear();
        saveStorage();
        updateStatusPolling();
        openModal();
        reconcileStatuses().then(() => {
            if (modalElement.classList.contains('show')) render();
        });
    }

    modalElement.addEventListener('hidden.bs.modal', () => {
        if (dismissCheckbox.checked) {
            dismissPendingOrders();
            disablePopupForSession();
        } else {
            snoozePendingOrders();
        }
        dismissCheckbox.checked = false;
    });

    if (!window.bootstrap?.Modal && !window.jQuery?.fn?.modal) {
        modalElement.querySelectorAll('[data-bs-dismiss="modal"]').forEach((button) => {
            button.addEventListener('click', hideModal);
        });
        modalElement.addEventListener('click', (event) => {
            if (event.target === modalElement) hideModal();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modalElement.classList.contains('show')) hideModal();
        });
    }

    viewOrdersLink.addEventListener('click', dismissPendingOrders);
    listElement.addEventListener('click', (event) => {
        if (event.target.closest('.panel-order-view')) snoozePendingOrders();
    });
    viewLatestLink.addEventListener('click', snoozePendingOrders);
    moreElement.addEventListener('click', dismissPendingOrders);
    updateStatusPolling();
    reconcileStatuses().then(openModal);

    if (!window.Echo || !window.Pusher) return;

    try {
        const config = JSON.parse(modalElement.dataset.realtimeConfig || '{}');
        const realtime = config.realtime || config;
        if (config.configured === false || !realtime.key || !realtime.auth_endpoint) return;

        const EchoClient = window.Echo.default || window.Echo;
        const echo = new EchoClient({
            broadcaster: realtime.driver,
            key: realtime.key,
            cluster: realtime.cluster || 'mt1',
            forceTLS: realtime.scheme === 'https',
            wsHost: realtime.host || undefined,
            wsPort: Number(realtime.port || 80),
            wssPort: Number(realtime.port || 443),
            enabledTransports: ['ws', 'wss'],
            authEndpoint: realtime.auth_endpoint,
            auth: {headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', Accept: 'application/json'}},
            channelAuthorization: {
                endpoint: realtime.auth_endpoint,
                transport: 'ajax',
                headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', Accept: 'application/json'},
            },
        });

        window.PanelOrderEcho = echo;
        const channelName = panel === 'admin' ? 'orders.admin' : `orders.seller.${sellerId}`;
        echo.private(channelName).listen('.order.placed', handleOrderPlaced);
    } catch (_) {
    }
})();
