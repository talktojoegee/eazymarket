/**
 * Seller shipping profile — "Use ranked locations".
 *
 * Keeps a drag-sortable priority list in sync with the locations TomSelect
 * picker. Each list item carries a hidden `store_location_priority[]` input;
 * SortableJS reorders the <li> nodes, so the inputs submit in priority order
 * (the backend assigns priority = position). Adding/removing a location in the
 * picker adds/removes the matching row here.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var select = document.getElementById('store-locations-select');
        var wrap = document.getElementById('location-priority-wrap');
        var list = document.getElementById('location-priority-list');
        if (!select || !wrap || !list) {
            return;
        }

        function instance() {
            return select.tomselect || null;
        }

        function toggleWrap() {
            if (list.children.length) {
                wrap.classList.remove('d-none');
            } else {
                wrap.classList.add('d-none');
            }
        }

        function labelFor(value) {
            var ts = instance();
            if (ts && ts.options && ts.options[value] && ts.options[value].text) {
                return ts.options[value].text;
            }
            return value;
        }

        function addItem(value) {
            if (!value || list.querySelector('[data-id="' + value + '"]')) {
                return;
            }
            var li = document.createElement('li');
            li.className = 'list-group-item d-flex align-items-center';
            li.setAttribute('data-id', value);

            var handle = document.createElement('i');
            handle.className = 'ti ti-grip-vertical text-secondary me-2 js-drag-handle';

            var name = document.createElement('span');
            name.className = 'js-loc-name';
            name.textContent = labelFor(value);

            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'store_location_priority[]';
            input.value = value;

            li.appendChild(handle);
            li.appendChild(name);
            li.appendChild(input);
            list.appendChild(li);
            toggleWrap();
        }

        function removeItem(value) {
            var li = list.querySelector('[data-id="' + value + '"]');
            if (li) {
                li.remove();
            }
            toggleWrap();
        }

        var ts = instance();
        if (ts) {
            ts.on('item_add', function (value) { addItem(value); });
            ts.on('item_remove', function (value) { removeItem(value); });
        }

        if (window.Sortable) {
            window.Sortable.create(list, {
                handle: '.js-drag-handle',
                animation: 150,
                ghostClass: 'bg-light',
            });
        }

        toggleWrap();
    });
})();
