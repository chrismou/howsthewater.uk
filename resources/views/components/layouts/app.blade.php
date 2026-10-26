@props(['title' => null, 'description' => null, 'noindex' => false, 'pageType' => 'WebPage', 'dateModified' => null, 'region' => null, 'liveSummary' => null, 'feed' => null, 'hero' => null, 'after' => null, 'breadcrumbs' => [], 'structuredData' => [], 'shareImage' => null])

@php
    // The live bar shows the summary a page passes (the home page passes every region combined), else the page's
    // region, or the first region added on pages that have none.
    $allRegions = \App\Models\Region::query()->orderBy('name')->get();
    $liveRegion = $region ?? $allRegions->sortBy('id')->first();
    $liveSummary ??= $liveRegion ? app(\App\Services\Conditions\RegionSummaries::class)->for($liveRegion) : null;
    $menus = app(\App\Services\Conditions\NavigationMenus::class)->all();
    // Spot, river, beach and lake pages pass their own card (GenerateShareImages); every other page, or one whose
    // card is not drawn yet, shares the site's.
    $ogImage = $shareImage?->url() ?? asset('og-image.png');
    $ogImageAlt = $shareImage?->alt ?? "How's the Water? card asking: what's the river doing right now?";
    // Built from the route and its bound models, so /Yorkshire (which the case-insensitive database still finds)
    // names /yorkshire as canonical. Only the URL's own parameters go in: Route::view() keeps its view and status as
    // defaults, which route() would otherwise append as a query string.
    $currentRoute = request()->route();
    $canonical = $currentRoute?->getName() !== null ? route($currentRoute->getName(), \Illuminate\Support\Arr::only($currentRoute->parameters(), $currentRoute->parameterNames())) : url()->current();
    // Overflow data is credited per source: the English companies via Stream, Scottish Water from its own API. Every
    // polled feed is credited, as monitors near a region's rivers, beaches and lakes can come from any of them.
    $overflowFeeds = collect(\App\Models\Region::overflowFeeds());
    $streamCompanies = $overflowFeeds->where('driver', 'stream')->pluck('name')->unique()->join(', ', ' and ');
    $analyticsId = app()->isProduction() ? config('services.google_analytics.measurement_id') : null;
@endphp
    <!DOCTYPE html>
<html lang="en-GB" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        // Every title ends with the site name. The page's own part comes first and stays within 60 characters (see the
        // SEO section of CLAUDE.md), so if search results cut the title short they only lose the site name.
        $pageTitle = ($title ? $title.' - ' : '').config('app.name');
        // Share previews show og:site_name (or the domain) beside the title, so the shared title leaves the name off.
        $shareTitle = $title ?? config('app.name');
        $pageDescription = $description ?? 'Storm overflow spills, river levels and bathing water results for swimming, paddling and fishing on rivers, beaches and lakes, updated every 15 minutes.';
        // Titles and descriptions keep their apostrophes as typed: they need no escaping in text or double-quoted
        // attributes, and some readers (Google's AI Overview source cards) show an escaped one as "&#039;".
        $meta = fn (?string $text) => htmlspecialchars((string) $text, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    @endphp
    <title>{!! $meta($pageTitle) !!}</title>
    <meta name="description" content="{!! $meta($pageDescription) !!}">
    @if ($noindex)
        <meta name="robots" content="noindex">
    @else
        <link rel="canonical" href="{{ $canonical }}">
    @endif
    @if ($feed)
        <link rel="alternate" type="application/rss+xml" title="{!! $meta($pageTitle) !!}" href="{{ $feed }}">
    @endif
    <meta property="og:site_name" content="{!! $meta(config('app.name')) !!}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{!! $meta($shareTitle) !!}">
    <meta property="og:description" content="{!! $meta($pageDescription) !!}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{!! $meta($ogImageAlt) !!}">
    <meta property="og:locale" content="en_GB">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{!! $meta($shareTitle) !!}">
    <meta name="twitter:description" content="{!! $meta($pageDescription) !!}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <meta name="twitter:image:alt" content="{!! $meta($ogImageAlt) !!}">
    {{--
        The page as one schema.org graph (see App\Services\StructuredData): a WebPage (or AboutPage) node for this URL,
        its trail as a BreadcrumbList, and what the page describes, each with an @id the others refer to. Facts the
        page states only, never a rating of the water. Pages kept out of the index carry none.
    --}}
    @unless ($noindex)
        @php
            $siteId = rtrim(route('home'), '/').'/#website';
            // The home page's ids take the same trailing slash as the site's, so every id on it shares one base.
            $idBase = $canonical === rtrim(route('home'), '/') ? $canonical.'/' : $canonical;
            $nodes = collect($structuredData)
                ->flatMap(fn (array $item) => $item['@graph'] ?? [$item])
                ->map(fn (array $node) => ['@id' => $idBase.'#'.lcfirst((string) $node['@type']), ...$node])
                ->values();
            $mainEntity = $nodes->first(fn (array $node) => ! in_array($node['@type'], ['WebSite', 'Organization'], true));

            if (! $nodes->contains('@id', $siteId)) {
                $nodes->push(['@type' => 'WebSite', '@id' => $siteId, 'url' => rtrim(route('home'), '/').'/', 'name' => config('app.name'), 'publisher' => ['@id' => rtrim(route('home'), '/').'/#organization']]);
            }

            // The home page's address is written one way everywhere in the graph, with the site's trailing slash.
            $graphUrl = fn (string $url) => $url === rtrim(route('home'), '/') ? $url.'/' : $url;

            if ($breadcrumbs !== []) {
                $nodes->push([
                    '@type' => 'BreadcrumbList',
                    '@id' => $idBase.'#breadcrumb',
                    'itemListElement' => collect($breadcrumbs)
                        ->map(fn (array $crumb, int $index) => ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $crumb[0], 'item' => $graphUrl($crumb[1])])
                        ->all(),
                ]);
            }

            $nodes->prepend(array_filter([
                '@type' => $pageType,
                '@id' => $idBase.'#webpage',
                'url' => $graphUrl($canonical),
                'name' => $pageTitle,
                'description' => $pageDescription,
                'inLanguage' => 'en-GB',
                'isPartOf' => ['@id' => $siteId],
                'dateModified' => $dateModified?->copy()->utc()->toIso8601String(),
                'breadcrumb' => $breadcrumbs !== [] ? ['@id' => $idBase.'#breadcrumb'] : null,
                'mainEntity' => $mainEntity !== null ? ['@id' => $mainEntity['@id']] : null,
            ]));

            // Built here rather than in an echo, where Blade would read the "@context" key as a directive.
            $graphJson = json_encode(['@context' => 'https://schema.org', '@graph' => $nodes->all()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        @endphp
        <script type="application/ld+json">{!! $graphJson !!}</script>
    @endunless
    {{-- Most pages open with a map, whose tiles come from OpenStreetMap. --}}
    <link rel="preconnect" href="https://tile.openstreetmap.org">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    {{-- Self-hosted fonts (see vite.config.js): preloads for the faces on screen first, then font-display: swap faces. --}}
    @fonts
    {{-- Apply the remembered theme before first paint so dark mode never flashes light. Light is the default. --}}
    <script>try {
            if (localStorage.getItem('theme') === 'dark') {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {
        }</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex flex-col bg-ground text-ink antialiased">
<header class="border-b border-line bg-surface" data-nav-current="{{ $liveRegion?->slug }}">
    {{-- Phones: logo, search button and theme toggle on one row, the menus below and the search box under them once the
         button opens it. Wider: one row with search and toggle on the right. --}}
    <div class="relative mx-auto flex max-w-site flex-wrap items-center gap-x-1 px-4 py-1.5 sm:gap-x-6 sm:gap-y-3 sm:py-4">
        <a href="{{ route('home') }}" class="order-1 font-display text-lg font-semibold tracking-tight">
            How's the Water<span class="text-accent">?</span>
        </a>

        {{-- Phones: the menus get their own row under the logo; wider screens keep them beside it. --}}
        <nav class="order-4 flex w-full items-center gap-x-5 sm:order-2 sm:w-auto" aria-label="Main">
            <x-nav-menu label="Rivers" menu="rivers" :url="route('hubs.rivers')" />
            @if ($menus['beaches'] !== [])
                <x-nav-menu label="Beaches" menu="beaches" :url="route('hubs.beaches')" />
            @endif
            @if ($menus['lakes'] !== [])
                <x-nav-menu label="Lakes" menu="lakes" :url="route('hubs.lakes')" />
            @endif
        </nav>

        {{-- Without JavaScript it goes to the search page, which has its own search box. --}}
        <a href="{{ route('search') }}" data-search-toggle aria-controls="site-search-form" aria-expanded="false" aria-label="Search"
           class="order-2 ml-auto inline-flex h-11 w-11 items-center justify-center rounded-md text-ink hover:text-accent focus:outline-none focus:ring-1 focus:ring-accent sm:hidden">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>
        </a>

        <form id="site-search-form" data-search-form action="{{ route('search') }}" method="get" role="search" class="order-5 hidden w-full pb-2 sm:order-3 sm:ml-auto sm:block sm:w-auto sm:pb-0">
                <label for="site-search" class="sr-only">Find a spot or river</label>
                <input id="site-search" type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}"
                    placeholder="Find a spot or river"
                    class="h-11 w-full rounded-md border border-line bg-surface px-3 text-base text-ink placeholder:text-faint focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent sm:h-9 sm:w-56 sm:text-sm">
        </form>
            <button type="button" data-theme-toggle
                class="order-3 inline-flex h-11 w-11 shrink-0 sm:order-4 sm:-ml-4 sm:h-9 sm:w-9 items-center justify-center rounded-md border border-line bg-surface text-ink hover:border-muted focus:outline-none focus:ring-1 focus:ring-accent"
                aria-label="Switch between light and dark mode">
                <svg class="h-5 w-5 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
                </svg>
                <svg class="hidden h-5 w-5 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
                </svg>
            </button>
    </div>
    @if ($liveSummary)
        <x-live-bar :summary="$liveSummary"/>
    @endif
</header>

{{-- Full-bleed sections a page wants above or below its content, such as the home page hero. --}}
{{ $hero }}

<main class="mx-auto w-full max-w-site flex-1 px-4 py-8">
    {{ $slot }}
</main>

{{ $after }}

{{-- The same dark band as the live bar. --}}
<footer class="border-t border-ground/15 bg-ink text-ground dark:border-line dark:bg-surface-2 dark:text-ink">
    <div class="mx-auto max-w-site px-4 py-8">
        {{-- The pages the header menus do not reach: what is happening now and the place hubs, each region's spills and
             levels, and the site's own pages. Phones: the first and last columns side by side, the regions below. --}}
        <nav aria-label="Footer" class="grid grid-cols-2 gap-x-6 gap-y-8 text-sm lg:grid-cols-4">
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-ground/70 dark:text-muted">Right now</h2>
                <ul class="mt-2">
                    <li><a href="{{ route('spills.index') }}" class="inline-block py-2 hover:underline sm:py-1">Spills now</a></li>
                    <li><a href="{{ route('levels.index') }}" class="inline-block py-2 hover:underline sm:py-1">River levels now</a></li>
                    <li><a href="{{ route('swimming') }}" class="inline-block py-2 hover:underline sm:py-1">Swimming spots near you</a></li>
                </ul>
                <h2 class="mt-6 text-xs font-semibold uppercase tracking-[0.08em] text-ground/70 dark:text-muted">Places</h2>
                <ul class="mt-2">
                    <li><a href="{{ route('hubs.rivers') }}" class="inline-block py-2 hover:underline sm:py-1">All rivers</a></li>
                    @if ($menus['beaches'] !== [])
                        <li><a href="{{ route('hubs.beaches') }}" class="inline-block py-2 hover:underline sm:py-1">All beaches</a></li>
                    @endif
                    @if ($menus['lakes'] !== [])
                        <li><a href="{{ route('hubs.lakes') }}" class="inline-block py-2 hover:underline sm:py-1">All lakes</a></li>
                    @endif
                </ul>
            </div>
            {{-- Each region's links carry its name for screen readers and search engines, which read them out of the row. --}}
            <div class="order-last col-span-2 lg:order-none">
                <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-ground/70 dark:text-muted">By region</h2>
                <ul class="mt-2 grid gap-x-6 sm:grid-cols-2">
                    @foreach ($allRegions as $footerRegion)
                        <li class="flex flex-wrap items-baseline gap-x-2">
                            <a href="{{ route('regions.show', $footerRegion) }}" class="inline-block py-2 font-medium hover:underline sm:py-1">{{ $footerRegion->name }}</a>
                            <span class="text-ground/70 dark:text-muted">
                                @if ($footerRegion->hasOverflowData())
                                    <a href="{{ route('spills.region', $footerRegion) }}" class="inline-block py-2 hover:text-ground hover:underline dark:hover:text-ink sm:py-1">Spills<span class="sr-only"> in {{ $footerRegion->nameInSentence() }}</span></a> &amp;
                                @endif
                                <a href="{{ route('levels.region', $footerRegion) }}" class="inline-block py-2 hover:text-ground hover:underline dark:hover:text-ink sm:py-1">Levels<span class="sr-only"> in {{ $footerRegion->nameInSentence() }}</span></a>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-ground/70 dark:text-muted">About</h2>
                <ul class="mt-2">
                    <li><a href="{{ route('about') }}" class="inline-block py-2 hover:underline sm:py-1">About the data</a></li>
                    <li><a href="{{ route('terms') }}" class="inline-block py-2 hover:underline sm:py-1">Terms of use</a></li>
                    <li><a href="{{ route('privacy') }}" class="inline-block py-2 hover:underline sm:py-1">Privacy</a></li>
                    @if ($analyticsId)
                        <li><a href="#" data-cookie-settings class="inline-block py-2 hover:underline sm:py-1">Cookie settings</a></li>
                    @endif
                    <li><a href="https://github.com/chrismou/howsthewater.uk/issues" rel="noopener" class="inline-block py-2 hover:underline sm:py-1">Report a mistake</a></li>
                    {{-- No email address on the pages: Cloudflare adds its email obfuscation script to any page that shows one. --}}
                    <li><a href="https://mou.me/contact" rel="noopener" class="inline-block py-2 hover:underline sm:py-1">Get in touch</a></li>
                </ul>
            </div>
        </nav>

        <div class="mt-8 border-t border-ground/15 dark:border-line-2 pt-6 text-xs leading-relaxed text-ground/70 dark:text-muted">
            <p>
                Near real-time, unvalidated data. This uses Environment Agency flood and river level data from the real-time data API (Beta)
                and Environment Agency bathing water data, Open Government Licence v3.0. Welsh river level, rainfall, flood warning and bathing water data from Natural Resources Wales, Open Government Licence v3.0. Scottish river level, rainfall and bathing water data from SEPA, Open Government Licence v3.0. Scottish flood alerts and warnings from SEPA's Floodline, which states no licence.
                @if ($streamCompanies !== '')
                    {{ $streamCompanies }} storm overflow data via Stream, CC BY 4.0.
                @endif
                @if ($overflowFeeds->contains('driver', 'scottish_water'))
                    Overflow data from Scottish Water.
                @endif
                @if ($overflowFeeds->contains('driver', 'welsh_water'))
                    Overflow data from Dŵr Cymru Welsh Water.
                @endif
                Storm overflow annual returns produced by The Rivers Trust from Environment Agency and Natural Resources Wales data, Open Government Licence v3.0. Past storm overflow spills from the water companies' records via the Environment Agency, Open Government Licence v3.0.
                River centrelines contain OS data © Crown copyright and database right. Map tiles © OpenStreetMap contributors.
                Weather forecasts from <a href="https://open-meteo.com/" rel="noopener" class="underline hover:text-ground dark:hover:text-ink">Open-Meteo</a>, CC BY 4.0.
            </p>
            <p class="mt-4 text-center sm:text-left">Lovingly crafted in Leeds <svg class="inline h-3.5 w-3.5 -translate-y-px align-middle text-alert" viewBox="0 0 24 24" fill="currentColor" role="img" aria-label="love"><path d="M12 21s-7.5-4.6-9.6-9.2C.9 8.4 2.9 4.5 6.6 4.5c2.1 0 3.6 1.2 4.4 2.5h2c.8-1.3 2.3-2.5 4.4-2.5 3.7 0 5.7 3.9 4.2 7.3C19.5 16.4 12 21 12 21z"/></svg> by <a href="https://mou.me" rel="noopener" class="underline hover:text-ground dark:hover:text-ink">Chris Chrisostomou</a> <span class="whitespace-nowrap">© {{ now()->year }}</span></p>
        </div>
    </div>
</footer>
@if ($analyticsId)
    <x-cookie-banner :measurement-id="$analyticsId"/>
@endif
{{-- The regions and places in the Rivers, Beaches and Lakes menus, which nav.js builds when a menu first opens. --}}
<script type="application/json" id="nav-menus">{!! app(\App\Services\Conditions\NavigationMenus::class)->menusJson() !!}</script>
{{-- Explanations for the page's help buttons (each button's aria-describedby), shown in the panel below by tooltip.js. --}}
<div hidden data-nosnippet>@stack('help-glossary')</div>
<div data-help-panel role="tooltip" aria-hidden="true" hidden
     class="pointer-events-none fixed left-0 top-0 z-[1100] w-72 max-w-[calc(100vw-2rem)] rounded-md bg-ink px-3.5 py-2.5 text-left text-[13px] leading-snug text-ground shadow-lg"></div>
</body>
</html>
