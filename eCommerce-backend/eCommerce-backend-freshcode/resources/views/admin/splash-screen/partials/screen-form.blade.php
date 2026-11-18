@php
    $prefix = 'splash-'.$surface.'-'.$slot;
    $fileUrl = fn (?string $path) => $path ? Storage::disk('public')->url($path) : '';
@endphp
<form class="card {{ $canEdit ? 'form-submit' : '' }} splash-config-form" action="{{ route('admin.splash-screen.save') }}" method="POST" enctype="multipart/form-data" data-surface="{{ $surface }}" data-slot="{{ $slot }}">
        @csrf
        <input type="hidden" name="surface" value="{{ $surface }}">
        <input type="hidden" name="slot" value="{{ $slot }}">
        <div class="card-header">
            <div>
                <h3 class="card-title">{{ $slotLabel }}</h3>
                <div class="text-secondary small">{{ $surfaceLabel }}</div>
            </div>
            <div class="card-actions align-items-center">
                <span class="badge {{ $screen ? 'bg-success-lt' : 'bg-secondary-lt' }}">{{ $screen ? __('labels.configured') : __('labels.not_configured') }}</span>
                @if($canEdit && $screen)
                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove-splash-screen data-url="{{ route('admin.splash-screen.delete', ['surface' => $surface, 'slot' => $slot]) }}">
                        <i class="ti ti-trash icon" aria-hidden="true"></i>{{ __('labels.remove_splash_screen') }}
                    </button>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if($slot === 'staged')
                <div class="mb-3">
                    <label class="form-label required" for="{{ $prefix }}-date">{{ __('labels.scheduled_date') }}</label>
                    <input class="form-control" id="{{ $prefix }}-date" type="date" name="scheduled_date" value="{{ old('scheduled_date', $screen?->scheduled_date?->toDateString()) }}" required {{ $canEdit ? '' : 'disabled' }}>
                </div>
            @endif
            <div class="mb-3">
                <label class="form-label required">{{ __('labels.background_media_type') }}</label>
                <select class="form-select" name="background_type" data-splash-background-type required {{ $canEdit ? '' : 'disabled' }}>
                    @foreach(['image' => __('labels.image'), 'color' => __('labels.color'), 'video' => __('labels.background_video')] as $type => $label)
                        <option value="{{ $type }}" @selected(old('background_type', $screen?->background_type ?? 'color') === $type)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3 d-none" data-splash-field="image">
                <label class="form-label" for="{{ $prefix }}-background-image">{{ __('labels.background_image') }}</label>
                <input class="form-control splash-filepond" id="{{ $prefix }}-background-image" type="file" name="background_image" accept="image/jpeg,image/png,image/webp" data-filepond-limit="10MB" {{ $canEdit ? '' : 'disabled' }}>
                @if($screen?->background_image_path)
                    <div class="splash-saved-media mt-2" data-splash-saved-media="background">
                        <img class="img-thumbnail splash-current-media" src="{{ $fileUrl($screen->background_image_path) }}" alt="{{ __('labels.current_image') }}">
                        @if($canEdit)<button type="button" class="btn btn-sm btn-outline-danger mt-2" data-remove-splash-media="background">{{ __('labels.remove_background_image') }}</button>@endif
                    </div>
                @endif
            </div>
            <div class="mb-3 d-none" data-splash-field="video">
                <label class="form-label" for="{{ $prefix }}-background-video">{{ __('labels.background_video') }}</label>
                <input class="form-control splash-filepond" id="{{ $prefix }}-background-video" type="file" name="background_video" accept="video/mp4,video/webm,video/quicktime" data-filepond-limit="50MB" data-filepond-preview="false" {{ $canEdit ? '' : 'disabled' }}>
                @if($screen?->background_video_path)<video class="splash-current-media mt-2" src="{{ $fileUrl($screen->background_video_path) }}" controls></video>@endif
            </div>
            <div class="mb-3 d-none" data-splash-field="color">
                <label class="form-label required" for="{{ $prefix }}-background-color">{{ __('labels.background_color') }}</label>
                <input class="form-control form-control-color" id="{{ $prefix }}-background-color" type="color" name="background_color" value="{{ old('background_color', $screen?->background_color ?? '#f8f8fc') }}" {{ $canEdit ? '' : 'disabled' }}>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" id="{{ $prefix }}-overlay-enabled" type="checkbox" value="1" data-splash-overlay-toggle @checked(old('overlay_enabled', $screen?->overlay_enabled ?? false)) {{ $canEdit ? '' : 'disabled' }}>
                <label class="form-check-label" for="{{ $prefix }}-overlay-enabled">{{ __('labels.overlay_enabled') }}</label>
                <input type="hidden" name="overlay_enabled" value="{{ old('overlay_enabled', $screen?->overlay_enabled ?? false) ? 1 : 0 }}" data-splash-overlay-value>
            </div>
            <div class="splash-overlay-fields d-none" data-splash-overlay-fields>
                <div class="mb-3">
                    <label class="form-label required" for="{{ $prefix }}-overlay-type">{{ __('labels.overlay_type') }}</label>
                    <select class="form-select" id="{{ $prefix }}-overlay-type" name="overlay_type" data-splash-overlay-type {{ $canEdit ? '' : 'disabled' }}>
                        <option value="image" @selected(old('overlay_type', $screen?->overlay_type ?? 'image') === 'image')>{{ __('labels.image') }}</option>
                        <option value="lottie" @selected(old('overlay_type', $screen?->overlay_type) === 'lottie')>{{ __('labels.lottie_animation') }}</option>
                    </select>
                </div>
                <div class="mb-3 d-none" data-splash-field="overlay-image">
                    <label class="form-label" for="{{ $prefix }}-overlay-image">{{ __('labels.image') }}</label>
                    <input class="form-control splash-filepond" id="{{ $prefix }}-overlay-image" type="file" name="overlay_image" accept="image/jpeg,image/png,image/webp" data-filepond-limit="10MB" {{ $canEdit ? '' : 'disabled' }}>
                    @if($screen?->overlay_image_path)
                        <div class="splash-saved-media mt-2" data-splash-saved-media="overlay">
                            <img class="img-thumbnail splash-current-media" src="{{ $fileUrl($screen->overlay_image_path) }}" alt="{{ __('labels.current_image') }}">
                            @if($canEdit)<button type="button" class="btn btn-sm btn-outline-danger mt-2" data-remove-splash-media="overlay">{{ __('labels.remove_overlay_image') }}</button>@endif
                        </div>
                    @endif
                </div>
                <div class="mb-3 d-none" data-splash-field="overlay-lottie">
                    <label class="form-label" for="{{ $prefix }}-overlay-lottie">{{ __('labels.lottie_animation') }}</label>
                    <input class="form-control splash-filepond" id="{{ $prefix }}-overlay-lottie" type="file" name="overlay_lottie" accept="application/json,.json" data-filepond-limit="5MB" data-filepond-preview="false" {{ $canEdit ? '' : 'disabled' }}>
                    @if($screen?->overlay_lottie_path)
                        <a class="d-block mt-2" href="{{ $fileUrl($screen->overlay_lottie_path) }}" target="_blank" rel="noopener">{{ __('labels.current_file') }}</a>
                        @if($canEdit)<button type="button" class="btn btn-sm btn-outline-danger mt-2" data-remove-splash-media="overlay">{{ __('labels.remove_overlay_media') }}</button>@endif
                    @endif
                </div>
                <div class="row g-3">
                    <div class="col-12 col-lg-4"><x-range-field :id="$prefix.'-horizontal'" name="overlay_horizontal_position" :label="__('labels.horizontal_position')" :value="old('overlay_horizontal_position', $screen?->overlay_horizontal_position ?? 50)" min="0" max="100" :disabled="!$canEdit" /></div>
                    <div class="col-12 col-lg-4"><x-range-field :id="$prefix.'-vertical'" name="overlay_vertical_position" :label="__('labels.vertical_position')" :value="old('overlay_vertical_position', $screen?->overlay_vertical_position ?? 50)" min="0" max="100" :disabled="!$canEdit" /></div>
                    <div class="col-12 col-lg-4"><x-range-field :id="$prefix.'-size'" name="overlay_size" :label="__('labels.overlay_size')" :value="old('overlay_size', $screen?->overlay_size ?? 40)" min="5" max="100" :disabled="!$canEdit" /></div>
                </div>
            </div>
            <div class="mt-3">
                <label class="form-label required" for="{{ $prefix }}-seconds">{{ __('labels.minimum_display_seconds') }}</label>
                <input class="form-control" id="{{ $prefix }}-seconds" type="number" min="1" max="600" name="min_display_seconds" value="{{ old('min_display_seconds', $screen?->min_display_seconds ?? 3) }}" required {{ $canEdit ? '' : 'disabled' }}>
            </div>
        </div>
        @if($canEdit)
            <div class="card-footer d-flex justify-content-end gap-2">
                <button type="reset" class="btn btn-outline-secondary">{{ __('labels.cancel') }}</button>
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy icon" aria-hidden="true"></i>{{ __('labels.save_splash') }}</button>
            </div>
        @endif
</form>
