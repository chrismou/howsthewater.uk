import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const recencyColours = {
    discharging_now: '#b91c1c',
    within_24_hours: '#c2410c',
    within_48_hours: '#b45309',
    none: '#047857',
    no_data: '#78716c',
};

const monitorColours = {
    1: '#b91c1c',
    0: '#64748b',
    '-1': '#a8a29e',
};

function markerFor(marker) {
    const isSpot = marker.kind === 'spot';
    const colour = isSpot ? recencyColours[marker.recency] ?? '#78716c' : monitorColours[marker.status] ?? '#64748b';

    return L.circleMarker([marker.lat, marker.lng], {
        radius: isSpot ? 8 : 5,
        color: '#ffffff',
        weight: isSpot ? 2 : 1,
        fillColor: colour,
        fillOpacity: 0.95,
    });
}

export function initMaps() {
    document.querySelectorAll('[data-map]').forEach((element) => {
        const centreline = JSON.parse(element.dataset.mapCentreline || '[]');
        const centrelines = JSON.parse(element.dataset.mapCentrelines || '[]');
        const markers = JSON.parse(element.dataset.mapMarkers || '[]');

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

        markers.forEach((marker) => {
            const layer = markerFor(marker).addTo(map);
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
    });
}
