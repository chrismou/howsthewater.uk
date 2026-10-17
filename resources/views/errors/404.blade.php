<x-layouts.app title="Page not found" noindex>
    <div class="mx-auto max-w-xl py-10 text-center">
        <p class="font-mono text-sm text-muted">404</p>
        <h1 class="font-display mt-2 text-4xl font-semibold tracking-tight">Nothing here</h1>
        <p class="mt-3 text-ink-2">That page doesn't exist, or the spot has moved. Spot pages live under their region's rivers, like <span class="font-mono text-sm">/yorkshire/rivers/wharfe/ilkley</span>.</p>
        <form action="{{ route('search') }}" method="get" role="search" class="mx-auto mt-6 flex max-w-md gap-2">
            <label for="missing-search" class="sr-only">Find a spot or river</label>
            <input id="missing-search" type="search" name="q" placeholder="Find a spot or river"
                   class="min-h-11 flex-1 rounded-lg border border-line bg-surface px-4 text-ink placeholder:text-faint focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
            <button type="submit" class="min-h-11 rounded-lg bg-accent px-4 text-sm font-semibold text-surface hover:bg-accent-strong">Search</button>
        </form>
        <p class="mt-6 text-sm text-muted">
            <a href="{{ route('home') }}" class="font-medium text-accent hover:underline">Back to the start</a>
            @foreach (\App\Models\Region::query()->orderBy('name')->get() as $region)
                <span class="mx-2">·</span><a href="{{ route('regions.show', $region) }}" class="font-medium text-accent hover:underline">{{ $region->name }}</a>
            @endforeach
        </p>
    </div>
</x-layouts.app>
