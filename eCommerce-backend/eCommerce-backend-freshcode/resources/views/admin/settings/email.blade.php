@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => $menuAdmin['settings']['route']['email']['sub_active'] ?? "" ])

@section('title', __('labels.email_settings'))

@section('header_data')
    @php
        $page_title = __('labels.email_settings');
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.email_settings'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.email_settings') }}</h2>
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
                            <a class="nav-link" href="#pills-smtp">{{ __('labels.smtp') }}</a>
                            <a class="nav-link" href="#pills-test-smtp">{{ __('labels.test_smtp') }}</a>
                            <a class="nav-link" href="#pills-smtp-guide">{{ __('labels.smtp_setup_guide') }}</a>
                        </nav>
                    </div>
                </div>
                <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                    <div class="row row-cards">
                        <div class="col-12">
                            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post">
                                @csrf
                                <input type="hidden" name="type" value="email">
                                <div class="card mb-4" id="pills-smtp">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.smtp') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.smtp_host') }}</label>
                                            <input type="text" class="form-control" name="smtpHost"
                                                   placeholder="{{ __('labels.smtp_host_placeholder') }}"
                                                   value="{{ $settings['smtpHost'] ?? '' }}" required/>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.smtp_port') }}</label>
                                            <input type="number" class="form-control" name="smtpPort"
                                                   placeholder="{{ __('labels.smtp_port_placeholder') }}"
                                                   value="{{ $settings['smtpPort'] ?? '' }}" required/>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.smtp_email') }}</label>
                                            <input type="email" class="form-control" name="smtpEmail"
                                                   placeholder="{{ __('labels.smtp_email_placeholder') }}"
                                                   value="{{ $isDemoUser ? Str::mask(($settings['smtpEmail'] ?? '****'), '****', 3, 8) : ($settings['smtpEmail'] ?? '') }}"
                                                   required/>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.smtp_password') }}</label>
                                            <div class="input-group input-group-flat">
                                                <input type="password" class="form-control" name="smtpPassword"
                                                       placeholder="{{ __('labels.smtp_password_placeholder') }}"
                                                       autocomplete="off" id="password"
                                                       value="{{ $isDemoUser && !empty($settings['smtpPassword']) ? '••••••••' : ($settings['smtpPassword'] ?? '') }}"/>
                                                <span class="input-group-text">
                                                    <a class="link-secondary" title="Show password" id="password-toggle"
                                                       data-bs-toggle="tooltip" data-panel-password-toggle="password">
                                                        <!-- Download SVG icon from http://tabler.io/icons/icon/eye -->
                                                        Show
                                                    </a>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label required">{{ __('labels.smtp_encryption') }}</label>
                                            <select class="form-select" name="smtpEncryption" required>
                                                <option value=""
                                                        disabled {{ !isset($settings['smtpEncryption']) ? 'selected' : '' }}>{{ __('labels.smtp_encryption_placeholder') }}</option>
                                                <option
                                                    value="tls" {{ isset($settings['smtpEncryption']) && $settings['smtpEncryption'] === 'tls' ? 'selected' : '' }}>
                                                    TLS
                                                </option>
                                                <option
                                                    value="ssl" {{ isset($settings['smtpEncryption']) && $settings['smtpEncryption'] === 'ssl' ? 'selected' : '' }}>
                                                    SSL
                                                </option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label required">{{ __('labels.smtp_content_type') }}</label>
                                            <select class="form-select" name="smtpContentType" required>
                                                <option value=""
                                                        disabled {{ !isset($settings['smtpContentType']) ? 'selected' : '' }}>{{ __('labels.smtp_content_type_placeholder') }}</option>
                                                <option
                                                    value="text" {{ isset($settings['smtpContentType']) && $settings['smtpContentType'] === 'text' ? 'selected' : '' }}>
                                                    Text
                                                </option>
                                                <option
                                                    value="html" {{ isset($settings['smtpContentType']) && $settings['smtpContentType'] === 'html' ? 'selected' : '' }}>
                                                    HTML
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4" id="pills-test-smtp">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.test_smtp') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">{{ __('labels.test_smtp_hint') }}</p>
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-7">
                                                <label class="form-label"
                                                       for="testSmtpEmail">{{ __('labels.test_smtp_email_label') }}</label>
                                                <input type="email" id="testSmtpEmail" class="form-control"
                                                       placeholder="{{ __('labels.test_smtp_email_placeholder') }}"/>
                                            </div>
                                            <div class="col-md-5">
                                                <button type="button" id="testSmtpButton"
                                                        class="btn btn-outline-primary w-100"
                                                        data-endpoint="{{ route('admin.settings.email.test-smtp') }}"
                                                        data-missing-email="{{ __('labels.test_smtp_email_required') }}"
                                                        data-success-title="{{ __('labels.test_smtp_success') }}"
                                                        data-failed-title="{{ __('labels.test_smtp_failed') }}">
                                                    <span>{{ __('labels.test_smtp_send_button') }}</span>
                                                    <span class="spinner-border spinner-border-sm ms-2 d-none"
                                                          role="status" aria-hidden="true"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-end">
                                    <div class="d-flex">
                                        @can('updateSetting', [\App\Models\Setting::class, 'email'])
                                            <button type="submit"
                                                    class="btn btn-primary ms-auto">{{ __('labels.submit') }}</button>
                                        @endcan
                                    </div>
                                </div>
                            </form>

                            <div class="card my-4" id="pills-smtp-guide">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="20" height="20"
                                             viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                             stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <path
                                                d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10z"/>
                                            <path d="M3 7l9 6l9 -6"/>
                                        </svg>
                                        {{ __('labels.smtp_gmail_guide_title') }}
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted">{{ __('labels.smtp_gmail_guide_intro') }}</p>
                                    <ol class="ps-3 mb-3">
                                        <li class="mb-1">{{ __('labels.smtp_gmail_guide_step_1') }}</li>
                                        <li class="mb-1">{{ __('labels.smtp_gmail_guide_step_2') }}</li>
                                        <li class="mb-1"><code>{{ __('labels.smtp_gmail_guide_step_3') }}</code></li>
                                        <li class="mb-1">{{ __('labels.smtp_gmail_guide_step_4') }}</li>
                                        <li class="mb-1">{{ __('labels.smtp_gmail_guide_step_5') }}</li>
                                        <li class="mb-1">{{ __('labels.smtp_gmail_guide_step_6') }}</li>
                                        <li class="mb-1">{{ __('labels.smtp_gmail_guide_step_7') }}</li>
                                    </ol>
                                    <a href="https://myaccount.google.com/apppasswords" target="_blank"
                                       rel="noopener noreferrer"
                                       class="btn btn-outline-primary btn-sm">
                                        {{ __('labels.smtp_gmail_guide_open_app_passwords') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/email-settings.js') }}" defer></script>
@endpush
