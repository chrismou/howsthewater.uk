@props(['nearby'])

{{-- Neighbouring spots, beaches and lakes, then links back to the listings this place belongs to. --}}
<section {{ $attributes->merge(['class' => 'mt-10']) }}>
    <h2 class="font-display mb-3 text-lg font-semibold">Nearby</h2>
    @if ($nearby->places->isNotEmpty())
        {{-- Two or four places sit in pairs rather than leaving one on its own in a row of three. --}}
        <ul @class(['grid gap-3 sm:grid-cols-2', 'lg:grid-cols-3' => ! in_array($nearby->places->count(), [2, 4], true)])>
            @foreach ($nearby->places as $place)
                <li>
                    <a href="{{ $place->url }}" class="flex h-full flex-col items-start gap-1.5 rounded-lg border border-line bg-surface p-4 transition hover:border-muted hover:shadow-sm">
                        <span class="text-xs uppercase tracking-wide text-muted">{{ $place->relation }}</span>
                        <span>
                            <span class="font-display block text-lg font-semibold">{{ $place->name }}</span>
                            <span class="block text-sm text-muted">{{ $place->distanceLabel() }}</span>
                        </span>
                        <x-spill-badge :recency="$place->spillRecency" :stale="$place->stale" :nearby="$place->nearbyWording" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
    <p class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm">
        @foreach ($nearby->links as [$text, $url])
            <a href="{{ $url }}" class="font-medium text-accent hover:underline">{{ $text }}</a>
        @endforeach
    </p>
</section>
