@once
    @push('styles')
        <link rel="stylesheet" href="{{ hyperAsset('assets/css/media-control.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ hyperAsset('assets/js/media-control.js') }}"></script>
    @endpush
@endonce

<div
    id="{{ $id }}"
    {{ $attributes->class(['media-control']) }}
    data-media-control
    data-media-control-preview-url="{{ $previewUrl }}"
    data-media-control-show-config="{{ $showConfig ? '1' : '0' }}"
>
    <div class="media-control__header">
        <label for="{{ $id }}-file">{{ $label }}</label>
        <span class="media-control__type-badge" data-media-control-type-badge>{{ $mediaType }}</span>
    </div>

    <div class="media-control__pond">
        <input
            id="{{ $id }}-file"
            class="media-control__file"
            type="file"
            name="{{ $name }}"
            data-media-control-file
            @required($required)
            @disabled($disabled)
        >
    </div>
    <input type="hidden" name="{{ $removeName }}" value="0" data-media-control-remove>

    <div class="media-control__grid" data-media-control-config @if (! $showConfig || ! $previewUrl) hidden @endif>
        <label class="media-control__field">
            <span>Media type</span>
            <select name="{{ $configName }}[media_type]" data-media-control-field="media_type" @disabled($disabled)>
                @foreach ($mediaTypeOptions as $value => $text)
                    <option value="{{ $value }}" @selected($value === $mediaType)>{{ $text }}</option>
                @endforeach
            </select>
        </label>

        <label class="media-control__field">
            <span>Aspect ratio</span>
            <select name="{{ $configName }}[aspect_ratio]" data-media-control-field="aspect_ratio" @disabled($disabled)>
                @foreach ($aspectRatioOptions as $value => $text)
                    <option value="{{ $value }}" @selected($value === $aspectRatio)>{{ $text }}</option>
                @endforeach
            </select>
        </label>

        <label class="media-control__field">
            <span>SEO alt text</span>
            <input
                type="text"
                name="{{ $configName }}[alt]"
                value="{{ $seoAlt }}"
                placeholder="Describe the media"
                data-media-control-field="alt"
                @disabled($disabled)
            >
        </label>

        <div class="media-control__custom-size" data-media-control-custom-size @if ($aspectRatio !== 'custom') hidden @endif>
            <span>Custom frame size</span>
            <div>
                <label>
                    <span>Width</span>
                    <div>
                        <input type="number" min="0.1" max="10000" step="0.1" name="{{ $configName }}[aspect_width]" value="{{ $aspectWidth }}" data-media-control-field="aspect_width" @disabled($disabled)>
                        <select name="{{ $configName }}[aspect_width_unit]" data-media-control-field="aspect_width_unit" @disabled($disabled)>
                            @foreach ($dimensionUnitOptions as $value => $text)
                                <option value="{{ $value }}" @selected($value === $aspectWidthUnit)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label>
                    <span>Height</span>
                    <div>
                        <input type="number" min="0.1" max="10000" step="0.1" name="{{ $configName }}[aspect_height]" value="{{ $aspectHeight }}" data-media-control-field="aspect_height" @disabled($disabled)>
                        <select name="{{ $configName }}[aspect_height_unit]" data-media-control-field="aspect_height_unit" @disabled($disabled)>
                            @foreach ($dimensionUnitOptions as $value => $text)
                                <option value="{{ $value }}" @selected($value === $aspectHeightUnit)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
            </div>
            <small>Use 100% × 300px for a responsive full-width frame.</small>
        </div>

        <label class="media-control__field">
            <span>Image fit</span>
            <select name="{{ $configName }}[object_fit]" data-media-control-field="object_fit" @disabled($disabled)>
                @foreach ($imageFitOptions as $value => $text)
                    <option value="{{ $value }}" @selected($value === $imageFit)>{{ $text }}</option>
                @endforeach
            </select>
        </label>

        <label class="media-control__field">
            <span>Image focus</span>
            <select name="{{ $configName }}[object_position]" data-media-control-field="object_position" @disabled($disabled)>
                @foreach ($imageFocusOptions as $value => $text)
                    <option value="{{ $value }}" @selected($value === $imageFocus)>{{ $text }}</option>
                @endforeach
            </select>
        </label>
    </div>
</div>
