'use strict';

document.addEventListener('DOMContentLoaded', function () {
    initTabState();
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

function initTabState() {
    var activeHash = window.location.hash;
    if (activeHash) {
        var activeTab = document.querySelector('[data-bs-toggle="tab"][href="' + activeHash + '"]');
        if (activeTab && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            bootstrap.Tab.getOrCreateInstance(activeTab).show();
        }
    }

    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (event) {
            var target = event.target.getAttribute('href');
            if (target) {
                history.replaceState(null, '', target);
            }
        });
    });
}
