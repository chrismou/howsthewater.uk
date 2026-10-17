@props(['places', 'limit' => 3])

{{-- The places whose pages show an overflow, nearest first: spots downstream with the distance along the water,
     beaches and lakes with the distance away. --}}
@php
    $shown = $places->take($limit);
    $more = $places->count() - $shown->count();
@endphp

@if ($places->isEmpty())
    <span class="text-muted">-</span>
@else
    @foreach ($shown as $link)
        <a href="{{ $link->placeUrl() }}" class="hover:underline">{{ $link->place->name }}</a> <span class="whitespace-nowrap font-mono text-xs text-muted">{{ number_format($link->distance_metres / 1000, 1) }} km</span>@if (! $loop->last || $more > 0),@endif
    @endforeach
    @if ($more > 0)
        <span class="text-muted">and {{ $more }} more</span>
    @endif
@endif
