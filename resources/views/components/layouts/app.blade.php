@props(['title' => null, 'description' => null, 'region' => null, 'liveSummary' => null, 'feed' => null, 'hero' => null, 'after' => null])

@php
    // The live bar shows the summary a page passes (the home page passes every region combined), else the page's
    // region, or the first region added on pages that have none.
    $allRegions = \App\Models\Region::query()->orderBy('name')->get();
    $liveRegion = $region ?? $allRegions->sortBy('id')->first();
    $liveSummary ??= $liveRegion ? app(\App\Services\Conditions\RegionSummaries::class)->for($liveRegion) : null;
    $menus = app(\App\Services\Conditions\NavigationMenus::class)->all();
@endphp
    <!DOCTYPE html>
<html lang="en-GB" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $pageTitle = ($title ? $title.' - ' : '').config('app.name');
        $pageDescription = $description ?? 'Storm overflow spills upstream, river levels and rainfall for swimming, paddling and fishing spots on rivers in '.$allRegions->pluck('name')->join(', ', ' and ').', read from the public monitors every 15 minutes.';
    @endphp
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @if ($feed)
        <link rel="alternate" type="application/rss+xml" title="{{ $pageTitle }}" href="{{ $feed }}">
    @endif
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="en_GB">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ asset('og-image.png') }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bricolage-grotesque:500,600,700|ibm-plex-mono:500|instrument-sans:400,500,600,700" rel="stylesheet">
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
<header class="border-b border-line bg-surface">
    {{-- Phones: logo, menu and theme toggle on one row, search below. Wider: one row with search and toggle on the right. --}}
    <div class="relative mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-4">
        <a href="{{ route('home') }}" class="order-1 font-display text-lg font-semibold tracking-tight">
            How's the Water<span class="text-accent">?</span>
        </a>

        {{-- Phones: the menus get their own row under the logo; wider screens keep them beside it. --}}
        <nav class="order-4 flex w-full items-center gap-x-5 sm:order-2 sm:w-auto" aria-label="Main">
            <x-nav-menu label="Rivers" :entries="$menus['rivers']" :current="$liveRegion?->slug" />
            <x-nav-menu label="Beaches" :entries="$menus['beaches']" :current="$liveRegion?->slug" />
            <x-nav-menu label="Lakes" :entries="$menus['lakes']" :current="$liveRegion?->slug" />
        </nav>

        <form action="{{ route('search') }}" method="get" role="search" class="order-5 w-full sm:order-3 sm:ml-auto sm:w-auto">
                <label for="site-search" class="sr-only">Find a spot or river</label>
                <input id="site-search" type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}"
                    placeholder="Find a spot or river"
                    class="w-full rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink placeholder:text-faint focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent sm:w-56">
        </form>
            <button type="button" data-theme-toggle
                class="order-3 ml-auto inline-flex h-9 w-9 shrink-0 sm:order-4 sm:-ml-4 items-center justify-center rounded-md border border-line bg-surface text-ink hover:border-muted focus:outline-none focus:ring-1 focus:ring-accent"
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

<main class="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
    {{ $slot }}
</main>

{{ $after }}

<footer class="border-t border-line bg-surface">
    <div class="mx-auto max-w-5xl px-4 py-6 text-xs leading-relaxed text-muted">
        <p>
            Near real-time, unvalidated data. This uses Environment Agency flood and river level data from the real-time data API (Beta)
            and Environment Agency bathing water data, Open Government Licence v3.0.
            {{ $allRegions->flatMap->waterCompanies()->unique()->join(', ', ' and ') }} storm overflow data via Stream, CC BY 4.0.
            River centrelines contain OS data © Crown copyright and database right. Map tiles © OpenStreetMap contributors.
            <a href="{{ route('home') }}#limits" class="underline hover:text-ink">What this site does not do</a>.
            Seen a mistake? <a href="https://github.com/chrismou/howsthewater.uk/issues" rel="noopener" class="underline hover:text-ink">Let us know on GitHub</a>.
        </p>
        {{-- Phones: credit and legal links stacked and centred. Wider: one row, legal links on the right. --}}
        <div class="mt-4 flex flex-col items-center gap-2 border-t border-line-2 pt-4 text-center sm:flex-row sm:items-baseline sm:justify-between sm:text-left">
            <p>Lovingly crafted in Leeds <svg class="inline h-3.5 w-3.5 -translate-y-px align-middle text-alert" viewBox="0 0 24 24" fill="currentColor" role="img" aria-label="love"><path d="M12 21s-7.5-4.6-9.6-9.2C.9 8.4 2.9 4.5 6.6 4.5c2.1 0 3.6 1.2 4.4 2.5h2c.8-1.3 2.3-2.5 4.4-2.5 3.7 0 5.7 3.9 4.2 7.3C19.5 16.4 12 21 12 21z"/></svg> by <a href="https://mou.me" rel="noopener" class="underline hover:text-ink">Chris Chrisostomou</a> <span class="whitespace-nowrap">© {{ now()->year }}</span></p>
            <p class="flex gap-4 whitespace-nowrap">
                <a href="{{ route('terms') }}" class="underline hover:text-ink">Terms of use</a>
                <a href="{{ route('privacy') }}" class="underline hover:text-ink">Privacy</a>
            </p>
        </div>
    </div>
</footer>
</body>
</html>
