<div class="tab-pane fade show active" id="auth-account" role="tabpanel" data-auth-section="account">
    <form action="{{ route('admin.settings.store') }}" method="post" class="form-submit auth-section-form">
        @csrf
        <input type="hidden" name="type" value="authentication">
        <input type="hidden" name="section" value="account">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-7">
                    <h3 class="card-title mb-2">{{ __('labels.account_identifier_mode') }}</h3>
                    <p class="text-muted">{{ __('labels.account_identifier_mode_help') }}</p>
                    <label class="form-label required" for="identifierMode">{{ __('labels.account_identifier_mode') }}</label>
                    <select class="form-select" id="identifierMode" name="identifierMode" required>
                        <option value="both" @selected(($settings['identifierMode'] ?? 'both') === 'both')>{{ __('labels.identifier_mode_both') }}</option>
                        <option value="email_primary" @selected(($settings['identifierMode'] ?? 'both') === 'email_primary')>{{ __('labels.identifier_mode_email_primary') }}</option>
                        <option value="mobile_primary" @selected(($settings['identifierMode'] ?? 'both') === 'mobile_primary')>{{ __('labels.identifier_mode_mobile_primary') }}</option>
                    </select>
                </div>
                <div class="col-lg-5">
                    <div class="alert alert-info h-100 align-items-start">
                        <i class="ti ti-info-circle me-2"></i>
                        <div>{{ __('labels.account_access_help') }}</div>
                    </div>
                </div>
            </div>
        </div>
        @include('admin.settings.authentication.actions')
    </form>
</div>
