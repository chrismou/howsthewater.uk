{{--
    One of the levels page's lists: a river per row, at the spot whose gauge put it there, with the reading and when it
    was taken. The first rows show; the rest open in place, which needs no script. spots: SpotSummary values.
    regionNames: region slug to name, given on the combined page so each row says where it is.
--}}
@php($shownCount = 25)

@if ($spots->isEmpty())
    <p class="text-sm text-ink-2">{{ $empty }}</p>
@else
    <ul class="divide-y divide-line-2 rounded-lg border border-line bg-surface">
        @foreach ($spots->take($shownCount) as $spot)
            @include('levels.row', ['spot' => $spot, 'regionName' => $regionNames->get($spot->regionSlug)])
        @endforeach
    </ul>
    @if ($spots->count() > $shownCount)
        <details class="mt-3">
            <summary class="cursor-pointer text-sm font-medium text-accent hover:underline">Show the other {{ $spots->count() - $shownCount }}</summary>
            <ul class="mt-3 divide-y divide-line-2 rounded-lg border border-line bg-surface">
                @foreach ($spots->skip($shownCount) as $spot)
                    @include('levels.row', ['spot' => $spot, 'regionName' => $regionNames->get($spot->regionSlug)])
                @endforeach
            </ul>
        </details>
    @endif
@endif
