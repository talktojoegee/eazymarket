@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? '', 'sub_page' => $menuAdmin['settings']['route']['whatsapp']['sub_active'] ?? ''])

@section('title', __('labels.whatsapp_settings'))

@section('header_data')
    @php
        $page_title = __('labels.whatsapp_settings');
        $page_pretitle = __('labels.admin') . ' ' . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.whatsapp_settings'), 'url' => null],
    ];
    $hasAccessToken = ($settings['accessToken'] ?? '') === \App\Support\SettingSecrets::MASK;
    $hasWebhookVerifyToken = ($settings['webhookVerifyToken'] ?? '') === \App\Support\SettingSecrets::MASK;
    $canEditWhatsApp = auth()->user()->can('updateSetting', [\App\Models\Setting::class, 'whatsapp']);
    $events = \App\Enums\WhatsAppTemplateEventEnum::options();
    $templateMappings = $settings['templateMappings'] ?? [];
    foreach ($events as $event) {
        if (isset($templateMappings[$event['value']])) {
            continue;
        }

        $legacy = collect($templateMappings)->first(fn ($mapping) => is_array($mapping) && ($mapping['event'] ?? '') === $event['value']);
        if (is_array($legacy)) {
            $templateMappings[$event['value']] = array_merge($legacy, ['key' => $event['value'], 'event' => $event['value']]);
        }
    }
    $whatsappMappingData = [
        'mappings' => $templateMappings,
        'events' => $events,
        'defaultLanguage' => $settings['defaultLanguage'] ?? 'en_US',
        'canEdit' => $canEditWhatsApp,
    ];
@endphp

@section('admin-content')
    <div class="page-body">
        <div class="row row-cards">
            <div class="col-12">
                <form id="whatsapp-settings-form" action="{{ route('admin.settings.store') }}" class="form-submit" method="post">
                    @csrf
                    <input type="hidden" name="type" value="whatsapp">
                    <input type="hidden" name="redirect_url" value="{{ route('admin.settings.show', ['setting' => 'whatsapp']) }}">

                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">{{ __('labels.whatsapp_settings') }}</h3>
                                <x-breadcrumb :items="$breadcrumbs" />
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info" role="note">
                                <div class="d-flex">
                                    <i class="ti ti-info-circle me-2"></i>
                                    <div>Enter your Meta Cloud API credentials. Create and approve message templates in Meta before selecting them below. Delivery callbacks process status only; inbound customer messages are ignored and not stored or answered.</div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="whatsapp-phone-number-id">Phone Number ID</label>
                                    <input class="form-control" id="whatsapp-phone-number-id" name="phoneNumberId" value="{{ old('phoneNumberId', $settings['phoneNumberId'] ?? '') }}" autocomplete="off" @disabled(!$canEditWhatsApp)>
                                    @error('phoneNumberId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="whatsapp-business-account-id">WhatsApp Business Account ID</label>
                                    <input class="form-control" id="whatsapp-business-account-id" name="businessAccountId" value="{{ old('businessAccountId', $settings['businessAccountId'] ?? '') }}" autocomplete="off" @disabled(!$canEditWhatsApp)>
                                    @error('businessAccountId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="whatsapp-access-token">Access Token</label>
                                    <input class="form-control" id="whatsapp-access-token" name="accessToken" type="password" value="" placeholder="{{ $hasAccessToken ? 'Saved securely; leave blank to keep the current token' : 'Paste your Meta access token' }}" autocomplete="new-password" @disabled(!$canEditWhatsApp)>
                                    @error('accessToken')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="whatsapp-default-language">Default Template Language</label>
                                    <input class="form-control" id="whatsapp-default-language" name="defaultLanguage" value="{{ old('defaultLanguage', $settings['defaultLanguage'] ?? 'en_US') }}" placeholder="en_US" @disabled(!$canEditWhatsApp)>
                                    @error('defaultLanguage')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="whatsapp-graph-version">Graph API Version</label>
                                    <input class="form-control" id="whatsapp-graph-version" name="graphApiVersion" value="{{ old('graphApiVersion', $settings['graphApiVersion'] ?? 'v26.0') }}" placeholder="v26.0" @disabled(!$canEditWhatsApp)>
                                    @error('graphApiVersion')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            @can('viewSetting', [\App\Models\Setting::class, 'whatsapp'])
                                <div class="border-top mt-4 pt-4">
                                    <h4 class="mb-3">WhatsApp Delivery Status Callback</h4>
                                    <div class="alert alert-info">This callback records statuses for messages sent by this app. Meta may also POST inbound customer message events to this callback because it uses the same subscription; this app ignores and does not store or answer them.</div>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label" for="whatsapp-webhook-callback-url">Callback URL</label>
                                            <input class="form-control" id="whatsapp-webhook-callback-url" value="{{ route('whatsapp.webhook.verify') }}" readonly>
                                            <div class="form-hint">Configure this URL in the Meta Developer Dashboard under Webhooks, then subscribe to the WhatsApp Business Account’s messages field.</div>
                                        </div>
                                        @can('updateSetting', [\App\Models\Setting::class, 'whatsapp'])
                                            <div class="col-12">
                                                <label class="form-label" for="whatsapp-webhook-verify-token">Webhook Verify Token</label>
                                                <input class="form-control" id="whatsapp-webhook-verify-token" name="webhookVerifyToken" type="password" value="" placeholder="{{ $hasWebhookVerifyToken ? 'Saved securely; leave blank to keep the current token' : 'Enter a private token and use the same value in Meta' }}" autocomplete="new-password">
                                                <div class="form-hint">Save this value in WhatsApp settings and enter the same value as the Verify Token in Meta.</div>
                                                @error('webhookVerifyToken')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                            </div>
                                        @endcan
                                    </div>
                                </div>
                            @endcan
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between flex-wrap gap-2">
                                <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-secondary">{{ __('labels.cancel') }}</a>
                                @can('updateSetting', [\App\Models\Setting::class, 'whatsapp'])
                                    <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>{{ __('labels.save') }}</button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </form>

                @can('viewSetting', [\App\Models\Setting::class, 'whatsapp'])
                    <div class="card mt-4">
                        <div class="card-header flex-wrap gap-3">
                            <div>
                                <h3 class="card-title">Template Mapping</h3>
                                <div class="text-secondary">Enter the template created in Meta for each app event. Sync checks its current Meta approval status.</div>
                            </div>
                            @can('updateSetting', [\App\Models\Setting::class, 'whatsapp'])
                                <div class="card-actions d-flex flex-wrap gap-2">
                                    <button class="btn btn-outline-secondary" id="whatsapp-sync-templates" type="button" data-url="{{ route('admin.settings.whatsapp.sync-templates') }}">Sync Configured Statuses</button>
                                    <button class="btn btn-outline-primary" id="whatsapp-save-templates" type="button" data-url="{{ route('admin.settings.whatsapp.store-templates') }}">Save Templates</button>
                                </div>
                            @endcan
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info d-flex align-items-start gap-2">
                                <i class="ti ti-info-circle flex-shrink-0 mt-1" aria-hidden="true"></i>
                                <div class="flex-grow-1">
                                    Create and approve a positional template in Meta using the fixed variables shown for each app event. Enter its exact template name and language here.
                                </div>
                            </div>
                            <form id="whatsapp-mapping-form">
                                @csrf
                                <div class="table-responsive">
                                    <table class="table table-vcenter">
                                        <thead>
                                            <tr><th>App Event</th><th>Meta Template Name</th><th>Language</th><th>Format</th><th>Meta Status</th><th>App Variables</th><th>Sync</th></tr>
                                        </thead>
                                        <tbody id="whatsapp-mapping-rows">
                                            <tr data-whatsapp-empty-row><td colspan="7" class="text-center text-secondary">No app events are configured.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                            <script type="application/json" id="whatsapp-mapping-data">@json($whatsappMappingData)</script>
                        </div>
                    </div>

                    @can('updateSetting', [\App\Models\Setting::class, 'whatsapp'])
                    <div class="card mt-4">
                        <div class="card-header"><h3 class="card-title">Send Test Template</h3></div>
                        <div class="card-body">
                            <form id="whatsapp-test-form" action="{{ route('admin.settings.whatsapp.test-message') }}" data-status-url="{{ route('admin.settings.whatsapp.message-status') }}">
                                @csrf
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-5">
                                        <label class="form-label" for="whatsapp-test-recipient">Test Mobile</label>
                                        <input class="form-control" id="whatsapp-test-recipient" name="recipient" placeholder="919999999999" required>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label" for="whatsapp-test-template">Template</label>
                                        <select class="form-select" id="whatsapp-test-template" name="templateKey" required>
                                            <option value="">Save and sync an approved template first</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit"><i class="ti ti-brand-whatsapp me-1"></i>Send Test WhatsApp</button></div>
                                </div>
                            </form>
                            <div class="form-hint mt-2" id="whatsapp-test-status" role="status" aria-live="polite"></div>
                        </div>
                    </div>
                    @endcan
                @endcan
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/whatsapp-settings.js') }}" defer></script>
@endpush
