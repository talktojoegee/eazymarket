@if($editPermission || ($suspendPermission && $profile->profile_type === 'seller'))
    <div class="dropdown">
        <button type="button" class="btn btn-outline-primary dropdown-toggle text-nowrap" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="ti ti-adjustments-horizontal me-1"></i>{{ __('labels.more_actions') }}
        </button>
        <div class="dropdown-menu dropdown-menu-end">
            @if($editPermission)
                <a href="{{ $profile->profile_type === 'platform' ? route('admin.watch-and-buy.official-profile.edit') : route('admin.watch-and-buy.profiles.edit', $profile) }}" class="dropdown-item">
                    <i class="ti ti-pencil me-2 text-primary"></i>{{ __('labels.edit') }}
                </a>
            @endif
            @if($suspendPermission && $profile->profile_type === 'seller')
                @if($profile->status === 'suspended')
                    <form method="post" action="{{ route('admin.watch-and-buy.profiles.unsuspend', $profile) }}" class="watch-buy-action-form" data-error-message="{{ __('labels.something_went_wrong') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="ti ti-user-check me-2 text-success"></i>{{ __('labels.watch_buy_unsuspend') }}
                        </button>
                    </form>
                @else
                    <form method="post" action="{{ route('admin.watch-and-buy.profiles.suspend', $profile) }}" class="watch-buy-action-form watch-buy-reason-form" data-error-message="{{ __('labels.something_went_wrong') }}">
                        @csrf
                        <input type="hidden" name="reason">
                        <button type="button" class="dropdown-item watch-buy-reason-action"
                                data-title="{{ __('labels.watch_buy_suspend_profile_title') }}"
                                data-placeholder="{{ __('labels.watch_buy_suspension_reason_placeholder') }}"
                                data-confirm-text="{{ __('labels.watch_buy_confirm') }}"
                                data-cancel-text="{{ __('labels.cancel') }}"
                                data-reason-required="{{ __('labels.watch_buy_reason_required') }}">
                            <i class="ti ti-user-pause me-2 text-warning"></i>{{ __('labels.watch_buy_suspend') }}
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>
@else
    <span class="text-muted">—</span>
@endif
