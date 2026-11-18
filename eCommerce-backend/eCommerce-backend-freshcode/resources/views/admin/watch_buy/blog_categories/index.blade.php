@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_content']['route']['blog_categories']['sub_active'] ?? '',
])

@section('title', __('labels.blog_categories'))

@section('header_data')
    @php
        $page_title = __('labels.blog_categories');
        $page_pretitle = __('labels.list');
        $breadcrumbs = [
            ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
            ['title' => __('labels.social_and_content'), 'url' => null],
            ['title' => __('labels.blog_categories'), 'url' => null],
        ];
    @endphp
@endsection

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.blog_categories') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                    <div class="card-actions d-flex gap-2">
                        @if($createPermission)
                            <button type="button" class="btn btn-primary" id="blog-category-add" data-bs-toggle="modal" data-bs-target="#blog-category-modal">
                                <i class="ti ti-plus me-1"></i>{{ __('labels.add_blog_category') }}
                            </button>
                        @endif
                        <button type="button" class="btn btn-outline-primary" id="blog-category-refresh">
                            <i class="ti ti-refresh me-1"></i>{{ __('labels.refresh') }}
                        </button>
                    </div>
                </div>
                <div class="card-table">
                    <div class="row w-full p-3">
                        <x-datatable id="blog-categories-table" :columns="$columns"
                                     :route="route('admin.watch-and-buy.blog-categories.datatable')"
                                     :options="['order' => [[3, 'asc']], 'pageLength' => 10]"/>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($createPermission || $editPermission)
        <div class="modal modal-blur fade" id="blog-category-modal" tabindex="-1" aria-labelledby="blog-category-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-2" id="blog-category-modal-title">{{ __('labels.create_blog_category') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.close') }}"></button>
                    </div>
                    <form class="form-submit" id="blog-category-form" method="POST" action="{{ route('admin.watch-and-buy.blog-categories.store') }}" enctype="multipart/form-data"
                          data-edit-title="{{ __('labels.edit_blog_category') }}"
                          data-delete-title="{{ __('labels.delete_blog_category_title') }}"
                          data-delete-text="{{ __('labels.delete_blog_category_text', ['name' => ':name']) }}"
                          data-delete-confirm="{{ __('labels.delete') }}"
                          data-cancel-text="{{ __('labels.cancel') }}"
                          data-error-text="{{ __('labels.something_went_wrong') }}"
                          data-update-url="{{ route('admin.watch-and-buy.blog-categories.update', ['blogCategory' => '__ID__']) }}"
                          data-delete-url="{{ route('admin.watch-and-buy.blog-categories.destroy', ['blogCategory' => '__ID__']) }}">
                        @csrf
                        <input type="hidden" name="_method" id="blog-category-method" value="POST">
                        <input type="hidden" name="remove_image" id="blog-category-remove-image" value="0">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label required" for="blog-category-name">{{ __('labels.name') }}</label>
                                    <input class="form-control" id="blog-category-name" name="name" maxlength="191" placeholder="{{ __('labels.enter_blog_category_name') }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="blog-category-sort-order">{{ __('labels.sort_order') }}</label>
                                    <input class="form-control" id="blog-category-sort-order" name="sort_order" type="number" min="0" value="0" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="blog-category-slug">{{ __('labels.slug') }}</label>
                                    <input class="form-control" id="blog-category-slug" name="slug" maxlength="191" placeholder="{{ __('labels.blog_category_slug_hint') }}">
                                    <small class="form-hint">{{ __('labels.blog_category_slug_hint') }}</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="blog-category-description">{{ __('labels.description') }}</label>
                                    <textarea class="form-control" id="blog-category-description" name="description" rows="4" placeholder="{{ __('labels.enter_description') }}"></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="blog-category-image">{{ __('labels.image') }}</label>
                                    <div id="blog-category-current-image" class="mb-2 d-none">
                                        <img class="avatar avatar-xl rounded object-fit-cover" src="" alt="{{ __('labels.blog_category_current_image') }}">
                                        <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="blog-category-image-remove">{{ __('labels.remove_image') }}</button>
                                    </div>
                                    <input class="form-control" id="blog-category-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-max-file-size="5MB">
                                    <small class="form-hint">{{ __('labels.blog_category_image_hint') }}</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-check form-switch">
                                        <input class="form-check-input" id="blog-category-active" name="is_active" type="checkbox" value="1" checked>
                                        <span class="form-check-label">{{ __('labels.active') }}</span>
                                    </label>
                                </div>
                                <div class="col-12">
                                    <section class="card card-sm" aria-labelledby="blog-category-seo-heading">
                                        <div class="card-header p-0">
                                            <button class="btn w-100 d-flex align-items-center gap-2 px-3 py-3 text-start" type="button"
                                                    data-bs-toggle="collapse" data-bs-target="#blog-category-seo-fields"
                                                    aria-expanded="false" aria-controls="blog-category-seo-fields">
                                                <i class="ti ti-seo" aria-hidden="true"></i>
                                                <span class="card-title mb-0" id="blog-category-seo-heading">{{ __('labels.seo_information') }}</span>
                                                <i class="ti ti-chevron-down ms-auto" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                        <div class="collapse" id="blog-category-seo-fields">
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <label class="form-label" for="blog-category-seo-title">{{ __('labels.seo_title') }}</label>
                                                    <input class="form-control" id="blog-category-seo-title" name="metadata[seo_title]" maxlength="255" placeholder="{{ __('labels.enter_seo_title') }}">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label" for="blog-category-seo-keywords">{{ __('labels.seo_keywords') }}</label>
                                                    <input class="form-control" id="blog-category-seo-keywords" name="metadata[seo_keywords]" maxlength="1000" placeholder="{{ __('labels.enter_seo_keywords') }}">
                                                </div>
                                                <div>
                                                    <label class="form-label" for="blog-category-seo-description">{{ __('labels.seo_description') }}</label>
                                                    <textarea class="form-control" id="blog-category-seo-description" name="metadata[seo_description]" rows="3" maxlength="2000" placeholder="{{ __('labels.enter_seo_description') }}"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                            <button type="submit" class="btn btn-primary" id="blog-category-save">{{ __('labels.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if($deletePermission)
        <form id="blog-category-delete-form" method="POST" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/blog-categories.js') }}" defer></script>
@endpush
