@props([
    'id',
    'title' => 'Are you sure?',
    'message' => '',
    'confirmText' => 'Confirm',
    'cancelText' => null,
    'status' => 'danger',
    'icon' => 'ti ti-alert-triangle',
    'confirmClass' => null,
])

@php
    $confirmBtnClass = $confirmClass ?? 'btn-' . $status;
    $iconColor = match ($status) {
        'success' => 'text-green',
        'warning' => 'text-yellow',
        'primary' => 'text-primary',
        'info'    => 'text-azure',
        default   => 'text-red',
    };
    $cancelLabel = $cancelText ?? (__('labels.cancel') ?? 'Cancel');
@endphp

<div class="modal modal-blur fade" id="{{ $id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-status bg-{{ $status }}"></div>
            <div class="modal-body text-center py-4">
                <i class="{{ $icon }} mb-2 {{ $iconColor }}" style="font-size: 2.5rem; line-height: 1;"></i>
                <h3>{{ $title }}</h3>
                <div class="text-secondary">{{ $message }}</div>
            </div>
            <div class="modal-footer">
                <div class="w-100">
                    <div class="row">
                        <div class="col">
                            <a href="#" class="btn w-100" data-bs-dismiss="modal">{{ $cancelLabel }}</a>
                        </div>
                        <div class="col">
                            <button type="button" class="btn {{ $confirmBtnClass }} w-100 js-confirm-action" data-action-url="" data-action-payload="{}">
                                {{ $confirmText }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
