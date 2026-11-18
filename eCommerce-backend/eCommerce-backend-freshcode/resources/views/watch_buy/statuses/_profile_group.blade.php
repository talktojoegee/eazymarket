@php
    $embedded = $embedded ?? false;
    $demoMode = $systemSettings['demoMode'] ?? false;
@endphp

<section class="watch-buy-status-profile-group {{ $embedded ? 'watch-buy-status-profile-group-embedded' : '' }} mb-4">
    @unless($embedded)
        <div class="watch-buy-status-profile-header">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <span class="watch-buy-status-profile-ring flex-shrink-0">
                    @if($profile->photo_url)
                        <span class="avatar avatar-lg rounded-circle" style="background-image: url('{{ $profile->photo_url }}')"></span>
                    @else
                        <span class="avatar avatar-lg rounded-circle bg-primary-lt text-primary">{{ strtoupper(substr($profile->username, 0, 1)) }}</span>
                    @endif
                </span>
                <div class="min-w-0">
                    <h3 class="card-title mb-1 text-truncate">{{ '@'.$profile->username }}</h3>
                    <div class="text-secondary small text-truncate">
                        {{ $profile->profile_type === 'platform' ? __('labels.watch_buy_official_profile') : ($profile->seller?->user?->name ?? __('labels.seller')) }}
                    </div>
                </div>
            </div>
            <span class="badge bg-primary-lt watch-buy-status-group-count"
                  data-singular-label="{{ trans_choice('labels.watch_buy_active_status_count', 1, ['count' => ':count']) }}"
                  data-plural-label="{{ trans_choice('labels.watch_buy_active_status_count', 2, ['count' => ':count']) }}">
                {{ trans_choice('labels.watch_buy_active_status_count', $profile->statuses->count(), ['count' => $profile->statuses->count()]) }}
            </span>
        </div>
    @endunless

    <div class="watch-buy-status-gallery" role="list">
        @foreach($profile->statuses as $status)
            @php
                $contentTypeLabel = match ($status->content_type) {
                    'image' => __('labels.watch_buy_status_image'),
                    'video' => __('labels.watch_buy_status_video'),
                    default => __('labels.watch_buy_status_text_only'),
                };
                $attachedProducts = $status->products->filter(fn ($attachment) => $attachment->product)->map(fn ($attachment) => [
                    'title' => collect([$attachment->product->title, $attachment->variant?->title])->filter()->implode(' / '),
                    'image_url' => $attachment->product->main_image,
                    'url' => $productRoute($attachment->product),
                ])->values();
            @endphp
            <article class="watch-buy-status-card" data-status-id="{{ $status->id }}" role="listitem">
                <div class="watch-buy-status-card-media watch-buy-status-theme-{{ $status->id % 4 }}">
                    @if($status->content_type === 'image' && $status->media_url)
                        <img src="{{ $status->media_url }}" alt="{{ __('labels.watch_buy_status_image') }}" loading="lazy">
                    @elseif($status->content_type === 'video' && $status->media_url)
                        <video src="{{ $status->media_url }}" muted preload="metadata" playsinline tabindex="-1"></video>
                        <span class="watch-buy-status-play-icon"><i class="ti ti-player-play-filled"></i></span>
                    @elseif($status->content_type === 'text')
                        <div class="watch-buy-status-text-only">{{ $status->text }}</div>
                    @else
                        <div class="watch-buy-status-media-unavailable">
                            <i class="ti ti-photo-off"></i>
                            <span>{{ __('labels.watch_buy_status_media_unavailable') }}</span>
                        </div>
                    @endif

                    <button type="button" class="watch-buy-status-preview-trigger"
                            data-bs-toggle="modal"
                            data-bs-target="#watch-buy-status-preview-modal"
                            data-status-id="{{ $status->id }}"
                            data-profile="{{ '@'.$profile->username }}"
                            data-content-type="{{ $status->content_type }}"
                            data-content-type-label="{{ $contentTypeLabel }}"
                            data-media-url="{{ $status->media_url }}"
                            data-text="{{ $status->text }}"
                            data-created-by="{{ __('labels.watch_buy_status_created_by', ['name' => $status->creator?->name ?? __('labels.unknown')]) }}"
                            data-expires-at="{{ $status->expires_at->toIso8601String() }}"
                            data-expires-label="{{ __('labels.watch_buy_status_expires_in') }}"
                            data-demo-mode="{{ $demoMode ? '1' : '0' }}"
                            data-never-expires-label="{{ __('labels.watch_buy_status_never_expires_demo') }}"
                            data-products-label="{{ trans_choice('labels.watch_buy_status_product_count', $status->products->count(), ['count' => $status->products->count()]) }}"
                            data-products="{{ $attachedProducts->toJson() }}"
                            @if($deletePermission)
                                data-delete-url="{{ $deleteRoute($status) }}"
                                data-delete-title="{{ __('labels.watch_buy_delete_status') }}"
                                data-delete-text="{{ __('labels.watch_buy_delete_status_confirmation') }}"
                                data-delete-confirm-text="{{ __('labels.watch_buy_delete_permanently') }}"
                                data-delete-cancel-text="{{ __('labels.cancel') }}"
                                data-delete-error-message="{{ __('labels.something_went_wrong') }}"
                            @endif
                            aria-label="{{ __('labels.watch_buy_preview_status_by', ['username' => '@'.$profile->username]) }}">
                        <span><i class="ti ti-eye"></i>{{ __('labels.watch_buy_view_status') }}</span>
                    </button>

                    <div class="watch-buy-status-card-topbar">
                        <span class="badge bg-dark-lt text-dark">{{ $contentTypeLabel }}</span>
                        @if($deletePermission)
                            <button type="button" class="btn btn-icon btn-sm btn-dark watch-buy-status-delete"
                                    data-status-id="{{ $status->id }}"
                                    data-url="{{ $deleteRoute($status) }}"
                                    data-title="{{ __('labels.watch_buy_delete_status') }}"
                                    data-text="{{ __('labels.watch_buy_delete_status_confirmation') }}"
                                    data-confirm-text="{{ __('labels.watch_buy_delete_permanently') }}"
                                    data-cancel-text="{{ __('labels.cancel') }}"
                                    data-error-message="{{ __('labels.something_went_wrong') }}"
                                    title="{{ __('labels.watch_buy_delete_status') }}"
                                    aria-label="{{ __('labels.watch_buy_delete_status') }}">
                                <i class="ti ti-trash"></i>
                            </button>
                        @endif
                    </div>

                    <div class="watch-buy-status-card-expiry">
                        <i class="ti ti-clock"></i>
                        @if($demoMode)
                            <span>{{ __('labels.watch_buy_status_never_expires_demo') }}</span>
                        @else
                            <span class="watch-buy-status-countdown"
                                  data-expires-at="{{ $status->expires_at->toIso8601String() }}"
                                  data-expires-label="{{ __('labels.watch_buy_status_expires_in') }}"></span>
                        @endif
                    </div>
                </div>

                <div class="watch-buy-status-card-summary">
                    @if($status->text && $status->content_type !== 'text')
                        <div class="watch-buy-status-card-text">{{ $status->text }}</div>
                    @endif
                    <div class="text-secondary small text-truncate">{{ __('labels.watch_buy_status_created_by', ['name' => $status->creator?->name ?? __('labels.unknown')]) }}</div>
                    @if($status->products->isNotEmpty())
                        <div class="watch-buy-status-products">
                            <div class="avatar-list avatar-list-stacked">
                                @foreach($status->products->take(3) as $attachment)
                                    @if($attachment->product?->main_image)
                                        <span class="avatar avatar-xs rounded" style="background-image: url('{{ $attachment->product->main_image }}')" title="{{ $attachment->product->title }}"></span>
                                    @else
                                        <span class="avatar avatar-xs rounded bg-secondary-lt"><i class="ti ti-shopping-bag"></i></span>
                                    @endif
                                @endforeach
                            </div>
                            <span>{{ trans_choice('labels.watch_buy_status_product_count', $status->products->count(), ['count' => $status->products->count()]) }}</span>
                        </div>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    <div class="watch-buy-status-inline-empty d-none">
        <i class="ti ti-clock-hour-4"></i>
        <span>{{ __('labels.watch_buy_no_active_statuses') }}</span>
    </div>
</section>
