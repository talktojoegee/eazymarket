<div class="tab-pane fade" id="auth-firebase" role="tabpanel" data-auth-section="firebase">
    <form action="{{ route('admin.settings.store') }}" method="post" enctype="multipart/form-data" class="form-submit auth-section-form" id="firebaseSettingsForm">
        @csrf
        <input type="hidden" name="type" value="authentication">
        <input type="hidden" name="section" value="firebase">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-xl-8">
                    <h3 class="card-title mb-2">{{ __('labels.firebase_client_configuration') }}</h3>
                    <p class="text-muted">{{ __('labels.firebase_client_configuration_hint') }}</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required" for="fireBaseApiKey">{{ __('labels.firebase_api_key') }}</label>
                            <input type="text" class="form-control" id="fireBaseApiKey" name="fireBaseApiKey" value="{{ $isDemoUser && !empty($settings['fireBaseApiKey']) ? '••••••••' : ($settings['fireBaseApiKey'] ?? '') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="fireBaseAuthDomain">{{ __('labels.firebase_auth_domain') }}</label>
                            <input type="text" class="form-control" id="fireBaseAuthDomain" name="fireBaseAuthDomain" value="{{ $settings['fireBaseAuthDomain'] ?? '' }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="fireBaseProjectId">{{ __('labels.firebase_project_id') }}</label>
                            <input type="text" class="form-control" id="fireBaseProjectId" name="fireBaseProjectId" value="{{ $settings['fireBaseProjectId'] ?? '' }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="fireBaseMessagingSenderId">{{ __('labels.firebase_messaging_sender_id') }}</label>
                            <input type="text" class="form-control" id="fireBaseMessagingSenderId" name="fireBaseMessagingSenderId" value="{{ $settings['fireBaseMessagingSenderId'] ?? '' }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="fireBaseAppId">{{ __('labels.firebase_app_id') }}</label>
                            <input type="text" class="form-control" id="fireBaseAppId" name="fireBaseAppId" value="{{ $settings['fireBaseAppId'] ?? '' }}" required>
                        </div>
                    </div>

                    <details class="mt-4">
                        <summary class="fw-semibold cursor-pointer">{{ __('labels.advanced_settings') }}</summary>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label" for="fireBaseDatabaseURL">{{ __('labels.firebase_database_url') }}</label>
                                <input type="url" class="form-control" id="fireBaseDatabaseURL" name="fireBaseDatabaseURL" value="{{ $settings['fireBaseDatabaseURL'] ?? '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="fireBaseStorageBucket">{{ __('labels.firebase_storage_bucket') }}</label>
                                <input type="text" class="form-control" id="fireBaseStorageBucket" name="fireBaseStorageBucket" value="{{ $settings['fireBaseStorageBucket'] ?? '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="fireBaseMeasurementId">{{ __('labels.firebase_measurement_id') }}</label>
                                <input type="text" class="form-control" id="fireBaseMeasurementId" name="fireBaseMeasurementId" value="{{ $settings['fireBaseMeasurementId'] ?? '' }}">
                            </div>
                        </div>
                    </details>
                </div>

                <div class="col-xl-4">
                    <div class="border rounded p-3 h-100">
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                            <div><h4 class="mb-1">{{ __('labels.firebase_server_credentials') }}</h4><div class="text-muted small">{{ __('labels.firebase_server_credentials_hint') }}</div></div>
                            <span class="badge {{ ($firebaseStatus['credentials']['configured'] ?? false) ? 'bg-success-lt text-success' : 'bg-warning-lt text-warning' }}">
                                {{ ($firebaseStatus['credentials']['configured'] ?? false) ? __('labels.configured') : __('labels.not_configured') }}
                            </span>
                        </div>
                        @if($firebaseStatus['credentials']['configured'] ?? false)
                            <dl class="row small mb-3">
                                <dt class="col-5">{{ __('labels.firebase_project_id') }}</dt><dd class="col-7 text-break">{{ $firebaseStatus['credentials']['project_id'] }}</dd>
                                <dt class="col-5">{{ __('labels.client_email') }}</dt><dd class="col-7 text-break">{{ $firebaseStatus['credentials']['client_email'] }}</dd>
                                <dt class="col-5">{{ __('labels.last_updated') }}</dt><dd class="col-7">{{ $firebaseStatus['credentials']['updated_at'] ? \Carbon\Carbon::parse($firebaseStatus['credentials']['updated_at'])->diffForHumans() : '—' }}</dd>
                            </dl>
                        @endif
                        <label class="form-label" for="serviceAccountFile">{{ ($firebaseStatus['credentials']['configured'] ?? false) ? __('labels.replace_service_account_file') : __('labels.service_account_file') }}</label>
                        <input type="file" class="filepond" id="serviceAccountFile" name="serviceAccountFile" accept="application/json,.json" data-max-file-size="1MB" data-max-files="1">
                        <div class="form-hint">{{ __('labels.firebase_service_account_upload_hint') }}</div>
                    </div>
                </div>
            </div>
        </div>
        @include('admin.settings.authentication.actions')
    </form>
</div>
