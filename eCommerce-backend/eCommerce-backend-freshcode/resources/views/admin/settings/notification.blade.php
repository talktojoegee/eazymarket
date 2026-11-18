@extends('layouts.admin.app',['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => $menuAdmin['settings']['route']['notification']['sub_active'] ?? "" ])

@section('title', __('labels.notification_settings'))

@section('header_data')
    @php
        $page_title = __('labels.notification_settings');
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.notification_settings'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.notification_settings') }}</h2>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row g-5">
                <div class="col-sm-2 d-none d-lg-block">
                    <div class="sticky-top">
                        <h3>{{ __('labels.menu') }}</h3>
                        <nav class="nav nav-vertical nav-pills" id="pills">
                            <a class="nav-link" href="#pills-general">{{ __('labels.general') }}</a>
                            <a class="nav-link" href="#pills-channels">{{ __('labels.notification_channels') }}</a>
                            <a class="nav-link" href="#pills-abandoned-cart">{{ __('labels.abandoned_cart_notifications') }}</a>
                            <a class="nav-link" href="#pills-test">{{ __('labels.test_notification') }}</a>
                        </nav>
                    </div>
                </div>
                <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                    <div class="row row-cards">
                        <div class="col-12">
                            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post"
                                  enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="type" value="notification">
                                <div class="card mb-4" id="pills-general">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.general') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.vap_id_key') }}</label>
                                            <input type="text" class="form-control" name="vapIdKey"
                                                   placeholder="{{ __('labels.vap_id_key_placeholder') }}"
                                            value="{{ $isDemoUser ? Str::mask(($settings['vapIdKey'] ?? '****'), '****', 3, 8) : ($settings['vapIdKey'] ?? '') }}"/>
                                            <div class="form-hint">{{ __('labels.firebase_vapid_hint') }}</div>
                                        </div>

                                        <div class="alert {{ ($firebaseStatus['push_ready'] ?? false) ? 'alert-success' : 'alert-warning' }} align-items-start">
                                            <i class="ti {{ ($firebaseStatus['push_ready'] ?? false) ? 'ti-circle-check' : 'ti-alert-triangle' }} me-2"></i>
                                            <div>
                                                <strong>{{ __('labels.firebase_push_readiness') }}</strong>
                                                <div>{{ ($firebaseStatus['push_ready'] ?? false) ? __('labels.firebase_push_ready') : __('labels.firebase_push_not_ready') }}</div>
                                                @unless($firebaseStatus['push_ready'] ?? false)
                                                    <a href="{{ route('admin.settings.show', 'authentication') }}#firebase" class="alert-link">{{ __('labels.complete_firebase_setup') }}</a>
                                                @endunless
                                            </div>
                                        </div>

                                        @php
                                            $cronLogPath = storage_path('logs/cron-log.txt');
                                            $cronLogExists = file_exists($cronLogPath);
                                            $cronLogMTime = $cronLogExists ? @filemtime($cronLogPath) : null;

                                            $phpBinary = shell_exec('which php')
                                                ? trim(shell_exec('which php'))
                                                : '/usr/local/bin/php';
                                        @endphp
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.cron_job_queue_worker') }}</label>

                                            <div class="text-muted mb-2">
                                                {{ __('messages.add_cron_instruction') }}
                                            </div>

                                            <pre class="p-3 rounded bg-gray-700 text-white" style="overflow:auto; font-size:0.9rem;">
                                                * * * * * {{$phpBinary}} {{ base_path('artisan') }} queue:work --stop-when-empty >> {{ storage_path('logs/cron-log.txt') }} 2>&1
                                            </pre>

                                            <div class="small text-muted">
                                                {{ __('messages.php_path_note') }}
                                            </div>

                                            <div class="mt-3">
                                                @if($cronLogExists)
                                                    <div class="alert alert-success" role="alert">
                                                        {{ __('labels.cron_status') }}: {{ __('messages.cron_detected', ['path' => $cronLogPath]) }}
                                                        @if($cronLogMTime)
                                                            <div class="small mt-1">
                                                                {{ __('labels.last_updated') }}:
                                                                {{ \Carbon\Carbon::createFromTimestamp($cronLogMTime)->toDayDateTimeString() }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                @else
                                                    <div class="alert alert-warning flex-column align-items-start" role="alert">
                                                        <div>{{ __('messages.cron_not_detected_full') }}</div>
                                                        <div>{{ __('labels.cron_not_detected') }}. {{ __('messages.log_file_not_found') }}</div>
                                                        <code>{{ $cronLogPath }}</code>
                                                        <div>{{ __('messages.cron_has_not_run_yet') }}</div>

                                                        <div class="mt-2">
                                                            {{ __('messages.view_documentation') }}:
                                                            <a href="https://docs-hyper-local.vercel.app/introduction" target="_blank" class="alert-link">
                                                                {{ __('labels.please_refer_to_docs') }}
                                                            </a>.
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                    </div>
                                </div>
                                <div class="card mb-4" id="pills-channels">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.notification_channels') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-check form-switch">
                                                <input type="hidden" name="enablePush" value="0">
                                                <input class="form-check-input" type="checkbox" name="enablePush"
                                                       value="1" {{ ($settings['enablePush'] ?? true) ? 'checked' : '' }}>
                                                <span class="form-check-label">{{ __('labels.send_push_notifications') }}</span>
                                            </label>
                                            <div class="form-hint">{{ __('labels.send_push_notifications_hint') }}</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-check form-switch">
                                                <input type="hidden" name="enableEmail" value="0">
                                                <input class="form-check-input" type="checkbox" name="enableEmail"
                                                       value="1" {{ ($settings['enableEmail'] ?? false) ? 'checked' : '' }}>
                                                <span class="form-check-label">{{ __('labels.send_email_notifications') }}</span>
                                            </label>
                                            <div class="form-hint">{{ __('labels.send_email_notifications_hint') }}</div>
                                        </div>
                                        @can('updateSetting', [\App\Models\Setting::class, 'whatsapp'])
                                            <div class="mb-3">
                                                <label class="form-check form-switch">
                                                    <input type="hidden" name="whatsappEnabled" value="0">
                                                    <input class="form-check-input" type="checkbox" id="whatsappEnabled" name="whatsappEnabled" value="1" @checked(old('whatsappEnabled', $whatsappEnabled ?? false))>
                                                    <span class="form-check-label">Enable WhatsApp</span>
                                                </label>
                                                <div class="form-hint">Enable template fetching and outbound WhatsApp messages.</div>
                                            </div>
                                        @endcan
                                        <div class="alert alert-info mb-0" role="note">
                                            Configure WhatsApp API credentials, templates, and message settings in
                                            <a href="{{ route('admin.settings.show', ['setting' => 'whatsapp']) }}" class="alert-link">Settings → WhatsApp Settings</a>.
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4" id="pills-abandoned-cart">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.abandoned_cart_notifications') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">{{ __('labels.abandoned_cart_notifications_hint') }}</p>
                                        <div class="mb-3">
                                            <label class="form-check form-switch">
                                                <input type="hidden" name="abandonedCartEnabled" value="0">
                                                <input class="form-check-input" type="checkbox" name="abandonedCartEnabled" value="1" {{ ($settings['abandonedCartEnabled'] ?? true) ? 'checked' : '' }}>
                                                <span class="form-check-label">{{ __('labels.enable_abandoned_cart_notifications') }}</span>
                                            </label>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="abandonedCartDelayMinutes">{{ __('labels.first_reminder_after_minutes') }}</label>
                                            <input class="form-control" type="number" id="abandonedCartDelayMinutes" name="abandonedCartDelayMinutes" min="1" step="1" required value="{{ old('abandonedCartDelayMinutes', $settings['abandonedCartDelayMinutes'] ?? 1440) }}">
                                            <div class="form-hint">{{ __('labels.abandoned_cart_delay_hint') }}</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="abandonedCartRepeatMinutes">{{ __('labels.repeat_reminder_every_minutes') }}</label>
                                            <input class="form-control" type="number" id="abandonedCartRepeatMinutes" name="abandonedCartRepeatMinutes" min="1" step="1" required value="{{ old('abandonedCartRepeatMinutes', $settings['abandonedCartRepeatMinutes'] ?? 180) }}">
                                            <div class="form-hint">{{ __('labels.abandoned_cart_repeat_hint') }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4" id="pills-test">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.test_notification') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">{{ __('labels.test_notification_self_hint') }}</p>
                                        @can('updateSetting', [\App\Models\Setting::class, 'notification'])
                                            <button type="button" class="btn btn-outline-primary" id="testNotificationSelf"
                                                    data-endpoint="{{ route('admin.settings.notification.test-self') }}"
                                                    data-refresh-timeout-message="{{ __('labels.firebase_test_browser_refresh_timeout') }}"
                                                    data-refresh-failed-message="{{ __('labels.firebase_test_browser_refresh_failed') }}">
                                                <span>{{ __('labels.send_test_notification_to_me') }}</span>
                                                <span class="spinner-border spinner-border-sm ms-2 d-none" aria-hidden="true"></span>
                                            </button>
                                        @endcan
                                        <div id="testNotificationResult" class="mt-3 d-none" aria-live="polite"></div>
                                    </div>
                                </div>
                                <div class="card-footer text-end">
                                    <div class="d-flex">
                                        @can('updateSetting', [\App\Models\Setting::class, 'notification'])
                                            <button type="submit"
                                                    class="btn btn-primary ms-auto">{{ __('labels.submit') }}</button>
                                        @endcan
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/notification-settings.js') }}" defer></script>
@endpush
