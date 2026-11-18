(function () {
    'use strict';

    function configureCanvasScripts(root, editor) {
        const states = new WeakMap();
        const scriptView = editor.Components.getType('script').view;
        let destroyed = false;

        function stateFor(doc) {
            if (!states.has(doc)) states.set(doc, {views: new Set(), executed: new WeakMap(), mounted: false, running: false, scheduled: false, ready: false});
            return states.get(doc);
        }

        async function execute(view, doc, state) {
            if (destroyed || doc !== editor.Canvas.getDocument() || !view.el.isConnected) return;
            const source = view.model.toHTML();
            if (state.executed.get(view.model) === source) return;
            state.executed.set(view.model, source);
            const parsed = new DOMParser().parseFromString(source, 'text/html').querySelector('script');
            if (!parsed) return;
            const script = doc.createElement('script');
            Array.from(parsed.attributes).forEach(function (attribute) { script.setAttribute(attribute.name, attribute.value); });
            script.textContent = parsed.textContent;
            const type = (script.type || '').toLowerCase();
            if (script.hasAttribute('nomodule') && 'noModule' in script) return;
            const executable = !type || type === 'module' || /(?:java|ecma)script/.test(type);
            if (!script.hasAttribute('async')) script.async = false;
            await new Promise(function (resolve) {
                const waiting = executable && (script.hasAttribute('src') || type === 'module');
                let timeout;
                const completionEvent = type === 'module' && !script.hasAttribute('src') ? 'landing-module-' + crypto.randomUUID() : null;
                function finish() {
                    if (completionEvent) doc.defaultView.removeEventListener(completionEvent, finish);
                    if (timeout) window.clearTimeout(timeout);
                    resolve();
                }
                if (waiting) {
                    script.onload = finish;
                    script.onerror = function () {
                        if (!destroyed && doc === editor.Canvas.getDocument()) reportError(root, (root.dataset.scriptErrorLabel || 'Could not load page script: :url').replace(':url', script.src));
                        finish();
                    };
                    timeout = window.setTimeout(script.onerror, 20000);
                }
                if (completionEvent) {
                    doc.defaultView.addEventListener(completionEvent, finish, {once: true});
                    script.textContent += '\n;window.dispatchEvent(new Event(' + JSON.stringify(completionEvent) + '));';
                }
                view.el.after(script);
                if (!waiting) finish();
            });
        }

        function schedule(doc) {
            const state = stateFor(doc);
            if (!state.mounted || state.scheduled || destroyed) return;
            state.scheduled = true;
            window.setTimeout(async function () {
                state.scheduled = false;
                if (state.running || destroyed || doc !== editor.Canvas.getDocument()) return;
                state.running = true;
                const views = Array.from(state.views).filter(function (view) { return view.el.isConnected; });
                views.sort(function (left, right) { return left.el.compareDocumentPosition(right.el) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1; });
                const deferred = [];
                const asynchronous = [];
                for (const view of views) {
                    const attributes = view.model.getAttributes();
                    const moduleScript = (attributes.type || '').toLowerCase() === 'module';
                    if (Object.hasOwn(attributes, 'async') && (attributes.src || moduleScript)) asynchronous.push(execute(view, doc, state));
                    else if (moduleScript || (attributes.src && Object.hasOwn(attributes, 'defer'))) deferred.push(view);
                    else await execute(view, doc, state);
                }
                for (const view of deferred) await execute(view, doc, state);
                await Promise.all(asynchronous);
                state.running = false;
                if (destroyed || doc !== editor.Canvas.getDocument()) return;
                if (!state.ready) {
                    state.ready = true;
                    doc.dispatchEvent(new doc.defaultView.Event('DOMContentLoaded'));
                    doc.defaultView.dispatchEvent(new doc.defaultView.Event('load'));
                }
                editor.trigger('landing-page:scripts:ready');
                if (Array.from(state.views).some(function (view) { return view.el.isConnected && state.executed.get(view.model) !== view.model.toHTML(); })) schedule(doc);
            }, 0);
        }

        editor.Components.addType('script', {
            view: scriptView.extend({
                updateAttributes: function () {
                    scriptView.prototype.updateAttributes.apply(this, arguments);
                    this.el.type = 'application/x-grapesjs-inert';
                    this.el.removeAttribute('src');
                    this.el.removeAttribute('id');
                },
                onRender: function () {
                    scriptView.prototype.onRender.apply(this, arguments);
                    window.setTimeout(() => {
                        if (destroyed || !this.el.isConnected) return;
                        const doc = this.el.ownerDocument;
                        stateFor(doc).views.add(this);
                        schedule(doc);
                    }, 0);
                }
            })
        });
        editor.on('canvas:frame:load:body', function ({el}) {
            const doc = el.contentDocument;
            stateFor(doc).mounted = true;
            schedule(doc);
        });
        editor.on('destroy', function () { destroyed = true; });
    }

    function configureMedia(root, editor) {
        const imageAsset = editor.AssetManager.getType('image');
        editor.AssetManager.addType('video', {
            model: imageAsset.model.extend({defaults: {...imageAsset.model.prototype.defaults, type: 'video'}}),
            view: imageAsset.view.extend({
                getPreview: function () {
                    const video = document.createElement('video');
                    video.className = 'grapesjs-editor__video-preview';
                    video.src = this.model.get('src');
                    video.muted = true;
                    video.preload = 'metadata';
                    return video.outerHTML;
                }
            }),
            isType: function (asset) { return asset?.mimeType?.startsWith('video/') ? {...asset, type: 'video'} : false; }
        });
        editor.on('asset:upload:response', function (response) {
            response.data?.forEach(function (asset) {
                if (asset.mimeType?.startsWith('video/')) asset.type = 'video';
            });
        });

        function selectVideo(component) {
            editor.AssetManager.open({
                modalTitle: root.dataset.localVideoLabel || 'Local video',
                types: ['video'],
                accept: '.mp4,.webm,.mov',
                select: function (asset, complete) {
                    if (asset.get('type') !== 'video') return;
                    component.set({provider: 'so', src: asset.getSrc(), sources: [], controls: true});
                    if (complete) editor.AssetManager.close();
                }
            });
        }
        const videoView = editor.Components.getType('video').view;
        editor.Components.addType('video', {view: videoView.extend({
            events: function () { return {...videoView.prototype.events.call(this), dblclick: 'onActive'}; },
            onActive: function () { selectVideo(this.model); }
        })});
        editor.on('component:selected', function (component) {
            if (component.is('video') && !component.getTrait('uploaded-video')) component.addTrait({
                type: 'button', name: 'uploaded-video', label: false,
                text: root.dataset.localVideoLabel || 'Choose uploaded video',
                command: function () { selectVideo(component); }
            });
        });
        if (root.dataset.readonly === '1') return;
        editor.Blocks.add('local-video', {
            label: root.dataset.localVideoLabel || 'Local video',
            category: 'Basic',
            media: '<span class="fa fa-file-video-o" aria-hidden="true"></span>',
            content: {type: 'video', provider: 'so', controls: true, style: {width: '100%', 'min-height': '200px'}}
        });
        editor.on('load', function () { editor.Panels.addButton('options', {
            id: 'landing-page-media', className: 'fa fa-picture-o',
            attributes: {title: root.dataset.mediaLabel || 'Media library', 'aria-label': root.dataset.mediaLabel || 'Media library'},
            command: function () {
                editor.AssetManager.open({modalTitle: root.dataset.mediaLabel || 'Media library', types: [], accept: '.jpg,.jpeg,.png,.gif,.webp,.mp4,.webm,.mov', select: function (asset, complete) {
                    if (!complete) return;
                    const selected = editor.getSelected();
                    if (asset.get('type') === 'video') {
                        if (selected?.is('video')) selected.set({provider: 'so', src: asset.getSrc(), sources: [], controls: true});
                        else editor.addComponents({type: 'video', provider: 'so', src: asset.getSrc(), controls: true, style: {width: '100%'}});
                    } else if (selected?.is('image')) selected.set('src', asset.getSrc());
                    else editor.addComponents({type: 'image', src: asset.getSrc(), attributes: {alt: asset.get('name') || ''}});
                    editor.AssetManager.close();
                }});
            }
        }); });
    }

    function configureSaveLifecycle(root, editor, form) {
        if (!form || root.dataset.readonly === '1') return;
        let uploads = 0;
        let unusedAssets = [];
        editor.on('asset:upload:start', function () { uploads++; });
        editor.on('asset:upload:end', function () { uploads = Math.max(0, uploads - 1); });
        form.addEventListener('submit', function (event) {
            if (uploads) {
                event.preventDefault();
                event.stopImmediatePropagation();
                reportError(root, root.dataset.uploadPendingLabel || 'Wait for media uploads to finish before saving.');
                return;
            }
            unusedAssets = editor.AssetManager.getAll().filter(function (asset) {
                const id = asset.get('id');
                return /^[0-9a-f]{8}-[0-9a-f-]{27}$/i.test(id || '') && !projectUsesAsset(editor, id);
            });
            root.inert = true;
            root.setAttribute('aria-busy', 'true');
        }, true);
        function finish(event) {
            if (event.detail?.form !== form) return;
            root.inert = false;
            root.removeAttribute('aria-busy');
            if (event.type === 'resource:saved') {
                unusedAssets.forEach(function (asset) { editor.AssetManager.remove(asset); });
                root.grapesjsRemovedAssetIds?.clear();
                updateFields(root, editor);
            }
            unusedAssets = [];
        }
        document.addEventListener('resource:saved', finish);
        document.addEventListener('resource:save-failed', finish);
        editor.on('destroy', function () {
            document.removeEventListener('resource:saved', finish);
            document.removeEventListener('resource:save-failed', finish);
        });
    }

    function parseOptions(root) {
        try {
            return JSON.parse(root.dataset.options || '{}');
        } catch (error) {
            return {};
        }
    }

    function readValue(root, selector) {
        return root.querySelector(selector)?.value || '';
    }

    function reportError(root, message) {
        root.dispatchEvent(new CustomEvent('grapesjs:error', {
            bubbles: true,
            detail: {message: message}
        }));

        if (window.Toast?.fire) {
            window.Toast.fire({icon: 'error', title: message});
        }
    }

    function updateFields(root, editor) {
        const htmlField = root.querySelector('[data-grapesjs-html]');
        const cssField = root.querySelector('[data-grapesjs-css]');
        const projectField = root.querySelector('[data-grapesjs-project]');
        const removedAssetsField = root.querySelector('[data-grapesjs-removed-assets]');
        const projectData = editor.getProjectData();

        const exportedHtml = editor.getHtml({asDocument: true});
        const css = editor.getCss({keepUnusedStyles: true});

        if (htmlField) htmlField.value = exportedHtml;
        if (cssField) cssField.value = css;
        if (projectField) projectField.value = JSON.stringify(projectData);
        if (removedAssetsField) {
            removedAssetsField.value = JSON.stringify(Array.from(root.grapesjsRemovedAssetIds || []).filter(function (id) {
                return !projectUsesAsset(editor, id);
            }));
        }

        root.dispatchEvent(new CustomEvent('grapesjs:updated', {
            bubbles: true,
            detail: {html: exportedHtml, css: css, project: projectData, editor: editor}
        }));
    }

    function projectUsesAsset(editor, id) {
        if (!id) return false;
        const content = {...editor.getProjectData()};
        delete content.assets;
        return JSON.stringify(content).includes(id);
    }

    function assetIdFromSource(source) {
        const match = String(source || '').match(/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\.[a-z0-9]+(?:[?#]|["'\\]|$)/i);
        return match ? match[1] : null;
    }

    function componentAssetIds(component) {
        try {
            const json = JSON.stringify(component.toJSON());
            return [...new Set(Array.from(json.matchAll(/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\.[a-z0-9]+/gi), function (match) {
                return match[1];
            }))];
        } catch (error) {
            return [];
        }
    }

    function configureAssetLifecycle(root, editor) {
        if (!root.grapesjsRemovedAssetIds) {
            try {
                root.grapesjsRemovedAssetIds = new Set(JSON.parse(readValue(root, '[data-grapesjs-removed-assets]') || '[]'));
            } catch (error) {
                root.grapesjsRemovedAssetIds = new Set();
            }
        }

        editor.on('asset:remove', function (asset) {
            const source = typeof asset.getSrc === 'function' ? asset.getSrc() : asset.get?.('src');
            const id = asset.get?.('id') || assetIdFromSource(source);
            if (id && !projectUsesAsset(editor, id)) root.grapesjsRemovedAssetIds.add(id);
            updateFields(root, editor);
        });

        editor.on('component:remove', function (component) {
            const assetIds = componentAssetIds(component);
            window.setTimeout(function () {
                if (editor.getModel().destroyed) return;
                assetIds.forEach(function (id) {
                    if (!projectUsesAsset(editor, id)) root.grapesjsRemovedAssetIds.add(id);
                });
                updateFields(root, editor);
            }, 0);
        });
    }

    function configureImportAction(root, editor) {
        if (root.dataset.readonly === '1') return;

        editor.Panels.addButton('options', {
            id: 'landing-page-import-code',
            className: 'fa fa-code',
            command: 'gjs-open-import-webpage',
            attributes: {
                title: root.dataset.importLabel,
                'aria-label': root.dataset.importLabel
            }
        });
    }

    function importHtml(editor, html) {
        const asDocument = /<!doctype\s+html|<html[\s>]|<head[\s>]|<body[\s>]/i.test(html);
        const source = asDocument && !/<!doctype/i.test(html) ? '<!doctype html>' + html : html;
        editor.setComponents(source, {asDocument: asDocument});
    }

    function initialize(root) {
        if (root.dataset.grapesjsInitialized === '1' || root.dataset.grapesjsInitializing === '1') return;

        if (!window.grapesjs || typeof window.grapesjs.init !== 'function') {
            reportError(root, 'GrapesJS could not be loaded. Check the local vendor assets and refresh the page.');
            return;
        }

        const options = parseOptions(root);
        const mount = root.querySelector('[data-grapesjs-mount]');
        const starterHtml = readValue(root, '[data-grapesjs-initial-html]');
        const starterCss = readValue(root, '[data-grapesjs-initial-css]');
        let projectData;
        const savedProject = readValue(root, '[data-grapesjs-initial-project]');

        if (savedProject) {
            try {
                const parsedProject = JSON.parse(savedProject);
                if (parsedProject && Array.isArray(parsedProject.pages) && parsedProject.pages.length) {
                    projectData = parsedProject;
                }
            } catch (error) {
                reportError(root, 'The saved page editor data is invalid.');
                return;
            }
        }

        const form = root.closest('form');
        const isReadOnly = root.dataset.readonly === '1';
        root.dataset.grapesjsInitializing = '1';

        try {
            const editor = window.grapesjs.init({
                ...options,
                container: mount,
                height: root.dataset.height || '700px',
                storageManager: false,
                allowScripts: 1,
                jsInHtml: true,
                keepUnusedStyles: true,
                parser: {
                    optionsHtml: {
                        allowScripts: true
                    },
                    ...(options.parser || {})
                },
                projectData: projectData,
                canvas: {
                    ...(options.canvas || {})
                },
                plugins: [
                    'grapesjs-parser-postcss',
                    'grapesjs-preset-webpage',
                    'gjs-blocks-basic',
                    'grapesjs-plugin-forms',
                    'grapesjs-navbar',
                    'grapesjs-component-countdown',
                    'grapesjs-custom-code',
                    'grapesjs-tabs',
                    'grapesjs-tooltip',
                    'grapesjs-plugin-export',
                    ...(options.plugins || []),
                    function (editor) { configureCanvasScripts(root, editor); configureMedia(root, editor); }
                ],
                pluginsOpts: {
                    'gjs-blocks-basic': {category: 'Basic'},
                    'grapesjs-plugin-forms': {category: 'Forms'},
                    'grapesjs-navbar': {block: {category: 'Extra'}},
                    'grapesjs-component-countdown': {block: {category: 'Extra'}},
                    'grapesjs-custom-code': {blockCustomCode: {category: 'Extra'}},
                    'grapesjs-tooltip': {blockTooltip: {category: 'Extra'}},
                    ...(options.pluginsOpts || {})
                },
                deviceManager: {
                    devices: [
                        {name: 'Desktop', width: ''},
                        {name: 'Tablet', width: '768px', widthMedia: '992px'},
                        {name: 'Mobile portrait', width: '375px', widthMedia: '480px'}
                    ],
                    ...(options.deviceManager || {})
                },
                assetManager: {
                    upload: root.dataset.assetUploadUrl,
                    uploadName: 'files',
                    params: {
                        asset_token: root.dataset.assetToken || '',
                        ...(root.dataset.documentId ? {document_id: root.dataset.documentId} : {})
                    },
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    autoAdd: true,
                    ...(options.assetManager || {})
                }
            });

            if (!projectData) {
                importHtml(editor, starterHtml);
                if (starterCss) editor.Css.addRules(starterCss);
            }

            const importCommand = editor.Commands.get('gjs-open-import-webpage');
            const getImportContainer = importCommand.getContainer;
            importCommand.getContainer = function () {
                const container = getImportContainer.call(this);
                container.querySelector('button').onclick = function () {
                    const html = importCommand.getCodeViewer().getContent().trim();
                    const previousProject = editor.getProjectData();
                    try {
                        editor.Css.clear();
                        editor.getWrapper().head.components().reset();
                        editor.getWrapper().docEl.set('attributes', {});
                        editor.getWrapper().set({doctype: '', attributes: {}});
                        importHtml(editor, html);
                        editor.Modal.close();
                        const frame = editor.Canvas.getFrameEl();
                        if (frame) frame.src = 'about:blank';
                    } catch (error) {
                        editor.loadProjectData(previousProject);
                        reportError(root, error?.message || 'The page could not be imported. Check the HTML and CSS and try again.');
                    }
                };
                return container;
            };

            root.grapesjsEditor = editor;
            configureAssetLifecycle(root, editor);
            configureImportAction(root, editor);
            configureSaveLifecycle(root, editor, form);

            if (isReadOnly) {
                mount.inert = true;
            } else {
                editor.on('update', function () {
                    updateFields(root, editor);
                });
                editor.on('asset:upload:error', function (error) {
                    let message = root.dataset.uploadErrorLabel || 'Media could not be uploaded. Please check the file and try again.';
                    try {
                        const response = typeof error === 'string' ? JSON.parse(error) : error;
                        message = Object.values(response?.errors || {}).flat()[0] || message;
                    } catch (error) {}
                    reportError(root, message);
                });
                form?.addEventListener('submit', function () {
                    updateFields(root, editor);
                }, true);
            }

            editor.on('load', function () {
                root.dataset.grapesjsInitialized = '1';
                delete root.dataset.grapesjsInitializing;
                updateFields(root, editor);
                root.dispatchEvent(new CustomEvent('grapesjs:ready', {
                    bubbles: true,
                    detail: {editor: editor}
                }));
            });
        } catch (error) {
            delete root.dataset.grapesjsInitializing;
            reportError(root, error?.message || 'The page editor could not be initialized. Refresh the page and try again.');
        }
    }

    function initializeAll() {
        document.querySelectorAll('[data-grapesjs-editor]').forEach(initialize);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeAll, {once: true});
    } else {
        initializeAll();
    }
})();
