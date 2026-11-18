@extends('layouts.admin.app', ['page' => 'seller-landing', 'sub_page' => ''])

@section('title', __('labels.seller_landing_page'))

@section('header_data')
    @php
        $page_title = __('labels.seller_landing_page');
        $page_pretitle = __('labels.admin');
    @endphp
@endsection

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.seller_landing_page') }}</h2>
                <div class="text-muted mt-1">{{ __('labels.seller_landing_description') }}</div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <form action="{{ route('admin.seller-landing.store') }}" class="form-submit" method="post" enctype="multipart/form-data" id="sellerLandingForm">
                @csrf
                <input type="hidden" name="configuration" id="sellerLandingConfiguration">
                <template id="sellerLandingFieldInfoTemplate">
                    <x-field-info-button title="__HELP_TEXT__" />
                </template>
                <div id="sellerLandingBuilder"
                     data-configuration='@json($configuration)'
                     data-languages='@json($languages)'
                     data-variants='@json($variants)'
                     data-section-templates='@json($sectionTemplates)'
                     data-media-base="{{ url('storage') }}">
                    <div class="card mb-4">
                        <div class="card-header d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.seller_landing_seo') }}</h3>
                            <button type="button" class="btn btn-icon btn-sm btn-ghost-secondary ms-auto"
                                    data-bs-toggle="collapse" data-bs-target="#sellerLandingSeo"
                                    aria-expanded="true" aria-controls="sellerLandingSeo">
                                <i class="ti ti-chevron-up" data-collapse-icon></i>
                            </button>
                        </div>
                        <div class="collapse show" id="sellerLandingSeo">
                            <div class="card-body">
                                <div class="nav nav-tabs mb-3" data-role="languages"></div>
                                <div class="row g-3" data-role="seo"></div>
                                <div class="mt-3">
                                    <div class="d-flex align-items-center mb-1">
                                        <label class="form-label mb-0">Social sharing image</label>
                                        <x-field-info-button title="Upload the image shown when the seller landing page is shared. Keep the default enabled to reuse the hero image." />
                                    </div>
                                    <input type="file" name="seo_media[image]"
                                           accept="image/jpeg,image/png,image/webp"
                                           data-filepond-image
                                           data-image-url="{{ data_get($configuration, 'seoMedia.image', '') }}">
                                    <div class="d-flex align-items-center mt-2">
                                        <label class="form-check mb-0">
                                            <input type="hidden" name="clear_seo_media[image]" value="0">
                                            <input class="form-check-input" type="checkbox" name="clear_seo_media[image]"
                                                   value="1" data-clear-seo-media="image"
                                                   @checked(data_get($configuration, 'seoMediaSettings.imageUseDefault', blank(data_get($configuration, 'seoMedia.image'))))>
                                            <span class="form-check-label">Use the hero image</span>
                                        </label>
                                        <x-field-info-button title="When enabled, the storefront uses the current hero image instead of a separate social sharing image." />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h3 class="mb-1">{{ __('labels.seller_landing_sections') }}</h3>
                            <div class="text-muted">{{ __('labels.seller_landing_sections_help') }}</div>
                        </div>
                        @can('updateAny', \App\Models\LandingPage::class)
                            <div class="d-flex align-items-end gap-2">
                                <div>
                                    <div class="d-flex align-items-center mb-1">
                                        <label class="form-label mb-0">Section type</label>
                                        <x-field-info-button title="Choose the kind of marketing section to add to the seller landing page." />
                                    </div>
                                    <select class="form-select" data-role="new-section-type">
                                        <option value="benefits">Benefits</option>
                                        <option value="steps">Steps</option>
                                        <option value="testimonials">Testimonials</option>
                                        <option value="hero">Hero</option>
                                    </select>
                                </div>
                                <button type="button" class="btn btn-outline-primary" data-action="add-section">
                                    <i class="ti ti-plus me-1"></i>{{ __('labels.add_section') }}
                                </button>
                            </div>
                        @endcan
                    </div>
                    <div data-role="sections"></div>
                </div>
                @can('updateAny', \App\Models\LandingPage::class)
                    <div class="card">
                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-primary">{{ __('labels.save_changes') }}</button>
                        </div>
                    </div>
                @endcan
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/seller-landing-builder.js') }}?v={{ filemtime(public_path('assets/js/seller-landing-builder.js')) }}"></script>
@endpush
