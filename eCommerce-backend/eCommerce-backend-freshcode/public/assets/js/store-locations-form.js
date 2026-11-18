/* global jQuery, google */
/*
 * Store Locations — form page glue.
 *
 * Two jobs:
 *   1. Keep `country_name` (hidden) in sync with the TomSelect country choice
 *      so the row stores the human label alongside the ISO2 code.
 *   2. Wire a Google Places (Places API New) address search that is restricted
 *      to the selected country and auto-fills address_1, city, state, pin_code,
 *      latitude and longitude on selection. Uses <gmp-place-autocomplete>
 *      (PlaceAutocompleteElement) — google.maps.places.Autocomplete is
 *      legacy as of Mar 2025. Disabled until a country is picked; the
 *      country restriction is updated live whenever the country changes.
 *      Hidden entirely when the Google API key isn't configured.
 *
 * The Google Maps JS API is loaded lazily on first use to keep the form
 * cheap when the key isn't set.
 */
(function ($) {
    'use strict';

    var GOOGLE_MAPS_URL_BASE = 'https://maps.googleapis.com/maps/api/js';
    var mapsLoaderPromise = null;

    /**
     * Lazy-load the Google Maps JS API (places library).
     * Resolves with the `google.maps` namespace.
     */
    function loadGoogleMaps(apiKey) {
        if (window.google && window.google.maps && window.google.maps.places) {
            return Promise.resolve(window.google.maps);
        }
        if (mapsLoaderPromise) return mapsLoaderPromise;

        mapsLoaderPromise = new Promise(function (resolve, reject) {
            var callbackName = '__hcStoreLocationMapsReady_' + Date.now();
            window[callbackName] = function () {
                try {
                    delete window[callbackName];
                } catch (e) {
                    window[callbackName] = undefined;
                }
                if (window.google && window.google.maps) {
                    resolve(window.google.maps);
                } else {
                    reject(new Error('Google Maps failed to initialise'));
                }
            };

            var script = document.createElement('script');
            var params = new URLSearchParams({
                key: apiKey,
                libraries: 'places',
                loading: 'async',
                callback: callbackName,
                v: 'weekly'
            });
            script.src = GOOGLE_MAPS_URL_BASE + '?' + params.toString();
            script.async = true;
            script.defer = true;
            script.onerror = function () {
                mapsLoaderPromise = null;
                reject(new Error('Google Maps script failed to load'));
            };
            document.head.appendChild(script);
        });

        return mapsLoaderPromise;
    }

    /**
     * Pull a single address component value out of a PlacesNew result by type.
     * PlaceAutocompleteElement returns components shaped { types: [], longText, shortText }.
     */
    function pickComponent(components, type, useShort) {
        if (!components || !components.length) return '';
        for (var i = 0; i < components.length; i++) {
            var c = components[i];
            if (c.types && c.types.indexOf(type) !== -1) {
                return useShort ? (c.shortText || '') : (c.longText || '');
            }
        }
        return '';
    }

    /**
     * Take a fetched `place` (Places API New) and fan it out into the form fields.
     * Only overwrites a field when Places gave us a value for it; that way a
     * partial autocomplete pick doesn't blank out something the user typed.
     */
    function applyPlaceToForm(place) {
        if (!place) return;

        var components = place.addressComponents || [];
        var city = pickComponent(components, 'locality')
            || pickComponent(components, 'postal_town')
            || pickComponent(components, 'sublocality_level_1')
            || pickComponent(components, 'administrative_area_level_2');
        var state = pickComponent(components, 'administrative_area_level_1');
        var postal = pickComponent(components, 'postal_code');
        var countryIso = pickComponent(components, 'country', true);
        var streetNumber = pickComponent(components, 'street_number');
        var route = pickComponent(components, 'route');
        var streetLine = [streetNumber, route].filter(Boolean).join(' ').trim();

        // address_1: prefer the constructed street line; otherwise use the
        // formatted address (which we already split country off of).
        var address1 = streetLine || place.formattedAddress || '';

        if (address1) $('#loc-address-1').val(address1);
        if (city) $('#loc-city').val(city);
        if (state) $('#loc-state').val(state);
        if (postal) $('#loc-pin-code').val(postal);

        // place.location is a LatLng-like with lat()/lng() methods in the New API.
        if (place.location) {
            var lat = typeof place.location.lat === 'function' ? place.location.lat() : place.location.lat;
            var lng = typeof place.location.lng === 'function' ? place.location.lng() : place.location.lng;
            if (typeof lat === 'number') $('#loc-latitude').val(lat);
            if (typeof lng === 'number') $('#loc-longitude').val(lng);
        }

        // Sync the country selector if Places returned a country that differs
        // from what's currently chosen — only when the option already exists.
        if (countryIso) {
            var $countrySelect = $('select[name="country_iso2"]');
            var current = ($countrySelect.val() || '').toString().toUpperCase();
            if (current && current !== countryIso.toUpperCase()) {
                var iso = countryIso.toUpperCase();
                if ($countrySelect[0] && $countrySelect[0].tomselect) {
                    var ts = $countrySelect[0].tomselect;
                    if (ts.options[iso]) ts.setValue(iso, false);
                }
            }
        }
    }

    function pickClassic(components, type, useShort) {
        if (!components || !components.length) return '';
        for (var i = 0; i < components.length; i++) {
            var c = components[i];
            if (c.types && c.types.indexOf(type) !== -1) {
                return useShort ? (c.short_name || '') : (c.long_name || '');
            }
        }
        return '';
    }

    function applyGeocodeToForm(result) {
        if (!result) return;
        var components = result.address_components || [];
        var city = pickClassic(components, 'locality')
            || pickClassic(components, 'postal_town')
            || pickClassic(components, 'sublocality_level_1')
            || pickClassic(components, 'administrative_area_level_2');
        var state = pickClassic(components, 'administrative_area_level_1');
        var postal = pickClassic(components, 'postal_code');
        var countryIso = pickClassic(components, 'country', true);
        var countryName = pickClassic(components, 'country');
        var streetNumber = pickClassic(components, 'street_number');
        var route = pickClassic(components, 'route');
        var streetLine = [streetNumber, route].filter(Boolean).join(' ').trim();
        var address1 = streetLine || result.formatted_address || '';

        if (address1) $('#loc-address-1').val(address1);
        if (city) $('#loc-city').val(city);
        if (state) $('#loc-state').val(state);
        if (postal) $('#loc-pin-code').val(postal);

        if (countryIso) {
            var $countrySelect = $('select[name="country_iso2"]');
            var iso = countryIso.toUpperCase();
            if ($countrySelect[0] && $countrySelect[0].tomselect) {
                var ts = $countrySelect[0].tomselect;
                if (ts.options[iso]) ts.setValue(iso, false);
            }
            if (countryName) $('#loc-country-name').val(countryName);
        }
    }

    function initLocationMap(mapEl, apiKey) {
        var latInput = document.getElementById('loc-latitude');
        var lngInput = document.getElementById('loc-longitude');
        var statusEl = document.getElementById('location-map-status');
        var loadingText = mapEl.getAttribute('data-loading-text');
        var doneText = mapEl.getAttribute('data-done-text');
        var errorText = mapEl.getAttribute('data-error-text');

        var startLat = parseFloat(latInput && latInput.value);
        var startLng = parseFloat(lngInput && lngInput.value);
        var hasStart = !isNaN(startLat) && !isNaN(startLng);
        var center = hasStart ? { lat: startLat, lng: startLng } : { lat: 20.5937, lng: 78.9629 };

        loadGoogleMaps(apiKey).then(function () {
            var maps = window.google.maps;
            var map = new maps.Map(mapEl, {
                center: center,
                zoom: hasStart ? 15 : 4,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false
            });
            var geocoder = new maps.Geocoder();
            var marker = new maps.Marker({ map: map, position: center, draggable: true });
            if (!hasStart) marker.setVisible(false);

            function reverse(lat, lng) {
                if (statusEl && loadingText) statusEl.textContent = loadingText;
                geocoder.geocode({ location: { lat: lat, lng: lng } }, function (results, status) {
                    if (status !== 'OK' || !results || !results.length) {
                        if (statusEl && errorText) statusEl.textContent = errorText;
                        return;
                    }
                    applyGeocodeToForm(results[0]);
                    if (statusEl && doneText) statusEl.textContent = doneText;
                });
            }

            function place(lat, lng) {
                marker.setPosition({ lat: lat, lng: lng });
                marker.setVisible(true);
                if (latInput) latInput.value = lat.toFixed(7);
                if (lngInput) lngInput.value = lng.toFixed(7);
                reverse(lat, lng);
            }

            map.addListener('click', function (e) {
                place(e.latLng.lat(), e.latLng.lng());
            });
            marker.addListener('dragend', function (e) {
                place(e.latLng.lat(), e.latLng.lng());
            });
        }).catch(function (err) {
            if (window.console && console.warn) {
                console.warn('Store-location map disabled:', err);
            }
            var wrap = mapEl.closest('.mb-3');
            if (wrap) wrap.setAttribute('hidden', 'hidden');
        });
    }

    $(function () {
        var $form = $('#store-location-form');
        if (!$form.length) return;

        // ─── country_name sync (always wired) ──────────────────────────────
        var $countrySelect = $('select[name="country_iso2"]');
        var $countryName = $('#loc-country-name');

        var syncCountryName = function () {
            if (!$countrySelect.length || !$countryName.length) return;
            var text = $countrySelect.find('option:selected').text() || '';
            $countryName.val(text.trim());
        };
        syncCountryName();
        $countrySelect.on('change', syncCountryName);

        // ─── Google Places (New) address search ────────────────────────────
        var apiKey = ($form.data('google-api-key') || '').toString().trim();

        var $wrap = $('#loc-address-search-wrap');
        var $host = $('#loc-address-search-host');

        if (!apiKey || !$wrap.length || !$host.length) {
            // Key not configured — keep the raw fields, drop the search input.
            return;
        }

        $wrap.removeAttr('hidden');

        var emptyPlaceholder = $host.data('empty-placeholder') || 'Select a country first';
        var readyPlaceholder = $host.data('ready-placeholder') || 'Search address…';
        var placeAutocomplete = null;

        function setIncludedRegion(iso) {
            if (!placeAutocomplete) return;
            if (iso) {
                placeAutocomplete.includedRegionCodes = [iso.toLowerCase()];
                placeAutocomplete.removeAttribute('disabled');
                // The web component exposes `placeholder` as a prop/attr.
                try { placeAutocomplete.placeholder = readyPlaceholder; } catch (e) { /* noop */ }
                placeAutocomplete.setAttribute('placeholder', readyPlaceholder);
            } else {
                placeAutocomplete.includedRegionCodes = [];
                placeAutocomplete.setAttribute('disabled', 'disabled');
                try { placeAutocomplete.placeholder = emptyPlaceholder; } catch (e) { /* noop */ }
                placeAutocomplete.setAttribute('placeholder', emptyPlaceholder);
            }
        }

        loadGoogleMaps(apiKey)
            .then(function () {
                return window.google.maps.importLibrary('places');
            })
            .then(function (placesLib) {
                var PlaceAutocompleteElement = (placesLib && placesLib.PlaceAutocompleteElement)
                    || window.google.maps.places.PlaceAutocompleteElement;

                if (!PlaceAutocompleteElement) {
                    throw new Error('PlaceAutocompleteElement not available');
                }

                placeAutocomplete = new PlaceAutocompleteElement();
                placeAutocomplete.id = 'loc-address-search-element';
                placeAutocomplete.style.width = '100%';

                $host.empty().append(placeAutocomplete);

                var initialIso = ($countrySelect.val() || '').toString();
                setIncludedRegion(initialIso);

                placeAutocomplete.addEventListener('gmp-select', function (event) {
                    var prediction = event && event.placePrediction;
                    if (!prediction) return;

                    var place = prediction.toPlace();
                    place.fetchFields({
                        fields: ['addressComponents', 'formattedAddress', 'location', 'displayName']
                    }).then(function () {
                        applyPlaceToForm(place);
                    }).catch(function (err) {
                        if (window.console && console.warn) {
                            console.warn('Place fetch failed:', err);
                        }
                    });
                });

                $countrySelect.on('change', function () {
                    var iso = ($countrySelect.val() || '').toString();
                    setIncludedRegion(iso);
                });
            })
            .catch(function (err) {
                // Maps failed (bad key, blocked network, billing off) — keep the
                // raw address fields usable and hide the search input.
                if (window.console && console.warn) {
                    console.warn('Store-location address search disabled:', err);
                }
                $wrap.attr('hidden', 'hidden');
            });
    });

    $(function () {
        var mapEl = document.getElementById('location-map');
        if (!mapEl) return;
        var apiKey = (mapEl.getAttribute('data-google-api-key') || '').trim();
        if (!apiKey) return;
        initLocationMap(mapEl, apiKey);
    });
})(jQuery);
