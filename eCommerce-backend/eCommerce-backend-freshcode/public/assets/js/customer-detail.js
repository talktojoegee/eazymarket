'use strict';

document.addEventListener('DOMContentLoaded', function () {
    initTabDatatableAdjust();
});

function initTabDatatableAdjust() {
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            if (typeof $ === 'undefined' || !$.fn.DataTable) {
                return;
            }

            var tables = $.fn.dataTable.tables({ visible: true, api: true });
            tables.columns.adjust();

            if (tables.responsive) {
                tables.responsive.recalc();
            }
        });
    });
}
