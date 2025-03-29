<?php

use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Nightwatch\Hooks\ScheduledTaskListener;

it('gracefully handles exceptions', function () {
    fakeIngest();
    $thrownInScheduledTaskSensor = false;
    nightwatch()->sensor->scheduledTaskSensor = function () use (&$thrownInScheduledTaskSensor) {
        $thrownInScheduledTaskSensor = true;

        throw new RuntimeException('Whoops!');
    };

    $event = new ScheduledTaskFinished(
        task: app(Schedule::class)->command('php artisan inspire'),
        runtime: 10.0,
    );

    $handler = new ScheduledTaskListener(nightwatch());
    $handler($event);

    expect($thrownInScheduledTaskSensor)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);
});
