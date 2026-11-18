(function (window) {
    'use strict';

    function initialize() {
        if (!window.TourManager) {
            return;
        }

        window.TourManager.registerTours(window.HypercommerceTourCatalog || {});

        var drawer = document.querySelector('[data-tour-drawer]');

        if (drawer) {
            drawer.addEventListener('click', function (event) {
                var trigger = event.target.closest('[data-tour-start]');

                if (trigger) {
                    window.TourManager.startTour(trigger.dataset.tourStart);
                }
            });

            var search = drawer.querySelector('[data-tour-search]');

            if (search) {
                search.addEventListener('input', function () {
                    var query = search.value.trim().toLowerCase();
                    var visibleGuides = 0;

                    drawer.querySelectorAll('[data-tour-group]').forEach(function (group) {
                        var groupMatch = group.dataset.tourSearchText.includes(query);
                        var groupVisible = 0;

                        group.querySelectorAll('[data-tour-guide]').forEach(function (guide) {
                            var visible = !query || groupMatch || guide.dataset.tourSearchText.includes(query);
                            guide.classList.toggle('d-none', !visible);

                            if (visible) {
                                groupVisible += 1;
                                visibleGuides += 1;
                            }
                        });

                        group.classList.toggle('d-none', groupVisible === 0);

                        if (query && groupVisible > 0 && window.bootstrap && window.bootstrap.Collapse) {
                            var collapse = group.querySelector('.accordion-collapse');
                            window.bootstrap.Collapse.getOrCreateInstance(collapse, {toggle: false}).show();
                        } else if (query && groupVisible > 0) {
                            var toggle = group.querySelector('.accordion-button');

                            if (toggle && toggle.classList.contains('collapsed')) {
                                toggle.click();
                            }
                        }
                    });

                    var noResults = drawer.querySelector('[data-tour-no-results]');

                    if (noResults) {
                        noResults.classList.toggle('d-none', visibleGuides > 0);
                    }
                });
            }
        }

        window.TourManager.resumeTourOnLoad();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})(window);
