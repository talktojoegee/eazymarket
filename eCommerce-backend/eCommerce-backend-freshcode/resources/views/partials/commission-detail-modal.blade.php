<!-- SETTLEMENT DETAIL MODAL -->
<div class="modal modal-blur fade" id="commissionDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="commissionDetailTitle">{{ __('labels.settlement_detail') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="commissionDetailBody">
                <div class="text-center text-secondary py-5" id="commissionDetailLoader">
                    <div class="spinner-border" role="status"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" data-bs-dismiss="modal">{{ __('labels.close') }}</button>
            </div>
        </div>
    </div>
</div>
