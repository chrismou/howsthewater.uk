{{--
    The key under a map, in the colours map.js draws: places (circles) and overflows (squares) coloured by spills on one
    scale. where: what the places' colours count ("upstream", "nearby", "upstream or nearby"), null on a map whose only
    coloured marker is an overflow. place: what the coloured circles are, named when overflows share the map.
    overflows: whether the map has any. neutral: what a white circle is (a place shown without its status), if any.
    spills false where we do not read the overflow data, so every marker is grey and the colours would mean nothing.
--}}
@props(['where' => null, 'place' => null, 'overflows' => false, 'neutral' => null, 'spills' => true])

@php
    $colours = [
        '#b91c1c' => 'Overflow discharging',
        '#c2410c' => 'Spill in 24h',
        '#b45309' => 'Spill in 48h',
        '#047857' => 'No spills reported in 48h',
        '#78716c' => 'No data or out of date',
    ];
    // With no coloured place, the colours are the overflows' own, so they are drawn as squares.
    $shape = $where === null ? 'rounded-[2px]' : 'rounded-full';
@endphp

@if ($spills || $neutral !== null)
<ul {{ $attributes->merge(['class' => 'mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted']) }} aria-label="Map key">
    @if ($spills)
        <li class="font-medium text-ink-2">{{ $where === null ? 'Spills:' : 'Spills '.$where.':' }}</li>
        @foreach ($colours as $colour => $label)
            <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 {{ $shape }}" style="background:{{ $colour }}"></span>{{ $label }}</li>
        @endforeach
    @endif
    @if ($spills && $where !== null && $overflows)
        <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-muted"></span>{{ $place ?? 'Place' }}</li>
        <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-[2px] bg-muted"></span>Overflow</li>
    @endif
    @if ($neutral !== null)
        <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full border-2 border-ink bg-surface"></span>{{ $neutral }}</li>
    @endif
</ul>
@endif
