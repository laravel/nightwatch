<?php

use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Hooks\ScheduledTaskStartingListener;

beforeAll(function () {
    forceCommandExecutionState();
});

it('gracefully handles exceptions', function () {
    $thrownInMicrotimeResolver = false;
    nightwatch()->clock = tap(new Clock, function ($clock) use (&$thrownInMicrotimeResolver) {
        $clock->microtimeResolver = function () use (&$thrownInMicrotimeResolver) {
            $thrownInMicrotimeResolver = true;

            throw new RuntimeException('Whoops!');
        };
    });

    $event = new ScheduledTaskStarting(app(Schedule::class)->command('php artisan inspire'));

    $handler = new ScheduledTaskStartingListener(nightwatch());
    $handler($event);

    expect($thrownInMicrotimeResolver)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);
});
