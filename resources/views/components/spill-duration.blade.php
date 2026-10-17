@props(['spill', 'monitor'])

{{-- How long a spill lasted, or that it is still running. A spill still open on a monitor that has stopped reporting
     is only known to have been running at its last report, so it never shows as live. --}}
@if (! $spill->isOngoing())
    {{ $spill->durationLabel() }}
@elseif ($monitor->isStale())
    <span class="font-sans text-muted">open at last report</span>
@else
    <span class="font-sans font-medium text-alert">ongoing</span>
@endif
