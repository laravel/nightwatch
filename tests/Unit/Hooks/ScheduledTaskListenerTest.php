<?php

namespace Tests\Unit\Hooks;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\ScheduledTaskListener;
use RuntimeException;
use Tests\TestCase;

class ScheduledTaskListenerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $this->fakeIngest();
        $unrecoverableExceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$unrecoverableExceptions): void {
            $unrecoverableExceptions[] = $e;
        });
        $thrownInScheduledTaskSensor = false;
        $this->core->sensor->scheduledTaskSensor = function () use (&$thrownInScheduledTaskSensor): void {
            $thrownInScheduledTaskSensor = true;

            throw new RuntimeException('Whoops!');
        };
        $thrownInExceptionSensor = false;
        $task = $this->app[Schedule::class]->command('php artisan inspire');
        $task->exitCode = 0;
        $event = new ScheduledTaskFinished($task, 10.0);

        $handler = new ScheduledTaskListener($this->core);
        $handler($event);

        $this->assertTrue($thrownInScheduledTaskSensor);
        $this->assertFalse($thrownInExceptionSensor);
        $this->assertCount(0, $unrecoverableExceptions);
        $this->assertSame(1, $this->core->executionState->exceptions);

        $thrownInScheduledTaskSensor = false;
        $thrownInExceptionSensor = false;
        $this->core->sensor->scheduledTaskSensor = fn () => null;
        $this->core->sensor->exceptionSensor = function () use (&$thrownInExceptionSensor): void {
            $thrownInExceptionSensor = true;

            throw new RuntimeException('Whoops!');
        };

        $event = new ScheduledTaskFailed($task, new RuntimeException('Whoops!'));

        $handler($event);

        $this->assertFalse($thrownInScheduledTaskSensor);
        $this->assertTrue($thrownInExceptionSensor);
        $this->assertCount(1, $unrecoverableExceptions);
        $this->assertSame('Whoops!', $unrecoverableExceptions[0]->getMessage());
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
