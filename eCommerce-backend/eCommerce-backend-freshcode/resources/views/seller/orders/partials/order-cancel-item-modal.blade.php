<div class="modal modal-blur fade"
     id="cancelItemModal"
     tabindex="-1"
     role="dialog"
     aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <form id="cancelItemForm">
            @csrf
            <div class="modal-content">
                <div class="modal-status bg-danger"></div>
                <div class="modal-header py-2">
                    <h4 class="modal-title"><i class="ti ti-ban me-1"></i>{{ __('labels.cancel_item') }}</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <p class="text-secondary small mb-3">
                        {{ __('labels.cancel_item_help_text') }}
                    </p>
                    <div class="mb-3">
                        <label class="form-label required" for="cancelItemReason">
                            {{ __('labels.reason') }}
                        </label>
                        <textarea id="cancelItemReason"
                                  name="reason"
                                  class="form-control"
                                  rows="3"
                                  maxlength="500"
                                  required
                                  placeholder="{{ __('labels.cancel_item_reason_placeholder') }}"></textarea>
                        <div class="invalid-feedback"></div>
                    </div>
                    <input type="hidden" name="order_item_id" id="cancelItemId">
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal">
                        {{ __('labels.close') }}
                    </button>
                    <button type="submit" class="btn btn-danger btn-sm" id="confirmCancelItem">
                        {{ __('labels.cancel_item') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
