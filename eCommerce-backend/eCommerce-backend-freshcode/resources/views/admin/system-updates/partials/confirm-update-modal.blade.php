<div class="modal modal-blur fade" id="confirm-update-modal" tabindex="-1" aria-labelledby="confirm-update-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirm-update-title">{{ __('labels.ready_to_update') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.close') }}"></button>
            </div>
            <div class="modal-body">
                <p>{{ __('labels.version') }} <strong data-update-version></strong> {{ __('labels.update_ready_for_review') }}</p>
                <div class="alert alert-warning" role="alert">{{ __('labels.update_maintenance_warning') }}</div>
                <p data-vendor-warning hidden>{{ __('labels.update_prepared_packages_notice') }}</p>
                <div class="alert alert-warning" data-platform-warning hidden role="alert">
                    <strong>{{ __('labels.update_server_requirements_unavailable') }}</strong>
                    <ul class="mb-2 mt-2" data-platform-warning-list></ul>
                    <div>{{ __('labels.update_package_install_warning') }}</div>
                </div>
                <p data-database-warning hidden>{{ __('labels.update_database_backup_warning') }}</p>
                <p class="text-secondary mb-0">{{ __('labels.update_progress_help') }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                <button type="button" class="btn btn-primary" data-confirm-update>{{ __('labels.confirm_update') }}</button>
            </div>
        </div>
    </div>
</div>
