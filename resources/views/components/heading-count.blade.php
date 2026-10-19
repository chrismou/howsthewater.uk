@props(['count', 'tone' => 'grey'])

{{-- A heading's count after its words ("Spills in the last 24 hours   1,108"): bold, spaced off from the words, in
     the colour of what it counts. The hidden comma keeps the words and the number apart for search engines, screen
     readers and copied text; put it straight after the last word, with no space. --}}
@php
    $colour = match ($tone) {
        'red' => 'text-alert',
        'amber' => 'text-spill',
        default => 'text-muted',
    };
@endphp
<span class="sr-only">, </span><span {{ $attributes->merge(['class' => "ml-3 font-mono font-bold tabular-nums {$colour}"]) }}>{{ number_format($count) }}</span>
