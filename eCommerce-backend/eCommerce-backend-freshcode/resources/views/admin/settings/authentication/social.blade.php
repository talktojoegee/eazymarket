<div class="tab-pane fade" id="auth-social" role="tabpanel" data-auth-section="social">
    <form action="{{ route('admin.settings.store') }}" method="post" class="form-submit auth-section-form">
        @csrf
        <input type="hidden" name="type" value="authentication">
        <input type="hidden" name="section" value="social">
        <input type="hidden" name="googleLogin" value="0">
        <input type="hidden" name="appleLogin" value="0">
        <div class="card-body">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                <div><h3 class="card-title mb-1">{{ __('labels.social_login_providers') }}</h3><p class="text-muted mb-0">{{ __('labels.social_login_providers_hint') }}</p></div>
                <a href="#firebase" data-auth-tab-link="firebase" class="btn btn-outline-secondary btn-sm">{{ __('labels.review_firebase_setup') }}</a>
            </div>
            <div class="row g-3">
                @foreach([
                    'googleLogin' => ['Google', 'ti-brand-google', __('labels.google_login_hint')],
                    'appleLogin' => ['Apple', 'ti-brand-apple', __('labels.apple_login_hint')],
                ] as $field => [$name, $icon, $hint])
                    <div class="col-lg-6">
                        <div class="card card-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div class="d-flex gap-3"><i class="ti {{ $icon }} fs-1"></i><div><h4 class="mb-1">{{ $name }}</h4><div class="text-muted">{{ $hint }}</div></div></div>
                                    <label class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="{{ $field }}" value="1" @checked(!empty($settings[$field]))>
                                    </label>
                                </div>
                                @unless($firebaseStatus['social_ready'] ?? false)
                                    <div class="text-warning small mt-3"><i class="ti ti-alert-triangle me-1"></i>{{ __('labels.firebase_setup_required_before_enabling') }}</div>
                                @endunless
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @include('admin.settings.authentication.actions')
    </form>
</div>
