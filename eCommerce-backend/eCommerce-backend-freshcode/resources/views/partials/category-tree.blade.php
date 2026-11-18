<div class="cat-tree-wrap" data-tree-url="{{ $treeRoute }}">
    <div class="p-3 border-bottom">
        <div class="input-icon">
            <span class="input-icon-addon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="icon">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/>
                    <path d="M21 21l-6 -6"/>
                </svg>
            </span>
            <input type="text" id="cat-tree-search" class="form-control"
                   placeholder="{{ __('labels.search') }}" autocomplete="off">
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
        <span class="text-uppercase small fw-medium text-secondary" id="cat-tree-heading">
            {{ __('labels.top_level_departments') }}
        </span>
        <span class="text-muted small" id="cat-tree-hint">{{ __('labels.category_tree_hint') }}</span>
    </div>

    <div id="cat-tree" class="cat-tree" style="max-height: 620px; overflow-y: auto;">
        <div id="cat-tree-body"></div>
        <div id="cat-tree-loader" class="text-center p-4 d-none">
            <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
        </div>
        <div id="cat-tree-empty" class="text-center text-muted p-4 d-none">
            {{ __('labels.no_data_found') }}
        </div>
    </div>
</div>

@push('scripts')
    <style>
        .cat-tree-row { position: relative; transition: background-color .15s ease; }
        .cat-tree-row-root { background: var(--tblr-bg-surface, #fff); }
        .cat-tree-row-root:hover { background: var(--tblr-active-bg, rgba(0,0,0,.03)); }
        .cat-tree-row-root.cat-tree-row-expanded { box-shadow: inset 3px 0 var(--tblr-primary); background: color-mix(in srgb, var(--tblr-primary) 5%, var(--tblr-bg-surface, #fff)); }
        .cat-tree-row-child { background: color-mix(in srgb, var(--tblr-primary) 5%, var(--tblr-bg-surface, #fff)); }
        .cat-tree-row-child:hover { background: color-mix(in srgb, var(--tblr-primary) 10%, var(--tblr-bg-surface, #fff)); }
        .cat-tree-row-child::before { content: ''; position: absolute; inset-block: 0; inset-inline-start: var(--cat-guide); border-inline-start: 2px solid color-mix(in srgb, var(--tblr-primary) 35%, var(--tblr-border-color)); }
        .cat-tree-row-child::after { content: ''; position: absolute; inset-inline-start: var(--cat-guide); top: 50%; width: 16px; border-top: 2px solid color-mix(in srgb, var(--tblr-primary) 35%, var(--tblr-border-color)); }
        .cat-tree-chevron.rotated { transform: rotate(90deg); }
        .catalog-list-stat { min-width: 76px; }
        .cat-tree-actions .btn { padding: .15rem; }
        .cat-tree-skeleton { height: 14px; border-radius: 6px; background: rgba(0,0,0,.08); animation: catPulse 1s ease-in-out infinite; }
        @keyframes catPulse { 0%,100%{opacity:.5} 50%{opacity:1} }
        @media (max-width: 575.98px) {
            .cat-tree-row { gap: .5rem !important; padding-inline-end: .75rem !important; }
        }
    </style>
    <script>
        (function () {
            function init() {
                var wrap = document.querySelector('.cat-tree-wrap');
                if (!wrap) return;

                var baseUrl = document.getElementById('base_url') ? document.getElementById('base_url').value : '';
                var treeUrl = wrap.getAttribute('data-tree-url');
                var scroller = document.getElementById('cat-tree');
                var body = document.getElementById('cat-tree-body');
                var loader = document.getElementById('cat-tree-loader');
                var empty = document.getElementById('cat-tree-empty');
                var searchInput = document.getElementById('cat-tree-search');

                var rootLoading = false;
                var searchMode = false;
                var searchTimer = null;

                function refreshLightbox() {
                    if (typeof window.refreshFsLightbox === 'function') window.refreshFsLightbox();
                }

                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) loadSentinel(entry.target);
                    });
                }, { root: scroller, rootMargin: '120px' });

                function fetchTree(params) {
                    var qs = Object.keys(params)
                        .filter(function (k) { return params[k] !== null && params[k] !== undefined && params[k] !== ''; })
                        .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); })
                        .join('&');
                    return fetch(treeUrl + (qs ? '?' + qs : ''), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(function (r) { return r.json(); });
                }

                function makeSentinel(parentId, depth, nextPage, kind) {
                    var s = document.createElement('div');
                    s.className = 'cat-tree-sentinel';
                    s.setAttribute('data-parent-id', parentId === null ? '' : parentId);
                    s.setAttribute('data-depth', depth);
                    s.setAttribute('data-next-page', nextPage);
                    s.setAttribute('data-kind', kind);
                    s.style.height = '1px';
                    return s;
                }

                function loadSentinel(sentinel) {
                    if (sentinel.getAttribute('data-loading') === '1') return;
                    sentinel.setAttribute('data-loading', '1');
                    var kind = sentinel.getAttribute('data-kind');
                    var parentId = sentinel.getAttribute('data-parent-id');
                    var depth = parseInt(sentinel.getAttribute('data-depth'), 10);
                    var nextPage = parseInt(sentinel.getAttribute('data-next-page'), 10);

                    var params = { page: nextPage };
                    if (kind === 'search') {
                        params.search = searchInput.value.trim();
                    } else {
                        params.parent_id = parentId;
                        params.depth = depth;
                    }

                    fetchTree(params).then(function (res) {
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

                function insertSkeleton(afterRow, depth) {
                    var sk = document.createElement('div');
                    sk.className = 'cat-tree-skeleton-row px-3 py-2 border-bottom';
                    sk.style.paddingLeft = (12 + (depth + 1) * 24) + 'px';
                    sk.innerHTML = '<div class="cat-tree-skeleton" style="width:35%"></div>';
                    afterRow.parentNode.insertBefore(sk, afterRow.nextSibling);
                    return sk;
                }

                function collapse(row) {
                    var depth = parseInt(row.getAttribute('data-depth'), 10);
                    var sib = row.nextElementSibling;
                    while (sib) {
                        var sd = sib.getAttribute('data-depth');
                        var isChildRow = sib.classList.contains('cat-tree-row') && parseInt(sd, 10) > depth;
                        var isChildSentinel = sib.classList.contains('cat-tree-sentinel') && parseInt(sd, 10) > depth;
                        var isSkeleton = sib.classList.contains('cat-tree-skeleton-row');
                        if (!isChildRow && !isChildSentinel && !isSkeleton) break;
                        var next = sib.nextElementSibling;
                        if (isChildSentinel) observer.unobserve(sib);
                        sib.parentNode.removeChild(sib);
                        sib = next;
                    }
                    row.setAttribute('data-loaded', '0');
                    row.querySelector('.cat-tree-toggle')?.setAttribute('aria-expanded', 'false');
                    row.classList.remove('cat-tree-row-expanded');
                }

                function expand(row) {
                    var id = row.getAttribute('data-id');
                    var depth = parseInt(row.getAttribute('data-depth'), 10);
                    var sk = insertSkeleton(row, depth);
                    fetchTree({ parent_id: id, depth: depth + 1, page: 1 }).then(function (res) {
                        sk.parentNode.removeChild(sk);
                        var tmp = document.createElement('div');
                        tmp.innerHTML = res.html;
                        var anchor = row;
                        while (tmp.firstElementChild) {
                            var el = tmp.firstElementChild;
                            anchor.parentNode.insertBefore(el, anchor.nextSibling);
                            anchor = el;
                        }
                        if (res.has_more) {
                            var sentinel = makeSentinel(id, depth + 1, res.next_page, 'child');
                            anchor.parentNode.insertBefore(sentinel, anchor.nextSibling);
                            observer.observe(sentinel);
                        }
                        row.setAttribute('data-loaded', '1');
                        row.querySelector('.cat-tree-toggle')?.setAttribute('aria-expanded', 'true');
                        row.classList.add('cat-tree-row-expanded');
                        refreshLightbox();
                    }).catch(function () {
                        if (sk.parentNode) sk.parentNode.removeChild(sk);
                        row.querySelector('.cat-tree-chevron').classList.remove('rotated');
                    });
                }

                function toggleRow(row) {
                    if (row.getAttribute('data-expandable') !== '1') return;
                    var chevron = row.querySelector('.cat-tree-chevron');
                    if (row.getAttribute('data-loaded') === '1') {
                        collapse(row);
                        if (chevron) chevron.classList.remove('rotated');
                    } else {
                        if (chevron) chevron.classList.add('rotated');
                        expand(row);
                    }
                }

                body.addEventListener('click', function (e) {
                    if (e.target.closest('.cat-tree-actions')) return;
                    var toggle = e.target.closest('.cat-tree-toggle');
                    var row = e.target.closest('.cat-tree-row');
                    if (!row || row.getAttribute('data-expandable') !== '1') return;
                    if (toggle || !e.target.closest('a,button')) toggleRow(row);
                });

                body.addEventListener('keydown', function (e) {
                    var toggle = e.target.closest('.cat-tree-toggle');
                    if (!toggle || (e.key !== 'Enter' && e.key !== ' ')) return;
                    e.preventDefault();
                    toggleRow(toggle.closest('.cat-tree-row'));
                });

                function clearTree() {
                    body.querySelectorAll('.cat-tree-sentinel').forEach(function (s) { observer.unobserve(s); });
                    body.innerHTML = '';
                }

                function loadRoot() {
                    if (rootLoading) return;
                    rootLoading = true;
                    searchMode = false;
                    clearTree();
                    empty.classList.add('d-none');
                    loader.classList.remove('d-none');
                    fetchTree({ page: 1 }).then(function (res) {
                        loader.classList.add('d-none');
                        rootLoading = false;
                        body.innerHTML = res.html;
                        refreshLightbox();
                        if (!res.html.trim()) { empty.classList.remove('d-none'); return; }
                        if (res.has_more) {
                            var sentinel = makeSentinel('', 0, res.next_page, 'root');
                            body.appendChild(sentinel);
                            observer.observe(sentinel);
                        }
                    }).catch(function () {
                        loader.classList.add('d-none');
                        rootLoading = false;
                    });
                }

                function runSearch(term) {
                    searchMode = true;
                    clearTree();
                    empty.classList.add('d-none');
                    loader.classList.remove('d-none');
                    fetchTree({ search: term, page: 1 }).then(function (res) {
                        loader.classList.add('d-none');
                        body.innerHTML = res.html;
                        refreshLightbox();
                        if (!res.html.trim()) { empty.classList.remove('d-none'); return; }
                        if (res.has_more) {
                            var sentinel = makeSentinel('', 0, res.next_page, 'search');
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
