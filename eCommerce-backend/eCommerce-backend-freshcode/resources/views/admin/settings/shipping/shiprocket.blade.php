@php
    use App\Http\Controllers\Webhooks\ShiprocketWebhookController;
    use App\Models\Currency;use Illuminate\Support\Facades\Cache;use Illuminate\Support\Str;
    $webhookUrl = route('webhooks.shiprocket');
    $lastWebhook = Cache::get(ShiprocketWebhookController::LAST_RECEIVED_CACHE_KEY);
@endphp
@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => $menuAdmin['settings']['route']['shipping']['sub_active'] ?? ""])

@section('title', 'Shiprocket')

@section('header_data')
    @php
        $page_title = 'Shiprocket';
        $page_pretitle = __('labels.shipping_settings') ?? 'Shipping Settings';
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.shipping_settings') ?? 'Shipping Settings', 'url' => route('admin.settings.shipping.index')],
        ['title' => 'Shiprocket', 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">
                    <img src="{{ asset($provider['logo']) }}" alt="" width="28" height="28" class="rounded me-2">Shiprocket
                </h2>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            @if(!app()->environment('production'))
                @php $srTestMode = \App\Services\Shipping\Testing\FakeShiprocketClient::enabled(); @endphp
                <div class="card mb-4 {{ $srTestMode ? 'border-warning' : '' }}">
                    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <strong>{{ __('labels.shiprocket_test_mode') ?? 'Test mode (fake Shiprocket)' }}</strong>
                            <span class="badge {{ $srTestMode ? 'bg-yellow-lt text-yellow' : 'bg-secondary-lt' }} ms-2 text-uppercase">
                                {{ $srTestMode ? (__('labels.on') ?? 'On') : (__('labels.off') ?? 'Off') }}
                            </span>
                            <div class="text-secondary small mt-1">
                                {{ __('labels.shiprocket_test_mode_hint') ?? 'When on, every Shiprocket call across the panel is simulated — no real orders, no wallet charges. Not available in production.' }}
                            </div>
                        </div>
                        <div class="btn-list">
                            @if($srTestMode)
                                <button type="button" class="btn btn-outline-danger js-sr-test-mode" data-enable="0">
                                    {{ __('labels.shiprocket_test_mode_disable') ?? 'Disable — use real API' }}
                                </button>
                            @else
                                <button type="button" class="btn btn-outline-warning js-sr-test-mode" data-enable="1">
                                    {{ __('labels.shiprocket_test_mode_enable') ?? 'Enable test mode' }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post">
                @csrf
                <input type="hidden" name="type" value="shipping">

                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title">
                            <img src="{{ asset($provider['logo']) }}" alt="" width="24" height="24" class="rounded me-2">{{ __('labels.shiprocket') ?? 'Shiprocket' }}
                        </h4>
                    </div>
                    <div class="card-body">
                        {{-- Enable + display title --}}
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-check form-switch">
                                    <input type="hidden" name="shiprocketEnabled" value="0">
                                    <input class="form-check-input" type="checkbox" name="shiprocketEnabled" value="1"
                                        @checked($settings['shiprocketEnabled'] ?? false)>
                                    <span class="form-check-label">{{ __('labels.enable_shiprocket') ?? 'Enable Shiprocket' }}
                                        <i class="ti ti-help-circle text-secondary ms-1" style="cursor:help"
                                           data-bs-toggle="tooltip"
                                           title="{{ __('labels.tip_enable_shiprocket') }}"></i>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label
                                class="form-label">{{ __('labels.display_title') ?? 'Display title (shown to sellers)' }}
                                <i class="ti ti-help-circle text-secondary ms-1" style="cursor:help"
                                   data-bs-toggle="tooltip" title="{{ __('labels.tip_display_title') }}"></i>
                            </label>
                            <input type="text" class="form-control" name="shiprocketTitle" maxlength="60"
                                   placeholder="Easy Ship" value="{{ $settings['shiprocketTitle'] ?? 'Easy Ship' }}">
                            <small
                                class="text-secondary">{{ __('labels.shiprocket_title_hint') ?? 'Sellers see this label instead of "shiprocket" when choosing a shipping method.' }}</small>
                        </div>

                        {{-- Credentials --}}
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('labels.email') }}
                                    <i class="ti ti-help-circle text-secondary ms-1" style="cursor:help"
                                       data-bs-toggle="tooltip" title="{{ __('labels.tip_shiprocket_email') }}"></i>
                                </label>
                                <input type="email" class="form-control" name="shiprocketEmail"
                                       value="{{ $settings['shiprocketEmail'] ?? '' }}"
                                       placeholder="api-user@yourstore.com">
                                <small
                                    class="text-secondary">{{ __('labels.shiprocket_api_user_hint') ?? 'Use a Shiprocket API User (Settings → API → Add New API User), not your login.' }}</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('labels.password') }}
                                    <i class="ti ti-help-circle text-secondary ms-1" style="cursor:help"
                                       data-bs-toggle="tooltip" title="{{ __('labels.tip_shiprocket_password') }}"></i>
                                </label>
                                <div class="input-group">
                                    <div class="input-group input-group-flat">
                                        <input type="password" class="form-control" name="shiprocketPassword"
                                               value="{{ $isDemoUser && !empty($settings['shiprocketPassword']) ? '••••••••' : ($settings['shiprocketPassword'] ?? '') }}"
                                               autocomplete="off" id="password"/>
                                        <span class="input-group-text">
                                        <a class="link-secondary" title="Show password" id="password-toggle"
                                           data-bs-toggle="tooltip" data-panel-password-toggle="password">
                                            Show
                                          </a>
                                    </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('labels.channel_id') ?? 'Channel ID (optional)' }}
                                    <i class="ti ti-help-circle text-secondary ms-1" style="cursor:help"
                                       data-bs-toggle="tooltip" title="{{ __('labels.tip_channel_id') }}"></i>
                                </label>
                                <input type="text" class="form-control" name="shiprocketChannelId"
                                       value="{{ $settings['shiprocketChannelId'] ?? '' }}">
                            </div>
                        </div>

                        {{-- Test connection --}}
                        <div class="mb-2">
                            <button type="button" class="btn btn-outline-primary" id="btnTestShiprocket">
                                <i class="ti ti-plug-connected me-1"></i>{{ __('labels.test_connection') ?? 'Test connection' }}
                            </button>
                            <span id="shiprocketTestResult" class="ms-2 small"></span>
                        </div>
                    </div>
                </div>

                {{-- Rate & pricing --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title"><i class="ti ti-cash me-1"></i>{{ __('labels.shiprocket_rate_pricing') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('labels.shiprocket_rate_mode') }}
                                    <i class="ti ti-help-circle text-secondary ms-1" style="cursor:help"
                                       data-bs-toggle="tooltip" title="{{ __('labels.tip_shiprocket_rate_mode') }}"></i>
                                </label>
                                <select class="form-select" name="shiprocketRateMode" id="shiprocketRateMode">
                                    @foreach(['carrier_calculated', 'flat', 'free'] as $m)
                                        <option
                                            value="{{ $m }}" @selected(($settings['shiprocketRateMode'] ?? 'carrier_calculated') === $m)>{{ __('labels.shiprocket_rate_mode_' . $m) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3" id="shiprocketFlatRateWrap">
                                <label class="form-label">{{ __('labels.shiprocket_flat_rate') }}</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="shiprocketFlatRate"
                                       value="{{ $settings['shiprocketFlatRate'] ?? 0 }}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('labels.shiprocket_free_above') }}
                                    <i class="ti ti-help-circle text-secondary ms-1" style="cursor:help"
                                       data-bs-toggle="tooltip"
                                       title="{{ __('labels.tip_shiprocket_free_above') }}"></i>
                                </label>
                                <div class="input-group">
                                    <span
                                        class="input-group-text">{{ optional(Currency::query()->where('is_base', true)->first())->symbol }}</span>
                                    <input type="number" step="0.01" min="0" class="form-control"
                                           name="shiprocketFreeAbove"
                                           value="{{ $settings['shiprocketFreeAbove'] ?? 0 }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Parcel defaults + safety --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title"><i
                                class="ti ti-package me-1"></i>{{ __('labels.shiprocket_parcel_defaults') }}</h4>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary small">{{ __('labels.shiprocket_parcel_defaults_hint') }}</p>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">{{ __('labels.shiprocket_default_weight') }}</label>
                                <input type="number" step="0.01" min="0" class="form-control"
                                       name="shiprocketDefaultWeight"
                                       value="{{ $settings['shiprocketDefaultWeight'] ?? 0.5 }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">{{ __('labels.length_cm') }}</label>
                                <input type="number" step="0.01" min="0" class="form-control"
                                       name="shiprocketDefaultLength"
                                       value="{{ $settings['shiprocketDefaultLength'] ?? 10 }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">{{ __('labels.breadth_cm') }}</label>
                                <input type="number" step="0.01" min="0" class="form-control"
                                       name="shiprocketDefaultBreadth"
                                       value="{{ $settings['shiprocketDefaultBreadth'] ?? 10 }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">{{ __('labels.height_cm') }}</label>
                                <input type="number" step="0.01" min="0" class="form-control"
                                       name="shiprocketDefaultHeight"
                                       value="{{ $settings['shiprocketDefaultHeight'] ?? 10 }}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('labels.shiprocket_wallet_alert') }}
                                    <i class="ti ti-help-circle text-secondary ms-1" style="cursor:help"
                                       data-bs-toggle="tooltip"
                                       title="{{ __('labels.tip_shiprocket_wallet_alert') }}"></i>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" min="0" class="form-control"
                                           name="shiprocketWalletAlert"
                                           value="{{ $settings['shiprocketWalletAlert'] ?? 0 }}">
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                {{-- Webhook (configured on Shiprocket's side) --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title"><i
                                class="ti ti-webhook me-1"></i>{{ __('labels.tracking_webhook') ?? 'Tracking webhook' }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary">{{ __('labels.shiprocket_webhook_hint') ?? 'Paste this URL and token into Shiprocket → Settings → API → Webhooks (Shipment Webhook Settings), then use their "Test Webhook" button.' }}</p>
                        <div
                            class="alert {{ Str::startsWith($webhookUrl, 'https://') ? 'alert-info' : 'alert-warning' }} d-flex"
                            role="alert">
                            <i class="ti ti-alert-triangle me-2 mt-1"></i>
                            <div>{{ __('labels.shiprocket_webhook_url_note') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('labels.webhook_url') ?? 'Webhook URL' }}
                                <i class="ti ti-help-circle text-secondary ms-1" style="cursor:help"
                                   data-bs-toggle="tooltip" title="{{ __('labels.tip_webhook_url') }}"></i>
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control user-select-all" id="shiprocketWebhookUrl"
                                       value="{{ $webhookUrl }}" readonly>
                                <button type="button" class="btn btn-outline-secondary"
                                        onclick="navigator.clipboard.writeText(document.getElementById('shiprocketWebhookUrl').value)">
                                    <i class="ti ti-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('labels.webhook_token') ?? 'Webhook token (x-api-key)' }}
                                <i class="ti ti-help-circle text-secondary ms-1" style="cursor:help"
                                   data-bs-toggle="tooltip" title="{{ __('labels.tip_webhook_token') }}"></i>
                            </label>
                            <input type="text" class="form-control" name="shiprocketWebhookSecret"
                                   value="{{ $isDemoUser && !empty($settings['shiprocketWebhookSecret']) ? '••••••••' : ($settings['shiprocketWebhookSecret'] ?? '') }}"
                                   placeholder="{{ __('labels.webhook_token_placeholder') ?? 'A secret you set here and paste into Shiprocket' }}">
                        </div>
                        <div class="text-secondary small">
                            <i class="ti ti-clock me-1"></i>{{ __('labels.last_webhook_received') ?? 'Last webhook received' }}
                            :
                            <strong>{{ $lastWebhook ?? (__('labels.none_yet') ?? 'none yet') }}</strong>
                        </div>
                    </div>
                </div>

                <div class="card-footer text-end">
                    <div class="d-flex">
                        @can('updateSetting', [\App\Models\Setting::class, 'shipping'])
                            <button type="submit" class="btn btn-primary ms-auto">{{ __('labels.submit') }}</button>
                        @endcan
                    </div>
                </div>
            </form>
            <div class="card mt-4">
                <div class="card-header" role="button" data-bs-toggle="collapse" data-bs-target="#shiprocketGuide"
                     aria-expanded="true">
                    <h4 class="card-title"><i
                            class="ti ti-help-circle me-1"></i>{{ __('labels.shiprocket_setup_guide') ?? 'Setup guide' }}
                    </h4>
                    <div class="card-actions"><i class="ti ti-chevron-down"></i></div>
                </div>
                <div class="collapse show" id="shiprocketGuide">
                    <div class="card-body">
                        <p class="text-secondary">{{ __('labels.shiprocket_setup_guide_intro') }}</p>
                        <div class="row g-4">
                            @foreach([
                                ['title' => 'shiprocket_guide_step1_title', 'body' => 'shiprocket_guide_step1_body', 'img' => 'shiprocket-s1.png'],
                                ['title' => 'shiprocket_guide_step2_title', 'body' => 'shiprocket_guide_step2_body', 'img' => 'shiprocket-s2.png'],
                                ['title' => 'shiprocket_guide_step3_title', 'body' => 'shiprocket_guide_step3_body', 'img' => 'shiprocket-s3.png'],
                            ] as $step)
                                <div class="col-md-4">
                                    <h5 class="mb-2">{{ __('labels.' . $step['title']) }}</h5>
                                    <p class="text-secondary small">{{ __('labels.' . $step['body']) }}</p>
                                    <a href="{{ asset('assets/images/guide/' . $step['img']) }}"
                                       data-fslightbox="shiprocket-guide"
                                       class="d-block border rounded overflow-hidden position-relative">
                                        <img src="{{ asset('assets/images/guide/' . $step['img']) }}"
                                             alt="{{ __('labels.' . $step['title']) }}"
                                             class="img-fluid w-100" style="object-fit:cover;max-height:200px"
                                             loading="lazy">
                                        <span class="badge bg-opacity-75 position-absolute bottom-0 end-0 m-2">
                                            <i class="ti ti-zoom-in me-1"></i>{{ __('labels.shiprocket_guide_zoom') ?? 'Click to enlarge' }}
                                        </span>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                if (window.bootstrap && !bootstrap.Tooltip.getInstance(el)) {
                    new bootstrap.Tooltip(el);
                }
            });

            const rateMode = document.getElementById('shiprocketRateMode');
            const flatWrap = document.getElementById('shiprocketFlatRateWrap');
            if (rateMode && flatWrap) {
                const syncFlat = function () {
                    flatWrap.style.display = rateMode.value === 'flat' ? '' : 'none';
                };
                rateMode.addEventListener('change', syncFlat);
                syncFlat();
            }

            document.querySelectorAll('.js-sr-test-mode').forEach(function (toggleBtn) {
                toggleBtn.addEventListener('click', async function () {
                    toggleBtn.disabled = true;
                    try {
                        const res = await axios.post(@json(route('admin.settings.shipping.shiprocket-test-mode')), {
                            enable: toggleBtn.getAttribute('data-enable') === '1',
                            reset: toggleBtn.getAttribute('data-enable') === '1',
                        });
                        Toast.fire({ icon: res.data?.success ? 'success' : 'error', title: res.data?.message ?? '' });
                        if (res.data?.success) setTimeout(() => window.location.reload(), 700);
                        else toggleBtn.disabled = false;
                    } catch (e) {
                        Toast.fire({ icon: 'error', title: e.response?.data?.message ?? @json(__('labels.something_went_wrong')) });
                        toggleBtn.disabled = false;
                    }
                });
            });

            const btn = document.getElementById('btnTestShiprocket');
            if (!btn) return;
            btn.addEventListener('click', async function () {
                const out = document.getElementById('shiprocketTestResult');
                btn.disabled = true;
                out.textContent = @json(__('labels.testing') ?? 'Testing…');
                out.className = 'ms-2 small text-secondary';
                try {
                    const res = await axios.post(@json(route('admin.settings.shipping.test-connection')), {
                        shiprocketEmail: document.querySelector('[name="shiprocketEmail"]').value,
                        shiprocketPassword: document.querySelector('[name="shiprocketPassword"]').value,
                    });
                    const ok = res.data?.success ?? false;
                    out.textContent = res.data?.message ?? '';
                    out.className = 'ms-2 small ' + (ok ? 'text-success' : 'text-danger');
                } catch (e) {
                    out.textContent = e.response?.data?.message ?? @json(__('labels.something_went_wrong'));
                    out.className = 'ms-2 small text-danger';
                }
                btn.disabled = false;
            });
        });
    </script>
@endpush
