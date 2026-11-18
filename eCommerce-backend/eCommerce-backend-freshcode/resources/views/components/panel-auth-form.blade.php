@props([
    'panel',
    'reauthentication' => false,
    'user' => null,
    'reauthenticationToken' => null,
])

@php
    $identifierField = $reauthentication && ! empty($user->email) ? 'email' : 'mobile';
    $identifier = $reauthentication ? $user->{$identifierField} : null;
    $otpIdentifier = $reauthentication && $identifierField === 'mobile'
        ? ($user->country_code ?? '').$user->mobile
        : $identifier;
    $passwordInputId = $reauthentication ? 'panel-relogin-password' : 'password';
    $passwordToggleId = $reauthentication ? 'panel-relogin-password-toggle' : 'password-toggle';
@endphp

<div data-panel-auth-form data-reauthentication="{{ $reauthentication ? 'true' : 'false' }}">
    <div data-panel-auth-password>
        <form id="{{ $reauthentication ? 'panel-relogin-password-form' : 'login-form' }}"
              @unless($reauthentication) action="{{ route($panel.'.login.post') }}" method="post" novalidate @endunless
              autocomplete="off">
            @csrf
            @unless($reauthentication)
                <input type="hidden" name="fcm_token" value="">
                <input type="hidden" name="device_type" value="web">
            @endunless
            @if($reauthentication)
                <input type="hidden" name="{{ $identifierField }}" value="{{ $identifier }}">
                <input type="hidden" name="reauthentication" value="1">
                <input type="hidden" name="reauthentication_token" value="{{ $reauthenticationToken }}">
            @endif

            <div class="mb-3">
                <label class="form-label">
                    {{ $reauthentication && $identifierField === 'mobile' ? __('labels.mobile') : __('labels.email') }}
                </label>
                <input type="{{ $reauthentication ? 'text' : 'email' }}" class="form-control"
                       name="{{ $reauthentication ? $identifierField : 'email' }}"
                       value="{{ $identifier }}" placeholder="your@email.com" autocomplete="username"
                       @readonly($reauthentication)>
            </div>
            <div class="mb-2">
                <label class="form-label">
                    {{ __('labels.password') }}
                    <span class="form-label-description">
                        <a href="{{ route($panel.'.password.request') }}">{{ __('labels.forgot_password') }}</a>
                    </span>
                </label>
                <div class="input-group input-group-flat">
                    <input type="password" class="form-control" name="password" placeholder="Your password"
                           autocomplete="current-password" id="{{ $passwordInputId }}" required>
                    <span class="input-group-text">
                        <a href="#" class="link-secondary" title="Show password" id="{{ $passwordToggleId }}"
                           data-panel-password-toggle="{{ $passwordInputId }}" data-bs-toggle="tooltip">Show</a>
                    </span>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-check">
                    <input type="checkbox" class="form-check-input" name="remember" value="1">
                    <span class="form-check-label">Remember me on this device</span>
                </label>
            </div>
            @if($reauthentication)
                <div id="panel-relogin-message" class="small text-danger mb-3" role="alert"></div>
            @endif
            <div class="form-footer">
                <button type="submit" class="btn btn-primary w-100">{{ __('labels.sign_in') }}</button>
            </div>
        </form>

        <div class="d-flex align-items-center my-3" aria-hidden="true">
            <div class="border-top flex-grow-1"></div>
            <span class="px-3 text-muted">{{ __('labels.or') }}</span>
            <div class="border-top flex-grow-1"></div>
        </div>

        @if($reauthentication)
            <button type="button" class="btn btn-outline-primary w-100" data-panel-auth-show-otp>
                {{ __('labels.login_with_otp') }}
            </button>
        @else
            <a href="{{ route($panel.'.login-otp') }}" class="btn btn-outline-primary w-100">
                {{ __('labels.login_with_otp') }}
            </a>
        @endif
    </div>

    @if($reauthentication)
        <div data-panel-auth-otp hidden>
            @include('partials.otp-login', [
                'gateway' => \App\Types\Settings\AuthenticationSettingType::smsGateway(),
                'sendUrl' => route($panel.'.login.send-otp'),
                'verifyUrl' => route($panel.'.login.verify-otp'),
                'lockedIdentifier' => $otpIdentifier,
                'reauthenticationToken' => $reauthenticationToken,
            ])

            <div class="d-flex align-items-center my-3" aria-hidden="true">
                <div class="border-top flex-grow-1"></div>
                <span class="px-3 text-muted">{{ __('labels.or') }}</span>
                <div class="border-top flex-grow-1"></div>
            </div>
            <button type="button" class="btn btn-outline-primary w-100" data-panel-auth-show-password>
                {{ __('labels.login_with_password') }}
            </button>
        </div>
    @endif
</div>
