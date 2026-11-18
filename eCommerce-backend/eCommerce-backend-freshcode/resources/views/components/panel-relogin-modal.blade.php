@php
    $reauthenticationToken = app(\App\Services\PanelReauthenticationService::class)->issue($user, $panel);
@endphp

<div id="panel-session"
     data-panel="{{ $panel }}"
     data-status-url="{{ route($panel.'.session.status') }}"
     data-login-url="{{ route($panel.'.login.post') }}"
     data-login-page-url="{{ route($panel.'.login') }}"
     data-reauthentication-token="{{ $reauthenticationToken }}"
     data-expires-in="{{ (int) config('session.lifetime', 120) * 60 }}">
</div>

<div class="modal modal-blur fade" id="panelReloginModal" tabindex="-1" aria-labelledby="panelReloginTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title" id="panelReloginTitle">{{ __('labels.session_expired_title') }}</h2>
                    <div class="text-muted">{{ __('labels.session_expired_description') }}</div>
                </div>
            </div>
            <div class="modal-body">
                <x-panel-auth-form :panel="$panel" :reauthentication="true" :user="$user"
                                   :reauthentication-token="$reauthenticationToken" />
            </div>
        </div>
    </div>
</div>
