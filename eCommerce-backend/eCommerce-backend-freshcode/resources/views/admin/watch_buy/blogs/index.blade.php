@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_content']['route']['blogs']['sub_active'] ?? '',
])

@section('title', __('labels.blogs'))

@section('header_data')
    @php
        $page_title = __('labels.blogs');
        $page_pretitle = __('labels.list');
        $breadcrumbs = [
            ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
            ['title' => __('labels.social_and_content'), 'url' => null],
            ['title' => __('labels.blogs'), 'url' => null],
        ];
    @endphp
@endsection

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.blogs') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                    <div class="card-actions d-flex gap-2">
                        @if($createPermission)
                            <a href="{{ route('admin.watch-and-buy.blogs.create') }}" class="btn btn-primary">
                                <i class="ti ti-plus me-1"></i>{{ __('labels.blog_add') }}
                            </a>
                        @endif
                        <button type="button" class="btn btn-outline-primary" id="blogs-refresh">
                            <i class="ti ti-refresh me-1"></i>{{ __('labels.refresh') }}
                        </button>
                    </div>
                </div>
                <div class="card-table">
                    <div class="row w-full p-3">
                        <x-datatable id="blogs-table" :columns="$columns"
                                     :route="route('admin.watch-and-buy.blogs.datatable')"
                                     :options="['order' => [[5, 'desc']], 'pageLength' => 10]"/>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" id="blogs-delete-title" value="{{ __('labels.blog_delete_title') }}">
    <input type="hidden" id="blogs-delete-text" value="{{ __('labels.blog_delete_text', ['title' => ':title']) }}">
    <input type="hidden" id="blogs-delete-confirm" value="{{ __('labels.delete') }}">
    <input type="hidden" id="blogs-cancel" value="{{ __('labels.cancel') }}">
    <input type="hidden" id="blogs-error" value="{{ __('labels.something_went_wrong') }}">
    <input type="hidden" id="blogs-delete-url" value="{{ route('admin.watch-and-buy.blogs.destroy', ['blog' => '__ID__']) }}">
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/blogs.js') }}" defer></script>
@endpush
