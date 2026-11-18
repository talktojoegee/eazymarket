@php
    $expandable = $expandable ?? false;
    $depth = $depth ?? 0;
    $pathPrefix = $pathPrefix ?? null;
    $productsCount = $category->products_count ?? 0;
    $childrenCount = $category->children_count ?? 0;
    $requiresApproval = $category->requires_approval ?? false;
    $commission = $category->commission ?? 0;
@endphp
<div class="cat-tree-row {{ $depth > 0 ? 'cat-tree-row-child' : 'cat-tree-row-root' }} d-flex align-items-center gap-3 px-3 py-3 border-bottom"
     data-id="{{ $category->id }}"
     data-title="{{ $category->title }}"
     data-depth="{{ $depth }}"
     data-expandable="{{ $expandable ? 1 : 0 }}"
     data-loaded="0"
     style="--cat-guide: {{ 24 + max(0, $depth - 1) * 28 }}px; padding-inline-start: {{ 12 + $depth * 28 }}px;">
    <span class="cat-tree-toggle d-inline-flex align-items-center justify-content-center"
          style="width: 22px; height: 22px; cursor: {{ $expandable ? 'pointer' : 'default' }};"
          @if($expandable)
              role="button"
              tabindex="0"
              aria-expanded="false"
              aria-label="{{ __('labels.expand_category', ['title' => $category->title]) }}"
          @endif>
        @if($expandable)
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 class="cat-tree-chevron text-secondary" style="transition: transform .15s;">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M9 6l6 6l-6 6"/>
            </svg>
        @endif
    </span>

    <span class="cat-tree-thumb flex-shrink-0">
        <a href="{{ $category->image }}" data-fslightbox="category-images"
           aria-label="{{ __('labels.preview') }}: {{ $category->title }}" class="d-block">
            <img src="{{ $category->image }}" alt="{{ $category->title }}" width="48" height="48"
                 class="rounded border bg-white" style="object-fit: cover;">
        </a>
    </span>

    <span class="cat-tree-title flex-fill text-truncate" style="min-width: 180px;">
        @if($pathPrefix)
            <span class="text-muted small d-block text-truncate">{{ $pathPrefix }}</span>
        @endif
        <span class="fw-semibold d-block text-truncate">{{ $category->title }}</span>
        @if(!empty($category->description))
            <span class="text-secondary small d-block text-truncate">{{ strip_tags($category->description) }}</span>
        @endif
        <span class="d-flex align-items-center gap-1 mt-1">
            <span class="cat-tree-id badge bg-secondary-lt font-monospace">{{ __('labels.id') }}: {{ $category->id }}</span>
            <span class="cat-tree-slug badge bg-secondary-lt font-monospace text-truncate d-none d-md-inline-flex"
                  style="max-width: 180px;">{{ $category->slug }}</span>
        </span>
    </span>

    <span class="catalog-list-stat d-none d-lg-flex flex-column align-items-end flex-shrink-0">
        <span class="fw-semibold">{{ $childrenCount }}</span>
        <span class="text-secondary small">{{ __('labels.subcategories') }}</span>
    </span>

    <span class="catalog-list-stat d-none d-lg-flex flex-column align-items-end flex-shrink-0">
        <span class="fw-semibold">{{ $productsCount }}</span>
        <span class="text-secondary small">{{ __('labels.products') }}</span>
    </span>

    <span class="d-none d-xl-flex flex-column align-items-end gap-1 flex-shrink-0">
        <span class="badge {{ $requiresApproval ? 'bg-info-lt' : 'bg-secondary-lt' }}">
            {{ $requiresApproval ? __('labels.approval_required') : __('labels.no_approval') }}
        </span>
        <span class="text-secondary small">{{ __('labels.commission') }}: {{ number_format((float) $commission, 2) }}%</span>
    </span>

    <span class="cat-tree-status flex-shrink-0">
        @include('partials.status', ['status' => $category->status ?? ''])
    </span>

    <span class="cat-tree-actions flex-shrink-0">
        <x-partial-actions modelName="category" id="{{ $category->id }}" title="{{ $category->title }}"
                           mode="model_view" :editPermission="$editPermission ?? false"
                           :deletePermission="$deletePermission ?? false"/>
    </span>
</div>
