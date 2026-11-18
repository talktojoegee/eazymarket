<div>
    <input
        type="file"
        class="form-control"
        id="{{ $id ?? $name }}"
        name="{{ $name }}"
        data-image-url="{{ $imageUrl ?? '' }}"
        disabled="{{ $disabled ?? 'false' }}"
        multiple="{{ $multiple ?? 'false' }}"
    />
    @if (($name ?? '') === 'main_image')
        <input type="hidden" name="main_image_changed" value="0" data-main-image-changed>
    @endif
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        FilePond.registerPlugin(FilePondPluginImagePreview);
        const input = document.querySelector('[name="{{ $name }}"]');
        if (input) {
            const imageUrl = input.getAttribute('data-image-url');
            const changeMarker = input.parentElement.querySelector('[data-main-image-changed]');
            const form = input.closest('form');
            const pond = FilePond.create(input, {
                allowImagePreview: true,
                instantUpload: false,
                acceptedFileTypes: ['image/*'],
                credits: false,
                storeAsFile: true,
                files: imageUrl ? [{
                    source: imageUrl, options: {
                        type: 'remote'
                    }
                }] : []
            });

            if (changeMarker) {
                pond.on('addfile', function (error, item) {
                    if (!error && item && item.source !== imageUrl) {
                        changeMarker.value = '1';
                    }
                });

                form?.addEventListener('formdata', function (event) {
                    if (imageUrl && changeMarker.value !== '1') {
                        event.formData.delete(input.name);
                    }
                });
            }
        }
    });
</script>
