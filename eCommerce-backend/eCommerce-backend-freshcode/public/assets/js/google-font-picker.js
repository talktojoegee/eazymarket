(function () {
    function initGoogleFontPickers() {
        if (!window.TomSelect) return;

        document.querySelectorAll('[data-google-font-select]').forEach(function (element) {
            if (element.tomselect) return;

            const selected = String(element.dataset.selected || '').trim();
            const picker = new TomSelect(element, {
                copyClassesToDropdown: false,
                dropdownParent: 'body',
                controlInput: '<input>',
                placeholder: element.dataset.placeholder || '',
                searchField: ['text'],
                maxOptions: 100,
                persist: false,
            });

            if (selected) {
                if (!picker.options[selected]) {
                    picker.addOption({value: selected, text: selected});
                }
                picker.setValue(selected, true);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGoogleFontPickers, {once: true});
    } else {
        initGoogleFontPickers();
    }
})();
