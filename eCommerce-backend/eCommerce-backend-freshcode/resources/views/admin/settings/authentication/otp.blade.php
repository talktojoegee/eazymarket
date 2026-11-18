<div class="tab-pane fade" id="auth-otp" role="tabpanel" data-auth-section="otp">
    <form action="{{ route('admin.settings.store') }}" method="post" class="form-submit auth-section-form">
        @csrf
        <input type="hidden" name="type" value="authentication">
        <input type="hidden" name="section" value="otp">
        <div class="card-body">
            <h3 class="card-title mb-2">{{ __('labels.choose_mobile_otp_method') }}</h3>
            <p class="text-muted mb-4">{{ __('labels.choose_mobile_otp_method_hint') }}</p>
            <div class="row g-3 mb-4">
                @foreach([
                    'custom' => ['ti-message', __('labels.custom_sms'), __('labels.custom_sms_gateway_hint')],
                    'firebase' => ['ti-brand-firebase', __('labels.firebase_phone_otp'), __('labels.firebase_phone_otp_hint')],
                ] as $value => [$icon, $title, $hint])
                    <div class="col-md-6">
                        <label class="card card-sm h-100 cursor-pointer sms-gateway-card">
                            <div class="card-body d-flex gap-3 align-items-start">
                                <input class="form-check-input mt-1" type="radio" name="smsGateway" value="{{ $value }}" @checked($selectedOtpGateway === $value) required>
                                <div><i class="ti {{ $icon }} fs-2"></i><div class="fw-semibold mt-2">{{ $title }}</div><div class="text-muted small">{{ $hint }}</div></div>
                            </div>
                        </label>
                    </div>
                @endforeach
            </div>

            <div id="firebaseOtpNotice" class="alert alert-info align-items-start d-none">
                <i class="ti ti-info-circle me-2"></i>
                <div>{{ __('labels.firebase_phone_uses_shared_setup') }} <a href="#firebase" data-auth-tab-link="firebase" class="alert-link">{{ __('labels.open_firebase_setup') }}</a></div>
            </div>

            <div id="customSmsFields" class="border rounded p-3 p-md-4 d-none">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                    <div><h4 class="mb-1">{{ __('labels.custom_sms_gateway') }}</h4><div class="text-muted">{{ __('labels.custom_sms_gateway_form_hint') }}</div></div>
                    <span class="badge bg-blue-lt">{{ __('labels.advanced') }}</span>
                </div>
                <div class="row g-3">
                    <div class="col-lg-8">
                        <label class="form-label required" for="customSmsUrl">{{ __('labels.custom_sms_url') }}</label>
                        <input type="url" class="form-control" id="customSmsUrl" name="customSmsUrl" value="{{ $settings['customSmsUrl'] ?? '' }}" placeholder="https://api.example.com/send">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label required" for="customSmsMethod">{{ __('labels.custom_sms_method') }}</label>
                        <select class="form-select" id="customSmsMethod" name="customSmsMethod">
                            @foreach(['GET', 'POST', 'PUT', 'PATCH'] as $method)
                                <option value="{{ $method }}" @selected(($settings['customSmsMethod'] ?? '') === $method)>{{ $method }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="customSmsMobileFormat">{{ __('labels.custom_sms_mobile_format') }}</label>
                        <select class="form-select" id="customSmsMobileFormat" name="customSmsMobileFormat">
                            <option value="">{{ __('labels.select') }}</option>
                            @foreach(\App\Enums\SmsMobileFormatEnum::cases() as $format)
                                <option value="{{ $format->value }}" data-example="{{ $format->example() }}" @selected(($settings['customSmsMobileFormat'] ?? '') === $format->value)>{{ $format->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="googleRecaptchaSiteKey">{{ __('labels.google_recaptcha_site_key') }}</label>
                        <input type="text" class="form-control" id="googleRecaptchaSiteKey" name="googleRecaptchaSiteKey" value="{{ $settings['googleRecaptchaSiteKey'] ?? '' }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="customSmsTextFormatData">{{ __('labels.custom_sms_text_format_data') }}</label>
                        <textarea class="form-control" id="customSmsTextFormatData" name="customSmsTextFormatData" rows="3" placeholder="Your OTP is {otp}. Valid for {minutes} minutes.">{{ $settings['customSmsTextFormatData'] ?? '' }}</textarea>
                        <div class="form-hint">{{ __('labels.custom_sms_template_variables') }}</div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            @foreach([
                                __('labels.custom_sms_template_suggestion_secure'),
                                __('labels.custom_sms_template_suggestion_short'),
                                __('labels.custom_sms_template_suggestion_branded'),
                            ] as $suggestion)
                                <button type="button" class="btn btn-sm btn-outline-secondary sms-template-suggestion" data-template="{{ $suggestion }}">{{ $suggestion }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                @foreach([
                    'header' => [__('labels.custom_sms_header'), 'customSmsHeaderKey', 'customSmsHeaderValue'],
                    'params' => [__('labels.custom_sms_params'), 'customSmsParamsKey', 'customSmsParamsValue'],
                    'body' => [__('labels.custom_sms_body'), 'customSmsBodyKey', 'customSmsBodyValue'],
                ] as $group => [$label, $keyName, $valueName])
                    <div class="mt-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label mb-0">{{ $label }}</label>
                            <button type="button" class="btn btn-sm btn-outline-primary add-sms-field" data-group="{{ $group }}" data-key-name="{{ $keyName }}" data-value-name="{{ $valueName }}">{{ __('labels.add') }}</button>
                        </div>
                        <div id="{{ $group }}Fields" class="sms-pair-list">
                            @foreach(($settings[$keyName] ?? []) as $index => $key)
                                <div class="row g-2 mb-2 sms-pair-row">
                                    <div class="col-md-5"><input type="text" class="form-control" name="{{ $keyName }}[]" value="{{ $key }}" placeholder="{{ __('labels.key') }}"></div>
                                    <div class="col-md-5"><input type="text" class="form-control" name="{{ $valueName }}[]" value="{{ $isDemoUser && !empty($settings[$valueName][$index]) ? '••••••••' : ($settings[$valueName][$index] ?? '') }}" placeholder="{{ __('labels.value') }}"></div>
                                    <div class="col-md-2"><button type="button" class="btn btn-icon btn-outline-danger remove-sms-field" aria-label="{{ __('labels.remove') }}"><i class="ti ti-trash"></i></button></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @include('admin.settings.authentication.actions')
    </form>
</div>
