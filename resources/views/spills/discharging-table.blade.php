{{-- Overflows discharging now, longest running first, with the places their discharge reaches. --}}
@php
    $timezone = config('app.display_timezone', 'Europe/London');
@endphp
<div class="overflow-x-auto rounded-lg border border-line bg-surface">
    <table class="w-full text-sm">
        <caption class="sr-only">{{ $label }}, longest running first</caption>
        <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
            <tr>
                <th scope="col" class="px-4 py-2.5 font-medium">Overflow</th>
                <th scope="col" class="hidden px-4 py-2.5 font-medium sm:table-cell">Into</th>
                <th scope="col" class="px-4 py-2.5 font-medium">Since</th>
                <th scope="col" class="hidden px-4 py-2.5 font-medium md:table-cell">{{ $placesHeading }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line-2">
            @foreach ($listings as $listing)
                @php
                    $monitor = $listing->monitor;
                    $since = $monitor->status_started_at?->copy()->timezone($timezone);
                @endphp
                <tr>
                    <td class="px-4 py-2.5">
                        <x-overflow-link :monitor="$monitor" />
                        @if ($monitor->isStale())
                            <span class="block text-xs"><x-overflow-status :monitor="$monitor" /></span>
                        @endif
                        <span class="block text-xs text-muted sm:hidden">{{ $monitor->intoName() }}</span>
                        <span class="block text-xs text-ink-2 md:hidden"><x-linked-places :places="$listing->places" :limit="2" /></span>
                    </td>
                    <td class="hidden px-4 py-2.5 text-ink-2 sm:table-cell">{{ $monitor->intoName() ?? '-' }}</td>
                    <td class="whitespace-nowrap px-4 py-2.5">
                        @if ($since)
                            <span class="font-mono text-xs tabular-nums">{{ $since->format($since->isSameDay(now($timezone)) ? 'H:i' : 'D j M, H:i') }}</span>
                            <span class="block text-xs text-muted">{{ $since->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td class="hidden px-4 py-2.5 text-ink-2 md:table-cell"><x-linked-places :places="$listing->places" /></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@if ($total > $listings->count())
    <p class="mt-2 text-sm text-muted">The {{ $listings->count() }} longest running of {{ $total }} shown. Each spot's page lists the overflows upstream of it.</p>
@endif
