@php($gateway = $gateway ?? 'custom')

<div class="otp-login"
     data-gateway="{{ $gateway }}"
     data-send-url="{{ $sendUrl }}"
     data-verify-url="{{ $verifyUrl }}"
     data-firebase-config-url="/api/settings/firebase-config"
     data-reauthentication-token="{{ $reauthenticationToken ?? '' }}"
     data-csrf="{{ csrf_token() }}">

    <div class="mb-3">
        <label class="form-label">Email or mobile number</label>
        <input type="text" class="form-control" id="ol-identifier"
               value="{{ $lockedIdentifier ?? '' }}"
               placeholder="you@example.com or +14155552671" autocomplete="username"
               @readonly(isset($lockedIdentifier))/>
    </div>

    <div class="mb-3">
        <button type="button" class="btn btn-primary w-100" id="ol-send-btn">Send OTP</button>
    </div>

    <div id="ol-otp-section" style="display:none;">
        <div class="mb-3">
            <label class="form-label">One-time code</label>
            <input type="text" inputmode="numeric" maxlength="6" class="form-control" id="ol-otp"/>
        </div>
        <div class="mb-3">
            <button type="button" class="btn btn-primary w-100" id="ol-verify-btn">Verify &amp; sign in</button>
        </div>
    </div>

    <div id="ol-message" class="small mt-2"></div>
    <div id="recaptcha-container"></div>
</div>

<script type="module" src="{{ hyperAsset('assets/js/otp-login.js') }}"></script>
