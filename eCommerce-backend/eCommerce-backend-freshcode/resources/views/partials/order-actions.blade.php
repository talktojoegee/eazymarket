<div class="btn-list flex-nowrap">
    <a href="{{ $route }}"
       class="btn btn-icon btn-outline-primary"
       title="{{ __('labels.view') }}"
       data-bs-toggle="tooltip">
        <i class="ti ti-eye"></i>
    </a>

    @if($panel === 'admin')
        <a href="{{ url('admin/orders/invoice?id=' . $uuid) }}"
           class="btn btn-icon"
           title="{{ __('labels.invoice') }}"
           data-bs-toggle="tooltip">
            <i class="ti ti-invoice"></i>
        </a>
    @endif
</div>
