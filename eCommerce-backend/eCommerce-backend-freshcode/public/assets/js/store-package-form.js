(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var readout = document.getElementById('volumetricReadout');
        var value = document.getElementById('volumetricValue');
        var typeSelect = document.querySelector('select[name="type"]');
        var typeHint = document.getElementById('packageTypeHint');
        var lengthInput = document.querySelector('[name="length_cm"]');
        var breadthInput = document.querySelector('[name="breadth_cm"]');
        var heightInput = document.querySelector('[name="height_cm"]');

        function num(inp) {
            return inp ? (parseFloat(inp.value) || 0) : 0;
        }

        function update() {
            if (!readout || !value) return;
            var divisor = parseFloat(readout.getAttribute('data-divisor')) || 5000;
            var vol = (num(lengthInput) * num(breadthInput) * num(heightInput)) / divisor;
            value.textContent = Math.round(vol * 1000) / 1000;
        }

        function applyType(prefill) {
            if (!typeSelect) return;
            var type = typeSelect.value;
            if (typeHint) {
                var hints = {};
                try { hints = JSON.parse(typeHint.getAttribute('data-hints') || '{}'); } catch (e) { hints = {}; }
                typeHint.textContent = hints[type] || '';
            }
            if (prefill && type === 'envelope' && heightInput && num(heightInput) === 0) {
                heightInput.value = 3;
                update();
            }
        }

        [lengthInput, breadthInput, heightInput].forEach(function (inp) {
            if (inp) inp.addEventListener('input', update);
        });
        if (typeSelect) {
            typeSelect.addEventListener('change', function () { applyType(true); });
        }

        applyType(false);
        update();
    });
})();
