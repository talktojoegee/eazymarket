<div class="d-flex flex-wrap gap-2">
    <a href="{{ route('admin.products.show', $id) }}" class="btn btn-outline-primary btn-sm">
        <i class="ti ti-eye"></i>{{ __('labels.view') }}
    </a>
    @if($editPermission)
        <a href="{{ route('admin.products.edit', $id) }}" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-edit"></i>{{ __('labels.edit') }}
        </a>
    @endif
    @if($processPermission)
        <button type="button" class="btn btn-success btn-sm approve-product" data-id="{{ $id }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M5 12l5 5l10 -10"/>
            </svg>
            {{ __('labels.approve') }}
        </button>
        <button type="button" class="btn btn-danger btn-sm reject-product" data-id="{{ $id }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-x">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M18 6l-12 12" />
                <path d="M6 6l12 12" />
            </svg>
            {{ __('labels.reject') }}
        </button>
    @endif
</div>
