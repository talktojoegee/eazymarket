@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_content']['route']['blogs']['sub_active'] ?? '',
])

@section('title', $blog->exists ? __('labels.blog_edit_page') : __('labels.blog_create_page'))

@section('header_data')
    @php
        $page_title = $blog->exists ? __('labels.blog_edit_page') : __('labels.blog_create_page');
        $page_pretitle = $blog->exists ? __('labels.edit') : __('labels.add');
        $breadcrumbs = [
            ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
            ['title' => __('labels.social_and_content'), 'url' => null],
            ['title' => __('labels.blogs'), 'url' => route('admin.watch-and-buy.blogs.index')],
            ['title' => $page_title, 'url' => null],
        ];
    @endphp
@endsection

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <form class="form-submit" id="blog-form" method="POST" enctype="multipart/form-data" novalidate
                  action="{{ $blog->exists ? route('admin.watch-and-buy.blogs.update', $blog) : route('admin.watch-and-buy.blogs.store') }}"
                  data-redirect="{{ route('admin.watch-and-buy.blogs.index') }}"
                  data-error-text="{{ __('labels.something_went_wrong') }}">
                @csrf
                <input type="hidden" name="remove_image" id="blog-remove-image" value="0">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('labels.general_information') }}</h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label required" for="blog-title">{{ __('labels.blog_title') }}</label>
                                    <input class="form-control @error('title') is-invalid @enderror" id="blog-title" name="title"
                                           value="{{ old('title', $blog->title) }}" maxlength="191"
                                           placeholder="{{ __('labels.blog_title_placeholder') }}" required>
                                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="blog-slug">{{ __('labels.slug') }}</label>
                                    <input class="form-control @error('slug') is-invalid @enderror" id="blog-slug" name="slug"
                                           value="{{ old('slug', $blog->slug) }}" maxlength="191"
                                           placeholder="{{ __('labels.blog_slug_hint') }}">
                                    <div class="form-hint">{{ __('labels.blog_slug_hint') }}</div>
                                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="blog-short-description">{{ __('labels.blog_short_description') }}</label>
                                    <textarea class="form-control @error('short_description') is-invalid @enderror" id="blog-short-description"
                                              name="short_description" rows="3" maxlength="10000"
                                              placeholder="{{ __('labels.blog_short_description_placeholder') }}">{{ old('short_description', $blog->short_description) }}</textarea>
                                    @error('short_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required" for="blog-content">{{ __('labels.blog_content') }}</label>
                                    <textarea class="form-control hugerte-mytextarea @error('content') is-invalid @enderror" id="blog-content"
                                              name="content" rows="16" required>{{ old('content', $blog->content) }}</textarea>
                                    @error('content')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('labels.seo_information') }}</h3>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="blog-seo-title">{{ __('labels.seo_title') }}</label>
                                        <input class="form-control" id="blog-seo-title" name="metadata[seo_title]" maxlength="255"
                                               value="{{ old('metadata.seo_title', data_get($blog->metadata, 'seo_title')) }}"
                                               placeholder="{{ __('labels.enter_seo_title') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="blog-seo-keywords">{{ __('labels.seo_keywords') }}</label>
                                        <input class="form-control" id="blog-seo-keywords" name="metadata[seo_keywords]" maxlength="1000"
                                               value="{{ old('metadata.seo_keywords', data_get($blog->metadata, 'seo_keywords')) }}"
                                               placeholder="{{ __('labels.blog_seo_keywords_placeholder') }}">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="blog-seo-description">{{ __('labels.seo_description') }}</label>
                                        <textarea class="form-control" id="blog-seo-description" name="metadata[seo_description]" rows="4" maxlength="2000"
                                                  placeholder="{{ __('labels.enter_seo_description') }}">{{ old('metadata.seo_description', data_get($blog->metadata, 'seo_description')) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('labels.blog_publishing_details') }}</h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label required" for="blog-status">{{ __('labels.status') }}</label>
                                    <select class="form-select" id="blog-status" name="status" required>
                                        <option value="draft" @selected(old('status', $blog->status ?: 'draft') === 'draft')>{{ __('labels.blog_draft') }}</option>
                                        <option value="published" @selected(old('status', $blog->status) === 'published')>{{ __('labels.blog_published') }}</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="editor_pick" value="1"
                                               @checked(old('editor_pick', $blog->editor_pick))>
                                        <span class="form-check-label">{{ __('labels.blog_editor_pick') }}</span>
                                    </label>
                                    <div class="form-hint">{{ __('labels.blog_editor_pick_hint') }}</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="blog-category-ids">{{ __('labels.blog_categories_select') }}</label>
                                    <select class="form-select @error('category_ids') is-invalid @enderror" id="blog-category-ids"
                                            name="category_ids[]" multiple data-placeholder="{{ __('labels.blog_select_categories') }}">
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" @selected(in_array($category->id, $selectedCategoryIds, true))>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('category_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="blog-published-at">{{ __('labels.published_at') }}</label>
                                    <input class="form-control" id="blog-published-at" type="datetime-local" name="published_at"
                                           value="{{ old('published_at', $blog->published_at?->format('Y-m-d\TH:i')) }}">
                                    <div class="form-hint">{{ __('labels.blog_publish_hint') }}</div>
                                </div>
                                <div>
                                    <label class="form-label required" for="blog-author-profile">{{ __('labels.blog_author_profile') }}</label>
                                    <select class="form-select js-tom-select-static @error('author_profile_id') is-invalid @enderror"
                                            id="blog-author-profile" name="author_profile_id" required data-placeholder="{{ __('labels.blog_select_author') }}">
                                        <option value="">{{ __('labels.blog_select_author') }}</option>
                                        @foreach($profiles as $profile)
                                            <option value="{{ $profile->id }}" @selected((int) old('author_profile_id', $blog->author_profile_id) === $profile->id)>
                                                {{ '@'.$profile->username }}{{ $profile->seller?->user?->name ? ' — '.$profile->seller->user->name : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('author_profile_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header"><h3 class="card-title">{{ __('labels.blog_tags') }}</h3></div>
                            <div class="card-body">
                                <label class="form-label" for="blog-tags">{{ __('labels.blog_tags') }}</label>
                                <select class="form-select" id="blog-tags" name="tags[]" multiple data-placeholder="{{ __('labels.blog_tags_placeholder') }}">
                                    @foreach($selectedTags as $tag)
                                        <option value="{{ $tag }}" selected>{{ $tag }}</option>
                                    @endforeach
                                </select>
                                <div class="form-hint">{{ __('labels.blog_tags_hint') }}</div>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header"><h3 class="card-title">{{ __('labels.blog_image') }}</h3></div>
                            <div class="card-body">
                                <div id="blog-current-image" class="mb-3 @if(!$blog->image) d-none @endif">
                                    <img class="img-fluid rounded" src="{{ $blog->image }}" alt="{{ $blog->title }}">
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-2" id="blog-image-remove">{{ __('labels.remove_image') }}</button>
                                </div>
                                <input class="form-control" id="blog-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-max-file-size="5MB">
                                <div class="form-hint">{{ __('labels.blog_image_hint') }}</div>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header"><h3 class="card-title">{{ __('labels.blog_linked_products') }}</h3></div>
                            <div class="card-body">
                                <label class="form-label" for="blog-product-ids">{{ __('labels.blog_linked_products') }}</label>
                                <select class="form-select" id="blog-product-ids" name="product_ids[]" multiple
                                        data-endpoint="{{ route('admin.watch-and-buy.blogs.product-options') }}"
                                        data-selected='@json($selectedProductIds)'
                                        data-placeholder="{{ __('labels.blog_select_products') }}" data-preload="focus" data-preload-limit="15">
                                    @foreach($selectedProducts as $product)
                                        <option value="{{ $product->id }}" selected>{{ $product->title }}</option>
                                    @endforeach
                                </select>
                                <div class="form-hint">{{ __('labels.blog_products_hint') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2 mt-3">
                    <a href="{{ route('admin.watch-and-buy.blogs.index') }}" class="btn btn-outline-secondary">{{ __('labels.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i>{{ __('labels.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendor/tom-select/js/plugins/remove_button.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/blog-form.js') }}" defer></script>
@endpush
