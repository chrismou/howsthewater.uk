{{--
    Share (or copy link), the place's RSS feed and links to open it in OpenStreetMap and Google Maps, for a place's
    page header. Phones get a two by two grid with shorter map labels; wider screens one row. The share button copies
    the link until share.js finds a touch screen with a share sheet, where it becomes "Share".
--}}
@props(['latitude', 'longitude', 'label', 'feed' => null, 'feedTitle' => null])

@php
    $buttonClasses = 'inline-flex h-11 items-center gap-2 rounded-md border border-line bg-surface px-3 text-sm text-ink hover:border-muted focus:outline-none focus:ring-1 focus:ring-accent';
@endphp

<div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
    <button type="button" data-copy-link="{{ url()->current() }}" data-share-title="{{ $label }}" class="{{ $buttonClasses }}"
            aria-label="Copy a link to {{ $label }}">
        <svg data-copy-link-icon class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1.5 1.5"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.5-1.5"/></svg>
        <svg data-share-icon hidden class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 15V3"/><path d="m7 8 5-5 5 5"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>
        <span data-copy-link-label>Copy link</span>
    </button>
    @if ($feed)
        <a href="{{ $feed }}" title="{{ $feedTitle ?? 'Follow nearby overflows, samples and pollution forecasts in a feed reader' }}" class="{{ $buttonClasses }}">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M5 11a8 8 0 0 1 8 8"/><path d="M5 5a14 14 0 0 1 14 14"/><circle cx="6" cy="18" r="1.2" fill="currentColor"/></svg>
            RSS feed
        </a>
    @endif
    <a href="{{ sprintf('https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=14/%s/%s', $latitude, $longitude, $latitude, $longitude) }}" rel="noopener" target="_blank" class="{{ $buttonClasses }}">
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2z"/><path d="M9 4v14M15 6v14"/></svg>
        <span><span class="hidden sm:inline">Open in </span>OpenStreetMap</span>
    </a>
    <a href="{{ sprintf('https://www.google.com/maps/search/?api=1&query=%s,%s', $latitude, $longitude) }}" rel="noopener" target="_blank" class="{{ $buttonClasses }}">
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-6-5.3-6-11a6 6 0 0 1 12 0c0 5.7-6 11-6 11z"/><circle cx="12" cy="10" r="2.2"/></svg>
        <span><span class="hidden sm:inline">Open in </span>Google Maps</span>
    </a>
</div>
