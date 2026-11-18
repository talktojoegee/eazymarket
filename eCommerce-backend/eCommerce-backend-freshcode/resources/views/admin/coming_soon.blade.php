@extends('layouts.admin.app', ['page' => $activePage ?? ''])

@section('title', __('labels.coming_soon'))

@section('header_data')
    @php
        $page_title = $featureTitle ?? __('labels.coming_soon');
        $page_pretitle = __('labels.coming_soon');
    @endphp
@endsection

@section('admin-content')
    <div class="container-xl">
        <div class="row justify-content-center mt-5">
            <div class="col-lg-6 col-md-8">
                <div class="card card-md">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg text-muted" width="64"
                                 height="64" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                 fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/>
                                <path d="M12 7v5l3 3"/>
                            </svg>
                        </div>
                        <h2 class="mb-2">{{ $featureTitle ?? __('labels.coming_soon') }}</h2>
                        <p class="text-secondary mb-0">{{ __('labels.feature_coming_soon_message') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
