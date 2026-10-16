{{--
    A top-level menu (Rivers, Beaches, Lakes) listing places by region. Wide screens get two panes: regions on the left
    (the current one first selected) and that region's places on the right, switched by nav.js. Narrow screens get a
    full-width accordion under the header.

    Only the regions and their overview links are rendered here, so the menu works without JavaScript and every page's
    header stays small. The places themselves come from the page's nav-menus JSON (see the layout), which nav.js turns
    into each region's lists the first time the menu opens.

    menu: the key into that JSON (rivers, beaches or lakes). entries: one per region from NavigationMenus, each
    ['slug', 'name', 'url' => overview link, 'blurb', 'groups']. current: the slug of the region to open on.
--}}
@props(['label', 'menu', 'entries', 'current' => null])

@php
    $entries = collect($entries)->values();
    $currentSlug = $entries->contains('slug', $current) ? $current : ($entries->first()['slug'] ?? null);
@endphp

@if ($entries->isNotEmpty())
    <details class="sm:relative" data-nav-menu="{{ $menu }}">
        <summary class="flex cursor-pointer list-none items-center gap-1 text-sm font-medium text-ink [&::-webkit-details-marker]:hidden">
            {{ $label }}
            <svg class="h-4 w-4 text-faint" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
            </svg>
        </summary>

        {{-- The panel starts flush with the button and carries its own top padding, so moving the mouse into it never leaves the menu. --}}
        <div class="absolute inset-x-0 top-full z-[1100] px-4 pt-2 sm:inset-x-auto sm:left-0 sm:w-max sm:max-w-[calc(100vw-2rem)] sm:px-0">
            <div class="overflow-hidden rounded-lg border border-line bg-surface text-sm text-ink-2 shadow-lg">
                {{-- Narrow screens: one section per region, the current one open. --}}
                <div class="divide-y divide-line-2 sm:hidden">
                    @foreach ($entries as $entry)
                        <details class="group/region" @if ($entry['slug'] === $currentSlug) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 font-semibold text-ink [&::-webkit-details-marker]:hidden">
                                {{ $entry['name'] }}
                                <svg class="h-4 w-4 text-faint transition group-open/region:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
                                </svg>
                            </summary>
                            <div class="flex flex-col gap-3 px-4 pb-4">
                                <a href="{{ $entry['url'] }}" class="font-medium text-accent hover:underline">{{ $entry['name'] }} overview →</a>
                                <div class="contents" data-nav-groups="{{ $entry['slug'] }}" data-nav-layout="narrow"></div>
                            </div>
                        </details>
                    @endforeach
                </div>

                {{-- Wide screens: regions on the left, the selected region's places on the right. --}}
                <div class="hidden sm:flex">
                    <ul class="w-48 shrink-0 border-r border-line-2 bg-surface-2 py-2" role="list">
                        @foreach ($entries as $entry)
                            <li>
                                {{-- A link to the overview, so it still works without JavaScript; nav.js turns hover or a first tap into a preview. --}}
                                <a href="{{ $entry['url'] }}" data-nav-region-tab="{{ $entry['slug'] }}" data-selected="{{ $entry['slug'] === $currentSlug ? 'true' : 'false' }}"
                                    class="flex w-full items-center justify-between px-4 py-2 font-medium text-ink-2 hover:text-ink data-[selected=true]:bg-surface data-[selected=true]:text-ink">
                                    {{ $entry['name'] }}
                                    <span class="text-faint" aria-hidden="true">›</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="w-[34rem] max-w-[calc(100vw-15rem)] p-4">
                        @foreach ($entries as $entry)
                            <div data-nav-region-panel="{{ $entry['slug'] }}" @if ($entry['slug'] !== $currentSlug) data-nav-region-hidden @endif
                                 class="grid grid-flow-row-dense grid-cols-3 gap-x-6 gap-y-4 [&[data-nav-region-hidden]]:hidden">
                                <div class="col-span-full border-b border-line-2 pb-3">
                                    <a href="{{ $entry['url'] }}" class="font-semibold text-ink hover:underline">{{ $entry['name'] }} overview</a>
                                    <span class="text-xs text-muted"> {{ $entry['blurb'] }}</span>
                                </div>
                                <div class="contents" data-nav-groups="{{ $entry['slug'] }}" data-nav-layout="wide"></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </details>
@endif
