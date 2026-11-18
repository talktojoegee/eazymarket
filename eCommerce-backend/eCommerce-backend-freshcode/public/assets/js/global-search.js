(function () {
    'use strict';

    const shortcutHandler = function (event) {
        const key = String(event.key || '').toLowerCase();
        if (! event.isComposing && (event.ctrlKey || event.metaKey) && (key === 'k' || event.code === 'KeyK')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            toggleGlobalSearch();
        }
    };

    function modalInstance(element) {
        if (window.bootstrap?.Modal) {
            return window.bootstrap.Modal.getOrCreateInstance(element);
        }

        return null;
    }

    function openGlobalSearch() {
        const element = document.getElementById('globalSearchModal');
        if (! element) return;

        if (window.jQuery && typeof window.jQuery(element).modal === 'function') {
            window.jQuery(element).modal('show');
        } else {
            const instance = modalInstance(element);
            instance?.show();
        }

        window.setTimeout(function () {
            document.getElementById('globalSearchInput')?.focus();
        }, 100);
    }

    function closeGlobalSearch() {
        const element = document.getElementById('globalSearchModal');
        if (! element) return;

        if (window.jQuery && typeof window.jQuery(element).modal === 'function') {
            window.jQuery(element).modal('hide');
        } else {
            modalInstance(element)?.hide();
        }
    }

    function toggleGlobalSearch() {
        const element = document.getElementById('globalSearchModal');
        if (! element) return;

        if (element.classList.contains('show')) {
            closeGlobalSearch();
        } else {
            openGlobalSearch();
        }
    }

    document.addEventListener('keydown', shortcutHandler, true);

    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('globalSearchModal');
        if (! modal) return;

        const input = document.getElementById('globalSearchInput');
        const clear = document.getElementById('globalSearchClear');
        const results = document.getElementById('globalSearchResults');
        const filters = document.getElementById('globalSearchFilters');
        const panel = document.getElementById('panel')?.dataset.panel || document.location.pathname.split('/')[1];
        const baseUrl = (document.getElementById('base_url')?.value || '').replace(/\/$/, '');
        let timer;
        let request;
        let resultGroups = {};
        let selectedFilter = 'All';
        let activeIndex = -1;

        document.querySelectorAll('[data-global-search-open]').forEach(function (button) {
            button.addEventListener('click', openGlobalSearch);
        });

        modal.addEventListener('shown.bs.modal', function () {
            window.setTimeout(function () { input.focus(); }, 0);
        });

        input.addEventListener('input', function () {
            const query = input.value.trim();
            clear.hidden = query.length === 0;
            window.clearTimeout(timer);
            request?.abort();

            if (query.length < 2) {
                resultGroups = {};
                selectedFilter = 'All';
                renderInitial();
                return;
            }

            renderLoading();
            timer = window.setTimeout(function () { search(query); }, 300);
        });

        modal.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeGlobalSearch();
                return;
            }

            const items = results.querySelectorAll('.global-search-result');
            if (! items.length || ! ['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;

            event.preventDefault();
            if (event.key === 'ArrowDown') activeIndex = (activeIndex + 1) % items.length;
            if (event.key === 'ArrowUp') activeIndex = activeIndex <= 0 ? items.length - 1 : activeIndex - 1;
            if (event.key === 'Enter' && activeIndex >= 0) {
                window.location.assign(items[activeIndex].href);
                return;
            }
            setActive(items);
        });

        clear.addEventListener('click', function () {
            input.value = '';
            clear.hidden = true;
            resultGroups = {};
            selectedFilter = 'All';
            renderInitial();
            input.focus();
        });

        async function search(query) {
            request = new AbortController();
            try {
                const response = await fetch(`${baseUrl}/${panel}/global-search?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: request.signal,
                });
                if (! response.ok) throw new Error(`Search failed with status ${response.status}`);

                const payload = await response.json();
                resultGroups = payload.data?.results || {};
                selectedFilter = 'All';
                renderFilters();
                renderResults(query);
            } catch (error) {
                if (error.name !== 'AbortError') renderError();
            }
        }

        function renderFilters() {
            const groups = Object.keys(resultGroups);
            filters.hidden = groups.length === 0;
            filters.innerHTML = groups.length ? ['All', ...groups].map(function (group) {
                const count = group === 'All' ? '' : ` (${resultGroups[group].length})`;
                const buttonClass = group === selectedFilter ? 'btn-primary' : 'btn-outline-primary';
                return `<button type="button" class="btn btn-sm rounded-pill ${buttonClass}" data-filter="${escapeHtml(group)}">${escapeHtml(group)}${count}</button>`;
            }).join('') : '';

            filters.querySelectorAll('[data-filter]').forEach(function (button) {
                button.addEventListener('click', function () {
                    selectedFilter = button.dataset.filter;
                    renderFilters();
                    renderResults(input.value.trim());
                });
            });
        }

        function renderResults(query) {
            activeIndex = -1;
            const groups = Object.entries(resultGroups).filter(([group]) => selectedFilter === 'All' || selectedFilter === group);
            if (! groups.length) {
                renderEmpty(query);
                return;
            }

            results.innerHTML = groups.map(function ([group, items]) {
                return `<div class="global-search-category">${escapeHtml(group)}</div>` + items.map(function (item) {
                    return `<a class="global-search-result" href="${escapeHtml(item.url)}">
                        <span class="global-search-result-icon"><i class="${escapeHtml(item.icon)}"></i></span>
                        <span class="global-search-result-content">
                            <span class="global-search-result-title">${highlight(item.title, query)} <span class="badge ${escapeHtml(item.badge_class || 'bg-secondary-lt')}">${escapeHtml(item.badge)}</span></span>
                            <span class="global-search-result-subtitle">${escapeHtml(item.subtitle)}</span>
                        </span>
                    </a>`;
                }).join('');
            }).join('');

            results.querySelectorAll('.global-search-result').forEach(function (item, index) {
                item.addEventListener('mouseenter', function () {
                    activeIndex = index;
                    setActive(results.querySelectorAll('.global-search-result'));
                });
            });
        }

        function setActive(items) {
            items.forEach(function (item, index) { item.classList.toggle('active', index === activeIndex); });
            items[activeIndex]?.scrollIntoView({ block: 'nearest' });
        }

        function renderInitial() {
            filters.hidden = true;
            filters.innerHTML = '';
            results.innerHTML = state('ti-search', 'Type at least 2 characters to search', 'Search menu pages, categories, products, orders, returns, customers, and stores');
        }

        function renderLoading() {
            filters.hidden = true;
            results.innerHTML = '<div class="global-search-state"><span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span><strong>Searching records...</strong></div>';
        }

        function renderEmpty(query) {
            filters.hidden = true;
            results.innerHTML = state('ti-circle-x text-danger', `No results found for “${query}”`, 'Try a product SKU, order ID, customer, store, or menu name');
        }

        function renderError() {
            filters.hidden = true;
            results.innerHTML = state('ti-alert-circle text-danger', 'Search is temporarily unavailable', 'Please try again in a moment');
        }

        function state(icon, title, subtitle) {
            return `<div class="global-search-state"><i class="ti ${icon}"></i><strong>${escapeHtml(title)}</strong><span>${escapeHtml(subtitle)}</span></div>`;
        }

        function highlight(text, query) {
            const safe = escapeHtml(text || '');
            const pattern = escapeHtml(query).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return safe.replace(new RegExp(`(${pattern})`, 'gi'), '<mark>$1</mark>');
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function (character) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character];
            });
        }

        clear.hidden = true;
        renderInitial();
    });
})();
