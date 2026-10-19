{{-- Rows of spills running or ended in the last 24 hours, most recent first: the table's first rows, and each batch
     its "show more" control loads. --}}
@php
    $timezone = config('app.display_timezone', 'Europe/London');
@endphp
@foreach ($spills as $spill)
    @php
        $monitor = $spill->overflowMonitor;
        $started = $spill->started_at->copy()->timezone($timezone);
    @endphp
    <tr>
        <td class="px-4 py-2.5">
            <x-overflow-link :monitor="$monitor" />
            <span class="block text-xs text-muted sm:hidden">{{ $monitor->intoName() }} · <x-spill-duration :spill="$spill" :monitor="$monitor" /></span>
        </td>
        <td class="hidden px-4 py-2.5 text-ink-2 sm:table-cell">{{ $monitor->intoName() ?? '-' }}</td>
        <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs tabular-nums">{{ $started->format($started->isSameDay(now($timezone)) ? 'H:i' : 'D H:i') }}</td>
        <td class="hidden whitespace-nowrap px-4 py-2.5 font-mono tabular-nums sm:table-cell"><x-spill-duration :spill="$spill" :monitor="$monitor" /></td>
    </tr>
@endforeach
