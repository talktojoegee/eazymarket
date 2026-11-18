<section class="card app-notification-preview-card" aria-labelledby="app-notification-preview-heading">
    <div class="card-header">
        <h3 class="card-title" id="app-notification-preview-heading">{{ __('labels.mobile_preview') }}</h3>
        <span class="badge bg-green-lt">{{ __('labels.live_preview') }}</span>
    </div>
    <div class="card-body">
        <p class="text-secondary text-center small mb-3">{{ __('labels.app_notification_preview_hint') }}</p>
        <div class="app-notification-phone mx-auto" aria-label="{{ __('labels.mobile_preview') }}">
            <div class="app-notification-phone-screen">
                <div class="app-notification-status" aria-hidden="true">
                    <span>9:41</span>
                    <span class="app-notification-status-icons"><i class="ti ti-wifi"></i><i class="ti ti-signal-4g"></i><i class="ti ti-battery-4"></i></span>
                </div>
                <div class="app-notification-camera" aria-hidden="true"></div>
                <div class="app-notification-lock-screen">
                    <div class="app-notification-clock">9:41</div>
                    <div class="app-notification-date">{{ now()->format('l, F j') }}</div>
                    <div class="app-notification-stack-label">{{ __('labels.notifications') }}</div>
                    <article class="app-notification-card" id="app-notification-preview-card" aria-live="polite">
                        <div class="app-notification-card-heading">
                            @if($appIcon !== '')
                                <img class="app-notification-app-icon" src="{{ $appIcon }}" alt="" />
                            @else
                                <span class="app-notification-app-icon" aria-hidden="true">{{ mb_substr($appName, 0, 1) }}</span>
                            @endif
                            <strong class="app-notification-collapsed-title" id="app-notification-collapsed-title" data-placeholder="{{ __('labels.notification_preview_title') }}">{{ __('labels.notification_preview_title') }}</strong>
                            <span class="app-notification-app-name">{{ $appName }}</span>
                            <span class="app-notification-time">{{ __('labels.now') }}</span>
                            <button class="app-notification-toggle" type="button" id="app-notification-toggle" data-expand-label="{{ __('labels.expand_notification') }}" data-collapse-label="{{ __('labels.collapse_notification') }}" aria-expanded="false" aria-controls="app-notification-expanded" aria-label="{{ __('labels.expand_notification') }}">
                                <i class="ti ti-chevron-down" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="app-notification-collapsed-content" id="app-notification-collapsed-content">
                            <div class="app-notification-collapsed-copy">
                                <p id="app-notification-preview-message" data-placeholder="{{ __('labels.notification_preview_message') }}">{{ __('labels.notification_preview_message') }}</p>
                            </div>
                            <img id="app-notification-preview-thumbnail" class="app-notification-preview-thumbnail d-none" alt="{{ __('labels.image') }}" />
                        </div>
                        <div class="app-notification-expanded-content" id="app-notification-expanded" hidden>
                            <strong id="app-notification-expanded-title">{{ __('labels.notification_preview_title') }}</strong>
                            <p id="app-notification-expanded-message">{{ __('labels.notification_preview_message') }}</p>
                            <img id="app-notification-preview-image" class="app-notification-preview-image d-none" alt="{{ __('labels.image') }}" />
                        </div>
                    </article>
                    <div class="app-notification-lock-hint" aria-hidden="true"></div>
                </div>
                <div class="app-notification-home-indicator" aria-hidden="true"></div>
            </div>
        </div>
    </div>
</section>
