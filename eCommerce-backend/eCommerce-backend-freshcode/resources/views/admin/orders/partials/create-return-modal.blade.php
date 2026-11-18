<div class="modal modal-blur fade" id="createReturnModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="createReturnForm" data-url="{{ $createReturnUrl }}">
                <div class="modal-header py-2">
                    <h4 class="modal-title"><i class="ti ti-arrow-back-up me-1"></i>{{ __('labels.create_return') ?? 'Create return' }}</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.item') ?? 'Item' }}</label>
                        <select class="form-select" name="order_item_id" required>
                            <option value="">{{ __('labels.select_item') ?? 'Select an item' }}</option>
                            @foreach($returnableItems as $it)
                                <option value="{{ $it['id'] }}">
                                    {{ $it['title'] }}@if($it['variant']) · {{ $it['variant'] }}@endif (× {{ $it['quantity'] }})
                                </option>
                            @endforeach
                        </select>
                        <small class="text-secondary">{{ __('labels.direct_return_hint') ?? 'The whole item is returned; choose the qty to refund on the next screen.' }}</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.return_reason') ?? 'Return reason' }}</label>
                        <select class="form-select" name="reason_code">
                            <option value="">{{ __('labels.select_reason') ?? 'Select a reason' }}</option>
                            @foreach(\App\Enums\Order\ReturnReasonCodeEnum::options() as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">{{ __('labels.note') ?? 'Note' }}</label>
                        <textarea class="form-control" name="note" rows="2" maxlength="1000"></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-link link-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') ?? 'Cancel' }}</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-plus me-1"></i>{{ __('labels.create_return') ?? 'Create return' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        var form = document.getElementById('createReturnForm');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = form.querySelector('button[type="submit"]');
            var itemId = form.querySelector('[name="order_item_id"]').value;
            if (!itemId) {
                if (typeof Toast !== 'undefined') Toast.fire({ icon: 'error', title: @json(__('labels.select_item') ?? 'Select an item') });
                return;
            }
            btn.disabled = true;
            var payload = {
                order_item_id: itemId,
                reason_code: form.querySelector('[name="reason_code"]').value || null,
                note: form.querySelector('[name="note"]').value || null,
            };
            window.axios.post(form.getAttribute('data-url'), payload)
                .then(function (res) {
                    var data = (res && res.data) || {};
                    if (data.success) {
                        if (typeof Toast !== 'undefined') Toast.fire({ icon: 'success', title: data.message || 'Done' });
                        var url = (data.data && data.data.redirect) || null;
                        setTimeout(function () { url ? (window.location.href = url) : window.location.reload(); }, 500);
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
