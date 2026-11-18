@extends('layouts.admin.app', ['page' => $menuAdmin['landing_page_builder']['active'] ?? 'landing-page-builder'])

@section('title', $pageTitle)

@section('header_data')
    @php
        $page_title = $pageTitle;
        $page_pretitle = __('labels.menu_section_marketing');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.landing_page_builder'), 'url' => route('admin.landing-page-builder.index')],
        ['title' => $pageTitle, 'url' => null],
    ];
    $isExistingDocument = $document->exists;
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <form action="{{ $isExistingDocument ? route('admin.landing-page-builder.update', $document) : route('admin.landing-page-builder.store') }}"
                  method="post" class="form-submit">
                @csrf
                @if ($isExistingDocument)
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">{{ $pageTitle }}</h3>
                            <x-breadcrumb :items="$breadcrumbs" />
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-4">
                            <div class="col-12 col-lg-6">
                                <label class="form-label required" for="landing-page-title">{{ __('labels.title') }}</label>
                                <input id="landing-page-title" name="title" type="text" class="form-control"
                                       value="{{ old('title', $document->title) }}" maxlength="150" required @readonly(! $canEdit)>
                                @error('title')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-lg-6">
                                <label class="form-label required" for="landing-page-slug">{{ __('labels.url_slug') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $landingPageBaseUrl }}</span>
                                    <input id="landing-page-slug" name="slug" type="text" class="form-control"
                                           value="{{ old('slug', $document->slug) }}" maxlength="180" pattern="[A-Za-z0-9_-]+" required @readonly(! $canEdit)>
                                </div>
                                @error('slug')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-lg-6">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="show_header_footer" value="0" @disabled(! $canEdit)>
                                    <input id="landing-page-show-header-footer" name="show_header_footer" type="checkbox" class="form-check-input"
                                           value="1" @checked(old('show_header_footer', $document->show_header_footer ?? true)) @disabled(! $canEdit)>
                                    <label class="form-check-label" for="landing-page-show-header-footer">{{ __('labels.show_site_header_footer') }}</label>
                                </div>
                                @error('show_header_footer')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-lg-6">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0" @disabled(! $canEdit)>
                                    <input id="landing-page-is-active" name="is_active" type="checkbox" class="form-check-input"
                                           value="1" @checked(old('is_active', $document->is_active ?? true)) @disabled(! $canEdit)>
                                    <label class="form-check-label" for="landing-page-is-active">{{ __('labels.active') }}</label>
                                </div>
                                @error('is_active')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        @unless ($canEdit)
                            <div class="alert alert-info" role="status">{{ __('labels.landing_page_builder_read_only') }}</div>
                        @endunless
                        <p class="text-secondary">{{ __('labels.landing_page_builder_description') }}</p>
                        <x-grapesjs-editor
                            name="page_content"
                            :html="old('page_content.html', $document->html ?? $starterHtml)"
                            :css="old('page_content.css', $document->css ?? $starterCss)"
                            :project-data="old('page_content.project', $document->project_data)"
                            :document-id="$document->exists ? $document->id : null"
                            :asset-token="old('page_content.asset_token')"
                            height="75vh"
                            :read-only="! $canEdit"
                        />
                        @if ($canEdit)
                            <p class="text-secondary mt-2">{{ __('labels.landing_page_media_cleanup_note') }}</p>
                        @endif
                        @error('page_content.html')
                            <div class="text-danger mt-2">{{ $message }}</div>
                        @enderror
                        @error('page_content.css')
                            <div class="text-danger mt-2">{{ $message }}</div>
                        @enderror
                        @error('page_content.project')
                            <div class="text-danger mt-2">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="card-footer d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.landing-page-builder.index') }}" class="btn btn-outline-secondary">{{ __('labels.cancel') }}</a>
                        @if ($canEdit)
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1" aria-hidden="true"></i>{{ $isExistingDocument ? __('labels.save_changes') : __('labels.create_landing_page') }}
                            </button>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.landingPageBuilderLabels = {
            linkCopied: @json(__('labels.landing_page_link_copied')),
            copyFailed: @json(__('labels.landing_page_link_copy_failed')),
        };
    </script>
    <script src="{{ hyperAsset('assets/js/landing-page-builder.js') }}" defer></script>
@endpush
