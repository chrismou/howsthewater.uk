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
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
            <a href="{{ route('home') }}" class="text-lg font-semibold tracking-tight">
                How's the Water<span class="text-sky-600">?</span>
            </a>
            <nav class="flex gap-5 text-sm text-stone-600">
                @foreach (\App\Models\River::query()->orderBy('name')->get() as $navRiver)
                    <a href="{{ route('rivers.show', $navRiver) }}" class="hover:text-stone-900">{{ $navRiver->name }}</a>
                @endforeach
            </nav>
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
