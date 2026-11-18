<div class="brand-groups-wrap" data-groups-url="{{ $groupsRoute }}">
    <div class="p-3 border-bottom">
        <div class="input-icon">
            <span class="input-icon-addon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/>
                    <path d="M21 21l-6 -6"/>
                </svg>
            </span>
            <input type="text" id="brand-group-search" class="form-control"
                   placeholder="{{ __('labels.search') }}" autocomplete="off">
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
        <span class="text-uppercase small fw-medium text-secondary">{{ __('labels.brands_by_scope') }}</span>
        <span class="text-muted small">{{ __('labels.brands_by_scope_hint') }}</span>
    </div>

    <div id="brand-groups" style="max-height: 620px; overflow-y: auto; padding: 0 12px 12px;">
        <div id="brand-groups-body"></div>
        <div id="brand-groups-loader" class="text-center p-4 d-none">
            <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
        </div>
        <div id="brand-groups-empty" class="text-center text-muted p-4 d-none">{{ __('labels.no_data_found') }}</div>
    </div>
</div>

@push('scripts')
    <style>
        .brand-group-header { background: var(--tblr-bg-surface, #fff); transition: border-color .15s ease, background-color .15s ease; }
        .brand-group-header:hover { background: var(--tblr-active-bg, rgba(0,0,0,.03)); border-color: var(--tblr-primary) !important; }
        .brand-group-chevron.rotated { transform: rotate(90deg); }
        .brand-scope-row { background: color-mix(in srgb, var(--tblr-primary) 3%, transparent); }
        .brand-scope-row:hover { background: color-mix(in srgb, var(--tblr-primary) 7%, transparent); }
        .brand-scope-indent { width: 30px; border-top: 1px solid var(--tblr-border-color); }
        .catalog-list-stat { min-width: 76px; }
        .brand-scope-actions .btn { padding: .15rem; }
        .brand-scope-skeleton { height: 14px; border-radius: 6px; background: rgba(0,0,0,.08); animation: brandPulse 1s ease-in-out infinite; }
        @keyframes brandPulse { 0%,100%{opacity:.5} 50%{opacity:1} }
        @media (max-width: 575.98px) {
            .brand-scope-row { gap: .5rem !important; padding-inline: .75rem !important; }
            .brand-scope-indent { width: 10px; }
        }
    </style>
    <script>
        (function () {
            function init() {
                var wrap = document.querySelector('.brand-groups-wrap');
                if (!wrap) return;

                var groupsUrl = wrap.getAttribute('data-groups-url');
                var scroller = document.getElementById('brand-groups');
                var body = document.getElementById('brand-groups-body');
                var loader = document.getElementById('brand-groups-loader');
                var empty = document.getElementById('brand-groups-empty');
                var searchInput = document.getElementById('brand-group-search');

                var rootLoading = false;
                var searchTimer = null;

                function refreshLightbox() {
                    if (typeof window.refreshFsLightbox === 'function') window.refreshFsLightbox();
                }

                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) loadSentinel(entry.target);
                    });
                }, { root: scroller, rootMargin: '120px' });

                function fetchGroups(params) {
                    var qs = Object.keys(params)
                        .filter(function (k) { return params[k] !== null && params[k] !== undefined && params[k] !== ''; })
                        .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); })
                        .join('&');
                    return fetch(groupsUrl + (qs ? '?' + qs : ''), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(function (r) { return r.json(); });
                }

                function makeSentinel(kind, group, scopeId, nextPage) {
                    var s = document.createElement('div');
                    s.className = 'brand-scope-sentinel';
                    s.setAttribute('data-kind', kind);
                    s.setAttribute('data-group', group || '');
                    s.setAttribute('data-scope-id', scopeId || '');
                    s.setAttribute('data-next-page', nextPage);
                    s.style.height = '1px';
                    return s;
                }

                function loadSentinel(sentinel) {
                    if (sentinel.getAttribute('data-loading') === '1') return;
                    sentinel.setAttribute('data-loading', '1');
                    var kind = sentinel.getAttribute('data-kind');
                    var nextPage = parseInt(sentinel.getAttribute('data-next-page'), 10);
                    var params = { page: nextPage };

                    if (kind === 'search') {
                        params.search = searchInput.value.trim();
                    } else if (kind === 'brands') {
                        params.type = 'brands';
                        params.group = sentinel.getAttribute('data-group');
                        params.scope_id = sentinel.getAttribute('data-scope-id');
                    } else {
                        params.type = 'groups';
                    }

                    fetchGroups(params).then(function (res) {
                        var tmp = document.createElement('div');
                        tmp.innerHTML = res.html;
                        while (tmp.firstElementChild) sentinel.parentNode.insertBefore(tmp.firstElementChild, sentinel);
                        refreshLightbox();
                        if (res.has_more) {
                            sentinel.setAttribute('data-next-page', res.next_page);
                            sentinel.setAttribute('data-loading', '0');
                        } else {
                            observer.unobserve(sentinel);
                            sentinel.parentNode.removeChild(sentinel);
                        }
                    }).catch(function () {
                        sentinel.setAttribute('data-loading', '0');
                    });
                }

                function insertSkeleton(afterEl) {
                    var sk = document.createElement('div');
                    sk.className = 'brand-scope-skeleton-row px-3 py-2 border-bottom';
                    sk.style.paddingLeft = '46px';
                    sk.innerHTML = '<div class="brand-scope-skeleton" style="width:35%"></div>';
                    afterEl.parentNode.insertBefore(sk, afterEl.nextSibling);
                    return sk;
                }

                function collapse(header) {
                    var sib = header.nextElementSibling;
                    while (sib && !sib.classList.contains('brand-group-header')) {
                        var next = sib.nextElementSibling;
                        if (sib.classList.contains('brand-scope-sentinel')) observer.unobserve(sib);
                        sib.parentNode.removeChild(sib);
                        sib = next;
                    }
                    header.setAttribute('data-loaded', '0');
                    header.setAttribute('aria-expanded', 'false');
                    header.classList.remove('border-primary');
                }

                function expand(header) {
                    var group = header.getAttribute('data-group');
                    var scopeId = header.getAttribute('data-scope-id');
                    var sk = insertSkeleton(header);
                    fetchGroups({ type: 'brands', group: group, scope_id: scopeId, page: 1 }).then(function (res) {
                        sk.parentNode.removeChild(sk);
                        var tmp = document.createElement('div');
                        tmp.innerHTML = res.html;
                        var anchor = header;
                        while (tmp.firstElementChild) {
                            var el = tmp.firstElementChild;
                            anchor.parentNode.insertBefore(el, anchor.nextSibling);
                            anchor = el;
                        }
                        if (res.has_more) {
                            var sentinel = makeSentinel('brands', group, scopeId, res.next_page);
                            anchor.parentNode.insertBefore(sentinel, anchor.nextSibling);
                            observer.observe(sentinel);
                        }
                        header.setAttribute('data-loaded', '1');
                        header.setAttribute('aria-expanded', 'true');
                        header.classList.add('border-primary');
                        refreshLightbox();
                    }).catch(function () {
                        if (sk.parentNode) sk.parentNode.removeChild(sk);
                        header.querySelector('.brand-group-chevron').classList.remove('rotated');
                    });
                }

                function toggle(header) {
                    var chevron = header.querySelector('.brand-group-chevron');
                    if (header.getAttribute('data-loaded') === '1') {
                        collapse(header);
                        if (chevron) chevron.classList.remove('rotated');
                    } else {
                        if (chevron) chevron.classList.add('rotated');
                        expand(header);
                    }
                }

                body.addEventListener('click', function (e) {
                    var header = e.target.closest('.brand-group-header');
                    if (header && !e.target.closest('a,button')) toggle(header);
                });

                body.addEventListener('keydown', function (e) {
                    var header = e.target.closest('.brand-group-header');
                    if (!header || e.target.closest('a,button') || (e.key !== 'Enter' && e.key !== ' ')) return;
                    e.preventDefault();
                    toggle(header);
                });

                function clearAll() {
                    body.querySelectorAll('.brand-scope-sentinel').forEach(function (s) { observer.unobserve(s); });
                    body.innerHTML = '';
                }

                function loadRoot() {
                    if (rootLoading) return;
                    rootLoading = true;
                    clearAll();
                    empty.classList.add('d-none');
                    loader.classList.remove('d-none');
                    fetchGroups({ type: 'groups', page: 1 }).then(function (res) {
                        loader.classList.add('d-none');
                        rootLoading = false;
                        body.innerHTML = res.html;
                        refreshLightbox();
                        if (!res.html.trim()) { empty.classList.remove('d-none'); return; }
                        if (res.has_more) {
                            var sentinel = makeSentinel('groups', '', '', res.next_page);
                            body.appendChild(sentinel);
                            observer.observe(sentinel);
                        }
                    }).catch(function () {
                        loader.classList.add('d-none');
                        rootLoading = false;
                    });
                }

                function runSearch(term) {
                    clearAll();
                    empty.classList.add('d-none');
                    loader.classList.remove('d-none');
                    fetchGroups({ search: term, page: 1 }).then(function (res) {
                        loader.classList.add('d-none');
                        body.innerHTML = res.html;
                        refreshLightbox();
                        if (!res.html.trim()) { empty.classList.remove('d-none'); return; }
                        if (res.has_more) {
                            var sentinel = makeSentinel('search', '', '', res.next_page);
                            body.appendChild(sentinel);
                            observer.observe(sentinel);
                        }
                    }).catch(function () {
                        loader.classList.add('d-none');
                    });
                }

                searchInput.addEventListener('input', function () {
                    var term = this.value.trim();
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(function () {
                        if (term === '') loadRoot();
                        else runSearch(term);
                    }, 350);
                });

                var refreshBtn = document.getElementById('refresh');
                if (refreshBtn) refreshBtn.addEventListener('click', function () {
                    searchInput.value = '';
                    loadRoot();
                });

                document.addEventListener('resource:saved', loadRoot);

                loadRoot();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>
@endpush
