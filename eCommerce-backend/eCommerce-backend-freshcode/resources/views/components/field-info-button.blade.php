@props([
    'title',
    'placement' => 'top',
])

<button type="button" class="btn btn-icon btn-sm btn-ghost-secondary ms-1"
        data-field-info data-bs-toggle="tooltip" data-bs-placement="{{ $placement }}"
        title="{{ $title }}" aria-label="{{ $title }}">
    <i class="ti ti-info-circle"></i>
</button>
