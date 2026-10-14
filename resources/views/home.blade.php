<x-layouts.app title="Upstream spills and river levels across Yorkshire"
               description="Live storm overflow activity and river levels for swimming, paddling and fishing spots on the Wharfe and Aire.">
    <div class="mb-8">
        <h1 class="text-3xl font-semibold tracking-tight">How's the water?</h1>
        <p class="mt-2 max-w-2xl text-stone-600">
            Upstream storm overflow spills and river levels for popular spots on Yorkshire's rivers, updated every
            15 minutes from Yorkshire Water and Environment Agency monitors.
        </p>
    </div>

    @foreach ($rivers as $overview)
        <section class="mb-10">
            <div class="mb-3 flex items-baseline justify-between">
                <h2 class="text-xl font-semibold">
                    <a href="{{ route('rivers.show', [$overview->river->region, $overview->river]) }}" class="hover:underline">{{ $overview->river->name }}</a>
                </h2>
                <p class="text-sm text-stone-500">
                    {{ $overview->monitorCount }} monitors,
                    {{ $overview->dischargingNowCount }} discharging now
                </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($overview->spots as $conditions)
                    <x-spot-card :conditions="$conditions" />
                @endforeach
            </div>
        </section>
    @endforeach
</x-layouts.app>
