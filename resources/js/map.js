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
        const markers = JSON.parse(element.dataset.mapMarkers || '[]');

        const map = L.map(element, { scrollWheelZoom: false });

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        const layers = [];

        if (centreline.length > 1) {
            layers.push(L.polyline(centreline, { color: '#0284c7', weight: 2, opacity: 0.7 }).addTo(map));
        }

        markers.forEach((marker) => {
            const layer = markerFor(marker).addTo(map);
            const label = marker.url ? `<a href="${marker.url}">${marker.label}</a>` : marker.label;
            layer.bindPopup(label);
            layers.push(layer);
        });

        if (layers.length > 0) {
            map.fitBounds(L.featureGroup(layers).getBounds().pad(0.1));
        } else {
            map.setView([53.9, -1.7], 9);
        }
    });
}
