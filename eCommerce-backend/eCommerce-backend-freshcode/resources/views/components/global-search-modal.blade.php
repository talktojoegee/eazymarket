<div class="modal fade" id="globalSearchModal" tabindex="-1" aria-labelledby="globalSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog global-search-dialog">
        <div class="modal-content">
            <div class="global-search-input-group">
                <i class="ti ti-search global-search-icon"></i>
                <label id="globalSearchModalLabel" class="visually-hidden" for="globalSearchInput">Global search</label>
                <input type="search" id="globalSearchInput" class="global-search-input"
                       placeholder="Search menu, products, orders, returns..." autocomplete="off">
                <button type="button" id="globalSearchClear" class="global-search-clear" title="Clear search" aria-label="Clear search">
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <div id="globalSearchFilters" class="global-search-filters" hidden></div>
            <div id="globalSearchResults" class="global-search-results" aria-live="polite"></div>
            <div class="global-search-footer">
                <span><kbd>↑</kbd> <kbd>↓</kbd> Navigate &nbsp; <kbd>↵</kbd> Select &nbsp; <kbd>ESC</kbd> Close</span>
                <span class="badge bg-primary-lt">Command Palette</span>
            </div>
        </div>
    </div>
</div>
