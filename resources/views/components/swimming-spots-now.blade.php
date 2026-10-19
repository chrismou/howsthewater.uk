@props(['now'])

{{--
    The home page's "Right now at popular swimming spots" (SwimmingSpots::popularNow()): designated bathing waters and
    well-known river spots with anything happening there first, then well-known spots as they are. On a quiet day it
    says so before listing them, so a list of calm spots never reads as a list of news.
--}}
<section aria-labelledby="swimming-now" {{ $attributes->merge(['class' => 'max-w-xl']) }}>
    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 id="swimming-now" class="font-display text-xl font-semibold tracking-tight">Right now at popular swimming spots</h2>
        <a href="{{ route('swimming') }}" class="text-sm font-medium text-accent hover:underline">All swimming spots</a>
    </div>
    <p class="mt-1 text-sm text-ink-2">
        @if ($now->anythingHappening())
            Designated bathing waters and well-known river spots, with anything happening there first.
        @else
            Nothing to report at {{ number_format($now->popularCount) }} designated bathing waters and well-known river spots right now. A few well-known spots:
        @endif
    </p>
    @if ($now->spots->isNotEmpty())
        <ul class="mt-3 divide-y divide-line-2 overflow-hidden rounded-lg border border-line bg-surface">
            @foreach ($now->spots as $spot)
                <li>
                    {{-- Phones put the status under the name, so long badges never cut the name short. --}}
                    <a href="{{ $spot->url }}" class="flex flex-col items-start gap-1.5 px-4 py-2.5 transition hover:bg-surface-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                        <span class="min-w-0 max-w-full">
                            <span class="block truncate font-semibold">{{ $spot->name }}</span>
                            <span class="block truncate text-xs text-muted">{{ $spot->detail }}, {{ $spot->regionName }}@if ($spot->note) · <span class="font-medium text-warn-text">{{ $spot->note }}</span>@endif</span>
                        </span>
                        <x-spill-badge :recency="$spot->spillRecency" :stale="$spot->stale" :nearby="$spot->nearbyWording" class="shrink-0 whitespace-nowrap" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
