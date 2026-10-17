{{--
    One plain sentence naming the place, the time of the monitor data and its source, e.g. "At 08:58 BST on 16 October,
    1 of 14 storm overflow monitors upstream of Ilkley on the River Wharfe was discharging, with 3 spills in the
    previous 48 hours, according to Yorkshire Water." It reports what the monitors say, never that the water is safe.

    monitors: upstream or nearby monitor summaries (each with ->monitor and ->status). where: the place as it reads
    after "monitors", such as "upstream of Ilkley on the River Wharfe" or "within 3 km of Millport Bay". stale: the
    data is older than the stale threshold, so the sentence says it is the latest the monitors sent. level: the
    place's LevelSummary, adding what its gauge last read, which also gives a place without overflow data a dated
    fact. Every sentence ends by linking to what the data cannot tell you.
--}}
@props(['monitors', 'where', 'dischargingNow', 'spillsLast48Hours', 'updatedAt' => null, 'covered' => true, 'stale' => false, 'level' => null])

@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $count = $monitors->count();
    $offline = $monitors->filter(fn ($summary) => $summary->status === \App\Enums\OverflowStatus::Offline)->count();
    $companies = $monitors->map(fn ($summary) => preg_replace('/\s+(Ltd|Limited|plc)\.?$/i', '', $summary->monitor->company))->unique()->sort()->values();
    $source = 'according to '.e($companies->join(', ', ' and '));
    $where = e($where);
    $when = $updatedAt
        ? 'At <time datetime="'.$updatedAt->toIso8601String().'">'.e($updatedAt->timezone($timezone)->format('H:i T \o\n j F Y')).'</time>'
        : 'In the latest data';
    $spills = $spillsLast48Hours.' '.\Illuminate\Support\Str::plural('spill', $spillsLast48Hours).' in the previous 48 hours';
    $wasOrWere = fn (int $number) => $number === 1 ? 'was' : 'were';

    // Offline monitors are said to be offline, never counted as "not discharging", so missing data never reads as no spills.
    $sentence = match (true) {
        ! $covered => "We do not read the water company's overflow data here yet, so storm overflows {$where} are not shown.",
        $count === 0 => "No storm overflow monitors {$where} appear in the water companies' data.",
        $offline === $count => $count === 1
            ? "{$when}, the one storm overflow monitor {$where} was offline, so there is no current spill data, {$source}."
            : "{$when}, all {$count} storm overflow monitors {$where} were offline, so there is no current spill data, {$source}.",
        $count === 1 => "{$when}, the one storm overflow monitor {$where} was ".($dischargingNow > 0 ? 'discharging' : 'not discharging').", with {$spills}, {$source}.",
        default => "{$when}, {$dischargingNow} of {$count} storm overflow monitors {$where} {$wasOrWere($dischargingNow)} discharging"
            .($offline > 0 ? " and {$offline} {$wasOrWere($offline)} offline" : '').", with {$spills}, {$source}.",
    };

    if ($stale && $covered && $count > 0) {
        $sentence .= ' That is the most recent data the monitors have sent.';
    }

    if ($level?->latestValue !== null && $level->latestAt !== null) {
        $readAt = $level->latestAt->timezone($timezone);
        $sentence .= sprintf(
            ' The %s gauge read %s %s at <time datetime="%s">%s</time>%s.',
            e($level->station->label),
            number_format($level->latestValue, 2),
            e($level->station->unitLabel()),
            $level->latestAt->copy()->utc()->toIso8601String(),
            e($readAt->format($readAt->isSameDay(now($timezone)) ? 'H:i T' : 'H:i T \o\n j F')),
            $level->relativeToTypical !== null ? ', '.e(\Illuminate\Support\Str::replace('typical range', 'its typical range', $level->relativeToTypical)) : '',
        );
    }

    $sentence .= ' <a href="'.route('about').'#limits" class="underline decoration-line underline-offset-2 hover:decoration-ink">Monitor data is not a safety check.</a>';
@endphp

{{-- Every part of the sentence is escaped above; the markup is only the <time> element. --}}
<p {{ $attributes }}>{!! $sentence !!}</p>
