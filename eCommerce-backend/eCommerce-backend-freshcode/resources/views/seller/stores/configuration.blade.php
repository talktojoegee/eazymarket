@extends('layouts.seller.app', ['page' => $menuSeller['stores']['active'] ?? ""])

@section('title', __('labels.store_configuration'))

@section('header_data')
    @php
        $page_title = __('labels.store_configuration');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.store_configuration'), 'url' => '']
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper">
        @include('components.page_header', ['title' => $store->name . ' Store Configuration', 'step' => 2])

        <!-- BEGIN PAGE BODY -->
        <!-- resources/views/admin/sellers/form.blade.php -->
        <div class="page-body">
            <div class="container-xl">
                <div class="row g-5">
                    <div class="col-sm-2 d-none d-lg-block">
                        <div class="sticky-top">
                            <h3>{{ __('labels.menu') }}</h3>
                            <nav class="nav nav-vertical nav-pills" id="pills">
                                <a class="nav-link" href="#pills-store">{{ __('labels.store_information') }}</a>
                                <a class="nav-link" href="#pills-policies">{{ __('labels.policies') }}</a>
                                <a class="nav-link" href="#pills-metadata">{{ __('labels.metadata') }}</a>
</nav>
                        </div>
                    </div>
                    <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                        <div class="row row-cards">
                            <div class="col-12">
                                <form action="{{route('seller.stores.store_configuration', ['id' =>$store->id])}}"
                                      class="form-submit" method="post" enctype="multipart/form-data">
                                    @csrf
                                    <div class="card mb-4" id="pills-store">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.store_information') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('labels.description') }}</label>
                                                <textarea class="hugerte-mytextarea"
                                                          name="description">{{ old('description', $store->description ?? '') }}</textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('labels.about_us') }}</label>
                                                <textarea class="hugerte-mytextarea" s
                                                          name="about_us">{{ old('about_us', $store->about_us ?? '') }}</textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Policies -->
                                    <div class="card mb-4" id="pills-policies">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.policies') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label">{{ __('labels.promotional_text') }}</label>
                                                        <textarea class="hugerte-mytextarea" name="promotional_text"
                                                                  placeholder="{{ __('labels.enter_promotional_text') }}">{{ old('promotional_text', $store->promotional_text ?? '') }}</textarea>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label required">{{ __('labels.return_replacement_policy') }}</label>
                                                        <textarea class="hugerte-mytextarea"
                                                                  name="return_replacement_policy"
                                                                  placeholder="{{ __('labels.enter_return_replacement_policy') }}">{{ old('return_replacement_policy', $store->return_replacement_policy ?? '') }}</textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label required">{{ __('labels.refund_policy') }}</label>
                                                        <textarea class="hugerte-mytextarea" name="refund_policy"
                                                                  placeholder="{{ __('labels.enter_refund_policy') }}">{{ old('refund_policy', $store->refund_policy ?? '') }}</textarea>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label required">{{ __('labels.terms_and_conditions') }}</label>
                                                        <textarea class="hugerte-mytextarea" name="terms_and_conditions"
                                                                  placeholder="{{ __('labels.enter_terms_and_conditions') }}">{{ old('terms_and_conditions', $store->terms_and_conditions ?? '') }}</textarea>
                                                    </div>
                                                </div>
                                            </div>

                                            </div>
                                    </div>

                                    <!-- Metadata -->
                                    <div class="card mb-4" id="pills-metadata">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.status_and_metadata') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <x-seo-fields :metadata="$store->metadata ?? null"/>
                                        </div>
                                    </div>

                                    <!-- POS Settings -->
<div class="card-footer text-end">
                                        <div class="d-flex">
                                            <button type="submit"
                                                    class="btn btn-primary ms-auto">Submit
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/store-configuration.js') }}" defer></script>
@endpush
