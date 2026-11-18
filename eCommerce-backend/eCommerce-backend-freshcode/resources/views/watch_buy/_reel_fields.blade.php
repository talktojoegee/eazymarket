<div class="mb-3">
    <label class="form-label">{{ __('labels.watch_buy_caption') }}</label>
    <textarea class="form-control" name="caption" rows="4" maxlength="2000">{{ old('caption', $reel->caption) }}</textarea>
    <small class="form-hint">{{ __('labels.watch_buy_caption_hint') }}</small>
    @error('caption')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label {{ $reel->exists ? '' : 'required' }}">{{ __('labels.watch_buy_mp4_video') }}</label>
            <input type="file" class="form-control" name="video" accept="video/mp4" data-max-file-size="{{ $maxUploadMb }}MB" @required(!$reel->exists)>
            <small class="form-hint">{{ __('labels.watch_buy_video_hint', ['size' => $maxUploadMb]) }}</small>
            @if($reel->exists && $reel->video_url)
                <a href="{{ $reel->video_url }}" target="_blank" class="btn btn-sm btn-outline-info mt-2" data-fslightbox="watch-buy-current-reel" data-type="video"><i class="ti ti-player-play me-1"></i>{{ __('labels.watch_buy_preview_current_video') }}</a>
            @endif
            @error('video')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label {{ $reel->exists ? '' : 'required' }}">{{ __('labels.watch_buy_cover_image') }}</label>
            <input type="file" class="form-control" name="cover" accept="image/jpeg,image/png,image/webp" data-image-url="{{ $reel->cover_url ?? '' }}" data-max-file-size="10MB" @required(!$reel->exists)>
            <small class="form-hint">{{ __('labels.watch_buy_cover_hint') }}</small>
            @error('cover')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

@php
    $selectedAttachments = array_map('intval', old('attachment_ids', $productVariantSelection['attachment_ids']));
    $primaryAttachment = (int) old('primary_attachment_id', $productVariantSelection['primary_attachment_id']);
@endphp

<div class="mt-3">
    <h4>{{ __('labels.watch_buy_product_attachments') }}</h4>
    <p class="text-secondary">{{ __('labels.watch_buy_product_attachments_hint') }}</p>
    <div class="row">
        <div class="col-lg-8 mb-3">
            <label class="form-label required">{{ __('labels.products') }}</label>
            <select class="form-select js-tom-select-product" name="attachment_ids[]"
                    data-endpoint="{{ $productOptionsEndpoint }}"
                    data-selected='@json($selectedAttachments)'
                    data-placeholder="{{ __('labels.watch_buy_search_eligible_products') }}"
                    data-preload="focus"
                    data-preload-limit="{{ $maxProductsPerReel }}"
                    data-image-style="1"
                    data-max-items="{{ $maxProductsPerReel }}"
                    data-primary-select="#watch-buy-primary-product"
                    @if(!empty($productSourceSelector)) data-source-selector="{{ $productSourceSelector }}" @endif
                    @if(!empty($productProfileSelector)) data-profile-selector="{{ $productProfileSelector }}" @endif
                    multiple required></select>
            <small class="form-hint">{{ __('labels.watch_buy_product_search_hint', ['max' => $maxProductsPerReel]) }}</small>
            @error('attachment_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @error('attachment_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-4 mb-3">
            <label class="form-label required">{{ __('labels.watch_buy_primary_product') }}</label>
            <select class="form-select" name="primary_attachment_id" id="watch-buy-primary-product" data-selected="{{ $primaryAttachment ?: '' }}" data-placeholder="{{ __('labels.watch_buy_select_primary_product') }}" required>
                <option value="">{{ __('labels.watch_buy_select_primary_product') }}</option>
            </select>
            <small class="form-hint">{{ __('labels.watch_buy_primary_product_hint') }}</small>
            @error('primary_attachment_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
