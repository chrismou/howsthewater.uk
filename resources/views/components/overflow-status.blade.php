@props(['monitor', 'spilledRecently' => false])

{{-- One monitor's status in an overflows table. A status the feed has not confirmed lately says when it last did,
     so an old "not discharging" never reads as current. --}}
@if ($monitor->status === \App\Enums\OverflowStatus::Offline)
    <span class="text-muted">Monitor offline</span>
@elseif ($monitor->isStale())
    <span class="text-muted" title="Monitor data is out of date">
        {{ $monitor->isDischarging() ? 'Discharging at last report' : 'No recent report' }}@if ($monitor->source_updated_at), {{ $monitor->source_updated_at->timezone(config('app.display_timezone', 'Europe/London'))->format($monitor->source_updated_at->isSameYear(now()) ? 'j M' : 'j M Y') }}@endif
    </span>
@elseif ($monitor->isDischarging())
    <span class="font-medium text-alert">Discharging now</span>
@elseif ($spilledRecently)
    <span class="font-medium text-pill-amber-text">Spilled in the last 48h</span>
@else
    <span class="text-ink-2">Not discharging</span>
@endif
