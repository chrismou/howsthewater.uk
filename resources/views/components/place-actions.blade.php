{{-- Copy link and open in OpenStreetMap, for a place's page header. --}}
@props(['latitude', 'longitude', 'label'])

<div class="flex flex-wrap gap-2">
    <button type="button" data-copy-link="{{ url()->current() }}"
            class="inline-flex h-10 items-center gap-2 rounded-md border border-line bg-surface px-3 text-sm text-ink hover:border-muted focus:outline-none focus:ring-1 focus:ring-accent"
            aria-label="Copy a link to {{ $label }}">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1.5 1.5"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.5-1.5"/></svg>
        <span data-copy-link-label>Copy link</span>
    </button>
    <a href="{{ sprintf('https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=14/%s/%s', $latitude, $longitude, $latitude, $longitude) }}" rel="noopener" target="_blank"
       class="inline-flex h-10 items-center gap-2 rounded-md border border-line bg-surface px-3 text-sm text-ink hover:border-muted focus:outline-none focus:ring-1 focus:ring-accent">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-6-5.3-6-11a6 6 0 0 1 12 0c0 5.7-6 11-6 11z"/><circle cx="12" cy="10" r="2.2"/></svg>
        Open in OpenStreetMap
    </a>
</div>
