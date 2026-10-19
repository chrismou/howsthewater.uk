{{--
    A headline figure or its label, linked to the page or section listing what it counts, or plain text when there is
    nowhere to go (url null). Pass figure on the number: it repeats its label's link, so screen readers and the
    keyboard skip it.
--}}
@props(['url' => null, 'figure' => false])

@if ($url === null){{ $slot }}@else<a href="{{ $url }}" @if ($figure) aria-hidden="true" tabindex="-1" @endif class="hover:underline">{{ $slot }}</a>@endif
