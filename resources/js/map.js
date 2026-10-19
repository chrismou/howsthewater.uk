import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { fetchJsonOnce } from './json-cache';

const recencyColours = {
    discharging_now: '#b91c1c',
    within_24_hours: '#c2410c',
    within_48_hours: '#b45309',
    none: '#047857',
    no_data: '#78716c',
    not_covered: '#78716c',
};

const monitorColours = {
    1: '#b91c1c',
    0: '#64748b',
    '-1': '#a8a29e',
};

// Beaches and lake bathing sites take the EA classification's colour; places without one (a lake centre) use the accent.
const classificationColours = {
    Excellent: '#047857',
    Good: '#047857',
    Sufficient: '#b45309',
    Poor: '#b91c1c',
};

function markerFor(marker, compact = false) {
    // On a map of hundreds of places (the swimming page) full-size markers merge into blobs at the national zoom.
    const isPlace = !compact && (marker.kind === 'spot' || marker.kind === 'bathing' || marker.kind === 'place');
    const colour = {
        spot: () => recencyColours[marker.recency] ?? '#78716c',
        bathing: () => classificationColours[marker.classification] ?? '#78716c',
        place: () => '#1f5f8b',
    }[marker.kind]?.() ?? monitorColours[marker.status] ?? '#64748b';

    return L.circleMarker([marker.lat, marker.lng], {
        radius: isPlace ? 8 : 5,
        color: '#ffffff',
        weight: isPlace ? 2 : 1,
        fillColor: colour,
        fillOpacity: 0.95,
    });
}

// Each map is built when it comes near the screen, so pages with several (the hubs, a region's cards) do not draw
// and fetch tiles for maps nobody scrolls to.
export function initMaps() {
    const elements = document.querySelectorAll('[data-map]');

    if (!('IntersectionObserver' in window)) {
        elements.forEach(initMap);

        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.filter((entry) => entry.isIntersecting).forEach((entry) => {
            observer.unobserve(entry.target);
            initMap(entry.target);
        });
    }, { rootMargin: '200px' });

    elements.forEach((element) => observer.observe(element));
}

// A page with very many markers (the swimming page) gives a URL to load them from instead (data-map-markers-url), so
// they never weigh down the page itself.
function initMap(element) {
    if (element.dataset.mapMarkersUrl) {
        fetchJsonOnce(element.dataset.mapMarkersUrl)
            .then((markers) => drawMap(element, markers))
            .catch(() => drawMap(element, []));

        return;
    }

    drawMap(element, JSON.parse(element.dataset.mapMarkers || '[]'));
}

function drawMap(element, markers) {
    const centreline = JSON.parse(element.dataset.mapCentreline || '[]');
    const centrelines = JSON.parse(element.dataset.mapCentrelines || '[]');

    // A static map is a picture: no panning, zooming or controls, so it can sit inside a link.
    const isStatic = element.hasAttribute('data-map-static');
    const map = L.map(element, {
        scrollWheelZoom: false,
        dragging: !isStatic,
        touchZoom: !isStatic,
        doubleClickZoom: !isStatic,
        boxZoom: !isStatic,
        keyboard: !isStatic,
        zoomControl: !isStatic,
        // Half steps let the map fit a region's shape closely (a tall region in a wide box would otherwise drop a
        // whole level and show twice the area), while keeping tile labels sharp enough to read.
        zoomSnap: 0.5,
        // Hundreds of markers (the swimming page has every place) draw far faster on one canvas than as SVG nodes.
        preferCanvas: markers.length > 300,
    });

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    const layers = [];

    [centreline, ...centrelines].forEach((line) => {
        if (line.length > 1) {
            layers.push(L.polyline(line, { color: '#1f5f8b', weight: 2, opacity: 0.7 }).addTo(map));
        }
    });

    const markerLayers = [];

    markers.forEach((marker) => {
        const layer = markerFor(marker, markers.length > 300).addTo(map);
        markerLayers.push({ marker, layer });
        // Labels carry feed-supplied text, so build the popup from DOM nodes rather than an HTML string.
        const label = document.createElement(marker.url ? 'a' : 'span');
        label.textContent = marker.label;

        if (marker.url) {
            label.href = marker.url;
        }

        layer.bindPopup(label);
        layers.push(layer);
    });

    if (layers.length > 0) {
        map.fitBounds(L.featureGroup(layers).getBounds().pad(0.05));
    } else {
        map.setView([53.9, -1.7], 9);
    }

    // Lets a page's own script work with the map once it is drawn (the swimming page filters and zooms it).
    element.leafletMap = { map, markers: markerLayers, L };
    element.dispatchEvent(new CustomEvent('map:ready', { detail: element.leafletMap }));
}
