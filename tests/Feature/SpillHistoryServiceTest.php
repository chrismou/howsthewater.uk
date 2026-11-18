<?php

use App\Data\UpstreamMonitorSummary;
use App\Enums\OverflowStatus;
use App\Models\OverflowMonitor;
use App\Models\OverflowSpillLog;
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

    // Days are UK days: 22:00 UTC is 23:00 British Summer Time.
    $overnight = SpillEvent::factory()->make([
        'overflow_monitor_id' => $monitor->id,
        'started_at' => Carbon::parse('2026-10-11 22:00:00'),
        'ended_at' => Carbon::parse('2026-10-12 01:00:00'),
    ]);
    $ongoing = SpillEvent::factory()->make([
        'overflow_monitor_id' => $monitor->id,
        'started_at' => Carbon::parse('2026-10-13 10:00:00'),
        'ended_at' => null,
    ]);
    // Running for a year, it only counts within the window.
    $longRunning = SpillEvent::factory()->make([
        'overflow_monitor_id' => $monitor->id,
        'started_at' => Carbon::parse('2025-10-01 00:00:00'),
        'ended_at' => Carbon::parse('2026-09-14 01:00:00'),
    ]);

    $days = app(SpillHistoryService::class)->dailyBreakdown(collect([$overnight, $ongoing, $longRunning]))->keyBy(fn ($day) => $day->date->toDateString());

    expect($days)->toHaveCount(30)
        ->and($days->keys()->first())->toBe('2026-09-14')
        ->and($days['2026-10-11']->spillMinutes)->toBe(60)
        ->and($days['2026-10-11']->spillCount)->toBe(1)
        ->and($days['2026-10-12']->spillMinutes)->toBe(120)
        ->and($days['2026-10-13']->spillMinutes)->toBe(120)
        ->and($days['2026-10-10']->spillCount)->toBe(0)
        ->and($days['2026-09-14']->spillMinutes)->toBe(120);

    Carbon::setTestNow();
});

it('counts the minutes when overflows spill at the same time once', function () {
    Carbon::setTestNow('2026-10-13 12:00:00');
    $first = SpillEvent::factory()->make(['started_at' => Carbon::parse('2026-10-12 08:00:00'), 'ended_at' => Carbon::parse('2026-10-12 10:00:00')]);
    $second = SpillEvent::factory()->make(['started_at' => Carbon::parse('2026-10-12 09:00:00'), 'ended_at' => Carbon::parse('2026-10-12 11:00:00')]);

    $day = app(SpillHistoryService::class)->dailyBreakdown(collect([$first, $second]))->first(fn ($day) => $day->date->toDateString() === '2026-10-12');

    expect($day->spillCount)->toBe(2)
        ->and($day->spillMinutes)->toBe(180);

    Carbon::setTestNow();
});

it('keeps every day of spills that started before the window, however many there are', function () {
    Carbon::setTestNow('2026-10-13 12:00:00');
    // Both started before the window; the second used to lose its first days to the first one's loop.
    $long = SpillEvent::factory()->make(['started_at' => Carbon::parse('2026-09-01 00:00:00'), 'ended_at' => Carbon::parse('2026-09-20 12:00:00')]);
    $inside = SpillEvent::factory()->make(['started_at' => Carbon::parse('2026-09-10 00:00:00'), 'ended_at' => Carbon::parse('2026-09-15 22:00:00')]);

    $days = app(SpillHistoryService::class)->dailyBreakdown(collect([$long, $inside]))->keyBy(fn ($day) => $day->date->toDateString());

    expect($days['2026-09-14']->spillCount)->toBe(2)
        ->and($days['2026-09-15']->spillCount)->toBe(2)
        ->and($days['2026-09-16']->spillCount)->toBe(1)
        ->and($days['2026-09-20']->spillMinutes)->toBe(13 * 60);

    Carbon::setTestNow();
});

it('counts a spill still open on a monitor that has stopped reporting only up to its last report', function () {
    Carbon::setTestNow('2026-10-13 12:00:00');
    $monitor = OverflowMonitor::factory()->create(['source_updated_at' => Carbon::parse('2026-10-12 12:00:00')]);
    $open = SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => Carbon::parse('2026-10-12 10:00:00'), 'ended_at' => null])->load('overflowMonitor');

    $days = app(SpillHistoryService::class)->dailyBreakdown(collect([$open]))->keyBy(fn ($day) => $day->date->toDateString());

    expect($days['2026-10-12']->spillMinutes)->toBe(120)
        ->and($days['2026-10-13']->spillCount)->toBe(0);

    Carbon::setTestNow();
});

/**
 * Short spills started 1 to $count hours ago, newest first, each lasting half an hour.
 *
 * @return Illuminate\Support\Collection<int, SpillEvent>
 */
function hourlySpills(OverflowMonitor $monitor, int $count): Illuminate\Support\Collection
{
    return collect(range(1, $count))->map(fn (int $hours) => SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subHours($hours), 'ended_at' => now()->subHours($hours)->addMinutes(30)]));
}

it('keeps every spill while there are no more than a feed lists', function () {
    $this->freezeTime();
    $monitor = OverflowMonitor::factory()->create();
    $spills = hourlySpills($monitor, 50);
    $service = app(SpillHistoryService::class);

    $kept = $service->kept($service->recentEvents(collect([summaryFor($monitor)])));

    expect($kept->pluck('id')->all())->toBe($spills->pluck('id')->all());
});

it('keeps the latest spills to start, and long spills the feed has just seen stop', function () {
    $this->freezeTime();
    $monitor = OverflowMonitor::factory()->create();
    $short = hourlySpills($monitor, 60);
    $long = SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subDays(20), 'ended_at' => now()->subMinutes(10)]);
    $longer = SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subDays(21), 'ended_at' => now()->subMinutes(20)]);
    $service = app(SpillHistoryService::class);

    $kept = $service->kept($service->recentEvents(collect([summaryFor($monitor)])));

    expect($kept->pluck('id')->all())->toBe([...$short->take(50)->pluck('id')->all(), $long->id, $longer->id]);
});

it('keeps the last spill to end from a published log without letting logged spills crowd out the feed\'s', function () {
    $this->freezeTime();
    $monitor = OverflowMonitor::factory()->create();
    hourlySpills($monitor, 60);
    $log = OverflowSpillLog::factory()->create();
    $logged = collect(range(1, 50))->map(fn (int $minutes) => SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'overflow_spill_log_id' => $log->id, 'started_at' => now()->subDays(25)->subMinutes($minutes), 'ended_at' => now()->subMinutes($minutes)]));
    $long = SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subDays(20), 'ended_at' => now()->subHours(2)]);
    $service = app(SpillHistoryService::class);

    $kept = $service->kept($service->recentEvents(collect([summaryFor($monitor)])));

    expect($kept->pluck('id'))->toContain($long->id, $logged->first()->id)
        ->not->toContain($logged->get(1)->id);
});
