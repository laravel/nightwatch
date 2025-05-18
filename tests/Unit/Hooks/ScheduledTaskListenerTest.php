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

    $this->assertTrue($thrownInScheduledTaskSensor);
    $this->assertFalse($thrownInExceptionSensor);
    $this->assertCount(0, $unrecoverableExceptions);
    $this->assertSame(1, nightwatch()->executionState->exceptions);

    $thrownInScheduledTaskSensor = false;
    $thrownInExceptionSensor = false;
    nightwatch()->sensor->scheduledTaskSensor = fn () => null;
    nightwatch()->sensor->exceptionSensor = function () use (&$thrownInExceptionSensor) {
        $thrownInExceptionSensor = true;

        throw new RuntimeException('Whoops!');
    };

    $event = new ScheduledTaskFailed($task, new RuntimeException('Whoops!'));

    $handler($event);

    $this->assertFalse($thrownInScheduledTaskSensor);
    $this->assertTrue($thrownInExceptionSensor);
    $this->assertCount(1, $unrecoverableExceptions);
    $this->assertSame('Whoops!', $unrecoverableExceptions[0]->getMessage());
    $this->assertSame(1, nightwatch()->executionState->exceptions);
});
