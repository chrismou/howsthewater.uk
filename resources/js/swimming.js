import { fetchJsonOnce } from './json-cache';

/** Places the nearest-first list adds at a time. */
const BATCH = 20;

/**
 * The swimming page's controls. "Show spots near me" asks the browser for the visitor's location (which never leaves
 * the device), loads every place from /swimming/places.json (the map's own data, fetched once) and lists the nearest,
 * a batch at a time, with their distances. The Rivers, Beaches and Lakes buttons filter that list and the map. Without
 * JavaScript the controls stay hidden and the region blocks still link to every place.
 */
export function initSwimming() {
    const page = document.querySelector('[data-swimming]');

    if (!page) {
        return;
    }

    const controls = page.querySelector('[data-swimming-controls]');
    const nearButton = page.querySelector('[data-swimming-near]');
    const status = page.querySelector('[data-swimming-status]');
    const nearest = page.querySelector('[data-swimming-nearest]');
    const nearestList = page.querySelector('[data-swimming-nearest-list]');
    const moreButton = page.querySelector('[data-swimming-more]');
    const regions = page.querySelector('[data-swimming-regions]');
    const mapElement = page.querySelector('[data-swimming-map]');
    const kindButtons = [...page.querySelectorAll('[data-swimming-kind]')];

    let kind = 'all';
    let position = null;
    let sorted = [];
    let shown = 0;
    let userMarker = null;

    controls.hidden = false;

    if (!('geolocation' in navigator)) {
        nearButton.hidden = true;
    }

    const withMap = (callback) => {
        if (mapElement.leafletMap) {
            callback(mapElement.leafletMap);
        } else {
            mapElement.addEventListener('map:ready', (event) => callback(event.detail), { once: true });
        }
    };

    const distanceKm = (lat, lng) => {
        const toRadians = (degrees) => (degrees * Math.PI) / 180;
        const dLat = toRadians(lat - position.lat);
        const dLng = toRadians(lng - position.lng);
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRadians(position.lat)) * Math.cos(toRadians(lat)) * Math.sin(dLng / 2) ** 2;

        return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    };

    const matchesKind = (place) => kind === 'all' || place.type === kind;

    // Built from DOM nodes, as names come from the agencies' and companies' data.
    const row = (place) => {
        const item = document.createElement('li');
        const link = document.createElement('a');
        const text = document.createElement('span');
        const name = document.createElement('b');
        const detail = document.createElement('small');
        const pill = document.createElement('span');
        const distance = document.createElement('strong');

        link.href = place.url;
        text.className = 'min-w-0 max-w-full';
        name.textContent = place.label;
        distance.className = 'font-semibold text-ink';
        distance.textContent = `(${place.distance < 10 ? place.distance.toFixed(1) : Math.round(place.distance)} km away)`;
        detail.append(`${place.detail}, ${place.region} `, distance);
        pill.dataset.pill = place.tone;

        // Anything discharging right now starts with the pulsing dot, as components/live-dot draws it.
        if (place.recency === 'discharging_now') {
            const dot = document.createElement('span');
            dot.className = 'relative inline-flex size-1.5 shrink-0 text-alert';
            dot.setAttribute('aria-hidden', 'true');
            dot.innerHTML = '<span class="absolute inset-0 rounded-full bg-current opacity-60 motion-safe:animate-live"></span><span class="relative inline-flex size-1.5 rounded-full bg-current"></span>';
            pill.append(dot);
        }

        pill.append(place.pill);
        text.append(name, detail);
        link.append(text, pill);
        item.append(link);

        return item;
    };

    const matching = () => sorted.filter(matchesKind);

    const updateMore = () => {
        const remaining = matching().length - shown;
        moreButton.hidden = remaining <= 0;
        moreButton.textContent = `Show ${Math.min(BATCH, remaining)} more`;
    };

    const addBatch = () => {
        matching().slice(shown, shown + BATCH).forEach((place) => nearestList.append(row(place)));
        shown = nearestList.children.length;
        updateMore();
    };

    // Zooms the map to the visitor and the ten nearest places of the kind shown.
    const fitNearest = () => {
        withMap(({ map, L }) => {
            const points = matching().slice(0, 10).map((place) => [place.lat, place.lng]);
            map.fitBounds(L.latLngBounds([[position.lat, position.lng], ...points]).pad(0.15));
        });
    };

    const renderNearest = () => {
        nearestList.replaceChildren();
        shown = 0;
        addBatch();
        fitNearest();
    };

    const filterMap = () => {
        withMap(({ map, markers }) => {
            markers.forEach(({ marker, layer }) => {
                const show = matchesKind(marker);

                if (show && !map.hasLayer(layer)) {
                    layer.addTo(map);
                } else if (!show && map.hasLayer(layer)) {
                    layer.remove();
                }
            });
        });
    };

    const showNearest = (places) => {
        sorted = places
            .map((place) => ({ ...place, distance: distanceKm(place.lat, place.lng) }))
            .sort((a, b) => a.distance - b.distance);

        nearest.hidden = false;
        regions.hidden = true;
        nearButton.textContent = 'Show by region';
        status.textContent = 'Nearest first. Your location stays on this device.';

        withMap(({ map, L }) => {
            userMarker?.remove();
            userMarker = L.circleMarker([position.lat, position.lng], { radius: 7, color: '#ffffff', weight: 2, fillColor: '#1f5f8b', fillOpacity: 1 }).addTo(map).bindPopup('You are here');
        });

        renderNearest();
    };

    const showByRegion = () => {
        nearest.hidden = true;
        regions.hidden = false;
        nearestList.replaceChildren();
        position = null;
        sorted = [];
        nearButton.textContent = 'Show spots near me';
        status.textContent = '';
        userMarker?.remove();
        userMarker = null;
    };

    nearButton.addEventListener('click', () => {
        if (position) {
            showByRegion();

            return;
        }

        status.textContent = 'Finding your location...';
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                position = { lat: coords.latitude, lng: coords.longitude };
                fetchJsonOnce(page.dataset.placesUrl)
                    .then(showNearest)
                    .catch(() => {
                        position = null;
                        status.textContent = 'The spots could not be loaded. Try again in a moment.';
                    });
            },
            () => {
                status.textContent = 'Your location is not available, so the spots stay by region.';
            },
            { maximumAge: 600000, timeout: 15000 },
        );
    });

    moreButton.addEventListener('click', addBatch);

    kindButtons.forEach((button) => {
        button.addEventListener('click', () => {
            kind = button.dataset.swimmingKind;
            kindButtons.forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
            filterMap();

            if (position) {
                renderNearest();
            }
        });
    });
}
