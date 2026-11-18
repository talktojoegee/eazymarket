@php use App\Enums\ActiveInactiveStatusEnum; @endphp
@extends('layouts.seller.app', ['page' => $menuSeller['products']['active'] ?? '', 'sub_page' => $menuSeller['products']['route']['product_faqs']['sub_active']])

@section('title', __('labels.product_faqs'))

@section('header_data')
    @php
        $page_title = __('labels.product_faqs');
        $page_pretitle = __('labels.seller') . ' ' . __('labels.products');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.products'), 'url' => route('seller.products.index')],
        ['title' => __('labels.product_faqs'), 'url' => ''],
    ];
@endphp

@section('seller-content')
    <div class="page-body">
        <div class="row row-cards">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">{{ __('labels.product_faqs') }}</h3>
                            <x-breadcrumb :items="$breadcrumbs"/>
                        </div>
                        <div class="card-actions">
                            <div class="row g-2">
                                <div class="col-12 col-md-auto">
                                    <label class="visually-hidden" for="select-product">{{ __('labels.product') }}</label>
                                    <select class="form-select" name="product_id_filter" id="select-product"
                                            aria-label="{{ __('labels.filter_by_product') }}">
                                        <option value="">{{ __('labels.all_products') }}</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-auto">
                                    <label class="visually-hidden" for="faqStatusFilter">{{ __('labels.status') }}</label>
                                    <select class="form-select" id="faqStatusFilter" aria-label="{{ __('labels.filter_by_status') }}">
                                        <option value="">{{ __('labels.all_statuses') }}</option>
                                        @foreach(ActiveInactiveStatusEnum::values() as $type)
                                            <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if($createPermission)
                                    <div class="col-12 col-md-auto">
                                        <a class="btn btn-primary w-100" href="{{ route('seller.product_faqs.create') }}">
                                            <i class="ti ti-plus me-1"></i>{{ __('labels.add_product_faqs') }}
                                        </a>
                                    </div>
                                @endif
                                <div class="col-12 col-md-auto">
                                    <button class="btn btn-outline-primary w-100" id="refresh" type="button">
                                        <i class="ti ti-refresh me-1"></i>{{ __('labels.refresh') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-table">
                        <div class="row w-full p-3">
                            <x-datatable id="product-faqs-table" :columns="$columns"
                                         route="{{ route('seller.product_faqs.datatable') }}"
                                         :options="['order' => [[0, 'desc']], 'pageLength' => 10]"/>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($editPermission)
        <div class="modal modal-blur fade" id="product-faq-modal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('labels.edit_product_faq') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.close') }}"></button>
                    </div>
                    <form class="form-submit" id="product-faq-form" method="POST" action="">
                        @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label required" for="faq-edit-product">{{ __('labels.product') }}</label>
                                <select class="form-select" id="faq-edit-product" name="product_id" required></select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required" for="question">{{ __('labels.question') }}</label>
                                <textarea class="form-control" id="question" name="question" rows="3" maxlength="1000" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required" for="answer">{{ __('labels.answer') }}</label>
                                <textarea class="form-control" id="answer" name="answer" rows="4" maxlength="5000" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="status">{{ __('labels.status') }}</label>
                                <select class="form-select text-capitalize" id="status" name="status">
                                    @foreach(ActiveInactiveStatusEnum::values() as $status)
                                        <option value="{{ $status }}">{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn" type="button" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                            <button class="btn btn-primary ms-auto" type="submit">
                                <i class="ti ti-device-floppy me-1"></i>{{ __('labels.save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/product.js') }}" defer></script>
@endpush
