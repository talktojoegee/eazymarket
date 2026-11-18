<div class="modal modal-blur fade" id="adminMarkPaymentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h4 class="modal-title"><i class="ti ti-cash me-1"></i>{{ __('labels.mark_payment_received') }}</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <p class="text-secondary small mb-3">{{ __('labels.mark_payment_received_confirm') }}</p>
                <div>
                    <label class="form-label">{{ __('labels.remark') }} <span class="text-muted">({{ __('labels.optional') }})</span></label>
                    <textarea class="form-control" id="adminPaymentRemark" rows="2"
                              maxlength="1000"
                              placeholder="{{ __('labels.admin_remark_placeholder') }}"></textarea>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-ghost-secondary btn-sm" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                <button type="button" class="btn btn-warning btn-sm" id="adminPaymentReceivedConfirm">
                    <i class="ti ti-cash me-1"></i>{{ __('labels.confirm') }}
                </button>
            </div>
        </div>
    </div>
</div>
