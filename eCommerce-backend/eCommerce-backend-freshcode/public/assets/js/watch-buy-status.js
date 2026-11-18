document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const formatCountdown = function (expiresAt, label) {
        const remaining = Math.max(0, new Date(expiresAt).getTime() - Date.now());
        const hours = Math.floor(remaining / 3600000);
        const minutes = Math.floor((remaining % 3600000) / 60000);

        return label.replace(':hours', hours).replace(':minutes', minutes);
    };

    const truncateText = function (text, maxLength) {
        return text.length > maxLength ? text.slice(0, maxLength).trimEnd() + '…' : text;
    };

    const updateCountdowns = function () {
        document.querySelectorAll('.watch-buy-status-countdown').forEach(function (element) {
            element.textContent = formatCountdown(element.dataset.expiresAt, element.dataset.expiresLabel);
        });
    };

    const searchForm = document.querySelector('[data-status-search-form]');
    const searchResults = document.querySelector('[data-status-results]');

    if (searchForm && searchResults) {
        const searchInput = searchForm.querySelector('input[name="search"]');
        const clearButton = searchForm.querySelector('.watch-buy-status-search-clear');
        let searchRequest;

        const loadSearchResults = function (url) {
            searchRequest?.abort();
            const request = new AbortController();
            searchRequest = request;
            searchForm.querySelectorAll('button').forEach(function (button) {
                button.disabled = true;
            });

            fetch(url, {
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'},
                signal: request.signal,
            }).then(function (response) {
                if (!response.ok) throw new Error(searchForm.dataset.errorMessage);
                return response.text();
            }).then(function (html) {
                const documentResult = new DOMParser().parseFromString(html, 'text/html');
                const replacement = documentResult.querySelector('[data-status-results]');
                if (!replacement) throw new Error(searchForm.dataset.errorMessage);
                searchResults.innerHTML = replacement.innerHTML;
                clearButton.classList.toggle('d-none', !searchInput.value.trim());
                window.history.replaceState({}, '', url);
                updateCountdowns();
            }).catch(function (error) {
                if (error.name !== 'AbortError') {
                    Toast.fire({icon: 'error', title: error.message || searchForm.dataset.errorMessage});
                }
            }).finally(function () {
                if (searchRequest !== request) return;
                searchForm.querySelectorAll('button').forEach(function (button) {
                    button.disabled = false;
                });
            });
        };

        searchForm.addEventListener('submit', function (event) {
            event.preventDefault();
            const url = new URL(searchForm.action || window.location.href, window.location.origin);
            const search = searchInput.value.trim();
            url.search = '';
            if (search) url.searchParams.set('search', search);
            loadSearchResults(url.toString());
        });

        clearButton.addEventListener('click', function () {
            searchInput.value = '';
            searchForm.requestSubmit();
            searchInput.focus();
        });
    }

    document.querySelectorAll('.watch-buy-status-form').forEach(function (form) {
        const source = form.querySelector('.watch-buy-status-source');
        const profileField = form.querySelector('.watch-buy-status-profile-field');
        const profile = profileField?.querySelector('select');
        const mediaInput = form.querySelector('.watch-buy-status-media');

        const syncProfile = function () {
            if (!source || !profileField || !profile) return;
            const sellerBehalf = source.value === 'seller_behalf';
            profileField.classList.toggle('d-none', !sellerBehalf);
            profile.required = sellerBehalf;
            if (!sellerBehalf && profile.tomselect) profile.tomselect.clear(true);
        };

        if (window.FilePond && mediaInput) {
            const plugins = [window.FilePondPluginImagePreview, window.FilePondPluginFileValidateType, window.FilePondPluginFileValidateSize].filter(Boolean);
            if (plugins.length) window.FilePond.registerPlugin(...plugins);
            window.FilePond.create(mediaInput, {
                credits: false,
                storeAsFile: true,
                allowImagePreview: true,
                acceptedFileTypes: ['image/jpeg', 'image/png', 'image/webp', 'video/mp4'],
                maxFileSize: mediaInput.dataset.maxFileSize,
                labelIdle: mediaInput.dataset.idleLabel,
            });
        }

        source?.addEventListener('change', syncProfile);
        syncProfile();

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (form.dataset.submitting === '1') return;
            const button = form.querySelector('.watch-buy-status-publish');
            form.dataset.submitting = '1';
            button.disabled = true;
            button.textContent = form.dataset.publishingLabel;

            axios.post(form.action, new FormData(form), {
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
            }).then(function (response) {
                Toast.fire({icon: 'success', title: response.data.message});
                window.location.assign(response.data.data?.redirect_url || form.dataset.successUrl);
            }).catch(function (error) {
                const errors = error.response?.data?.data?.errors || error.response?.data?.errors;
                const firstError = errors ? Object.values(errors).flat()[0] : null;
                Toast.fire({icon: 'error', title: firstError || error.response?.data?.message || form.dataset.requestError});
            }).finally(function () {
                delete form.dataset.submitting;
                button.disabled = false;
                button.textContent = form.dataset.publishLabel;
            });
        });
    });

    const previewModal = document.getElementById('watch-buy-status-preview-modal');

    if (previewModal) {
        const profile = previewModal.querySelector('.watch-buy-status-preview-profile');
        const type = previewModal.querySelector('.watch-buy-status-preview-type');
        const image = previewModal.querySelector('.watch-buy-status-preview-image');
        const video = previewModal.querySelector('.watch-buy-status-preview-video');
        const textOnly = previewModal.querySelector('.watch-buy-status-preview-text-only');
        const unavailable = previewModal.querySelector('.watch-buy-status-preview-unavailable');
        const copy = previewModal.querySelector('.watch-buy-status-preview-copy');
        const expiry = previewModal.querySelector('.watch-buy-status-preview-expiry');
        const creator = previewModal.querySelector('.watch-buy-status-preview-creator');
        const products = previewModal.querySelector('.watch-buy-status-preview-products');
        const productList = previewModal.querySelector('.watch-buy-status-preview-product-list');
        const openMedia = previewModal.querySelector('.watch-buy-status-preview-open');
        const deleteButton = previewModal.querySelector('.watch-buy-status-preview-delete');

        const resetPreview = function () {
            image.classList.add('d-none');
            video.classList.add('d-none');
            textOnly.classList.add('d-none');
            unavailable.classList.add('d-none');
            image.removeAttribute('src');
            video.pause();
            video.removeAttribute('src');
            video.load();
        };

        previewModal.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            if (!trigger?.classList.contains('watch-buy-status-preview-trigger')) return;

            resetPreview();
            const contentType = trigger.dataset.contentType;
            const mediaUrl = trigger.dataset.mediaUrl;
            const statusText = trigger.dataset.text?.trim() || '';

            profile.textContent = trigger.dataset.profile;
            type.textContent = trigger.dataset.contentTypeLabel;
            expiry.textContent = trigger.dataset.demoMode === '1'
                ? trigger.dataset.neverExpiresLabel
                : formatCountdown(trigger.dataset.expiresAt, trigger.dataset.expiresLabel);
            creator.textContent = trigger.dataset.createdBy;
            products.textContent = trigger.dataset.productsLabel;
            productList.replaceChildren();
            JSON.parse(trigger.dataset.products || '[]').forEach(function (product) {
                const item = document.createElement('li');
                const link = document.createElement('a');
                const image = document.createElement(product.image_url ? 'img' : 'span');
                const title = document.createElement('span');
                const icon = document.createElement('i');

                link.className = 'watch-buy-status-preview-product';
                link.href = product.url;
                link.target = '_blank';
                link.rel = 'noopener';
                image.className = product.image_url
                    ? 'avatar avatar-sm rounded watch-buy-status-preview-product-image'
                    : 'avatar avatar-sm rounded bg-secondary-lt watch-buy-status-preview-product-image';
                if (product.image_url) {
                    image.src = product.image_url;
                    image.alt = '';
                } else {
                    image.innerHTML = '<i class="ti ti-shopping-bag"></i>';
                }
                title.className = 'text-body text-truncate flex-fill';
                title.textContent = truncateText(product.title, 20);
                link.title = product.title;
                icon.className = 'ti ti-external-link text-secondary flex-shrink-0';
                link.append(image, title, icon);
                item.appendChild(link);
                productList.appendChild(item);
            });
            copy.textContent = statusText;
            copy.classList.toggle('d-none', !statusText || contentType === 'text');

            if (contentType === 'image' && mediaUrl) {
                image.src = mediaUrl;
                image.classList.remove('d-none');
            } else if (contentType === 'video' && mediaUrl) {
                video.src = mediaUrl;
                video.classList.remove('d-none');
                video.load();
            } else if (contentType === 'text') {
                textOnly.textContent = statusText;
                textOnly.classList.remove('d-none');
            } else {
                unavailable.classList.remove('d-none');
            }

            openMedia.classList.toggle('d-none', !mediaUrl);
            if (mediaUrl) openMedia.href = mediaUrl;

            const canDelete = Boolean(trigger.dataset.deleteUrl);
            deleteButton.classList.toggle('d-none', !canDelete);
            deleteButton.dataset.statusId = trigger.dataset.statusId;
            deleteButton.dataset.url = trigger.dataset.deleteUrl || '';
            deleteButton.dataset.title = trigger.dataset.deleteTitle || '';
            deleteButton.dataset.text = trigger.dataset.deleteText || '';
            deleteButton.dataset.confirmText = trigger.dataset.deleteConfirmText || '';
            deleteButton.dataset.cancelText = trigger.dataset.deleteCancelText || '';
            deleteButton.dataset.errorMessage = trigger.dataset.deleteErrorMessage || '';
            deleteButton.disabled = false;
        });

        previewModal.addEventListener('hidden.bs.modal', resetPreview);
    }

    const removeStatus = function (statusId) {
        document.querySelectorAll('.watch-buy-status-card').forEach(function (card) {
            if (card.dataset.statusId !== String(statusId)) return;
            const group = card.closest('.watch-buy-status-profile-group');
            card.remove();
            const remaining = group.querySelectorAll('.watch-buy-status-card').length;
            const count = group.querySelector('.watch-buy-status-group-count');

            if (count) {
                const label = remaining === 1 ? count.dataset.singularLabel : count.dataset.pluralLabel;
                count.textContent = label.replace(':count', remaining);
            }

            if (remaining === 0) {
                const results = group.closest('[data-status-results]');
                group.remove();
                if (!results?.querySelector('.watch-buy-status-profile-group')) {
                    results?.querySelector('[data-status-empty]')?.classList.remove('d-none');
                }
            }
        });
    };

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.watch-buy-status-delete');
        if (!button) return;

        if (!window.Swal) {
            Toast.fire({icon: 'error', title: button.dataset.errorMessage});
            return;
        }

        Swal.fire({
            title: button.dataset.title,
            text: button.dataset.text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: button.dataset.confirmText,
            cancelButtonText: button.dataset.cancelText,
            confirmButtonColor: '#d63939',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            button.disabled = true;
            axios.delete(button.dataset.url, {
                headers: {'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
            }).then(function (response) {
                Toast.fire({icon: 'success', title: response.data.message});
                const modal = button.closest('#watch-buy-status-preview-modal');
                if (modal) {
                    $('#watch-buy-status-preview-modal').modal('hide');
                }
                removeStatus(button.dataset.statusId);
            }).catch(function (error) {
                Toast.fire({icon: 'error', title: error.response?.data?.message || button.dataset.errorMessage});
                button.disabled = false;
            });
        });
    });

    updateCountdowns();
    window.setInterval(updateCountdowns, 60000);
});
