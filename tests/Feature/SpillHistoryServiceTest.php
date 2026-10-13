<?php

use App\Data\UpstreamMonitorSummary;
use App\Enums\OverflowStatus;
use App\Models\OverflowMonitor;
use App\Models\River;
use App\Models\SpillEvent;
use App\Services\Conditions\SpillHistoryService;
use Illuminate\Support\Carbon;

function summaryFor(OverflowMonitor $monitor): UpstreamMonitorSummary
{
    return new UpstreamMonitorSummary($monitor, $monitor->river ?? River::factory()->create(), 1000, OverflowStatus::NotDischarging, null);
}

it('lists recent events for the given monitors only, newest first', function () {
    $monitor = OverflowMonitor::factory()->create();
    $other = OverflowMonitor::factory()->create();

    $old = SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subDays(40), 'ended_at' => now()->subDays(40)->addHour()]);
    $recent = SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subDays(2), 'ended_at' => now()->subDays(2)->addHour()]);
    $newest = SpillEvent::factory()->ongoing()->create(['overflow_monitor_id' => $monitor->id]);
    SpillEvent::factory()->create(['overflow_monitor_id' => $other->id, 'started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addHour()]);

    $events = app(SpillHistoryService::class)->recentEvents(collect([summaryFor($monitor)]));

    expect($events->pluck('id')->all())->toBe([$newest->id, $recent->id])
        ->and($events->contains($old))->toBeFalse();
});

it('splits event minutes across the days they overlap', function () {
    Carbon::setTestNow('2026-10-13 12:00:00');

    $monitor = OverflowMonitor::factory()->create();

    $overnight = SpillEvent::factory()->make([
        'overflow_monitor_id' => $monitor->id,
        'started_at' => Carbon::parse('2026-10-11 23:00:00'),
        'ended_at' => Carbon::parse('2026-10-12 02:00:00'),
    ]);
    $ongoing = SpillEvent::factory()->make([
        'overflow_monitor_id' => $monitor->id,
        'started_at' => Carbon::parse('2026-10-13 10:00:00'),
        'ended_at' => null,
    ]);

    $days = app(SpillHistoryService::class)->dailyBreakdown(collect([$overnight, $ongoing]))->keyBy(fn ($day) => $day->date->toDateString());

    expect($days)->toHaveCount(30)
        ->and($days->keys()->first())->toBe('2026-09-14')
        ->and($days['2026-10-11']->spillMinutes)->toBe(59)
        ->and($days['2026-10-11']->spillCount)->toBe(1)
        ->and($days['2026-10-12']->spillMinutes)->toBe(120)
        ->and($days['2026-10-13']->spillMinutes)->toBe(120)
        ->and($days['2026-10-10']->spillCount)->toBe(0);

    Carbon::setTestNow();
});
