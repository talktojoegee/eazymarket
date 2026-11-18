<div class="product-row-actions">
    @if($editPermission ?? false)
        <a href="{{ $route }}" class="btn btn-sm btn-primary">
            <i class="ti ti-edit me-1"></i>{{ __('labels.edit') }}
        </a>
    @elseif($viewPermission ?? false)
        <a href="{{ $viewRoute }}" class="btn btn-sm btn-outline-primary">
            <i class="ti ti-eye me-1"></i>{{ __('labels.view') }}
        </a>
    @endif

    @if(($editPermission ?? false) || ($deletePermission ?? false) || ($viewPermission ?? false))
        <div class="dropdown">
            <button type="button"
                    class="btn btn-sm btn-icon btn-outline-secondary"
                    data-bs-toggle="dropdown"
                    data-bs-boundary="viewport"
                    aria-expanded="false"
                    aria-label="{{ __('labels.more') }}">
                <i class="ti ti-dots-vertical"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end">
                @if($viewPermission ?? false)
                    <a class="dropdown-item" href="{{ $viewRoute }}">
                        <i class="ti ti-eye me-2"></i>{{ __('labels.view') }}
                    </a>
                @endif

                @if($editPermission ?? false)
                    <a class="dropdown-item" href="{{ $route }}#section-pricing">
                        <i class="ti ti-building-warehouse me-2"></i>{{ __('labels.variant_pricing_inventory') }}
                    </a>
                    <button type="button"
                            class="dropdown-item update-product-status"
                            data-id="{{ $id }}"
                            data-title="{{ $title }}">
                        <i class="ti {{ ($status ?? '') === 'active' ? 'ti-toggle-left' : 'ti-toggle-right' }} me-2"></i>
                        {{ ($status ?? '') === 'active' ? __('labels.deactivate') : __('labels.activate') }}
                    </button>
                @endif

                @if(($showBadgeAction ?? false) && ($editPermission ?? false))
                    <button type="button"
                            class="dropdown-item"
                            data-bs-toggle="modal"
                            data-bs-target="#assign-badge-modal"
                            data-id="{{ $id }}"
                            data-title="{{ $title }}"
                            data-badge-id="{{ $badge->id ?? '' }}">
                        <i class="ti ti-badge me-2"></i>{{ __('labels.assign_badge') }}
                    </button>
                @endif

                @if($deletePermission ?? false)
                    <div class="dropdown-divider"></div>
                    <button type="button"
                            class="dropdown-item text-danger delete-{{ $modelName }}"
                            data-id="{{ $id }}"
                            data-title="{{ $title }}">
                        <i class="ti ti-trash me-2"></i>{{ __('labels.delete') }}
                    </button>
                @endif
            </div>
        </div>
    @endif
</div>
