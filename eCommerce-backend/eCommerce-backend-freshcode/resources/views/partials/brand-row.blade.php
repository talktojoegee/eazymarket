@php
    $scopeLabel = $scopeLabel ?? null;
    $productsCount = $brand->products_count ?? 0;
    $logo = !empty($brand->logo) ? $brand->logo : asset('assets/images/brand-placeholder.png');
@endphp
<div class="brand-scope-row d-flex align-items-center gap-3 px-3 py-3 border-bottom" data-kind="brand">
    <span class="brand-scope-indent flex-shrink-0" aria-hidden="true"></span>
    <span class="flex-shrink-0">
        <a href="{{ $logo }}" data-fslightbox="brand-logos"
           aria-label="{{ __('labels.preview') }}: {{ $brand->title }}" class="d-block">
            <img src="{{ $logo }}" alt="{{ $brand->title }}" width="48" height="48"
                 class="rounded border bg-white" style="object-fit: contain;">
        </a>
    </span>

    <span class="flex-fill text-truncate" style="min-width: 180px;">
        @if($scopeLabel)
            <span class="badge bg-azure-lt mb-1">{{ $scopeLabel }}</span>
        @endif
        <span class="fw-semibold d-block text-truncate">{{ $brand->title }}</span>
        @if(!empty($brand->description))
            <span class="text-secondary small d-block text-truncate">{{ strip_tags($brand->description) }}</span>
        @endif
        <span class="d-flex align-items-center gap-1 mt-1">
            <span class="brand-scope-id badge bg-secondary-lt font-monospace">{{ __('labels.id') }}: {{ $brand->id }}</span>
            @if(!empty($brand->slug))
                <span class="badge bg-secondary-lt font-monospace text-truncate d-none d-md-inline-flex" style="max-width: 180px;">{{ $brand->slug }}</span>
            @endif
        </span>
    </span>

    <span class="catalog-list-stat d-none d-lg-flex flex-column align-items-end flex-shrink-0">
        <span class="fw-semibold">{{ $productsCount }}</span>
        <span class="text-secondary small">{{ __('labels.products') }}</span>
    </span>

    @if(!empty($brand->created_at))
        <span class="catalog-list-stat d-none d-xl-flex flex-column align-items-end flex-shrink-0">
            <span class="fw-medium">{{ $brand->created_at->format('d M Y') }}</span>
            <span class="text-secondary small">{{ __('labels.created_at') }}</span>
        </span>
    @endif

    <span class="flex-shrink-0">
        @include('partials.status', ['status' => $brand->status ?? ''])
    </span>

    <span class="flex-shrink-0">
        <x-partial-actions modelName="brand" id="{{ $brand->id }}" title="{{ $brand->title }}"
                           mode="model_view" :editPermission="$editPermission ?? false"
                           :deletePermission="$deletePermission ?? false"/>
    </span>
</div>
