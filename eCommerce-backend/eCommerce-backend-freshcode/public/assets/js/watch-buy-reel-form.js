document.addEventListener('DOMContentLoaded', function () {
    const source = document.getElementById('watch-buy-source');
    const profileField = document.getElementById('watch-buy-profile-field');
    const form = document.querySelector('.form-submit [name="caption"]')?.closest('form');
    if (!form) {
        return;
    }

    const profile = document.getElementById('watch-buy-profile');
    const caption = form.querySelector('[name="caption"]');
    const videoInput = form.querySelector('[name="video"]');
    const coverInput = form.querySelector('[name="cover"]');
    const productSelect = form.querySelector('[name="attachment_ids[]"]');
    const primarySelect = document.getElementById('watch-buy-primary-product');
    const previewVideo = document.querySelector('[data-reel-video]');
    const previewVideoEmpty = document.querySelector('[data-reel-video-empty]');
    const previewCover = document.querySelector('[data-reel-cover]');
    const previewCaption = document.querySelector('[data-reel-caption]');
    const previewProfile = document.querySelector('[data-reel-profile]');
    const previewProducts = document.querySelector('[data-reel-products]');
    const defaultCaption = previewCaption?.textContent || '';
    const defaultProfile = previewProfile?.textContent || '';
    let videoObjectUrl = null;
    let coverObjectUrl = null;
    let selectedVideoFile = null;
    let selectedCoverFile = null;

    const syncProfileField = function () {
        const direct = source?.value === 'admin_direct';
        if (profileField && source) {
            profileField.classList.toggle('d-none', direct);
            const profileSelect = profileField.querySelector('select');
            if (profileSelect) profileSelect.required = !direct;
        }
        if (previewProfile) {
            const profileName = profile?.selectedOptions[0]?.textContent?.trim();
            previewProfile.textContent = direct
                ? previewProfile.dataset.officialProfile
                : profileName || defaultProfile;
        }
    };

    const updateVideo = function () {
        if (!previewVideo || !previewVideoEmpty) return;
        const file = selectedVideoFile || videoInput?.files?.[0];
        if (videoObjectUrl) {
            URL.revokeObjectURL(videoObjectUrl);
            videoObjectUrl = null;
        }
        if (file) {
            videoObjectUrl = URL.createObjectURL(file);
            previewVideo.replaceChildren();
            previewVideo.src = videoObjectUrl;
            previewVideo.classList.remove('d-none');
            previewVideoEmpty.classList.add('d-none');
            previewVideo.load();
            return;
        }
        const originalUrl = previewVideo.dataset.originalSrc;
        if (originalUrl) {
            previewVideo.src = originalUrl;
            previewVideo.classList.remove('d-none');
            previewVideoEmpty.classList.add('d-none');
        } else {
            previewVideo.removeAttribute('src');
            previewVideo.classList.add('d-none');
            previewVideoEmpty.classList.toggle('d-none', Boolean(previewCover?.getAttribute('src')));
        }
        previewVideo.load();
    };

    const updateCover = function () {
        if (!previewVideo) return;
        const file = selectedCoverFile || coverInput?.files?.[0];
        if (coverObjectUrl) {
            URL.revokeObjectURL(coverObjectUrl);
            coverObjectUrl = null;
        }
        if (file) {
            coverObjectUrl = URL.createObjectURL(file);
            previewVideo.poster = coverObjectUrl;
            if (previewCover) {
                previewCover.src = coverObjectUrl;
                previewCover.classList.remove('d-none');
            }
            previewVideoEmpty?.classList.add('d-none');
            return;
        }
        const coverUrl = coverInput?.dataset.imageUrl;
        if (coverUrl) {
            previewVideo.poster = coverUrl;
            if (previewCover) {
                previewCover.src = coverUrl;
                previewCover.classList.remove('d-none');
            }
        } else {
            previewVideo.removeAttribute('poster');
            previewCover?.removeAttribute('src');
            previewCover?.classList.add('d-none');
            if (!videoInput?.files?.length && !previewVideo.dataset.originalSrc) {
                previewVideoEmpty?.classList.remove('d-none');
            }
        }
    };

    const updateCaption = function () {
        if (previewCaption) {
            previewCaption.textContent = caption?.value.trim() || defaultCaption;
        }
    };

    const updateProducts = function () {
        if (!previewProducts || !productSelect) return;
        previewProducts.replaceChildren();
        const selectedValues = Array.from(productSelect.selectedOptions).map(function (option) {
            return {value: option.value, text: option.textContent.trim()};
        });
        const primaryValue = primarySelect?.value;
        const orderedProducts = selectedValues.sort(function (left, right) {
            return left.value === primaryValue ? -1 : right.value === primaryValue ? 1 : 0;
        });
        if (orderedProducts.length === 0) {
            const empty = document.createElement('span');
            empty.className = 'watch-buy-reel-phone__product-empty';
            empty.textContent = previewProducts.dataset.emptyLabel;
            previewProducts.append(empty);
            return;
        }
        orderedProducts.slice(0, 2).forEach(function (product) {
            const item = document.createElement('span');
            item.className = 'watch-buy-reel-phone__product';
            item.textContent = product.text;
            previewProducts.append(item);
        });
        if (orderedProducts.length > 2) {
            const more = document.createElement('span');
            more.className = 'watch-buy-reel-phone__product';
            more.textContent = '+' + (orderedProducts.length - 2);
            previewProducts.append(more);
        }
    };

    source?.addEventListener('change', syncProfileField);
    profile?.addEventListener('change', syncProfileField);
    caption?.addEventListener('input', updateCaption);
    videoInput?.addEventListener('change', updateVideo);
    coverInput?.addEventListener('change', updateCover);
    productSelect?.addEventListener('change', updateProducts);
    primarySelect?.addEventListener('change', updateProducts);

    const bindFilePondPreview = function (input, setFile, updatePreview) {
        if (!input) return;
        let attempts = 0;
        const bind = function () {
            const pond = window.FilePond?.find(input);
            if (pond) {
                pond.on('addfile', function (error, item) {
                    if (error) return;
                    setFile(item?.file || null);
                    updatePreview();
                });
                pond.on('removefile', function () {
                    const currentFile = pond.getFile()?.file || null;
                    setFile(currentFile);
                    updatePreview();
                });
                setFile(pond.getFile()?.file || null);
                updatePreview();
                return;
            }
            attempts += 1;
            if (attempts < 40) window.setTimeout(bind, 50);
        };
        bind();
    };

    bindFilePondPreview(videoInput, function (file) {
        selectedVideoFile = file;
    }, updateVideo);
    bindFilePondPreview(coverInput, function (file) {
        selectedCoverFile = file;
    }, updateCover);
    syncProfileField();
    updateCaption();
    updateVideo();
    updateCover();
    updateProducts();
});
