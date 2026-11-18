@if($mode === 'settle' || $mode === 'view')
    <div class="d-flex justify-content-center align-items-center gap-1">
        @if($isHeld ?? false)
            <span class="badge bg-yellow-lt" data-bs-toggle="tooltip" title="{{ $holdReason ?? __('labels.held') }}">{{ __('labels.held') }}</span>
        @endif
        <button class="btn btn-icon view-commission-btn"
                data-detail-url="{{ $detailUrl ?? '' }}"
                data-bs-toggle="tooltip" title="{{ __('labels.view_settlement_detail') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 class="icon icon-tabler icons-tabler-outline icon-tabler-eye">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/>
                <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"/>
            </svg>
        </button>
        @if($mode === 'settle')
            @if($isHeld ?? false)
                <button class="btn btn-icon btn-outline-warning unhold-commission-btn"
                        data-unhold-url="{{ $unholdUrl ?? '' }}"
                        data-bs-toggle="tooltip" title="{{ __('labels.release_hold') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         class="icon icon-tabler icons-tabler-outline icon-tabler-lock-open">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M5 11m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z"/>
                        <path d="M12 16m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/>
                        <path d="M8 11v-5a4 4 0 0 1 8 0"/>
                    </svg>
                </button>
            @else
                @if($settlePermission ?? false)
                    <button class="btn btn-icon btn-outline-primary settle-commission-btn"
                            data-id="{{ $id }}"
                            data-order-id="{{ $orderId ?? '' }}"
                            data-store="{{ $store_name ?? '' }}"
                            data-product="{{ $productTitle ?? '' }}"
                            data-commission="{{ $adminCommissionAmount ?? '' }}"
                            data-amount="{{ $amountToPay ?? '' }}"
                            data-settle-url="{{ $settleUrl ?? '' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             class="icon icon-tabler icons-tabler-outline icon-tabler-cash">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <rect x="7" y="9" width="14" height="10" rx="2"/>
                            <circle cx="14" cy="14" r="2"/>
                            <path d="M17 9v-2a2 2 0 0 0 -2 -2h-10a2 2 0 0 0 -2 2v6a2 2 0 0 0 2 2h2"/>
                        </svg>
                    </button>
                @endif
                <button class="btn btn-icon hold-commission-btn"
                        data-hold-url="{{ $holdUrl ?? '' }}"
                        data-bs-toggle="tooltip" title="{{ __('labels.hold') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         class="icon icon-tabler icons-tabler-outline icon-tabler-lock">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z"/>
                        <path d="M12 16m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/>
                        <path d="M8 11v-4a4 4 0 1 1 8 0v4"/>
                    </svg>
                </button>
            @endif
        @endif
    </div>
@else
    <div>
        <x-partial-actions modelName="{{$modelName}}" id="{{$id}}" title="{{$title}}" mode="{{$mode}}"
                           route="{{$route ?? null}}" editPermission="{{$editPermission ?? false}}"
                           deletePermission="{{$deletePermission ?? false}}"
                           buttonVariant="{{$buttonVariant ?? 'default'}}"
                           deleteUrl="{{$deleteUrl ?? null}}"
                           shareUrl="{{$shareUrl ?? null}}"
                           :edit-attributes="$editAttributes ?? []"/>
    </div>
@endif
