<?php

namespace Tests\Unit\Hooks;

use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\ScheduledTaskStartingListener;
use RuntimeException;
use Tests\TestCase;

use function tap;

class ScheduledTaskStartingListenerTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceCommandExecutionState();

        parent::setUp();
    }

    public function test_it_gracefully_handles_exceptions(): void
    {
        $thrownInMicrotimeResolver = false;
        $this->core->clock = tap(new Clock, function ($clock) use (&$thrownInMicrotimeResolver): void {
            $clock->microtimeResolver = function () use (&$thrownInMicrotimeResolver): void {
                $thrownInMicrotimeResolver = true;

                throw new RuntimeException('Whoops!');
            };
        });

        $event = new ScheduledTaskStarting($this->app[Schedule::class]->command('php artisan inspire'));

        $handler = new ScheduledTaskStartingListener($this->core);
        $handler($event);

        $this->assertTrue($thrownInMicrotimeResolver);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }

    public function test_it_prepends_sample_env_to_command(): void
    {
        $cmd = $this->app[Schedule::class]->command('php artisan inspire');
        $event = new ScheduledTaskStarting($cmd);

        $handler = new ScheduledTaskStartingListener($this->core);
        $handler($event);

        $this->assertStringStartsWith('NIGHTWATCH_SCHEDULE_TASK_SUBCOMMAND_SAMPLED=1', $cmd->command);
    }

    public function test_it_prepends_no_sample_env_to_command(): void
    {
        Nightwatch::dontSample();

        $cmd = $this->app[Schedule::class]->command('php artisan inspire');
        $event = new ScheduledTaskStarting($cmd);

        $handler = new ScheduledTaskStartingListener($this->core);
        $handler($event);

        $this->assertStringStartsWith('NIGHTWATCH_SCHEDULE_TASK_SUBCOMMAND_SAMPLED=0', $cmd->command);
    }
}
