<div class="modal modal-blur fade" id="restockModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <form id="restockForm" data-url="{{ $restockUrl }}">
                <div class="modal-header py-2">
                    <div>
                        <h4 class="modal-title"><i class="ti ti-rotate-2 me-1"></i>{{ __('labels.restock') ?? 'Restock' }}</h4>
                        <div class="text-secondary small">{{ __('labels.restock_hint') ?? 'Select the items to add back into inventory.' }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="d-flex align-items-center justify-content-between gap-3 px-3 py-2 border-bottom bg-secondary-lt">
                        <span class="small text-secondary">
                            <span class="fw-semibold text-body" id="restockSelectedCount">0</span> / {{ count($restockableItems) }} {{ __('labels.selected') }}
                        </span>
                        <button type="button" class="btn btn-sm btn-ghost-primary" id="restockSelectAll">
                            {{ __('labels.select_all') ?? 'Select all' }}
                        </button>
                    </div>
                    <div class="list-group list-group-flush" data-restock-items>
                        @foreach($restockableItems as $it)
                            @php
                                $restockStatusClass = match((string) $it['status']) {
                                    'cancelled' => 'bg-red-lt text-red',
                                    'returned' => 'bg-orange-lt text-orange',
                                    'refunded' => 'bg-azure-lt text-azure',
                                    default => 'bg-secondary-lt text-secondary',
                                };
                                $restockProductUrl = !empty($it['product_id'])
                                    ? route((($isSeller ?? false) ? 'seller' : 'admin') . '.products.show', ['id' => $it['product_id']])
                                    : null;
                            @endphp
                            <label class="list-group-item d-flex align-items-center gap-3 px-3 py-2" data-restock-item>
                                <input type="checkbox" class="form-check-input m-0 js-restock-item flex-shrink-0" value="{{ $it['id'] }}">
                                <span class="avatar avatar-md bg-secondary-lt flex-shrink-0 overflow-hidden">
                                    @if(!empty($it['image']))
                                        <img src="{{ $it['image'] }}" alt="{{ $it['title'] }}" class="avatar-img object-fit-cover">
                                    @else
                                        <i class="ti ti-photo text-secondary"></i>
                                    @endif
                                </span>
                                <span class="flex-fill" style="min-width: 0;">
                                    <span class="fw-semibold d-block text-truncate">
                                        @if($restockProductUrl)
                                            <a href="{{ $restockProductUrl }}" class="text-reset text-decoration-underline-hover">{{ $it['title'] }}</a>
                                        @else
                                            {{ $it['title'] }}
                                        @endif
                                    </span>
                                    <span class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                        @if($it['variant'])<span class="text-secondary small">{{ $it['variant'] }}</span>@endif
                                        <span class="badge {{ $restockStatusClass }} text-uppercase">{{ ucwords(str_replace('_', ' ', $it['status'])) }}</span>
                                    </span>
                                </span>
                                <span class="badge bg-secondary-lt text-nowrap">{{ __('labels.quantity') }}: {{ $it['quantity'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-link link-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') ?? 'Cancel' }}</button>
                    <button type="submit" class="btn btn-sm btn-primary" disabled><i class="ti ti-rotate-2 me-1"></i>{{ __('labels.restock') ?? 'Restock' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        var form = document.getElementById('restockForm');
        if (!form) return;
        var items = Array.from(form.querySelectorAll('.js-restock-item'));
        var selectedCount = document.getElementById('restockSelectedCount');
        var selectAll = document.getElementById('restockSelectAll');
        var submitButton = form.querySelector('button[type="submit"]');
        function updateSelection() {
            var selected = items.filter(function (item) { return item.checked; }).length;
            if (selectedCount) selectedCount.textContent = selected;
            if (submitButton) submitButton.disabled = selected === 0;
        }
        items.forEach(function (item) { item.addEventListener('change', updateSelection); });
        if (selectAll) {
            selectAll.addEventListener('click', function () {
                var checkAll = !items.every(function (item) { return item.checked; });
                items.forEach(function (item) { item.checked = checkAll; });
                updateSelection();
            });
        }
        updateSelection();
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = submitButton;
            var ids = Array.from(form.querySelectorAll('.js-restock-item:checked')).map(function (el) { return el.value; });
            if (!ids.length) {
                if (typeof Toast !== 'undefined') Toast.fire({ icon: 'error', title: @json(__('labels.restock_select_item') ?? 'Select at least one item to restock.') });
                return;
            }
            btn.disabled = true;
            window.axios.post(form.getAttribute('data-url'), { item_ids: ids })
                .then(function (res) {
                    var data = (res && res.data) || {};
                    if (data.success) {
                        if (typeof Toast !== 'undefined') Toast.fire({ icon: 'success', title: data.message || 'Done' });
                        setTimeout(function () { window.location.reload(); }, 600);
                    } else {
                        if (typeof Toast !== 'undefined') Toast.fire({ icon: 'error', title: data.message || 'Error' });
                        btn.disabled = false;
                    }
                })
                .catch(function (err) {
                    var msg = (err && err.response && err.response.data && err.response.data.message) || null;
                    if (typeof Toast !== 'undefined') Toast.fire({ icon: 'error', title: msg || 'Something went wrong' });
                    btn.disabled = false;
                });
        });
    })();
</script>
