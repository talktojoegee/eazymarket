@extends('layouts.seller.app', ['page' => $menuSeller['products']['active'] ?? '', 'sub_page' => $menuSeller['products']['route']['product_faqs']['sub_active']])

@section('title', __('labels.add_product_faqs'))

@section('header_data')
    @php
        $page_title = __('labels.add_product_faqs');
        $page_pretitle = __('labels.product_faqs');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.product_faqs'), 'url' => route('seller.product_faqs.index')],
        ['title' => __('labels.add_product_faqs'), 'url' => ''],
    ];
@endphp

@section('seller-content')
    <div class="page-body">
        <div class="row row-cards">
            <div class="col-12">
                <form class="form-submit" id="product-faq-bulk-form" method="POST"
                      action="{{ route('seller.product_faqs.bulk-store') }}">
                    @csrf
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">{{ __('labels.add_product_faqs') }}</h3>
                                <x-breadcrumb :items="$breadcrumbs"/>
                            </div>
                            <div class="card-actions">
                                <span class="badge bg-blue-lt" id="product-faq-row-count">1 / 25</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info" role="alert">
                                <div class="alert-title">{{ __('labels.add_multiple_faqs_at_once') }}</div>
                                <div>{{ __('labels.product_faq_bulk_help') }}</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label required" for="select-product">{{ __('labels.product') }}</label>
                                <select class="form-select" id="select-product" name="product_id" required>
                                    @if($selectedProduct)
                                        <option value="{{ $selectedProduct->id }}" selected>{{ $selectedProduct->title }}</option>
                                    @endif
                                </select>
                                <div class="form-hint">{{ __('labels.product_faq_product_once_help') }}</div>
                            </div>

                            <div class="d-flex flex-wrap align-items-center mb-3 gap-2">
                                <div class="me-auto">
                                    <h3 class="mb-0">{{ __('labels.questions_and_answers') }}</h3>
                                    <div class="text-secondary">{{ __('labels.maximum_25_faqs_per_batch') }}</div>
                                </div>
                                <button class="btn btn-outline-primary" id="add-product-faq-row" type="button">
                                    <i class="ti ti-plus me-1"></i>{{ __('labels.add_another_faq') }}
                                </button>
                            </div>

                            <div id="product-faq-rows"></div>
                        </div>
                        <div class="card-footer d-flex justify-content-end gap-2">
                            <a class="btn" href="{{ route('seller.product_faqs.index') }}">{{ __('labels.cancel') }}</a>
                            <button class="btn btn-primary" type="submit">
                                <i class="ti ti-device-floppy me-1"></i>{{ __('labels.save_all_faqs') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <template id="product-faq-row-template">
        <div class="card mb-3 product-faq-row" data-faq-row>
            <div class="card-header py-2">
                <h4 class="card-title" data-faq-row-title></h4>
                <div class="card-actions">
                    <button class="btn btn-sm btn-ghost-danger" type="button" data-remove-faq-row
                            aria-label="{{ __('labels.remove_faq') }}">
                        <i class="ti ti-trash"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label required">{{ __('labels.question') }}</label>
                        <textarea class="form-control" data-field="question" rows="2" maxlength="1000"
                                  placeholder="{{ __('labels.enter_question') }}" required></textarea>
                        <div class="form-hint text-end"><span data-question-count>0</span> / 1000</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label required">{{ __('labels.answer') }}</label>
                        <textarea class="form-control" data-field="answer" rows="4" maxlength="5000"
                                  placeholder="{{ __('labels.enter_answer') }}" required></textarea>
                        <div class="form-hint text-end"><span data-answer-count>0</span> / 5000</div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">{{ __('labels.status') }}</label>
                        <select class="form-select" data-field="status">
                            <option value="active">{{ __('labels.active') }}</option>
                            <option value="inactive">{{ __('labels.inactive') }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </template>
@endsection

@push('scripts')
    @php
        $faqJsConfig = [
            'csrf' => csrf_token(),
            'faqNumber' => __('labels.faq_number'),
            'maximumRows' => __('labels.maximum_25_faqs_per_batch'),
            'minimumRows' => __('labels.at_least_one_faq_is_required'),
            'genericError' => __('labels.unexpected_error'),
        ];
    @endphp
    <script>
        window.sellerProductFaqs = @json($faqJsConfig);
    </script>
    <script src="{{ hyperAsset('assets/js/seller-product-faqs.js') }}" defer></script>
@endpush
