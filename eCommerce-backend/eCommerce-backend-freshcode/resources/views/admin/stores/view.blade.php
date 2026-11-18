@extends('layouts.admin.app',  ['page' => $menuAdmin['stores']['active'] ?? ""])

@section('title', $store->name)

@section('header_data')
    @php
        $page_title = $store->name;
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.stores'), 'url' => route('admin.sellers.store.index')],
        ['title' => $store->name, 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-wrapper">
        <!-- BEGIN PAGE HEADER -->
        <div class="page-header d-print-none">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h2 class="page-title text-capitalize">{{$store->name}} {{ __('labels.stores') }}</h2>
                    <x-breadcrumb :items="$breadcrumbs"/>
                </div>
                <div class="col-auto">
                    <a href="{{ route('admin.sellers.store.locations.index', ['storeId' => $store->id]) }}"
                       class="btn btn-outline-primary">
                        <i class="ti ti-map-pin me-1"></i>{{ __('labels.locations') }}
                    </a>
                </div>
            </div>
        </div>
        <div class="page-body">
            <div class="row row-cards">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4 class="card-title">{{ __('labels.about') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table mb-0" style="border: none;">
                                    <tbody>
                                    <tr>
                                        <td class="fw-bold border-0" style="width: 180px;">{{ __('labels.name') }}</td>
                                        <td class="border-0">{{ $store->name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold border-0">{{ __('labels.visibility_status') }}</td>
                                        <td class="border-0">
                                            <span
                                                class="badge p-2 bg-info-lt border border-info-subtle">{{ ucfirst(Str::replace("_", " ",$store->visibility_status->value)) }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold border-0">{{ __('labels.verification_status') }}</td>
                                        <td class="border-0">
                                            <span
                                                class="badge border p-2 {{ $store->verification_status->value === 'approved' ? 'bg-success-lt border-success-subtle' : 'bg-danger-lt border-danger-subtle' }}">
                                                {{ ucfirst(Str::replace("_", " ",$store->verification_status->value)) }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold border-0">{{ __('labels.slug') }}</td>
                                        <td class="border-0"><code>{{ $store->slug }}</code></td>
                                    </tr>
                                    @if($store->seller)
                                        <tr>
                                            <td class="fw-bold border-0">{{ __('labels.seller') }}</td>
                                            <td class="border-0">
                                                <a href="{{ route('admin.sellers.show', $store->seller->id) }}">
                                                    {{ $store->seller->user->name ?? $store->seller->id }}
                                                </a>
                                            </td>
                                        </tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4 class="card-title">{{ __('labels.contact_information') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table mb-0" style="border: none;">
                                    <tbody>
                                    <tr>
                                        <td class="fw-bold border-0"
                                            style="width: 180px;">{{ __('labels.contact_email') }}</td>
                                        <td class="border-0">{{ $store->contact_email }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold border-0">{{ __('labels.contact_number') }}</td>
                                        <td class="border-0">{{ $store->contact_number }}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4 class="card-title">{{ __('labels.enabled_markets') }}</h4>
                        </div>
                        <div class="card-body">
                            @if($store->markets && $store->markets->count())
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($store->markets as $m)
                                        <span class="badge bg-blue-lt fs-5 px-2 py-1">
                                            {{ $m->code }} — {{ $m->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-muted mb-0">{{ __('labels.no_markets_yet') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="row row-cards">
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4 class="card-title">{{ __('labels.financial_bank_details') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table mb-0" style="border: none;">
                                    <tbody>
                                    <tr>
                                        <td class="fw-bold border-0"
                                            style="width: 180px;">{{ __('labels.currency_code') }}</td>
                                        <td class="border-0">{{ $store->currency_code }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold border-0">{{ __('labels.tax_name') }}</td>
                                        <td class="border-0">{{ $store->tax_name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold border-0">{{ __('labels.tax_number') }}</td>
                                        <td class="border-0">{{ $store->tax_number }}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    </div>
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h4 class="card-title">{{ __('labels.logo') }}</h4>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-center align-items-center">
                                        <a href="{{ $store->store_logo }}" class="img-box-200px-h" target="_blank"
                                           data-fslightbox="gallery">
                                            <img src="{{$store->store_logo}}" alt="">
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h4 class="card-title">{{ __('labels.banner') }}</h4>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-center align-items-center">
                                        <a href="{{ $store->store_banner }}" class="img-box-200px-h" target="_blank"
                                           data-fslightbox="gallery">
                                            <img src="{{$store->store_banner}}" alt="">
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @if($store->seller)
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card mb-4">
                                    <div class="card-body py-3 text-muted">
                                        {!! __('labels.store_kyc_moved_to_seller', [
                                            'link' => '<a href="' . route('admin.sellers.show', $store->seller->id) . '">' . e($store->seller->user->name ?? __('labels.seller')) . '</a>',
                                        ]) !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            @if($verifyPermission ?? false)
                <div class="row row-cards">
                    <div class="col-md-12">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4 class="card-title">{{ __('labels.verify_account') }}</h4>
                            </div>
                            <div class="card-body">
                                <form method="post" action="{{route('admin.sellers.store.verify', $store->id)}}"
                                      class="form-submit">
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.verification_status') }}</label>
                                                <select class="form-select" name="verification_status">
                                                    <option value="">{{ __('labels.select_status') }}</option>
                                                    @foreach($verificationStatus as $status)
                                                        <option
                                                            value="{{$status}}" {{$status === $store->verification_status->value ? "selected" : ""}}>{{ ucfirst(Str::replace("_", " ",$status))}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.visibility_status') }}</label>
                                                <select class="form-select" name="visibility_status">
                                                    <option value="">{{ __('labels.select_status') }}</option>
                                                    @foreach($visibilityStatus as $vStatus)
                                                        <option
                                                            value="{{$vStatus}}" {{$vStatus === $store->visibility_status->value ? "selected" : ""}}>{{ ucfirst(Str::replace("_", " ",$vStatus))}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('labels.is_recommended') }}</label>
                                                <input type="hidden" name="is_recommended" value="0">
                                                <label class="form-check form-switch mt-2">
                                                    <input class="form-check-input" type="checkbox" name="is_recommended"
                                                           value="1" {{ $store->is_recommended ? 'checked' : '' }}>
                                                    <span class="form-check-label">{{ __('labels.recommended') }}</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">{{ __('labels.submit') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
