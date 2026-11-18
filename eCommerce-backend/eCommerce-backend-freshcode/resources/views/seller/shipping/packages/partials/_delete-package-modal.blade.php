<div class="modal modal-blur fade" id="deleteStorePackageModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('labels.delete_store_package') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="deleteStorePackageMessage">{{ __('labels.delete_store_package_confirm') }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                <button type="button" class="btn btn-danger" id="deleteStorePackageConfirm">
                    {{ __('labels.delete') }}
                </button>
            </div>
        </div>
    </div>
</div>
