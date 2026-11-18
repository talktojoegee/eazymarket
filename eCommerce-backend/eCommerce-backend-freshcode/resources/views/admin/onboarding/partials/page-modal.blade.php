<div class="modal modal-blur fade onboarding-page-modal" id="onboarding-page-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form class="form-submit" id="onboarding-page-form" action="{{ route('admin.onboarding.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="surface" id="onboarding-surface" value="customer_app">
                <div class="modal-header">
                    <h5 class="modal-title" id="onboarding-page-modal-title">{{ __('labels.create_onboarding_page') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-12 col-lg-7">
                            <div class="mb-3">
                                <label class="form-label" for="onboarding-title">{{ __('labels.title') }}</label>
                                <input class="form-control" id="onboarding-title" name="title" maxlength="30">
                                <div class="form-hint">{{ __('labels.onboarding_title_hint') }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="onboarding-subtitle">{{ __('labels.subtitle') }}</label>
                                <textarea class="form-control" id="onboarding-subtitle" name="subtitle" rows="4" maxlength="50"></textarea>
                                <div class="form-hint">{{ __('labels.onboarding_subtitle_hint') }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" id="onboarding-image-label" for="onboarding-image">{{ __('labels.image') }}</label>
                                <input class="form-control" id="onboarding-image" name="onboarding_image" type="file" accept="image/jpeg,image/png,image/webp">
                                <div class="form-hint">{{ __('labels.onboarding_image_hint') }}</div>
                                <img id="onboarding-current-image" class="img-thumbnail mt-2 d-none" alt="{{ __('labels.current_image') }}" style="max-height: 160px">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="onboarding-font-color">{{ __('labels.font_color') }}</label>
                                <div class="input-group">
                                    <input class="form-control form-control-color" id="onboarding-font-color" name="font_color" type="color" value="#172033">
                                    <input class="form-control font-monospace" id="onboarding-font-color-text" value="#172033" maxlength="7" aria-label="{{ __('labels.font_color') }} hex value">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="onboarding-next-button-background-color">{{ __('labels.next_button_background_color') }}</label>
                                <input class="form-control form-control-color" id="onboarding-next-button-background-color" name="next_button_background_color" type="color" value="#ffb511">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="onboarding-next-button-text-color">{{ __('labels.next_button_text_color') }}</label>
                                <input class="form-control form-control-color" id="onboarding-next-button-text-color" name="next_button_text_color" type="color" value="#ffffff">
                            </div>
                            <div class="mb-3">
                                <label class="form-label required">{{ __('labels.background_type') }}</label>
                                <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column flex-sm-row mb-3" role="radiogroup" aria-label="{{ __('labels.background_type') }}">
                                    <label class="form-selectgroup-item flex-fill">
                                        <input class="form-selectgroup-input" type="radio" name="background_type" value="color" checked>
                                        <span class="form-selectgroup-label">{{ __('labels.color') }}</span>
                                    </label>
                                    <label class="form-selectgroup-item flex-fill">
                                        <input class="form-selectgroup-input" type="radio" name="background_type" value="image">
                                        <span class="form-selectgroup-label">{{ __('labels.image') }}</span>
                                    </label>
                                </div>
                                <div id="onboarding-background-color-field">
                                    <label class="form-label required" for="onboarding-background-color">{{ __('labels.background_color') }}</label>
                                    <div class="input-group">
                                        <input class="form-control form-control-color" id="onboarding-background-color" name="background_color" type="color" value="#ffffff">
                                        <input class="form-control font-monospace" id="onboarding-background-color-text" value="#ffffff" maxlength="7" aria-label="{{ __('labels.background_color') }} hex value">
                                    </div>
                                </div>
                                <div id="onboarding-background-image-field" class="d-none">
                                    <label class="form-label" id="onboarding-background-image-label" for="onboarding-background-image">{{ __('labels.background_image') }}</label>
                                    <input class="form-control" id="onboarding-background-image" name="background_image" type="file" accept="image/jpeg,image/png,image/webp">
                                    <div class="form-hint">{{ __('labels.onboarding_background_image_hint') }}</div>
                                    <img id="onboarding-current-background-image" class="img-thumbnail mt-2 d-none" alt="{{ __('labels.current_background_image') }}" style="max-height: 160px">
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-5">
                            <section class="onboarding-modal-preview" aria-label="{{ __('labels.mobile_preview') }}">
                                <h6 class="text-center mb-3">{{ __('labels.mobile_preview') }}</h6>
                                <div class="onboarding-phone-frame onboarding-modal-phone-frame mx-auto">
                                    <div class="onboarding-phone-screen" id="onboarding-modal-preview-screen">
                                        <img class="onboarding-phone-background d-none" id="onboarding-modal-preview-background" alt="">
                                        <div class="onboarding-phone-status" aria-hidden="true">
                                            <span>9:41</span>
                                            <span class="onboarding-phone-status-icons"><span class="onboarding-phone-signal"><i></i><i></i><i></i><i></i></span><span class="onboarding-phone-wifi">⌁</span><span class="onboarding-phone-battery"><i></i></span></span>
                                        </div>
                                        <div class="onboarding-iphone-dynamic-island" aria-hidden="true"></div>
                                        <div class="onboarding-phone-skip-row"><span id="onboarding-modal-preview-skip">{{ __('labels.onboarding_preview_skip') }}</span></div>
                                        <div class="onboarding-phone-illustration">
                                            <img id="onboarding-modal-preview-image" class="d-none rounded-8" alt="">
                                        </div>
                                        <div class="onboarding-phone-copy">
                                            <h4 id="onboarding-modal-preview-title"></h4>
                                            <p id="onboarding-modal-preview-subtitle"></p>
                                        </div>
                                        <div class="onboarding-phone-bottom">
                                            <div class="onboarding-phone-page-dots"><span class="onboarding-phone-page-dot is-active"></span></div>
                                            <button type="button" class="btn btn-primary" id="onboarding-modal-preview-next" disabled>{{ __('labels.next') }}</button>
                                        </div>
                                        <div class="onboarding-iphone-home-indicator" aria-hidden="true"></div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" id="onboarding-page-submit">
                        <i class="ti ti-device-floppy icon" aria-hidden="true"></i>
                        <span id="onboarding-page-submit-label">{{ __('labels.create_onboarding_page') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
