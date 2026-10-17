{{-- Where a region's overflow feed is not read yet, names the water company and links its own live map. --}}
@props(['region'])

@if ($map = $region?->unreadOverflowMap())
    <span {{ $attributes }}>{{ $map['name'] }} shows the live status of its overflows on its <a href="{{ $map['url'] }}" rel="noopener" target="_blank" class="underline hover:text-ink">storm overflow map</a>.</span>
@endif
