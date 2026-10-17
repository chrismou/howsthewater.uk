{{--
    The visible trail above a page's heading: every crumb but the last, which is the page itself (its H1). Pages pass
    the same items to the layout's breadcrumbs, which become the BreadcrumbList, so the two always match.
    items: [name, url] pairs, ending with the current page.
--}}
@props(['items'])

@if (count($items) > 1)
    <nav aria-label="Breadcrumb" {{ $attributes->merge(['class' => 'text-sm text-muted']) }}>
        @foreach (array_slice($items, 0, -1) as [$name, $url])
            <a href="{{ $url }}" class="hover:underline">{{ $name }}</a>
            @if (! $loop->last)
                <span class="mx-1">/</span>
            @endif
        @endforeach
    </nav>
@endif
