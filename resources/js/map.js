import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { fetchJsonOnce } from './json-cache';

// Every map colours places and overflows alike, by spills on one scale: red while an overflow discharges, orange and
// amber after a spill in the last 24 and 48 hours, green when none was reported in 48 hours and grey with nothing to
// go on (offline, out of date, none matched, or a feed we do not read). Places are circles and overflows squares,
// so the two never read as each other. Levels are blue, which no spill status uses, and a place drawn only to show
// where it is (no status on that map) is white with a dark ring. components/map-key draws the same colours.
const recencyColours = {
    discharging_now: '#b91c1c',
    within_24_hours: '#c2410c',
    within_48_hours: '#b45309',
    none: '#047857',
    offline: '#78716c',
    no_data: '#78716c',
    not_covered: '#78716c',
};

const levelColour = '#1f5f8b';
const inkColour = '#1b2a33';

function markerFor(marker, compact = false) {
    const latLng = [marker.lat, marker.lng];

    if (marker.kind === 'monitor') {
        const colour = recencyColours[marker.recency] ?? recencyColours.no_data;

        return L.marker(latLng, {
            pane: 'overflows',
            keyboard: false,
            icon: L.divIcon({
                className: '',
                iconSize: [12, 12],
                html: `<span style="display:block;box-sizing:border-box;width:12px;height:12px;border-radius:2px;background:${colour};border:1.5px solid #fff;box-shadow:0 0 0 1px rgba(27,42,51,.35)"></span>`,
            }),
        });
    }

    // On a map of hundreds of places (the swimming page) full-size markers merge into blobs at the national zoom.
    const radius = compact ? 5 : 8;
    const style = marker.kind === 'level'
        ? { fillColor: marker.state === 'rising' ? '#ffffff' : levelColour, color: marker.state === 'rising' ? levelColour : '#ffffff', weight: marker.state === 'rising' ? 3 : 2 }
        : marker.recency === undefined
            ? { fillColor: '#ffffff', color: inkColour, weight: 2.5 }
            : { fillColor: recencyColours[marker.recency] ?? recencyColours.no_data, color: '#ffffff', weight: compact ? 1 : 2 };

    return L.circleMarker(latLng, { pane: 'places', radius, fillOpacity: 0.95, ...style });
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

    // Places sit above overflows, so the place a page is about is never hidden under the overflows around it.
    map.createPane('overflows').style.zIndex = 450;
    map.createPane('places').style.zIndex = 460;

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
