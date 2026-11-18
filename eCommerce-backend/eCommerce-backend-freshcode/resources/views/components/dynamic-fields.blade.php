<div class="row">
    @foreach($fields as $field)
        @php
            $name = $prefix.'['.$field->key.']';
            $errorKey = $prefix.'.'.$field->key;
            $legacyValueKey = '__legacy_'.preg_replace('/^(?:m\d+|[a-z]{2})_/', '', $field->key);
            $fieldValue = $values[$field->key] ?? $values[$legacyValueKey] ?? $field->default_value ?? '';
            $value = old($errorKey, $fieldValue);
            $options = is_array($field->options) ? $field->options : [];
        @endphp
        <div class="col-md-6 mb-3">
            <label class="form-label {{ $field->required ? 'required' : '' }}" for="{{ $prefix }}_{{ $field->key }}">{{ $field->label }}</label>
            @if($field->type === 'select')
                <select id="{{ $prefix }}_{{ $field->key }}" name="{{ $name }}" class="form-select @error($errorKey) is-invalid @enderror" @required($field->required)>
                    <option value="">{{ $field->placeholder ?: __('Select :field', ['field' => $field->label]) }}</option>
                    @if(filled($value) && !in_array((string)$value, array_map('strval', $options), true))
                        <option value="{{ $value }}" selected>{{ $value }}</option>
                    @endif
                    @foreach($options as $option)
                        <option value="{{ $option }}" @selected((string)$value === (string)$option)>{{ $option }}</option>
                    @endforeach
                </select>
            @elseif($field->type === 'image')
                <input id="{{ $prefix }}_{{ $field->key }}" type="file" name="{{ $name }}" class="form-control js-dynamic-filepond @error($errorKey) is-invalid @enderror" data-image-url="{{ $fieldValue }}" data-filepond-placeholder="{{ $field->placeholder ?? '' }}" accept="image/jpeg,image/png,image/webp,image/jpg" @required($field->required)>
            @else
                <input id="{{ $prefix }}_{{ $field->key }}" type="{{ $field->type }}" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $field->placeholder ?? '' }}" class="form-control @error($errorKey) is-invalid @enderror" @required($field->required)>
            @endif
            @error($errorKey)<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @endforeach
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.FilePond || !window.FilePondPluginImagePreview) return;
        FilePond.registerPlugin(FilePondPluginImagePreview);
        document.querySelectorAll('.js-dynamic-filepond:not([data-filepond-ready])').forEach(function (input) {
            input.dataset.filepondReady = '1';
            const url = input.dataset.imageUrl;
            const uploadName = input.getAttribute('name');
            if (!uploadName) return;
            input.setAttribute('name', uploadName);
            FilePond.create(input, {
                name: uploadName,
                allowImagePreview: true,
                acceptedFileTypes: ['image/jpeg', 'image/png', 'image/webp'],
                maxFileSize: '5MB',
                labelIdle: input.dataset.filepondPlaceholder || 'Drop or browse an image',
                credits: false,
                storeAsFile: true,
                files: url ? [{ source: url, options: { type: 'remote' } }] : []
            });
        });
    });
</script>
