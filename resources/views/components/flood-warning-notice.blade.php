@props(['summary', 'spotLimit' => null, 'linkRegion' => false])

@php
    $entries = $summary->floodWarnings();
    $hasWarning = $entries->contains(fn ($entry) => $entry['warning']->severity !== \App\Enums\FloodSeverity::FloodAlert);
    // A faint tint of the most severe warning's colour, layered over the surface so it stays light on the page background.
    $boxTint = match ($entries->first()['warning']->severity ?? null) {
        \App\Enums\FloodSeverity::SevereFloodWarning => 'border-l-alert bg-linear-to-r from-alert/10 to-alert/10',
        \App\Enums\FloodSeverity::FloodWarning => 'border-l-alert bg-linear-to-r from-alert/5 to-alert/5',
        default => 'border-l-spill bg-linear-to-r from-spill/5 to-spill/5',
    };
    $badgeClasses = fn ($severity) => match ($severity) {
        \App\Enums\FloodSeverity::SevereFloodWarning => 'border-alert bg-alert text-surface',
        \App\Enums\FloodSeverity::FloodWarning => 'border-pill-red-ring bg-pill-red-bg text-pill-red-text',
        \App\Enums\FloodSeverity::FloodAlert => 'border-pill-amber-ring bg-pill-amber-bg text-pill-amber-text',
    };
@endphp

{{-- The flood alerts and warnings in force across a region, shown only while there are some. Pass spotLimit to shorten long spot lists and linkRegion to link the heading to the region page. --}}
@if ($entries->isNotEmpty())
    {{-- The home page lists several regions, so it wraps them in one #flood-warnings anchor instead. --}}
    <section {!! $linkRegion ? '' : 'id="flood-warnings"' !!} class="mb-8 rounded-lg border border-l-4 border-line bg-surface px-5 py-4 {{ $boxTint }}" role="alert">
        <h2 class="font-display text-lg font-semibold">
            Environment Agency {{ \Illuminate\Support\Str::plural($hasWarning ? 'flood warning' : 'flood alert', $entries->count()) }} in
            @if ($linkRegion)
                <a href="{{ route('regions.show', $summary->slug) }}" class="hover:underline">{{ $summary->name }}</a>
            @else
                {{ $summary->name }}
            @endif
            <x-help term="flood_warning" />
        </h2>
        <ul class="mt-3 divide-y divide-line-2 text-sm">
            @foreach ($entries as $entry)
                @php
                    $warning = $entry['warning'];
                    $shownSpots = $spotLimit === null ? $entry['spots'] : $entry['spots']->take($spotLimit);
                    $hiddenSpotCount = $entry['spots']->count() - $shownSpots->count();
                @endphp
                <li class="flex flex-col gap-1.5 py-2.5 first:pt-0 last:pb-0 sm:flex-row sm:gap-4">
                    <span class="w-fit shrink-0 self-start rounded-full border px-2.5 py-2.5 text-xs font-semibold sm:w-44 sm:text-center {{ $badgeClasses($warning->severity) }}">{{ $warning->severity->label() }}</span>
                    <div class="min-w-0">
                        <a href="{{ $warning->url() }}" rel="noopener" target="_blank" class="font-semibold text-ink hover:underline">{{ $warning->areaLabel }}</a>
                        @if ($warning->isStale())
                            <span class="text-warn-text">· not confirmed for over {{ \App\Data\FloodWarningSummary::STALE_AFTER_HOURS }} hours</span>
                        @endif
                        <p class="mt-0.5 text-ink-2">
                            <span class="text-muted">Covers</span>
                            @foreach ($shownSpots as $spot)
                                <a href="{{ route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]) }}" class="hover:underline">{{ $spot->name }}</a>@if (! $loop->last)<span class="text-faint" aria-hidden="true"> · </span>@endif
                            @endforeach
                            @if ($hiddenSpotCount > 0)
                                <a href="{{ route('regions.show', $summary->slug) }}#flood-warnings" class="text-accent hover:underline">and {{ $hiddenSpotCount }} more</a>
                            @endif
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@endif
