<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

it('logs an error when any scheduled command exits with a failure', function () {
    Log::spy();
    $events = collect(app(Schedule::class)->events());

    expect($events)->not->toBeEmpty();

    $events->each(function (Event $event) {
        $event->exitCode = 1;
        $event->callAfterCallbacks(app());
    });

    Log::shouldHaveReceived('error')->times($events->count());
    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'ingest:overflows'));
});

it('logs nothing when a scheduled command succeeds', function () {
    Log::spy();

    collect(app(Schedule::class)->events())->each(function (Event $event) {
        $event->exitCode = 0;
        $event->callAfterCallbacks(app());
    });

    Log::shouldNotHaveReceived('error');
});
