{{-- Rows of overflows discharging now, with the places their discharge reaches: the table's first rows, and each
     batch its "show more" control loads. --}}
@php
    $timezone = config('app.display_timezone', 'Europe/London');
@endphp
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
