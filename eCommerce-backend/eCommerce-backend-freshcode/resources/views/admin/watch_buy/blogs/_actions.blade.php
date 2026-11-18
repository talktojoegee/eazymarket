<div class="d-flex gap-2">
    @if($editPermission)
        <a href="{{ route('admin.watch-and-buy.blogs.edit', $blog) }}" class="btn btn-outline-primary btn-icon"
           aria-label="{{ __('labels.edit') }}" title="{{ __('labels.edit') }}">
            <i class="ti ti-edit"></i>
        </a>
    @endif
    @if($deletePermission)
        <button type="button" class="btn btn-outline-danger btn-icon blog-delete" data-id="{{ $blog->id }}"
                data-title="{{ $blog->title }}" aria-label="{{ __('labels.delete') }}" title="{{ __('labels.delete') }}">
            <i class="ti ti-trash"></i>
        </button>
    @endif
</div>
