@php($channels = $channels ?? ['email'])
@php($allowEmail = in_array('email', $channels))
@php($allowMobile = in_array('mobile', $channels))
@php($both = $allowEmail && $allowMobile)
@php($gateway = $gateway ?? 'custom')
@php($default = $allowEmail ? 'email' : 'mobile')

<div class="password-recovery"
     data-gateway="{{ $gateway }}"
     data-default-channel="{{ $default }}"
     data-send-otp-url="{{ $sendOtpUrl ?? '' }}"
     data-verify-otp-url="{{ $verifyOtpUrl ?? '' }}"
     data-firebase-config-url="/api/settings/firebase-config"
     data-csrf="{{ csrf_token() }}">

    @if ($both)
        <div class="btn-group w-100 mb-3 gap-2" role="group">
            <input type="radio" class="btn-check" name="recovery_channel" id="rc_email" value="email" checked>
            <label class="btn btn-outline-primary" for="rc_email">Email</label>
            <input type="radio" class="btn-check" name="recovery_channel" id="rc_mobile" value="mobile">
            <label class="btn btn-outline-primary" for="rc_mobile">Mobile</label>
        </div>
    @endif

    @if ($allowEmail)
        <div class="recovery-pane" data-pane="email" @if($both && $default !== 'email') style="display:none;" @endif>
            <div class="mb-3">
                <label class="form-label">Email address</label>
                <input type="email" class="form-control" id="pr-email" placeholder="your@email.com"
                       value="{{ old('email') }}" autocomplete="off"/>
            </div>
        </div>
    @endif

    @if ($allowMobile)
        <div class="recovery-pane" data-pane="mobile" @if($both) style="display:none;" @endif>
            <div class="mb-3">
                <label class="form-label">Mobile number</label>
                <div class="input-group row">
                    <span class="col-3 g-0">
                    <x-tom-select-country id="pr-country" name="pr_country" :required="false"
                                          :no-filter="true" textField="phonecode"
                                          :selected="$defaultCountry ?? null"
                                          placeholder="" /></span>
                    <input type="text" class="form-control col-9 g-0" id="pr-number" inputmode="numeric"
                           placeholder="Phone number"/>
                </div>
            </div>
        </div>
    @endif

    <div class="mb-3">
        <button type="button" class="btn btn-primary w-100" id="pr-send-btn">Send OTP</button>
    </div>
    <div id="pr-otp-section" style="display:none;">
        <div class="mb-3">
            <label class="form-label">One-time code</label>
            <input type="text" inputmode="numeric" maxlength="6" class="form-control" id="pr-otp"/>
        </div>
        <div class="mb-3">
            <button type="button" class="btn btn-primary w-100" id="pr-verify-btn">Verify &amp; continue</button>
        </div>
    </div>
    <div id="pr-message" class="small mt-2"></div>
    <div id="recaptcha-container"></div>
</div>

<script type="module" src="{{ hyperAsset('assets/js/password-recovery.js') }}"></script>
