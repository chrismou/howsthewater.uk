const requests = new Map();

/**
 * Fetches a JSON file once per page however many scripts ask for it (the swimming page's map and its "near me" list
 * both read /swimming/places.json).
 */
export function fetchJsonOnce(url) {
    if (!requests.has(url)) {
        requests.set(url, fetch(url, { headers: { Accept: 'application/json' } }).then((response) => {
            if (!response.ok) {
                requests.delete(url);
                throw new Error(`HTTP ${response.status}`);
            }

            return response.json();
        }));
    }

    return requests.get(url);
}
