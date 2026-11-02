const requests = new Map<string, Promise<unknown>>();

/**
 * Fetches a JSON file once per page however many scripts ask for it (the swimming page's map and its "near me" list
 * both read /swimming/places.json).
 */
export function fetchJsonOnce<T>(url: string): Promise<T> {
    let request = requests.get(url);

    if (!request) {
        request = fetch(url, { headers: { Accept: 'application/json' } }).then((response) => {
            if (!response.ok) {
                requests.delete(url);
                throw new Error(`HTTP ${response.status}`);
            }

            return response.json();
        });
        requests.set(url, request);
    }

    return request as Promise<T>;
}
