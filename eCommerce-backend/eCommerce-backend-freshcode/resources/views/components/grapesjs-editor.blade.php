@once
    @push('styles')
        <link rel="stylesheet" href="{{ hyperAsset('assets/vendor/grapesjs/core/grapes.min.css') }}">
        <link rel="stylesheet" href="{{ hyperAsset('assets/css/grapesjs-editor.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ hyperAsset('assets/vendor/grapesjs/core/grapes.min.js') }}"></script>
        <script src="{{ hyperAsset('assets/vendor/grapesjs/plugins/grapesjs-parser-postcss.js') }}"></script>
        <script src="{{ hyperAsset('assets/vendor/grapesjs/plugins/grapesjs-preset-webpage.js') }}"></script>
        <script src="{{ hyperAsset('assets/vendor/grapesjs/plugins/grapesjs-blocks-basic.js') }}"></script>
        <script src="{{ hyperAsset('assets/vendor/grapesjs/plugins/grapesjs-plugin-forms.js') }}"></script>
        <script src="{{ hyperAsset('assets/vendor/grapesjs/plugins/grapesjs-navbar.js') }}"></script>
        <script src="{{ hyperAsset('assets/vendor/grapesjs/plugins/grapesjs-component-countdown.js') }}"></script>
        <script src="{{ hyperAsset('assets/vendor/grapesjs/plugins/grapesjs-custom-code.js') }}"></script>
        <script src="{{ hyperAsset('assets/vendor/grapesjs/plugins/grapesjs-tabs.js') }}"></script>
        <script src="{{ hyperAsset('assets/vendor/grapesjs/plugins/grapesjs-tooltip.js') }}"></script>
        <script src="{{ hyperAsset('assets/vendor/grapesjs/plugins/grapesjs-plugin-export.js') }}"></script>
        <script src="{{ hyperAsset('assets/js/grapesjs-editor.js') }}" defer></script>
    @endpush
@endonce

<div
    id="{{ $id }}"
    {{ $attributes->class(['grapesjs-editor', 'grapesjs-editor--readonly' => $readOnly]) }}
    data-grapesjs-editor
    data-editor-id="{{ $id }}"
    data-options="{{ json_encode($options, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}"
    data-height="{{ $height }}"
    data-readonly="{{ $readOnly ? '1' : '0' }}"
    data-document-id="{{ $documentId }}"
    data-asset-token="{{ $assetToken }}"
    data-asset-upload-url="{{ route('admin.landing-page-builder.assets.store') }}"
    data-import-label="{{ __('labels.editor_import_code') }}"
    data-media-label="{{ __('labels.landing_page_media_library') }}"
    data-local-video-label="{{ __('labels.landing_page_local_video') }}"
    data-script-error-label="{{ __('labels.landing_page_script_load_failed') }}"
    data-upload-pending-label="{{ __('labels.landing_page_upload_pending') }}"
    data-upload-error-label="{{ __('labels.landing_page_upload_failed') }}"
    style="height: {{ $height }}"
>
    <textarea hidden data-grapesjs-initial-html>{{ $html }}</textarea>
    <textarea hidden data-grapesjs-initial-css>{{ $css }}</textarea>
    <textarea hidden data-grapesjs-initial-project>{{ $projectDataJson }}</textarea>
    <input type="hidden" name="{{ $name }}[asset_token]" value="{{ $assetToken }}" data-grapesjs-asset-token>
    <input type="hidden" name="{{ $name }}[removed_assets]" value="{{ old($name.'.removed_assets', '[]') }}" data-grapesjs-removed-assets>
    <div id="{{ $id }}-mount" class="grapesjs-editor__mount" data-grapesjs-mount aria-label="{{ __('labels.landing_page_builder') }}" style="height: 100%; width: 100%"></div>
    <input type="hidden" name="{{ $name }}[html]" value="{{ $html }}" data-grapesjs-html>
    <input type="hidden" name="{{ $name }}[css]" value="{{ $css }}" data-grapesjs-css>
    <input type="hidden" name="{{ $name }}[project]" value="{{ $projectDataJson }}" data-grapesjs-project>
</div>
