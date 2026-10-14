<!DOCTYPE html>
<html lang="en-GB" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' - ' : '' }}{{ config('app.name') }}</title>
    @isset($description)
        <meta name="description" content="{{ $description }}">
    @endisset
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex flex-col bg-stone-50 text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-4">
            <a href="{{ route('home') }}" class="text-lg font-semibold tracking-tight">
                How's the Water<span class="text-sky-600">?</span>
            </a>

            <nav class="flex items-center gap-x-4 text-sm text-stone-600" aria-label="Regions">
                @foreach (\App\Models\Region::query()->orderBy('name')->get() as $navRegion)
                    <details class="relative" data-nav-menu>
                        <summary class="flex cursor-pointer list-none items-center gap-1 font-medium text-stone-700 hover:text-stone-900 [&::-webkit-details-marker]:hidden">
                            {{ $navRegion->name }}
                            <svg class="h-4 w-4 text-stone-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </summary>
                        {{-- The panel starts flush with the button and carries its own top padding, so moving the mouse into it never leaves the menu. --}}
                        <div class="absolute left-0 top-full z-20 w-max max-w-[calc(100vw-2rem)] pt-2">
                            <div class="flex flex-wrap gap-x-8 gap-y-4 rounded-lg border border-stone-200 bg-white p-4 shadow-lg">
                                @foreach ($navRegion->riversByCatchment() as $catchment => $rivers)
                                    <div>
                                        <p class="mb-1.5 text-xs font-medium uppercase tracking-wide text-stone-400">{{ $catchment }}</p>
                                        <ul class="grid gap-x-6 gap-y-1 {{ $rivers->count() > 5 ? 'grid-cols-2' : '' }}">
                                            @foreach ($rivers as $navRiver)
                                                <li><a href="{{ route('rivers.show', [$navRegion, $navRiver]) }}" class="block whitespace-nowrap py-0.5 hover:text-stone-900">{{ $navRiver->name }}</a></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endforeach
            </nav>

            <form action="{{ route('search') }}" method="get" role="search" class="ml-auto w-full sm:w-auto">
                <label for="site-search" class="sr-only">Find a spot or river</label>
                <input id="site-search" type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}"
                       placeholder="Find a spot or river"
                       class="w-full rounded-md border border-stone-300 px-3 py-1.5 text-sm placeholder:text-stone-400 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 sm:w-56">
            </form>
        </div>
    </header>

    <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
        {{ $slot }}
    </main>

    <footer class="border-t border-stone-200 bg-white">
        <div class="mx-auto max-w-5xl px-4 py-6 text-xs leading-relaxed text-stone-500">
            <p>
                Shows unvalidated near real-time data from Yorkshire Water storm overflow monitors (via Stream) and
                Environment Agency river level gauges. It describes what the monitors report. It does not say whether
                water is safe to enter.
            </p>
            <p class="mt-2">
                Contains Environment Agency data from the real-time data API (Beta) and Yorkshire Water storm overflow
                data, Open Government Licence v3.0. River centrelines from Ordnance Survey Open Rivers, OGL v3.0.
            </p>
        </div>
    </footer>
</body>
</html>
