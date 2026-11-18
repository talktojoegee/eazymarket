<div class="d-flex justify-content-start align-items-center {{ $buttonVariant === 'icon' ? 'gap-1' : '' }}">
    @if($shareUrl)
        <button type="button"
                class="btn btn-icon btn-outline-secondary fs-2"
                data-share-landing-page="{{ $shareUrl }}"
                data-share-title="{{ $title }}"
                data-bs-toggle="tooltip"
                title="{{ __('labels.share') }}"
                aria-label="{{ __('labels.share_landing_page_named', ['title' => $title]) }}">
            <i class="ti ti-share"></i>
        </button>
    @endif
    @if($editPermission)
        <a
            class="{{ $buttonVariant === 'icon' ? 'btn btn-icon btn-outline-primary fs-2' : 'btn btn-outline-blue me-2 p-1' }} edit-{{$modelName}}"
            data-id="{{$id}}"
            data-title="{{$title}}"
            title="{{ $buttonVariant === 'icon' ? __('labels.edit') : 'Edit '.$title }}"
            aria-label="{{ $buttonVariant === 'icon' ? __('labels.edit_landing_page_named', ['title' => $title]) : 'Edit '.$title }}"
            @if($mode == "model_view")
                href="javascript:void(0);"
            data-bs-toggle="modal"
            data-bs-target="#{{$modelName}}-modal"
            @else
                href="{{ $route }}"
            @endif
            @foreach($editAttributes as $attribute => $value)
                {{ $attribute }}="{{ $value }}"
            @endforeach
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 class="icon icon-tabler icons-tabler-outline icon-tabler-edit m-0">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"/>
                <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"/>
                <path d="M16 5l3 3"/>
            </svg>
        </a>
    @endif

    @if($deletePermission)
        <a href="javascript:void(0);" class="{{ $buttonVariant === 'icon' ? 'btn btn-icon btn-outline-danger fs-2' : 'btn btn-outline-danger me-2 p-1' }} delete-{{$modelName}}"
           data-id="{{$id}}"
           data-title="{{$title}}"
           @if($deleteUrl) data-delete-url="{{ $deleteUrl }}" @endif
           data-bs-toggle="tooltip"
           title="{{ $buttonVariant === 'icon' ? __('labels.delete') : 'Delete '.$title }}"
           aria-label="{{ $buttonVariant === 'icon' ? __('labels.delete_landing_page_named', ['title' => $title]) : 'Delete '.$title }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 class="icon icon-tabler icons-tabler-outline icon-tabler-trash m-0">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M4 7l16 0"/>
                <path d="M10 11l0 6"/>
                <path d="M14 11l0 6"/>
                <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/>
                <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"/>
            </svg>
        </a>
    @endif

    </div>
