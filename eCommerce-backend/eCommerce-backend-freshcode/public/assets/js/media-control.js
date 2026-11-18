(function () {
    'use strict';

    let pluginsRegistered = false;

    function field(root, name) {
        return root.querySelector('[data-media-control-field="' + name + '"]');
    }

    function value(root, name, fallback) {
        return field(root, name)?.value || fallback;
    }

    function mediaValues(root) {
        return {
            media_type: value(root, 'media_type', 'image'),
            aspect_ratio: value(root, 'aspect_ratio', 'original'),
            aspect_width: Number(value(root, 'aspect_width', 100)) || 100,
            aspect_width_unit: value(root, 'aspect_width_unit', '%'),
            aspect_height: Number(value(root, 'aspect_height', 300)) || 300,
            aspect_height_unit: value(root, 'aspect_height_unit', 'px'),
            alt: value(root, 'alt', ''),
            object_fit: value(root, 'object_fit', 'cover'),
            object_position: value(root, 'object_position', 'center')
        };
    }

    function registerFilePondPlugins() {
        if (pluginsRegistered || !window.FilePond) return;
        const plugins = [
            window.FilePondPluginImagePreview,
            window.FilePondPluginFileValidateSize
        ].filter(Boolean);
        if (plugins.length) window.FilePond.registerPlugin(...plugins);
        pluginsRegistered = true;
    }

    function emitChange(root, changedField) {
        root.dispatchEvent(new CustomEvent('media-control-change', {
            bubbles: true,
            detail: {
                field: changedField,
                value: mediaValues(root),
                file: root._mediaControlFile || null,
                removed: root.querySelector('[data-media-control-remove]')?.value === '1'
            }
        }));
    }

    function updateState(root) {
        const values = mediaValues(root);
        const customSize = root.querySelector('[data-media-control-custom-size]');
        const config = root.querySelector('[data-media-control-config]');
        const badge = root.querySelector('[data-media-control-type-badge]');
        if (customSize) customSize.hidden = values.aspect_ratio !== 'custom';
        if (config) {
            config.hidden = root.dataset.mediaControlShowConfig === '0' ||
                (!root._mediaControlFile && !root.dataset.mediaControlPreviewUrl);
        }
        if (badge) badge.textContent = values.media_type;
    }

    function markRemoved(root, changedField = 'remove') {
        root._mediaControlFile = null;
        root.dataset.mediaControlPreviewUrl = '';
        const removeInput = root.querySelector('[data-media-control-remove]');
        if (removeInput) removeInput.value = '1';
        updateState(root);
        emitChange(root, changedField);
    }

    function selectFile(root, selectedFile) {
        if (!selectedFile) return;
        root._mediaControlFile = selectedFile;
        const removeInput = root.querySelector('[data-media-control-remove]');
        if (removeInput) removeInput.value = '0';
        updateState(root);
        emitChange(root, 'file');
    }

    function loadExistingFile(source, load, error, progress, abort) {
        const controller = new AbortController();
        fetch(source, {signal: controller.signal})
            .then(response => {
                if (!response.ok) throw new Error('Unable to load existing media');
                const total = Number(response.headers.get('content-length')) || 0;
                progress(true, total, total);
                return response.blob();
            })
            .then(load)
            .catch(fetchError => {
                if (fetchError.name !== 'AbortError') error(fetchError.message);
            });
        return {abort: () => controller.abort()};
    }

    function initializeFilePond(root) {
        const input = root.querySelector('[data-media-control-file]');
        if (!input || !window.FilePond) return;
        registerFilePondPlugins();

        const mediaType = value(root, 'media_type', 'image');
        const previewUrl = root.dataset.mediaControlPreviewUrl || '';
        const pond = window.FilePond.create(input, {
            allowImagePreview: mediaType === 'image',
            allowMultiple: false,
            allowReorder: false,
            allowReplace: true,
            credits: false,
            instantUpload: false,
            storeAsFile: true,
            maxFileSize: '5MB',
            labelIdle: mediaType === 'lottie'
                ? 'Drag & Drop animation or <span class="filepond--label-action">Browse</span>'
                : 'Drag & Drop image or <span class="filepond--label-action">Browse</span>',
            files: previewUrl ? [{source: previewUrl, options: {type: 'local'}}] : [],
            server: {load: loadExistingFile},
            onaddfile: (error, item) => {
                if (error) return;
                updateState(root);
                if (item.origin === window.FilePond.FileOrigin.INPUT) selectFile(root, item.file);
            },
            onremovefile: () => {
                if (!root._mediaControlDestroying) markRemoved(root);
            }
        });
        root._mediaControlPond = pond;
    }

    function resetFilePond(root) {
        const pond = root._mediaControlPond;
        if (!pond) return;
        root._mediaControlDestroying = true;
        pond.removeFiles().finally(() => {
            root._mediaControlDestroying = false;
        });
        pond.setOptions({allowImagePreview: value(root, 'media_type', 'image') === 'image'});
    }

    function initialize(root) {
        if (root.dataset.mediaControlReady === '1') return;
        root.dataset.mediaControlReady = '1';

        root.addEventListener('change', event => {
            if (event.target.matches('[data-media-control-file]')) {
                if (!root._mediaControlPond) selectFile(root, event.target.files?.[0]);
                return;
            }

            const changedField = event.target.dataset.mediaControlField;
            if (!changedField) return;
            if (changedField === 'media_type') {
                markRemoved(root, changedField);
                resetFilePond(root);
                updateState(root);
                return;
            }
            updateState(root);
            emitChange(root, changedField);
        });

        root.addEventListener('input', event => {
            const changedField = event.target.dataset.mediaControlField;
            if (!changedField || event.target.tagName === 'SELECT') return;
            updateState(root);
            emitChange(root, changedField);
        });

        updateState(root);
        initializeFilePond(root);
    }

    function controlsWithin(scope) {
        const controls = [];
        if (scope.matches?.('[data-media-control]')) controls.push(scope);
        scope.querySelectorAll?.('[data-media-control]').forEach(control => controls.push(control));
        return controls;
    }

    function initializeAll(scope) {
        controlsWithin(scope).forEach(initialize);
    }

    function destroyAll(scope) {
        controlsWithin(scope).forEach(root => {
            if (!root._mediaControlPond) return;
            root._mediaControlDestroying = true;
            root._mediaControlPond.destroy();
            root._mediaControlPond = null;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initializeAll(document));
    } else {
        initializeAll(document);
    }

    new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.removedNodes.forEach(node => {
                if (node.nodeType === Node.ELEMENT_NODE) destroyAll(node);
            });
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === Node.ELEMENT_NODE) initializeAll(node);
            });
        });
    }).observe(document.documentElement, {childList: true, subtree: true});
}());
