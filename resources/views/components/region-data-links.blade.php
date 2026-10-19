{{--
    Links from a region's own pages to its spills (where we read its overflow data) and levels pages, named in full so
    the link says where it goes. region: a Region.
--}}
@props(['region'])

<p class="mt-3 text-sm">
    @if ($region->hasOverflowData())
        <a href="{{ route('spills.region', $region) }}" class="font-medium text-accent hover:underline">Spills in {{ $region->nameInSentence() }} now</a>
        <span class="text-muted" aria-hidden="true">·</span>
    @endif
    <a href="{{ route('levels.region', $region) }}" class="font-medium text-accent hover:underline">River levels in {{ $region->nameInSentence() }} now</a>
</p>
