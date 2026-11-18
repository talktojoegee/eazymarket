document.addEventListener('DOMContentLoaded', () => {
    $(function () {
        FilePond.registerPlugin(FilePondPluginImagePreview);
        FilePond.registerPlugin(FilePondPluginFileValidateType);
        FilePond.registerPlugin(FilePondPluginFileValidateSize);
        // Initialize FilePond for serviceAccountFile
        const serviceAccountInput = document.querySelector('[name="serviceAccountFile"]');
        const serviceAccountUrl = serviceAccountInput ? serviceAccountInput.getAttribute('data-service-url') : '';
        FilePond.create(serviceAccountInput, {
            allowImagePreview: false, // Disable image preview for non-image files
            credits: false, storeAsFile: true, acceptedFileTypes: ['application/json', 'text/plain'], // Adjust based on expected file types
            files: serviceAccountUrl ? [{
                source: serviceAccountUrl,
                options: {
                    type: 'remote'
                }
            }] : []
        });

        const systemUpdateInput = document.querySelector('[name="package"]');

        FilePond.create(systemUpdateInput, {
            allowImagePreview: false, // Disable image preview for non-image files
            credits: false,
            storeAsFile: true,
            acceptedFileTypes: [
                'application/zip',
                'application/x-zip-compressed',
                'multipart/x-zip',
            ],
            maxFileSize: '100MB',
            files: []
        })
        const imagesZip = document.querySelector('[name="images-zip"]');
        FilePond.create(imagesZip, {
            allowImagePreview: false, // Disable image preview for non-image files
            credits: false,
            storeAsFile: true,
            acceptedFileTypes: [
                'application/zip',
                'application/x-zip-compressed',
                'multipart/x-zip',
            ],
            maxFileSize: '200MB',
            files: []
        })

        function initializeFilePond(inputName, allowFileTypes = ['image/*'], maxFileSize = null, extraOptions = {}) {
            const input = document.querySelector(`[name="${inputName}"]`);
            if (!input) return;

            const imageUrl = input.getAttribute('data-image-url') || '';
            const fileSizeLimit = maxFileSize || input.getAttribute('data-max-file-size') || null;
            const acceptedFileTypes = input.getAttribute('data-accepted-file-types');
            const circleOptions = input.getAttribute('data-filepond-circle') === '1'
                ? {stylePanelLayout: 'compact circle', stylePanelAspectRatio: '1:1'}
                : {};
            const pond = FilePond.create(input, Object.assign({
                allowImagePreview: true,
                credits: false,
                storeAsFile: true,
                maxFileSize: fileSizeLimit,
                acceptedFileTypes: acceptedFileTypes ? acceptedFileTypes.split(',').map(type => type.trim()) : allowFileTypes,
                files: imageUrl ? [{
                    source: imageUrl,
                    options: {type: 'remote'}
                }] : []
            }, circleOptions, extraOptions));
            input.dispatchEvent(new CustomEvent('filepond:initialized', {detail: {pond}}));
        }

        // Initialize CSV FilePond for Categories Bulk Upload
        (function(){
            const csvInput = document.getElementById('csv-file');
            if (csvInput) {
                FilePond.create(csvInput, {
                    allowImagePreview: false,
                    credits: false,
                    storeAsFile: true,
                    maxFileSize: '10MB',
                    acceptedFileTypes: ['text/csv', 'application/vnd.ms-excel', '.csv'],
                    labelIdle: csvInput.getAttribute('data-label-idle') || 'Drag & Drop your CSV or <span class="filepond--label-action">Browse</span>',
                });
            }
        })();

        initializeFilePond('backgroundImage', ['image/jpeg','image/png','image/jpg','image/webp']);
        initializeFilePond('banner');
        initializeFilePond('profile_image');
        initializeFilePond('photo', ['image/jpeg','image/png','image/webp']);
        initializeFilePond('cover', ['image/jpeg','image/png','image/webp']);
        initializeFilePond('video', ['video/mp4'], null, {allowImagePreview: false});
        initializeFilePond('address_proof');
        initializeFilePond('voided_check');
        initializeFilePond('activeIcon');
        initializeFilePond('image');
        initializeFilePond('active_icon');
        initializeFilePond('icon');
        initializeFilePond('desktop_4k_background_image');
        initializeFilePond('desktop_fdh_background_image');
        initializeFilePond('tablet_background_image');
        initializeFilePond('mobile_background_image');
        initializeFilePond('product_video', ['video/mp4', 'video/mkv', 'video/webm'], '20MB');
        initializeFilePond('siteFavicon');
        initializeFilePond('siteHeaderDarkLogo');
        initializeFilePond('siteHeaderLogo');
        initializeFilePond('siteFooterLogo');
        initializeFilePond('banner_image');
        initializeFilePond('favicon', ['image/png','image/webp']);
        initializeFilePond('logo');
        initializeFilePond('darkLogo');
        initializeFilePond('store_logo');
        initializeFilePond('store_banner');
        initializeFilePond('adminSignature')

        const input = document.querySelector(`[name="additional_images[]"]`);
        if (input) {
            const imagesJson = input.getAttribute('data-images');
            let imageUrls = [];
            if (imagesJson !== null && imagesJson !== '' && imagesJson !== undefined) {
                imageUrls = imagesJson ? JSON.parse(imagesJson) : [];
            }

            FilePond.create(input, {
                allowImagePreview: true,
                credits: false,
                storeAsFile: true,
                maxFileSize: '2MB',
                acceptedFileTypes: ['image/*'],
                files: imageUrls.map(url => ({
                    source: url,
                    options: {
                        type: 'remote'
                    }
                }))
            });
        }
    });
});
