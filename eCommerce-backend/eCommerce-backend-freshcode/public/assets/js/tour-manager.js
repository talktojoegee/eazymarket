(function (window) {
    'use strict';

    if (window.TourManager) {
        return;
    }

    var tours = {};
    var runningDriver = null;
    var transitionInProgress = false;
    var completed = false;
    var mobileSidebarOpenedByTour = false;

    function panel() {
        return document.getElementById('panel')?.dataset.panel || window.location.pathname.split('/')[1] || 'admin';
    }

    function storageKey() {
        var userId = document.getElementById('user_id')?.value || 'guest';

        return 'hypercommerce-tour:' + userId + ':' + panel();
    }

    function readState() {
        try {
            return JSON.parse(window.localStorage.getItem(storageKey()) || 'null');
        } catch (error) {
            return null;
        }
    }

    function writeState(state) {
        try {
            window.localStorage.setItem(storageKey(), JSON.stringify(state));
        } catch (error) {
            return;
        }
    }

    function clearState() {
        try {
            window.localStorage.removeItem(storageKey());
        } catch (error) {
            return;
        }
    }

    function pathMatches(segmentUrl) {
        var current = new URL(window.location.href);
        var target = new URL(segmentUrl, window.location.origin);

        if (current.pathname.replace(/\/$/, '') !== target.pathname.replace(/\/$/, '')) {
            return false;
        }

        for (var pair of target.searchParams.entries()) {
            if (current.searchParams.get(pair[0]) !== pair[1]) {
                return false;
            }
        }

        return true;
    }

    function currentSegmentIndex(tour) {
        var state = readState();
        if (state && state.segment) {
            var savedIndex = tour.segments.findIndex(function (segment) {
                return segment.id === state.segment;
            });
            if (savedIndex >= 0) {
                return savedIndex;
            }
        }
        return tour.segments.findIndex(function (segment) {
            return pathMatches(segment.url);
        });
    }

    function notify(message) {
        if (typeof Toast !== 'undefined' && typeof Toast.fire === 'function') {
            Toast.fire({icon: 'warning', title: message});
        }
    }

    function waitForElement(selector, timeout) {
        return new Promise(function (resolve) {
            var started = Date.now();
            var selectors = selector.split(',').map(function (candidate) {
                return candidate.trim();
            }).filter(Boolean);

            function find() {
                var element = null;

                for (var index = 0; index < selectors.length; index += 1) {
                    element = Array.from(document.querySelectorAll(selectors[index])).find(function (candidate) {
                        var rect = candidate.getBoundingClientRect();

                        return candidate.getClientRects().length > 0 && rect.width > 0 && rect.height > 0;
                    });

                    if (element) {
                        break;
                    }
                }

                if (element) {
                    resolve(element);

                    return;
                }

                if (Date.now() - started >= timeout) {
                    resolve(null);

                    return;
                }

                window.setTimeout(find, 60);
            }

            find();
        });
    }

    function prepareResponsiveTarget(element) {
        if (!window.matchMedia('(max-width: 991.98px)').matches) {
            return;
        }

        var isSidebarTarget = element && element.closest('#panel-sidebar');

        if (isSidebarTarget && !document.documentElement.classList.contains('mobile-sidebar-open')) {
            document.documentElement.classList.add('mobile-sidebar-open');
            mobileSidebarOpenedByTour = true;
        } else if (!isSidebarTarget && mobileSidebarOpenedByTour) {
            document.documentElement.classList.remove('mobile-sidebar-open');
            mobileSidebarOpenedByTour = false;
        }
    }

    function restoreResponsiveLayout() {
        if (mobileSidebarOpenedByTour) {
            document.documentElement.classList.remove('mobile-sidebar-open');
            mobileSidebarOpenedByTour = false;
        }
    }

    async function activeSteps(tour, segmentIndex) {
        var segment = tour.segments[segmentIndex];
        var steps = [];
        var total = tour.segments.reduce(function (count, item) {
            return count + item.steps.length;
        }, 0);
        var preceding = tour.segments.slice(0, segmentIndex).reduce(function (count, item) {
            return count + item.steps.length;
        }, 0);

        for (var index = 0; index < segment.steps.length; index += 1) {
            var definition = segment.steps[index];
            var element = await waitForElement(definition.element, definition.optional ? 180 : 1400);

            if (!element && definition.optional) {
                continue;
            }

            if (!element) {
                continue;
            }

            steps.push({
                element: element,
                popover: {
                    title: definition.title,
                    description: definition.description,
                    side: definition.side || 'bottom',
                    currentStepNum: preceding + index + 1,
                    totalSteps: total,
                },
            });
        }

        return steps;
    }

    function navigateToSegment(action, tour, segmentIndex) {
        var segment = tour.segments[segmentIndex];
        if (!segment) return;
        transitionInProgress = true;
        writeState({action: action, segment: segment.id, step: 0});
        if (pathMatches(segment.url)) {
            runTour(action, 0);
        } else {
            window.location.href = segment.url;
        }
    }

    async function runTour(action, requestedStep) {
        var tour = tours[action];

        if (!tour || !window.Driver) {
            clearState();
            notify('This guide is not available right now.');

            return;
        }

        var segmentIndex = currentSegmentIndex(tour);

        if (segmentIndex < 0) {
            clearState();

            return;
        }

        var segment = tour.segments[segmentIndex];
        var steps = await activeSteps(tour, segmentIndex);

        if (steps.length === 0) {
            clearState();
            notify('No guide steps are available on this page.');

            return;
        }

        var nextSegment = tour.segments[segmentIndex + 1];
        var finalStep = steps[steps.length - 1];
        finalStep.onNext = function () {
            if (nextSegment) {
                navigateToSegment(action, tour, segmentIndex + 1);

                return false;
            }

            completed = true;
            clearState();

            return true;
        };

        transitionInProgress = false;
        completed = false;
        runningDriver = new window.Driver({
            allowOverlayClose: false,
            nextBtnText: 'Next step',
            prevBtnText: 'Back',
            doneBtnText: nextSegment ? 'Continue to next page' : 'Finish guide',
            closeBtnText: 'Close guide',
            onBeforeStepChanged: function (stepIndex, step, element) {
                prepareResponsiveTarget(element);
            },
            onStepChanged: function (stepIndex) {
                writeState({action: action, segment: segment.id, step: stepIndex});
            },
            onDestroyStarted: function () {
                restoreResponsiveLayout();

                if (!transitionInProgress && !completed) {
                    clearState();
                }

                runningDriver = null;
            },
        });
        runningDriver.setSteps(steps);
        runningDriver.start(Math.min(requestedStep || 0, steps.length - 1));
    }

    function closeDrawer(callback) {
        var drawer = document.getElementById('offcanvasTourDrawer');

        if (!drawer || !drawer.classList.contains('show')) {
            callback();

            return;
        }

        drawer.addEventListener('hidden.bs.offcanvas', callback, {once: true});

        if (window.bootstrap && window.bootstrap.Offcanvas) {
            window.bootstrap.Offcanvas.getOrCreateInstance(drawer).hide();

            return;
        }

        var dismiss = drawer.querySelector('[data-bs-dismiss="offcanvas"]');

        if (dismiss) {
            dismiss.click();
        } else {
            callback();
        }
    }

    function startTour(action) {
        var tour = tours[action];

        if (!tour || tour.segments.length === 0) {
            notify('This guide is not available for your current permissions.');

            return;
        }

        if (runningDriver) {
            runningDriver.destroy('close');
        }

        var segmentIndex = currentSegmentIndex(tour);

        if (segmentIndex < 0) {
            segmentIndex = 0;
        }

        var segment = tour.segments[segmentIndex];
        writeState({action: action, segment: segment.id, step: 0});
        closeDrawer(function () {
            if (pathMatches(segment.url)) {
                runTour(action, 0);
            } else {
                window.location.href = segment.url;
            }
        });
    }

    function resumeTourOnLoad() {
        var state = readState();

        if (!state || !tours[state.action]) {
            return;
        }

        var tour = tours[state.action];
        var segment = tour.segments.find(function (item) {
            return item.id === state.segment;
        });

        if (!segment || !pathMatches(segment.url)) {
            clearState();

            return;
        }

        window.setTimeout(function () {
            runTour(state.action, state.step || 0);
        }, 300);
    }

    function registerTours(catalog) {
        Object.assign(tours, catalog || {});
    }

    window.TourManager = {
        getTours: function () {
            return tours;
        },
        registerTours: registerTours,
        resumeTourOnLoad: resumeTourOnLoad,
        runTour: runTour,
        startTour: startTour,
    };
})(window);
