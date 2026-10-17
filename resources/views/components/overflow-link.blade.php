@props(['monitor'])

{{-- An overflow's name linking to its page, with its id beside it; an overflow without a name goes by its id. --}}
<a href="{{ route('overflows.show', $monitor) }}" {{ $attributes->merge(['class' => 'font-medium hover:underline']) }}>{{ $monitor->displayName() ?? $monitor->external_id }}</a>
@if ($monitor->displayName() !== null)
    <span class="whitespace-nowrap font-mono text-xs text-muted">{{ $monitor->external_id }}</span>
@endif
