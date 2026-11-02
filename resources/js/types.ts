import type L from 'leaflet';

/** The values of SpillRecency::onMap() and SpillRecency::forMonitor(), which colour every map marker. */
export type MapRecency = 'discharging_now' | 'within_24_hours' | 'within_48_hours' | 'none' | 'offline' | 'no_data' | 'not_covered';

/** A marker in a map's data-map-markers JSON: a place (circle), an overflow (square) or a level (blue). */
export interface MapMarker {
    lat: number;
    lng: number;
    label: string;
    kind: 'place' | 'monitor' | 'level';
    recency?: MapRecency;
    /** Levels only: 'higher', 'rising' or 'usual' (at or below the usual range, on river pages). */
    state?: string;
    note?: string | null;
    url?: string | null;
    link?: string | null;
    /** Places on the swimming page: 'river', 'beach' or 'lake'. */
    type?: string;
}

/** A place in /swimming/places.json (SwimmingSpot::toMapPlace()). */
export interface MapPlace extends MapMarker {
    type: string;
    region: string;
    detail: string;
    pill: string;
    tone: string;
}

/** What map.ts hands a page's own script once a map is drawn (element.leafletMap, the map:ready event). */
export interface LeafletMapHandle {
    map: L.Map;
    markers: Array<{ marker: MapMarker; layer: L.Marker | L.CircleMarker }>;
    L: typeof L;
}

/** A region in the header menus' JSON (NavigationMenus::menusJson()): its groups are [heading, [name, path][]]. */
export interface NavRegion {
    slug: string;
    name: string;
    url: string;
    blurb: string;
    groups: Array<[string, Array<[string, string]>]>;
}

declare global {
    interface HTMLElement {
        leafletMap?: LeafletMapHandle;
    }

    interface HTMLElementEventMap {
        'map:ready': CustomEvent<LeafletMapHandle>;
    }

    interface Window {
        dataLayer: IArguments[];
        gtag?: (...args: unknown[]) => void;
    }
}
