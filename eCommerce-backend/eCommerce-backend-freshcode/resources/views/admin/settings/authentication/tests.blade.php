<div class="tab-pane fade" id="auth-tests" role="tabpanel" data-auth-section="tests"
     data-connection-endpoint="{{ route('admin.settings.authentication.test-firebase-connection') }}"
     data-identity-endpoint="{{ route('admin.settings.authentication.test-firebase-identity') }}"
     data-sms-endpoint="{{ route('admin.settings.authentication.test-sms') }}"
     data-missing-config="{{ __('labels.firebase_missing_configuration') }}"
     data-test-failed="{{ __('labels.test_failed') }}"
     data-test-completed="{{ __('labels.test_completed') }}"
     data-enter-sms-mobile="{{ __('labels.enter_sms_test_mobile') }}"
     data-enter-firebase-phone="{{ __('labels.enter_firebase_test_mobile') }}"
     data-otp-sent="{{ __('labels.firebase_test_otp_sent') }}"
     data-complete-phone="{{ __('labels.firebase_test_complete_phone') }}"
     data-apple-operation-not-allowed="{{ __('labels.firebase_apple_operation_not_allowed', [
         'project' => $settings['fireBaseProjectId'] ?? '',
         'return_url' => 'https://'.($settings['fireBaseAuthDomain'] ?? '').'/__/auth/handler',
     ]) }}"
     data-firebase-unauthorized-domain="{{ __('labels.firebase_unauthorized_domain', ['domain' => request()->getHost()]) }}"
     data-firebase-popup-blocked="{{ __('labels.firebase_popup_blocked') }}"
     data-firebase-popup-closed="{{ __('labels.firebase_popup_closed') }}"
     data-key-label="{{ __('labels.key') }}"
     data-value-label="{{ __('labels.value') }}"
     data-remove-label="{{ __('labels.remove') }}">
    @php
        $selectedSmsFormat = \App\Enums\SmsMobileFormatEnum::tryFrom($settings['customSmsMobileFormat'] ?? '')
            ?? \App\Enums\SmsMobileFormatEnum::E164_WITH_PLUS;
    @endphp
    <div class="card-body">
        <div class="mb-4"><h3 class="card-title mb-1">{{ __('labels.authentication_test_center') }}</h3><p class="text-muted mb-0">{{ __('labels.authentication_test_center_hint') }}</p></div>
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="border rounded p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div><h4 class="mb-1">{{ __('labels.firebase_connection_test') }}</h4><div class="text-muted small">{{ __('labels.firebase_connection_test_hint') }}</div></div>
                        <i class="ti ti-plug-connected fs-1 text-primary"></i>
                    </div>
                    <button type="button" class="btn btn-outline-primary auth-test-button" data-test="connection">{{ __('labels.run_test') }}</button>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="border rounded p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div><h4 class="mb-1">{{ __('labels.custom_sms_delivery_test') }}</h4><div class="text-muted small">{{ __('labels.custom_sms_delivery_test_hint') }}</div></div>
                        <i class="ti ti-message-code fs-1 text-primary"></i>
                    </div>
                    <div class="input-group mb-2"><input type="text" class="form-control" id="testSmsMobile" placeholder="{{ $selectedSmsFormat->example() }}"><button type="button" class="btn btn-outline-primary auth-test-button" data-test="sms">{{ __('labels.send') }}</button></div>
                    <div class="form-hint">{{ __('labels.custom_sms_test_unsaved_hint') }}</div>
                </div>
            </div>
            <div class="col-12">
                <div class="border rounded p-3">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div><h4 class="mb-1">{{ __('labels.firebase_phone_otp_test') }}</h4><div class="text-muted small">{{ __('labels.firebase_phone_otp_test_hint') }}</div></div>
                        <i class="ti ti-device-mobile-check fs-1 text-primary"></i>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="bg-body-tertiary rounded p-3 h-100">
                                <div class="d-flex align-items-center gap-2 mb-3"><span class="badge bg-primary text-primary-fg">1</span><strong>{{ __('labels.firebase_phone_send_step') }}</strong></div>
                                <label class="form-label" for="firebaseTestPhone">{{ __('labels.mobile') }}</label>
                                <div class="input-group"><input type="text" class="form-control" id="firebaseTestPhone" placeholder="+15551234567"><button type="button" class="btn btn-outline-primary auth-test-button" data-test="phone-send">{{ __('labels.send_otp') }}</button></div>
                                <div id="firebaseTestRecaptcha" class="mt-3"></div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="bg-body-tertiary rounded p-3 h-100">
                                <div class="d-flex align-items-center gap-2 mb-3"><span class="badge bg-primary text-primary-fg">2</span><strong>{{ __('labels.firebase_phone_verify_step') }}</strong></div>
                                <label class="form-label" for="firebaseTestOtp">{{ __('labels.otp') }}</label>
                                <div class="input-group"><input type="text" class="form-control" id="firebaseTestOtp" inputmode="numeric" autocomplete="one-time-code" maxlength="6"><button type="button" class="btn btn-primary auth-test-button" data-test="phone-verify" disabled>{{ __('labels.verify') }}</button></div>
                                <div class="form-hint mt-2">{{ __('labels.firebase_phone_retry_hint') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3 h-100"><h4>{{ __('labels.test_google_login') }}</h4><p class="text-muted">{{ __('labels.test_google_login_hint') }}</p><button type="button" class="btn btn-outline-primary auth-test-button" data-test="google"><i class="ti ti-brand-google me-1"></i>{{ __('labels.continue_with_google') }}</button></div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3 h-100"><h4>{{ __('labels.test_apple_login') }}</h4><p class="text-muted">{{ __('labels.test_apple_login_hint') }}</p><button type="button" class="btn btn-outline-primary auth-test-button" data-test="apple"><i class="ti ti-brand-apple me-1"></i>{{ __('labels.continue_with_apple') }}</button></div>
            </div>
        </div>
        <div id="authenticationTestResult" class="alert mt-4 d-none" aria-live="polite"></div>
    </div>
</div>
