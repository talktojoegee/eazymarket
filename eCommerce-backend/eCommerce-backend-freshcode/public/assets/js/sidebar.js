(function () {
    'use strict';

    var storageKey = 'hyper-sidebar-slim';
    var root = document.documentElement;
    var sidebar = document.getElementById('panel-sidebar');
    var toggle = document.querySelector('[data-sidebar-size-toggle]');
    var mobileOpen = document.querySelector('[data-mobile-sidebar-open]');
    var mobileClosers = document.querySelectorAll('[data-mobile-sidebar-close]');

    function scrollActiveItemIntoView() {
        var panelSidebar = sidebar || document.getElementById('hui-sidebar');
        if (!panelSidebar) return;

        var desktopWidth = panelSidebar.id === 'hui-sidebar' ? 1024 : 992;
        if (!window.matchMedia('(min-width: ' + desktopWidth + 'px)').matches) return;

        var scrollContainer = panelSidebar.querySelector('[data-sidebar-scroll-container]');
        var activeItem = scrollContainer && (
            (panelSidebar.id === 'panel-sidebar' && root.classList.contains('sidebar-slim')
                ? scrollContainer.querySelector('.nav-item.active > .nav-link')
                : scrollContainer.querySelector('[aria-current="page"]')) ||
            scrollContainer.querySelector('.nav-item.active > .nav-link')
        );
        if (!activeItem) return;

        var containerRect = scrollContainer.getBoundingClientRect();
        var activeRect = activeItem.getBoundingClientRect();
        if (activeRect.top >= containerRect.top && activeRect.bottom <= containerRect.bottom) return;

        scrollContainer.scrollTop += activeRect.top - containerRect.top -
            (scrollContainer.clientHeight - activeRect.height) / 2;
    }

    window.requestAnimationFrame(scrollActiveItemIntoView);

    if (!sidebar || !toggle) return;

    function isDesktop() {
        return window.matchMedia('(min-width: 992px)').matches;
    }

    function isSlim() {
        return root.classList.contains('sidebar-slim');
    }

    function usesCompactSidebar() {
        return isDesktop() && isSlim();
    }

    function updateToggle() {
        var slim = isSlim();
        var label = slim ? 'Expand sidebar' : 'Collapse sidebar';
        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('aria-pressed', slim ? 'true' : 'false');
        toggle.setAttribute('title', label);
    }

    function positionFlyout(item) {
        if (!usesCompactSidebar()) return;
        var rect = item.getBoundingClientRect();
        var menu = item.querySelector(':scope > .dropdown-menu');
        if (!menu) {
            item.style.setProperty('--sidebar-item-top', (rect.top + Math.max(0, (rect.height - 34) / 2)) + 'px');
            return;
        }

        var menuHeight = Math.min(menu.scrollHeight || 220, 420, window.innerHeight - 24);
        var top = Math.max(12, Math.min(rect.top - 8, window.innerHeight - menuHeight - 12));
        menu.style.setProperty('--sidebar-flyout-top', top + 'px');
    }

    toggle.addEventListener('click', function () {
        if (!isDesktop()) return;
        var slim = !isSlim();
        root.classList.toggle('sidebar-slim', slim);
        updateToggle();
        try { localStorage.setItem(storageKey, slim ? 'true' : 'false'); } catch (e) {}
    });

    function setMobileSidebar(open) {
        root.classList.toggle('mobile-sidebar-open', open);
        if (mobileOpen) mobileOpen.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    if (mobileOpen) {
        mobileOpen.addEventListener('click', function () { setMobileSidebar(true); });
    }

    mobileClosers.forEach(function (closer) {
        closer.addEventListener('click', function () { setMobileSidebar(false); });
    });

    sidebar.querySelectorAll('.nav-item[data-sidebar-label]').forEach(function (item) {
        var closeTimer;
        var menu = item.querySelector(':scope > .dropdown-menu');

        function openFromHover() {
            window.clearTimeout(closeTimer);
            positionFlyout(item);
            if (menu && isSlim() && isDesktop()) item.classList.add('is-hover-open');
        }

        function closeAfterPointerLeaves() {
            window.clearTimeout(closeTimer);
            closeTimer = window.setTimeout(function () {
                item.classList.remove('is-hover-open');
            }, 280);
        }

        item.addEventListener('mouseenter', openFromHover);
        item.addEventListener('mouseleave', closeAfterPointerLeaves);
        item.addEventListener('focusin', function () { positionFlyout(item); });

        if (menu) {
            menu.addEventListener('mouseenter', openFromHover);
            menu.addEventListener('mouseleave', closeAfterPointerLeaves);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !isDesktop()) setMobileSidebar(false);
    });

    window.addEventListener('resize', function () {
        updateToggle();
        if (isDesktop()) setMobileSidebar(false);
    }, { passive: true });
    if (!isDesktop()) setMobileSidebar(false);
    updateToggle();
})();
