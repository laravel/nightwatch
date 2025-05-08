<?php

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\ScheduledTaskListener;

it('gracefully handles exceptions', function () {
    fakeIngest();
    $unrecoverableExceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$unrecoverableExceptions) {
        $unrecoverableExceptions[] = $e;
    });
    $thrownInScheduledTaskSensor = false;
    nightwatch()->sensor->scheduledTaskSensor = function () use (&$thrownInScheduledTaskSensor) {
        $thrownInScheduledTaskSensor = true;

        throw new RuntimeException('Whoops!');
    };
    $thrownInExceptionSensor = false;
    $task = app(Schedule::class)->command('php artisan inspire');
    $event = new ScheduledTaskFinished($task, 10.0);

    $handler = new ScheduledTaskListener(nightwatch());
    $handler($event);

    expect($thrownInScheduledTaskSensor)->toBeTrue();
    expect($thrownInExceptionSensor)->toBeFalse();
    expect($unrecoverableExceptions)->toHaveCount(0);
    expect(nightwatch()->state->exceptions)->toBe(1);

    $thrownInScheduledTaskSensor = false;
    $thrownInExceptionSensor = false;
    nightwatch()->sensor->scheduledTaskSensor = fn () => null;
    nightwatch()->sensor->exceptionSensor = function () use (&$thrownInExceptionSensor) {
        $thrownInExceptionSensor = true;

        throw new RuntimeException('Whoops!');
    };

    $event = new ScheduledTaskFailed($task, new RuntimeException('Whoops!'));

    $handler($event);

    expect($thrownInScheduledTaskSensor)->toBeFalse();
    expect($thrownInExceptionSensor)->toBeTrue();
    expect($unrecoverableExceptions)->toHaveCount(2);
    expect($unrecoverableExceptions[0]->getMessage())->toBe('Whoops!');
    // This exception is only thrown in tests to ensure we don't
    // accidently write empty payloads.
    expect($unrecoverableExceptions[1]->getMessage())->toBe('The payload was empty.');
    expect(nightwatch()->state->exceptions)->toBe(1);

    forgetRecordedExceptions(1);
});
