@php
    $isGlobal = $isGlobal ?? false;
    $count = $count ?? 0;
    $categoryImage = $categoryImage ?? null;
@endphp
<div class="brand-group-header d-flex align-items-center gap-3 px-3 py-3 border rounded mt-2"
     data-kind="group"
     data-group="{{ $isGlobal ? 'global' : '' }}"
     data-scope-id="{{ $scopeId ?? '' }}"
     data-loaded="0"
     role="button"
     tabindex="0"
     aria-expanded="false"
     aria-label="{{ __('labels.expand_brand_group', ['title' => $title]) }}"
     style="cursor: pointer;">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
         class="brand-group-chevron text-secondary" style="transition: transform .15s;">
        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
        <path d="M9 6l6 6l-6 6"/>
    </svg>

    <span class="d-inline-flex align-items-center justify-content-center rounded flex-shrink-0 overflow-hidden"
          style="width: 42px; height: 42px; background: {{ $isGlobal ? 'var(--tblr-primary-lt, #e8f0ff)' : 'var(--tblr-active-bg, rgba(0,0,0,.04))' }};">
        @if($isGlobal)
            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/>
                <path d="M3.6 9h16.8"/><path d="M3.6 15h16.8"/>
                <path d="M11.5 3a17 17 0 0 0 0 18"/><path d="M12.5 3a17 17 0 0 1 0 18"/>
            </svg>
        @elseif($categoryImage)
            <a href="{{ $categoryImage }}" data-fslightbox="brand-scope-categories"
               aria-label="{{ __('labels.preview') }}: {{ $title }}" class="d-block w-100 h-100">
                <img src="{{ $categoryImage }}" alt="{{ $title }}" width="42" height="42"
                     class="w-100 h-100" style="object-fit: cover;">
            </a>
        @else
            <i class="ti ti-category text-secondary fs-2"></i>
        @endif
    </span>

    <span class="flex-fill text-truncate">
        <span class="fw-semibold d-block text-truncate">{{ $title }}</span>
        @if($isGlobal)
            <span class="text-secondary small">{{ __('labels.global') }} {{ __('labels.scope') }}</span>
        @else
            <span class="text-secondary small">{{ __('labels.category') }} {{ __('labels.scope') }}</span>
        @endif
    </span>

    <span class="badge bg-blue-lt flex-shrink-0">{{ $count }} {{ __('labels.brands') }}</span>
</div>
