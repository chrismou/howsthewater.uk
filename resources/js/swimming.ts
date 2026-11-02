import type L from 'leaflet';
import { fetchJsonOnce } from './json-cache';
import type { LeafletMapHandle, MapPlace } from './types';

/** Places the nearest-first list adds at a time. */
const BATCH = 20;

interface Position {
    lat: number;
    lng: number;
}

type PlaceWithDistance = MapPlace & { distance: number };

/**
 * The swimming page's controls. "Show spots near me" asks the browser for the visitor's location (which never leaves
 * the device), loads every place from /swimming/places.json (the map's own data, fetched once) and lists the nearest,
 * a batch at a time, with their distances. The Rivers, Beaches and Lakes buttons filter that list and the map. Without
 * JavaScript the controls stay hidden and the region blocks still link to every place.
 */
export function initSwimming(): void {
    const page = document.querySelector<HTMLElement>('[data-swimming]');

    if (!page) {
        return;
    }

    const controls = page.querySelector<HTMLElement>('[data-swimming-controls]');
    const nearButton = page.querySelector<HTMLButtonElement>('[data-swimming-near]');
    const status = page.querySelector<HTMLElement>('[data-swimming-status]');
    const nearest = page.querySelector<HTMLElement>('[data-swimming-nearest]');
    const nearestList = page.querySelector<HTMLElement>('[data-swimming-nearest-list]');
    const moreButton = page.querySelector<HTMLButtonElement>('[data-swimming-more]');
    const regions = page.querySelector<HTMLElement>('[data-swimming-regions]');
    const mapElement = page.querySelector<HTMLElement>('[data-swimming-map]');
    const kindButtons = [...page.querySelectorAll<HTMLElement>('[data-swimming-kind]')];
    const placesUrl = page.dataset.placesUrl;

    if (!controls || !nearButton || !status || !nearest || !nearestList || !moreButton || !regions || !mapElement || !placesUrl) {
        return;
    }

    let kind = 'all';
    let position: Position | null = null;
    let sorted: PlaceWithDistance[] = [];
    let shown = 0;
    let userMarker: L.CircleMarker | null = null;

    controls.hidden = false;

    if (!('geolocation' in navigator)) {
        nearButton.hidden = true;
    }

    const withMap = (callback: (handle: LeafletMapHandle) => void): void => {
        if (mapElement.leafletMap) {
            callback(mapElement.leafletMap);
        } else {
            mapElement.addEventListener('map:ready', (event) => callback(event.detail), { once: true });
        }
    };

    const distanceKm = (from: Position, lat: number, lng: number): number => {
        const toRadians = (degrees: number): number => (degrees * Math.PI) / 180;
        const dLat = toRadians(lat - from.lat);
        const dLng = toRadians(lng - from.lng);
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRadians(from.lat)) * Math.cos(toRadians(lat)) * Math.sin(dLng / 2) ** 2;

        return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    };

    const matchesKind = (place: { type?: string }): boolean => kind === 'all' || place.type === kind;

    // Built from DOM nodes, as names come from the agencies' and companies' data.
    const row = (place: PlaceWithDistance): HTMLLIElement => {
        const item = document.createElement('li');
        const link = document.createElement('a');
        const text = document.createElement('span');
        const name = document.createElement('b');
        const detail = document.createElement('small');
        const pill = document.createElement('span');
        const distance = document.createElement('strong');

        link.href = place.url ?? '';
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

    const matching = (): PlaceWithDistance[] => sorted.filter(matchesKind);

    const updateMore = (): void => {
        const remaining = matching().length - shown;
        moreButton.hidden = remaining <= 0;
        moreButton.textContent = `Show ${Math.min(BATCH, remaining)} more`;
    };

    const addBatch = (): void => {
        matching().slice(shown, shown + BATCH).forEach((place) => nearestList.append(row(place)));
        shown = nearestList.children.length;
        updateMore();
    };

    // Zooms the map to the visitor and the ten nearest places of the kind shown.
    const fitNearest = (from: Position): void => {
        withMap(({ map, L }) => {
            const points = matching().slice(0, 10).map((place): [number, number] => [place.lat, place.lng]);
            map.fitBounds(L.latLngBounds([[from.lat, from.lng], ...points]).pad(0.15));
        });
    };

    const renderNearest = (from: Position): void => {
        nearestList.replaceChildren();
        shown = 0;
        addBatch();
        fitNearest(from);
    };

    const filterMap = (): void => {
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

    const showNearest = (from: Position, places: MapPlace[]): void => {
        sorted = places
            .map((place) => ({ ...place, distance: distanceKm(from, place.lat, place.lng) }))
            .sort((a, b) => a.distance - b.distance);

        nearest.hidden = false;
        regions.hidden = true;
        nearButton.textContent = 'Show by region';
        status.textContent = 'Nearest first. Your location stays on this device.';

        withMap(({ map, L }) => {
            userMarker?.remove();
            userMarker = L.circleMarker([from.lat, from.lng], { pane: 'places', radius: 7, color: '#ffffff', weight: 2, fillColor: '#1b2a33', fillOpacity: 1 }).addTo(map).bindPopup('You are here');
        });

        renderNearest(from);
    };

    const showByRegion = (): void => {
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
                const from: Position = { lat: coords.latitude, lng: coords.longitude };
                position = from;
                fetchJsonOnce<MapPlace[]>(placesUrl)
                    .then((places) => showNearest(from, places))
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
            kind = button.dataset.swimmingKind ?? 'all';
            kindButtons.forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
            filterMap();

            if (position) {
                renderNearest(position);
            }
        });
    });
}
