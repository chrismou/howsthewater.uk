{{-- current: the region to open on; the page's region, or the first region added on pages without one. --}}
@props(['regions', 'current' => null])

@php
    // Built once per region, so the wide and narrow layouts share the same lists.
    $menuRegions = $regions->map(fn ($region) => [
        'region' => $region,
        'catchments' => $region->riversByCatchment(),
        'isCurrent' => $current !== null && $region->is($current),
    ]);
@endphp

{{--
    One menu for every river, whatever the number of regions. Wide screens get two panes: regions on the left (the
    current one first selected) and that region's rivers on the right, switched by nav.js. Narrow screens get a
    full-width accordion under the header. Sibling menus (beaches, lakes) can sit beside it with the same pattern.
--}}
<details class="sm:relative" data-nav-menu>
    <summary class="flex cursor-pointer list-none items-center gap-1 text-sm font-medium text-ink [&::-webkit-details-marker]:hidden">
        Rivers
        <svg class="h-4 w-4 text-faint" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
        </svg>
    </summary>

    {{-- The panel starts flush with the button and carries its own top padding, so moving the mouse into it never leaves the menu. --}}
    <div class="absolute inset-x-0 top-full z-[1100] px-4 pt-2 sm:inset-x-auto sm:left-0 sm:w-max sm:max-w-[calc(100vw-2rem)] sm:px-0">
        <div class="overflow-hidden rounded-lg border border-line bg-surface text-sm text-ink-2 shadow-lg">
            {{-- Narrow screens: one section per region, the current one open. --}}
            <div class="divide-y divide-line-2 sm:hidden">
                @foreach ($menuRegions as $entry)
                    <details class="group/region" @if ($entry['isCurrent']) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 font-semibold text-ink [&::-webkit-details-marker]:hidden">
                            {{ $entry['region']->name }}
                            <svg class="h-4 w-4 text-faint transition group-open/region:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
                            </svg>
                        </summary>
                        <div class="flex flex-col gap-3 px-4 pb-4">
                            <a href="{{ route('regions.show', $entry['region']) }}" class="font-medium text-accent hover:underline">{{ $entry['region']->name }} overview →</a>
                            @foreach ($entry['catchments'] as $catchment => $rivers)
                                <div>
                                    <p class="mb-1 text-xs font-medium uppercase tracking-wide text-faint">{{ $catchment }}</p>
                                    <ul class="grid grid-cols-2 gap-x-4 gap-y-1">
                                        @foreach ($rivers as $navRiver)
                                            <li><a href="{{ route('rivers.show', [$entry['region'], $navRiver]) }}" class="block py-0.5 hover:text-ink">{{ $navRiver->name }}</a></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>

            {{-- Wide screens: regions on the left, the selected region's rivers on the right. --}}
            <div class="hidden sm:flex">
                <ul class="w-48 shrink-0 border-r border-line-2 bg-surface-2 py-2" role="list">
                    @foreach ($menuRegions as $entry)
                        <li>
                            {{-- A link to the overview, so it still works without JavaScript; nav.js turns hover or a first tap into a preview. --}}
                            <a href="{{ route('regions.show', $entry['region']) }}" data-nav-region-tab="{{ $entry['region']->slug }}" data-selected="{{ $entry['isCurrent'] ? 'true' : 'false' }}"
                                class="flex w-full items-center justify-between px-4 py-2 font-medium text-ink-2 hover:text-ink data-[selected=true]:bg-surface data-[selected=true]:text-ink">
                                {{ $entry['region']->name }}
                                <span class="text-faint" aria-hidden="true">›</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="w-[34rem] max-w-[calc(100vw-15rem)] p-4">
                    @foreach ($menuRegions as $entry)
                        <div data-nav-region-panel="{{ $entry['region']->slug }}" @if (! $entry['isCurrent']) data-nav-region-hidden @endif class="flex flex-wrap gap-x-8 gap-y-4 [&[data-nav-region-hidden]]:hidden">
                            <div class="basis-full border-b border-line-2 pb-3">
                                <a href="{{ route('regions.show', $entry['region']) }}" class="font-semibold text-ink hover:underline">{{ $entry['region']->name }} overview</a>
                                <span class="text-xs text-muted"> map, river strips and every spot</span>
                            </div>
                            @foreach ($entry['catchments'] as $catchment => $rivers)
                                <div>
                                    <p class="mb-1.5 text-xs font-medium uppercase tracking-wide text-faint">{{ $catchment }}</p>
                                    <ul class="grid gap-x-6 gap-y-1 {{ $rivers->count() > 5 ? 'grid-cols-2' : '' }}">
                                        @foreach ($rivers as $navRiver)
                                            <li><a href="{{ route('rivers.show', [$entry['region'], $navRiver]) }}" class="block whitespace-nowrap py-0.5 hover:text-ink">{{ $navRiver->name }}</a></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</details>
