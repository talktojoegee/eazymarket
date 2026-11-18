'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const timezoneEl = document.getElementById('select-timezone');
    if (timezoneEl && window.TomSelect && !timezoneEl.tomselect) {
        new TomSelect(timezoneEl, {
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            controlInput: '<input>',
            maxOptions: null,
            placeholder: timezoneEl.getAttribute('placeholder') || '',
        });
    }

    const walletPresetAmountsEl = document.getElementById('wallet-recharge-preset-amounts');
    if (walletPresetAmountsEl && window.TomSelect && !walletPresetAmountsEl.tomselect) {
        new TomSelect(walletPresetAmountsEl, {
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            controlInput: '<input type="number" min="0.01" step="0.01">',
            create: function (input, callback) {
                const amount = Number(input);
                if (!Number.isFinite(amount) || amount <= 0) {
                    callback();
                    return;
                }

                const value = String(amount);
                callback({ value: value, text: value });
            },
            persist: false,
            maxItems: 4,
            placeholder: walletPresetAmountsEl.dataset.placeholder || '',
        });
    }
});
