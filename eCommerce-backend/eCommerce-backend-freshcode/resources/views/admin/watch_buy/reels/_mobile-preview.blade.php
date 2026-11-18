<section class="watch-buy-reel-preview" aria-label="{{ __('labels.preview') }}">
    <h4 class="watch-buy-reel-preview__heading">{{ __('labels.preview') }}</h4>
    <div class="watch-buy-iphone">
        <div class="watch-buy-iphone__screen watch-buy-reel-phone__screen">
            <img class="watch-buy-reel-phone__cover {{ $coverUrl ? '' : 'd-none' }}" data-reel-cover src="{{ $coverUrl ?? '' }}" alt="">
            <video class="watch-buy-reel-phone__video {{ $videoUrl ? '' : 'd-none' }}" data-reel-video data-original-src="{{ $videoUrl ?? '' }}" playsinline controls></video>
            <div class="watch-buy-reel-phone__empty {{ $videoUrl ? 'd-none' : '' }}" data-reel-video-empty>
                <i class="ti ti-video-plus" aria-hidden="true"></i>
                <span>{{ __('labels.watch_buy_mp4_video') }}</span>
            </div>
            <div class="watch-buy-iphone__island" aria-hidden="true"></div>
            <div class="watch-buy-iphone__status" aria-hidden="true"><span>9:41</span><span><i class="ti ti-signal-4"></i><i class="ti ti-wifi"></i><i class="ti ti-battery-3"></i></span></div>
            <div class="watch-buy-reel-phone__topbar">
                <span class="watch-buy-reel-phone__avatar">
                    @if(!empty($profilePhotoUrl))
                        <img src="{{ $profilePhotoUrl }}" alt="">
                    @else
                        <i class="ti ti-user" aria-hidden="true"></i>
                    @endif
                </span>
                <span class="watch-buy-reel-phone__profile" data-reel-profile data-official-profile="{{ __('labels.watch_buy_official_hypercommerce_profile') }}">{{ $profileName }}</span>
                <i class="ti ti-dots-vertical" aria-hidden="true"></i>
            </div>
            <div class="watch-buy-reel-phone__actions" aria-hidden="true">
                <i class="ti ti-heart"></i>
                <i class="ti ti-message-circle"></i>
                <i class="ti ti-send"></i>
            </div>
            <div class="watch-buy-reel-phone__details">
                <p class="watch-buy-reel-phone__caption" data-reel-caption>{{ old('caption', $reel->caption) ?: __('labels.watch_buy_caption') }}</p>
                <div class="watch-buy-reel-phone__products" data-reel-products data-empty-label="{{ __('labels.watch_buy_product_attachments') }}">
                    <span class="watch-buy-reel-phone__product-empty">{{ __('labels.watch_buy_product_attachments') }}</span>
                </div>
            </div>
            <div class="watch-buy-iphone__home-indicator" aria-hidden="true"></div>
        </div>
    </div>
</section>
